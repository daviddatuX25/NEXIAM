<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordExamSubmission
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamAssignment $assignment, User $actor, string $idempotencyKey): ExamSubmissionRecord
    {
        $existing = ExamSubmissionRecord::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($assignment, $actor, $idempotencyKey): ExamSubmissionRecord {
            $assignment = ExamAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $session = ExamSession::query()->findOrFail($assignment->exam_session_id);

            if (! in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
                throw new RuntimeException('Submission is not allowed for this session state.');
            }

            if (now()->gt($session->effective_end_at)) {
                throw new RuntimeException('Submission window has closed.');
            }

            if ($assignment->submissionRecord()->exists()) {
                throw new RuntimeException('Submission already recorded.');
            }

            $record = ExamSubmissionRecord::query()->create([
                'exam_assignment_id' => $assignment->id,
                'recorded_by_user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'submitted_at' => now(),
            ]);

            $this->recordAuditEvent->handle('exam.submission_recorded', [
                'record_id' => $record->id,
                'assignment_id' => $assignment->id,
            ], (string) $actor->id);

            return $record;
        });
    }
}
