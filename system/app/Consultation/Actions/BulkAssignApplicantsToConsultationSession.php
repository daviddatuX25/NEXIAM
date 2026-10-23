<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BulkAssignApplicantsToConsultationSession
{
    public function __construct(
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Atomically assigns multiple eligible admission attempts to a consultation session inside the domain mutex.
     *
     * @param  list<int>  $admissionAttemptIds
     * @return array{assigned_count: int, assigned_ids: list<int>}
     *
     * @throws ValidationException
     */
    public function handle(ConsultationSession $session, array $admissionAttemptIds, int $actorId): array
    {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $session->academic_cycle_id,
            function () use ($session, $admissionAttemptIds, $actorId): array {
                /** @var ConsultationSession $lockedSession */
                $lockedSession = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

                if (in_array($lockedSession->status, [ConsultationSession::STATUS_CANCELLED, ConsultationSession::STATUS_COMPLETED], true)) {
                    throw ValidationException::withMessages([
                        'consultation_session_id' => 'Cannot assign applicants to a terminal (cancelled or completed) Release Day session.',
                    ]);
                }

                $cleanIds = array_values(array_unique(array_filter(
                    array_map('intval', $admissionAttemptIds),
                    static fn (int $id): bool => $id > 0,
                )));
                sort($cleanIds);

                if ($cleanIds === []) {
                    return [
                        'assigned_count' => 0,
                        'assigned_ids' => [],
                    ];
                }

                $remainingCapacity = $this->invariant->remainingCapacity($lockedSession);
                if (count($cleanIds) > $remainingCapacity) {
                    throw ValidationException::withMessages([
                        'admission_attempt_id' => 'Session does not have enough remaining capacity for the selected applicants. Available: '.$remainingCapacity.', Requested: '.count($cleanIds).'.',
                    ]);
                }

                $attempts = AdmissionAttempt::query()
                    ->whereIn('id', $cleanIds)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

                if ($attempts->count() !== count($cleanIds)) {
                    throw ValidationException::withMessages([
                        'admission_attempt_id' => 'One or more selected admission attempts could not be found.',
                    ]);
                }

                foreach ($cleanIds as $attemptId) {
                    /** @var AdmissionAttempt $attempt */
                    $attempt = $attempts->get($attemptId);

                    try {
                        $this->invariant->validateManualConsultationAssignment($lockedSession, $attempt);
                    } catch (RuntimeException $exception) {
                        throw ValidationException::withMessages([
                            'admission_attempt_id' => "Applicant [{$attempt->reference_code}]: {$exception->getMessage()}",
                        ]);
                    }

                    ConsultationAssignment::query()->create([
                        'consultation_session_id' => $lockedSession->id,
                        'admission_attempt_id' => $attempt->id,
                        'status' => ConsultationAssignment::STATUS_ASSIGNED,
                        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
                        'origin' => ConsultationAssignment::ORIGIN_MANUAL,
                        'assigned_by_user_id' => $actorId,
                    ]);
                }

                $lockedSession->increment('version');

                $this->recordAuditEvent->handle('consultation.bulk_assignment_created', [
                    'consultation_session_id' => (int) $lockedSession->id,
                    'attempt_ids' => $cleanIds,
                    'count' => count($cleanIds),
                ], (string) $actorId);

                return [
                    'assigned_count' => count($cleanIds),
                    'assigned_ids' => $cleanIds,
                ];
            },
        );
    }
}
