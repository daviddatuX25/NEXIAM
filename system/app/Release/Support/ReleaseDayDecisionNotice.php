<?php

namespace App\Release\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Release\ConsultationDecision;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ReleaseDayDecisionNotice
{
    public const EVENT = 'release_day.decision_recorded.v1';

    public const TEMPLATE = 'release_day.decision_recorded';

    /** @var list<string> */
    private const DECISION_TYPES = ['approve', 'modify', 'override', 'defer', 'refer'];

    public function __construct(private readonly NotificationIntentDispatcher $dispatcher) {}

    public function handle(AdmissionAttempt $attempt, ConsultationDecision $decision): void
    {
        if ((int) $decision->admission_attempt_id !== (int) $attempt->id
            || ! in_array($decision->decision_type, self::DECISION_TYPES, true)) {
            return;
        }

        $identity = NotificationRecipients::applicantFor($attempt);
        if ($identity === null) {
            return;
        }

        $intent = NotificationIntent::v1(
            self::EVENT,
            self::TEMPLATE,
            [
                'attempt_id' => (int) $attempt->id,
                'identity_id' => (int) $identity->id,
                'institution_id' => (int) $attempt->institution_id,
                'academic_cycle_id' => (int) $attempt->academic_cycle_id,
                'decision_id' => (int) $decision->id,
                'decision_generation' => (int) $decision->version,
                'subject' => 'Release Day update',
                'body' => 'There is an update to your Release Day information. Sign in to review it.',
                'action_label' => 'View Release Day',
                'action_url' => route('applicant.release-day', [], false),
            ],
            [NotificationDelivery::CHANNEL_IN_APP],
        );

        $created = $this->dispatcher->dispatch(
            $intent,
            hash('sha256', self::EVENT.':'.$decision->id),
            [$identity],
            [NotificationDelivery::CHANNEL_IN_APP],
        );

        DB::afterCommit(function () use ($created, $decision): void {
            try {
                NotificationIntentDispatcher::enqueueAfterCommit($created);
            } catch (\Throwable $exception) {
                Log::error('Release Day decision notice could not be queued.', [
                    'decision_id' => $decision->id,
                    'exception' => $exception,
                ]);
            }
        });
    }

    public function isCurrent(NotificationDelivery $delivery): bool
    {
        if ($delivery->event_type !== self::EVENT
            || $delivery->event_version !== 1
            || $delivery->template_key !== self::TEMPLATE
            || $delivery->channel !== NotificationDelivery::CHANNEL_IN_APP
            || $delivery->recipient_type !== ApplicantIdentity::class) {
            return false;
        }

        $payload = $delivery->payload ?? [];
        foreach (['attempt_id', 'identity_id', 'institution_id', 'academic_cycle_id', 'decision_id', 'decision_generation'] as $key) {
            if (! is_numeric($payload[$key] ?? null) || (int) $payload[$key] < 1) {
                return false;
            }
        }

        $attemptId = (int) $payload['attempt_id'];
        $identityId = (int) $payload['identity_id'];
        $institutionId = (int) $payload['institution_id'];
        $academicCycleId = (int) $payload['academic_cycle_id'];
        $decisionId = (int) $payload['decision_id'];
        $decisionGeneration = (int) $payload['decision_generation'];

        if ((int) $delivery->recipient_id !== $identityId) {
            return false;
        }

        $attempt = AdmissionAttempt::query()
            ->whereKey($attemptId)
            ->where('applicant_identity_id', $identityId)
            ->where('institution_id', $institutionId)
            ->where('academic_cycle_id', $academicCycleId)
            ->whereHas('identity', fn ($query) => $query
                ->whereKey($identityId)
                ->where('institution_id', $institutionId))
            ->whereHas('academicCycle', fn ($query) => $query
                ->whereKey($academicCycleId)
                ->where('institution_id', $institutionId))
            ->first(['id']);

        if ($attempt === null
            || (int) AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->orderByDesc('id')
                ->value('id') !== $attemptId) {
            return false;
        }

        $latestDecision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attemptId)
            ->orderByDesc('id')
            ->first(['id', 'version', 'decision_type']);

        return $latestDecision !== null
            && (int) $latestDecision->id === $decisionId
            && (int) $latestDecision->version === $decisionGeneration
            && in_array($latestDecision->decision_type, self::DECISION_TYPES, true);
    }
}
