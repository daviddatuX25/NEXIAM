<?php

namespace App\ProgramHead\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Testing\PracticeFixtureSet;
use Illuminate\Database\Eloquent\Builder;

final class AcademicReferralNotice
{
    public const EVENT = 'admission.academic_referral_opened.v1';

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly NotificationIntentDispatcher $dispatcher,
    ) {}

    public function handle(ProgramFollowUp $followUp): void
    {
        $followUp->loadMissing(['attempt.academicCycle', 'program']);
        $attempt = $followUp->attempt;
        $program = $followUp->program;
        if ($attempt === null || $program === null || ! $followUp->isOpen()) {
            return;
        }

        $recipients = User::query()
            ->whereHas('staffProfile', fn (Builder $query): Builder => $query
                ->where('institution_id', $attempt->institution_id)
                ->where('office', 'program')
                ->where('status', 'active')
                ->whereNull('deactivated_at'))
            ->lazyById(100)
            ->filter(fn (User $actor): bool => $this->canReview($actor, $attempt, $program));
        $intent = NotificationIntent::v1(
            self::EVENT,
            'admission.academic_referral_opened',
            [
                'institution_id' => (int) $attempt->institution_id,
                'attempt_id' => (int) $attempt->id,
                'academic_cycle_id' => (int) $attempt->academic_cycle_id,
                'program_id' => (int) $program->id,
                'follow_up_id' => (int) $followUp->id,
                'follow_up_version' => (int) $followUp->version,
                'subject' => 'Academic review requested',
                'body' => 'Guidance referred an applicant to your program for academic review.',
                'action_label' => 'Open academic review',
                'action_url' => route('admission-support.show', [
                    'attempt' => $attempt->id,
                    'program_code' => $program->code,
                    'cycle_code' => $attempt->academicCycle?->code,
                    'tab' => 'record',
                ], false),
            ],
            [NotificationDelivery::CHANNEL_IN_APP],
        );
        $created = $this->dispatcher->dispatch(
            $intent,
            hash('sha256', self::EVENT.':'.$followUp->id.':'.$followUp->version),
            $recipients,
            $intent->channels,
        );
        NotificationIntentDispatcher::enqueueAfterCommit($created);
    }

    public function isCurrent(NotificationDelivery $delivery): bool
    {
        $payload = $delivery->payload ?? [];
        if ($delivery->recipient_type !== User::class || $delivery->channel !== NotificationDelivery::CHANNEL_IN_APP) {
            return false;
        }
        $followUp = ProgramFollowUp::query()->with(['attempt.academicCycle', 'program'])
            ->find($payload['follow_up_id'] ?? null);
        $actor = User::query()->find($delivery->recipient_id);

        return $followUp !== null && $actor !== null && $followUp->isOpen()
            && (int) $followUp->version === (int) ($payload['follow_up_version'] ?? 0)
            && (int) $followUp->admission_attempt_id === (int) ($payload['attempt_id'] ?? 0)
            && (int) $followUp->program_id === (int) ($payload['program_id'] ?? 0)
            && $followUp->attempt !== null && $followUp->program !== null
            && (int) $followUp->attempt->institution_id === (int) ($payload['institution_id'] ?? 0)
            && (int) $followUp->attempt->academic_cycle_id === (int) ($payload['academic_cycle_id'] ?? 0)
            && $this->canReview($actor, $followUp->attempt, $followUp->program);
    }

    private function canReview(User $actor, AdmissionAttempt $attempt, Program $program): bool
    {
        $profile = $actor->staffProfile()->first();
        $scope = $actor->actorScope();
        $cycle = $attempt->academicCycle;
        if ($profile === null || ! $profile->isActive() || $profile->deactivated_at !== null
            || $profile->office !== 'program' || $actor->applicantAccount()->exists()
            || (int) $profile->institution_id !== (int) $attempt->institution_id
            || (int) $program->institution_id !== (int) $attempt->institution_id
            || ! $program->is_active || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
            || $cycle === null || ! $cycle->is_active
            || $scope->activeCycleCode !== $cycle->code
            || ! in_array($program->code, $scope->programCodes, true)) {
            return false;
        }
        if ($attempt->practice_fixture_set_id !== null) {
            $set = PracticeFixtureSet::query()->find($attempt->practice_fixture_set_id);
            if ($set === null || $set->status !== 'active'
                || (int) ($set->actor_bindings['academic-leader'] ?? 0) !== (int) $actor->id) {
                return false;
            }
        }
        $resource = ['institution_id' => (int) $attempt->institution_id, 'program' => $program->code, 'cycle' => $cycle->code];

        return $this->authorizer->decideFresh($actor, CapabilityCatalog::PROGRAM_HEAD_VIEW, $resource)->allowed
            && $this->authorizer->decideFresh($actor, CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE, $resource)->allowed;
    }
}
