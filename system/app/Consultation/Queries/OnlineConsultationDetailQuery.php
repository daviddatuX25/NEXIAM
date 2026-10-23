<?php

declare(strict_types=1);

namespace App\Consultation\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\AttemptResultReadiness;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OnlineConsultationDetailQuery
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ConsultationMediaReadiness $mediaReadiness,
    ) {}

    /**
     * @return array{visible: bool, can_start: bool, reason: ?string, exception_mode: bool, encounter_url: ?string}
     */
    public function forGuidance(AdmissionAttempt $attempt, User $actor): array
    {
        $hidden = ['visible' => false, 'can_start' => false, 'reason' => null, 'exception_mode' => false, 'encounter_url' => null];
        $decision = $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, [
            'institution_id' => (int) $attempt->institution_id,
            'office' => 'guidance',
        ]);
        if (! $decision->allowed) {
            return $hidden;
        }

        $cycle = AcademicCycle::query()
            ->whereKey($attempt->academic_cycle_id)
            ->where('institution_id', $attempt->institution_id)
            ->first();
        if ($cycle === null) {
            return $hidden;
        }

        $policy = ConsultationPolicy::query()
            ->where('institution_id', $attempt->institution_id)
            ->where('academic_cycle_id', $cycle->id)
            ->first();
        $onlineAllowed = $policy?->allowsOnlineConsultation() ?? true;
        if (! $onlineAllowed) {
            return $hidden;
        }

        $exceptionMode = $policy?->normal_method === ConsultationPolicy::METHOD_F2F
            && $policy->allow_online_exceptions;
        $activeEncounter = ConsultationEncounter::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
            ])
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('access_ends_at')
                ->orWhere('access_ends_at', '>', now()))
            ->orderByDesc('id')
            ->first();
        $ownedEncounterUrl = $activeEncounter !== null
            && $activeEncounter->access_starts_at !== null
            && $activeEncounter->access_ends_at !== null
            && now()->gte($activeEncounter->access_starts_at)
            && $activeEncounter->media_close_status === ConsultationEncounter::MEDIA_CLOSE_STATUS_NONE
            && (int) $activeEncounter->counselor_user_id === (int) $actor->id
            ? route('consultation.encounter.show', ['encounter' => $activeEncounter->id])
            : null;

        $reason = match (true) {
            $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED || ! AttemptResultReadiness::isReady($attempt) => 'Online consultation requires an accepted applicant with a prepared result.',
            $activeEncounter !== null => 'An online consultation is already active for this applicant.',
            default => null,
        };

        $assignment = ConsultationAssignment::query()
            ->where('admission_attempt_id', $attempt->id)
            ->active()
            ->first();
        $session = $assignment === null ? null : ConsultationSession::query()
            ->whereKey($assignment->consultation_session_id)
            ->where('academic_cycle_id', $cycle->id)
            ->first();

        if ($reason === null && $assignment !== null) {
            $reason = match (true) {
                $session === null => 'The scheduled consultation is outside this academic cycle.',
                ! in_array($session->status, [ConsultationSession::STATUS_PUBLISHED, ConsultationSession::STATUS_IN_PROGRESS], true)
                    || $session->published_at === null
                    || $session->scheduled_at === null => 'The scheduled consultation is not published or available.',
                default => null,
            };
            if ($reason === null) {
                $startsAt = $session->scheduled_at;
                $endsAt = $startsAt->copy()->addSeconds((int) config('jitsi.scheduled_window_seconds', 3600));
                if (now()->lt($startsAt) || now()->gte($endsAt)) {
                    $reason = now()->lt($startsAt)
                        ? 'The scheduled online consultation window has not opened.'
                        : 'The scheduled online consultation window has expired.';
                }
            }
        }

        if ($reason === null) {
            $media = $this->mediaReadiness->report();
            if (! $media['ready']) {
                $reason = $media['reason'];
            }
        }

        return [
            'visible' => true,
            'can_start' => $reason === null,
            'reason' => $reason,
            'exception_mode' => $exceptionMode,
            'encounter_url' => $ownedEncounterUrl,
        ];
    }
}
