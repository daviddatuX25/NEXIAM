<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RemoveApplicantFromExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ExamSession $session, ExamAssignment $assignment, User $actor, ?string $reason = null): ExamAssignment
    {
        return DB::transaction(function () use ($session, $assignment, $actor, $reason): ExamAssignment {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $assignment->exam_session_id !== (int) $session->id) {
                throw new RuntimeException('Assignment does not belong to this session.');
            }

            if (! $assignment->isActive()) {
                throw new RuntimeException('Assignment is not active.');
            }
            if ($session->status !== ExamSession::STATUS_DRAFT) {
                throw new RuntimeException('Published or finalized session rosters are locked for ordinary scheduling. Exam Day walk-in and early-traveler changes must use AccommodateApplicantExamSession on Monitoring.');
            }

            $assignment->update([
                'status' => ExamAssignment::STATUS_REMOVED,
                'ended_at' => now(),
                'end_reason' => $reason ?: 'Removed by staff',
                'version' => $assignment->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.assignment_removed', [
                'assignment_id' => $assignment->id,
                'exam_session_id' => $session->id,
                'attempt_id' => $assignment->admission_attempt_id,
            ], (string) $actor->id);

            return $assignment->fresh();
        });
    }
}
