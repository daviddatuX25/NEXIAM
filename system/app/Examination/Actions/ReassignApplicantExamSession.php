<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Force-move an active seat from one session to another (D-088: remove then assign, one transaction).
 *
 * Applicant notify on reassignment is deferred (outbox seam later).
 */
class ReassignApplicantExamSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentInvariant $assignmentInvariant,
    ) {}

    public function handle(
        ExamSession $fromSession,
        ExamAssignment $assignment,
        ExamSession $toSession,
        User $actor,
        ?string $reason = null,
    ): ExamAssignment {
        return DB::transaction(function () use ($fromSession, $assignment, $toSession, $actor, $reason): ExamAssignment {
            $firstId = min((int) $fromSession->id, (int) $toSession->id);
            $secondId = max((int) $fromSession->id, (int) $toSession->id);
            $first = ExamSession::query()->lockForUpdate()->findOrFail($firstId);
            $second = ExamSession::query()->lockForUpdate()->findOrFail($secondId);
            $fromSession = (int) $fromSession->id === $firstId ? $first : $second;
            $toSession = (int) $toSession->id === $firstId ? $first : $second;
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $assignment->exam_session_id !== (int) $fromSession->id) {
                throw new RuntimeException('Assignment does not belong to this session.');
            }

            if (! $assignment->isActive()) {
                throw new RuntimeException('Assignment is not active.');
            }

            if ((int) $fromSession->id === (int) $toSession->id) {
                throw new RuntimeException('Choose a different exam session for reassignment.');
            }
            if ($fromSession->status !== ExamSession::STATUS_DRAFT || $toSession->status !== ExamSession::STATUS_DRAFT) {
                throw new RuntimeException('Published or finalized session rosters are locked for ordinary scheduling. Exam Day walk-in and early-traveler changes must use AccommodateApplicantExamSession on Monitoring.');
            }

            if ((int) $fromSession->academic_cycle_id !== (int) $toSession->academic_cycle_id) {
                throw new RuntimeException('Target session must be in the same academic year.');
            }

            $activeOnTarget = ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $toSession->id)
                ->count();
            $capacity = $this->assignmentInvariant->capacityLimit($toSession);
            if ($activeOnTarget >= $capacity) {
                throw new RuntimeException('Target exam session is full.');
            }

            $endReason = trim((string) $reason);
            if ($endReason === '') {
                $endReason = 'Reassigned by staff';
            }

            $attemptId = (int) $assignment->admission_attempt_id;
            $fromId = (int) $fromSession->id;
            $toId = (int) $toSession->id;

            $assignment->update([
                'status' => ExamAssignment::STATUS_REMOVED,
                'ended_at' => now(),
                'end_reason' => $endReason,
                'version' => $assignment->version + 1,
            ]);

            $newAssignment = ExamAssignment::query()->create([
                'exam_session_id' => $toSession->id,
                'admission_attempt_id' => $attemptId,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            ]);

            $this->recordAuditEvent->handle('exam.assignment_reassigned', [
                'from_assignment_id' => $assignment->id,
                'to_assignment_id' => $newAssignment->id,
                'from_exam_session_id' => $fromId,
                'to_exam_session_id' => $toId,
                'attempt_id' => $attemptId,
                'reason' => $endReason,
                // notify_deferred: applicant outbox on remove/reassign not in this cut
            ], (string) $actor->id);

            return $newAssignment;
        });
    }
}
