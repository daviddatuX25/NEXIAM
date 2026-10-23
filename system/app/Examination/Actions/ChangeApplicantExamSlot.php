<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChangeApplicantExamSlot
{
    public function __construct(
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        ExamSession $targetSession,
        AdmissionAttempt $attempt,
        User $actor,
        int $expectedAssignmentId,
        int $expectedAssignmentVersion,
        string $idempotencyKey,
    ): ExamAssignment {
        return $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $attempt->academic_cycle_id,
            function () use ($targetSession, $attempt, $actor, $expectedAssignmentId, $expectedAssignmentVersion, $idempotencyKey): ExamAssignment {
                return DB::transaction(function () use ($targetSession, $attempt, $actor, $expectedAssignmentId, $expectedAssignmentVersion, $idempotencyKey): ExamAssignment {
                    $currentAssignment = ExamAssignment::query()
                        ->active()
                        ->where('admission_attempt_id', $attempt->id)
                        ->firstOrFail();

                    $sourceSessionId = (int) $currentAssignment->exam_session_id;
                    $targetSessionId = (int) $targetSession->id;

                    if ($sourceSessionId === $targetSessionId) {
                        throw new RuntimeException('You are already assigned to this exam session.');
                    }

                    $firstId = min($sourceSessionId, $targetSessionId);
                    $secondId = max($sourceSessionId, $targetSessionId);
                    $first = ExamSession::query()->lockForUpdate()->findOrFail($firstId);
                    $second = ExamSession::query()->lockForUpdate()->findOrFail($secondId);
                    $lockedSource = $sourceSessionId === $firstId ? $first : $second;
                    $lockedTarget = $targetSessionId === $firstId ? $first : $second;
                    $lockedAttempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
                    $lockedAssignment = ExamAssignment::query()->lockForUpdate()->findOrFail($currentAssignment->id);

                    if ((int) $lockedAssignment->id !== $expectedAssignmentId || (int) $lockedAssignment->version !== $expectedAssignmentVersion) {
                        throw new RuntimeException('Your current appointment has been modified. Please refresh.');
                    }

                    if ($lockedSource->status !== ExamSession::STATUS_DRAFT) {
                        throw new RuntimeException('Your current exam schedule has been published and cannot be changed.');
                    }

                    if ($lockedTarget->status !== ExamSession::STATUS_DRAFT || ! $lockedTarget->isOpenForReservation()) {
                        throw new RuntimeException('Selected exam session is not open for reservation.');
                    }

                    if ((int) $lockedSource->academic_cycle_id !== (int) $lockedTarget->academic_cycle_id) {
                        throw new RuntimeException('Target session belongs to a different academic cycle.');
                    }

                    if ($this->invariant->remainingCapacity($lockedTarget) <= 0) {
                        throw new RuntimeException('The selected exam session is full.');
                    }

                    $lockedAssignment->update([
                        'status' => ExamAssignment::STATUS_REMOVED,
                        'ended_at' => now(),
                        'end_reason' => 'Changed by applicant',
                        'version' => $lockedAssignment->version + 1,
                    ]);

                    $newAssignment = ExamAssignment::query()->create([
                        'exam_session_id' => $lockedTarget->id,
                        'admission_attempt_id' => $lockedAttempt->id,
                        'status' => ExamAssignment::STATUS_ASSIGNED,
                        'source' => ExamAssignment::SOURCE_APPLICANT_RESERVED,
                        'origin' => ExamAssignment::ORIGIN_RESERVATION,
                        'version' => 1,
                    ]);

                    $lockedSource->increment('version');
                    $lockedTarget->increment('version');

                    $this->recordAuditEvent->handle('exam.slot_switched', [
                        'attempt_id' => $lockedAttempt->id,
                        'from_assignment_id' => $lockedAssignment->id,
                        'to_assignment_id' => $newAssignment->id,
                        'from_session_id' => $lockedSource->id,
                        'to_session_id' => $lockedTarget->id,
                        'idempotency_key' => $idempotencyKey,
                    ], (string) $actor->id);

                    return $newAssignment;
                });
            },
        );
    }
}
