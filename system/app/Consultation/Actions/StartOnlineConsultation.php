<?php

declare(strict_types=1);

namespace App\Consultation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\AttemptResultReadiness;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Consultation\Support\ConsultationPolicyResolver;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationRecipients;
use App\Scheduling\AssignmentPlanningMutex;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StartOnlineConsultation
{
    private readonly ?Closure $readinessReport;

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly ConsultationPolicyResolver $policies,
        private readonly ConsultationMediaReadiness $readiness,
        private readonly RecordAuditEvent $audit,
        private readonly NotificationIntentDispatcher $dispatcher,
        ?Closure $readinessReport = null,
    ) {
        $this->readinessReport = $readinessReport;
    }

    /** @return array{encounter_id: int, url: string, status: string} */
    public function handle(AdmissionAttempt $attempt, User $actor, string $idempotencyKey): array
    {
        if (trim($idempotencyKey) === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'A start key is required.']);
        }

        $cycle = AcademicCycle::query()
            ->whereKey($attempt->academic_cycle_id)
            ->where('institution_id', $attempt->institution_id)
            ->firstOrFail();
        $this->authorizeStart($actor, (int) $attempt->institution_id);
        $fingerprint = $this->commandFingerprint($attempt, $actor, $cycle);

        $replayed = ConsultationEncounter::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($replayed !== null) {
            $this->assertReplayMatches($replayed, $attempt, $actor, $fingerprint);

            return $this->receipt($replayed);
        }

        $createdDeliveries = collect();
        $expiredEncounterIds = [];

        try {
            $encounter = $this->planningMutex->run(
                AssignmentPlanningMutex::DOMAIN_CONSULTATION,
                (int) $cycle->id,
                function () use ($attempt, $actor, $cycle, $idempotencyKey, $fingerprint, &$createdDeliveries, &$expiredEncounterIds): ConsultationEncounter {
                    $lockedCycle = AcademicCycle::query()
                        ->where('institution_id', $cycle->institution_id)
                        ->lockForUpdate()
                        ->findOrFail($cycle->id);

                    $assignmentPreview = ConsultationAssignment::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES)
                        ->first();
                    $session = $assignmentPreview === null
                        ? null
                        : ConsultationSession::query()
                            ->lockForUpdate()
                            ->findOrFail($assignmentPreview->consultation_session_id);

                    $lockedAttempt = AdmissionAttempt::query()
                        ->where('institution_id', $lockedCycle->institution_id)
                        ->where('academic_cycle_id', $lockedCycle->id)
                        ->lockForUpdate()
                        ->findOrFail($attempt->id);

                    $this->authorizeStart($actor, (int) $lockedAttempt->institution_id);

                    $existing = ConsultationEncounter::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing !== null) {
                        $this->assertReplayMatches($existing, $lockedAttempt, $actor, $fingerprint);

                        return $existing;
                    }

                    if ($lockedAttempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                        throw ValidationException::withMessages(['attempt' => 'Only accepted applicants with a prepared result may start an online consultation.']);
                    }

                    $documentId = $lockedAttempt->current_result_document_id;
                    $document = $documentId === null
                        ? null
                        : ResultDocument::query()
                            ->whereKey($documentId)
                            ->where('admission_attempt_id', $lockedAttempt->id)
                            ->where('status', ResultDocument::STATUS_READY)
                            ->lockForUpdate()
                            ->first();
                    if ($document === null
                        || (int) $document->version < 1
                        || ! AttemptResultReadiness::isReady($lockedAttempt)) {
                        throw ValidationException::withMessages(['attempt' => 'Only accepted applicants with a current prepared result may start an online consultation.']);
                    }

                    $policy = $this->policies->forCycle($lockedCycle);
                    if (! $policy->allowsOnlineConsultation()) {
                        throw ValidationException::withMessages(['online' => 'Online consultation is not permitted for this academic cycle.']);
                    }

                    $readiness = $this->mediaReadinessReport();
                    if (! $readiness['ready']) {
                        throw ValidationException::withMessages([
                            'online' => $readiness['reason'] ?? 'Online consultation media is unavailable.',
                        ]);
                    }

                    $assignment = null;
                    if ($assignmentPreview !== null) {
                        if ($session === null
                            || (int) $session->academic_cycle_id !== (int) $lockedCycle->id) {
                            throw ValidationException::withMessages(['attempt' => 'The scheduled consultation is outside this academic cycle.']);
                        }

                        $assignment = ConsultationAssignment::query()
                            ->whereKey($assignmentPreview->id)
                            ->where('admission_attempt_id', $lockedAttempt->id)
                            ->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES)
                            ->lockForUpdate()
                            ->first();
                        if ($assignment === null
                            || (int) $assignment->consultation_session_id !== (int) $session->id) {
                            throw ValidationException::withMessages(['attempt' => 'The scheduled consultation is no longer active.']);
                        }

                        if (! in_array($session->status, [
                            ConsultationSession::STATUS_PUBLISHED,
                            ConsultationSession::STATUS_IN_PROGRESS,
                        ], true) || $session->published_at === null || $session->scheduled_at === null) {
                            throw ValidationException::withMessages(['attempt' => 'The scheduled consultation is not published or available.']);
                        }

                        $bookingPath = ConsultationEncounter::BOOKING_PATH_SCHEDULED;
                        $startsAt = $session->scheduled_at->copy();
                        $endsAt = $startsAt->copy()->addSeconds((int) config('jitsi.scheduled_window_seconds', 3600));
                    } else {
                        $bookingPath = ConsultationEncounter::BOOKING_PATH_DIRECT;
                        $startsAt = now();
                        $endsAt = $startsAt->copy()->addSeconds((int) config('jitsi.direct_ttl_seconds', 1800));
                    }

                    $currentTime = now();
                    if ($currentTime->lt($startsAt) || $currentTime->gte($endsAt)) {
                        throw ValidationException::withMessages(['attempt' => 'Online consultation can only start during its access window.']);
                    }

                    $active = ConsultationEncounter::query()
                        ->where('admission_attempt_id', $lockedAttempt->id)
                        ->whereIn('status', [
                            ConsultationEncounter::STATUS_INVITED,
                            ConsultationEncounter::STATUS_WAITING,
                            ConsultationEncounter::STATUS_IN_PROGRESS,
                        ])
                        ->lockForUpdate()
                        ->first();
                    if ($active !== null) {
                        if ($active->medium !== ConsultationEncounter::MEDIUM_ONLINE
                            || $active->access_ends_at === null
                            || $currentTime->lt($active->access_ends_at)) {
                            throw ValidationException::withMessages(['attempt' => 'An online consultation is already active for this applicant.']);
                        }

                        $active->update([
                            'status' => ConsultationEncounter::STATUS_CLOSED_WITHOUT_OUTCOME,
                            'schedule_generation' => (int) $active->schedule_generation + 1,
                            'closed_at' => $currentTime,
                            'close_reason' => 'Online consultation access window expired',
                            'media_close_status' => ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING,
                        ]);
                        $expiredEncounterIds[] = (int) $active->id;
                        $this->audit->handle('consultation.online_expired', [
                            'consultation_encounter_id' => $active->id,
                            'admission_attempt_id' => $lockedAttempt->id,
                            'schedule_generation' => (int) $active->schedule_generation,
                        ], (string) $actor->id);
                    }

                    $encounter = ConsultationEncounter::query()->create([
                        'institution_id' => $lockedAttempt->institution_id,
                        'admission_attempt_id' => $lockedAttempt->id,
                        'result_document_id' => $document->id,
                        'result_document_version' => $document->version,
                        'consultation_policy_version' => $policy->version,
                        'command_fingerprint' => $fingerprint,
                        'consultation_session_id' => $session?->id,
                        'consultation_assignment_id' => $assignment?->id,
                        'counselor_user_id' => $actor->id,
                        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
                        'booking_path' => $bookingPath,
                        'status' => ConsultationEncounter::STATUS_INVITED,
                        'room_name' => 'consultation-'.Str::lower(Str::random(40)),
                        'schedule_generation' => 1,
                        'access_starts_at' => $startsAt,
                        'access_ends_at' => $endsAt,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    $this->audit->handle('consultation.online_started', [
                        'consultation_encounter_id' => $encounter->id,
                        'admission_attempt_id' => $lockedAttempt->id,
                        'result_document_id' => $document->id,
                        'result_document_version' => $document->version,
                        'consultation_assignment_id' => $assignment?->id,
                        'consultation_policy_version' => $policy->version,
                        'booking_path' => $bookingPath,
                        'command_fingerprint' => $fingerprint,
                        'idempotency_key' => $idempotencyKey,
                    ], (string) $actor->id);

                    $recipient = NotificationRecipients::applicantFor($lockedAttempt);
                    if ($recipient !== null) {
                        $intent = NotificationIntent::v1(
                            eventType: 'consultation.online_invited.v1',
                            templateKey: 'consultation.online_invited',
                            payload: [
                                'domain' => 'results',
                                'canonical_slug' => 'consultation-online-invited',
                                'action_label' => 'Open applicant portal',
                                'action_url' => route('applicant.portal'),
                                'consultation_encounter_id' => $encounter->id,
                                'admission_attempt_id' => $lockedAttempt->id,
                                'institution_id' => $lockedAttempt->institution_id,
                                'academic_cycle_id' => $lockedCycle->id,
                                'identity_id' => $recipient->id,
                                'schedule_generation' => $encounter->schedule_generation,
                                'consultation_policy_version' => $policy->version,
                                'booking_path' => $bookingPath,
                                'access_starts_at' => $startsAt->toIso8601String(),
                                'access_ends_at' => $endsAt->toIso8601String(),
                                'result_document_id' => $document->id,
                                'result_document_version' => $document->version,
                            ],
                        );
                        $createdDeliveries = $this->dispatcher->dispatch(
                            $intent,
                            hash('sha256', 'consultation.online_invited:'.$encounter->id.':v1'),
                            [$recipient],
                            $intent->channels,
                        );
                    }

                    return $encounter;
                },
            );
        } catch (QueryException $exception) {
            $existing = ConsultationEncounter::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing === null) {
                throw $exception;
            }

            $this->assertReplayMatches($existing, $attempt, $actor, $fingerprint);
            $encounter = $existing;
        }

        if ($expiredEncounterIds !== []) {
            app(DispatchPendingConsultationMediaCloses::class)->handle($expiredEncounterIds);
        }

        NotificationIntentDispatcher::enqueueAfterCommit($createdDeliveries);

        return $this->receipt($encounter);
    }

    private function authorizeStart(User $actor, int $institutionId): void
    {
        if (! $this->authorizer->allows(
            $actor,
            CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
            ['institution_id' => $institutionId, 'office' => 'guidance'],
        )) {
            throw new AuthorizationException;
        }
    }

    private function assertReplayMatches(
        ConsultationEncounter $encounter,
        AdmissionAttempt $attempt,
        User $actor,
        string $fingerprint,
    ): void {
        if ((int) $encounter->admission_attempt_id !== (int) $attempt->id
            || (int) $encounter->institution_id !== (int) $attempt->institution_id
            || (int) $encounter->counselor_user_id !== (int) $actor->id
            || $encounter->medium !== ConsultationEncounter::MEDIUM_ONLINE
            || ! is_string($encounter->command_fingerprint)
            || ! hash_equals($encounter->command_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['idempotency_key' => 'This start key was already used for a different consultation command.']);
        }
    }

    /**
     * @return array{encounter_id: int, url: string, status: string}
     */
    private function receipt(ConsultationEncounter $encounter): array
    {
        $activeStatuses = [
            ConsultationEncounter::STATUS_INVITED,
            ConsultationEncounter::STATUS_WAITING,
            ConsultationEncounter::STATUS_IN_PROGRESS,
        ];
        $attempt = $encounter->admissionAttempt;
        $currentPolicyAllowsOnline = $attempt !== null
            && $this->policies->permitsEncounter(
                (int) $encounter->institution_id,
                (int) $attempt->academic_cycle_id,
                $encounter->consultation_policy_version,
            );
        $currentTime = now();
        $canOpenEncounter = $attempt !== null
            && in_array($encounter->status, $activeStatuses, true)
            && $encounter->media_close_status === ConsultationEncounter::MEDIA_CLOSE_STATUS_NONE
            && $encounter->access_starts_at !== null
            && $encounter->access_ends_at !== null
            && $currentTime->gte($encounter->access_starts_at)
            && $currentTime->lt($encounter->access_ends_at)
            && $currentPolicyAllowsOnline
            && $this->mediaReadinessReport()['ready'];

        return [
            'encounter_id' => (int) $encounter->id,
            'url' => $canOpenEncounter
                ? route('consultation.encounter.show', $encounter)
                : route('consultation.index'),
            'status' => (string) $encounter->status,
        ];
    }

    private function commandFingerprint(AdmissionAttempt $attempt, User $actor, AcademicCycle $cycle): string
    {
        return hash('sha256', json_encode([
            'command' => 'consultation.online.start',
            'actor_id' => (int) $actor->id,
            'admission_attempt_id' => (int) $attempt->id,
            'institution_id' => (int) $attempt->institution_id,
            'academic_cycle_id' => (int) $cycle->id,
            'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{ready: bool, reason: ?string}
     */
    private function mediaReadinessReport(): array
    {
        return $this->readinessReport !== null
            ? ($this->readinessReport)()
            : $this->readiness->report();
    }
}
