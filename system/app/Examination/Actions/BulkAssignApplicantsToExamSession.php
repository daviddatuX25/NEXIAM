<?php

namespace App\Examination\Actions;

use App\Examination\Support\PublishedSessionSeatAllocator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Validation\ValidationException;

class BulkAssignApplicantsToExamSession
{
    public function __construct(
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
        private readonly PublishedSessionSeatAllocator $seatAllocator,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Atomically assigns multiple accepted admission attempts to an exam session inside the domain mutex.
     *
     * @param  list<int>  $admissionAttemptIds
     * @return array{assigned_count: int, assigned_ids: list<int>}
     *
     * @throws ValidationException
     */
    public function handle(ExamSession $session, array $admissionAttemptIds, ?int $assignedBy = null): array
    {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $session->academic_cycle_id,
            function () use ($session, $admissionAttemptIds, $assignedBy): array {
                /** @var ExamSession $lockedSession */
                $lockedSession = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

                if ($lockedSession->status !== ExamSession::STATUS_DRAFT) {
                    throw ValidationException::withMessages([
                        'exam_session_id' => 'Published or finalized session rosters are locked for ordinary scheduling. Exam Day walk-in and early-traveler changes must use AccommodateApplicantExamSession on Monitoring.',
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

                $actor = ($assignedBy !== null ? User::query()->find($assignedBy) : null)
                    ?? auth()->user()
                    ?? User::query()->firstOrFail();

                foreach ($cleanIds as $attemptId) {
                    /** @var AdmissionAttempt $attempt */
                    $attempt = $attempts->get($attemptId);

                    if ((int) $attempt->academic_cycle_id !== (int) $lockedSession->academic_cycle_id) {
                        throw ValidationException::withMessages([
                            'admission_attempt_id' => "Applicant [{$attempt->reference_code}] does not belong to this academic cycle.",
                        ]);
                    }

                    if (! $attempt->isExamEligible()) {
                        throw ValidationException::withMessages([
                            'admission_attempt_id' => "Applicant [{$attempt->reference_code}] is not in accepted status.",
                        ]);
                    }

                    if ($lockedSession->practice_fixture_set_id !== $attempt->practice_fixture_set_id) {
                        throw ValidationException::withMessages([
                            'admission_attempt_id' => "Applicant [{$attempt->reference_code}] and exam session must belong to the same workspace.",
                        ]);
                    }

                    ExamAssignment::releaseTerminalMissedAssignmentsForAttempt(
                        (int) $attempt->id,
                        "Reassigned to {$lockedSession->code} after prior absence",
                    );

                    $hasActiveAssignmentInAnotherSession = ExamAssignment::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->where('exam_session_id', '!=', $lockedSession->id)
                        ->whereIn('status', [ExamAssignment::STATUS_ASSIGNED])
                        ->lockForUpdate()
                        ->exists();
                    if ($hasActiveAssignmentInAnotherSession) {
                        throw ValidationException::withMessages([
                            'admission_attempt_id' => "Applicant [{$attempt->reference_code}] is already assigned to another session. Reassign instead.",
                        ]);
                    }

                    /** @var ExamAssignment|null $existingAssignment */
                    $existingAssignment = ExamAssignment::query()
                        ->where('admission_attempt_id', $attempt->id)
                        ->where('exam_session_id', $lockedSession->id)
                        ->lockForUpdate()
                        ->first();
                    if ($existingAssignment !== null) {

                        $existingAssignment->update([
                            'status' => ExamAssignment::STATUS_ASSIGNED,
                            'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                            'origin' => ExamAssignment::ORIGIN_MANUAL,
                            'ended_at' => null,
                            'end_reason' => null,
                            'version' => (int) $existingAssignment->version + 1,
                        ]);

                        $this->seatAllocator->allocateIfPublished(
                            $lockedSession,
                            $existingAssignment,
                            $actor,
                            'Bulk staff assignment reactivated',
                        );
                    } else {
                        $assignment = ExamAssignment::query()->create([
                            'exam_session_id' => $lockedSession->id,
                            'admission_attempt_id' => $attempt->id,
                            'status' => ExamAssignment::STATUS_ASSIGNED,
                            'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                            'origin' => ExamAssignment::ORIGIN_MANUAL,
                        ]);

                        $this->seatAllocator->allocateIfPublished(
                            $lockedSession,
                            $assignment,
                            $actor,
                            'Bulk staff assignment created',
                        );
                    }
                }

                $lockedSession->increment('version');

                $this->recordAuditEvent->handle('exam.bulk_assignment_created', [
                    'exam_session_id' => (int) $lockedSession->id,
                    'attempt_ids' => $cleanIds,
                    'count' => count($cleanIds),
                ], (string) $actor->id);

                return [
                    'assigned_count' => count($cleanIds),
                    'assigned_ids' => $cleanIds,
                ];
            },
        );
    }
}
