<?php

namespace App\Assessment\Support;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class EnsureGradingSessionForCompletedExam
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ExamSession $examSession, User $actor, string $source): GradingSession
    {
        if (! in_array($source, ['close', 'direct_assessment', 'ingestion', 'repair'], true)) {
            throw new InvalidArgumentException('Unsupported grading session initialization source.');
        }

        return DB::transaction(function () use ($examSession, $actor, $source): GradingSession {
            $lockedExamSession = ExamSession::query()
                ->whereKey($examSession->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedExamSession === null) {
                throw new RuntimeException('Exam session not found.');
            }

            if ($lockedExamSession->status !== ExamSession::STATUS_COMPLETED) {
                throw new RuntimeException('A grading session can only be initialized for a completed exam session.');
            }

            $gradingSession = GradingSession::query()
                ->lockForUpdate()
                ->where('exam_session_id', $lockedExamSession->getKey())
                ->first();

            if ($gradingSession !== null) {
                return $gradingSession;
            }

            $gradingSession = GradingSession::query()->create([
                'exam_session_id' => $lockedExamSession->getKey(),
                'status' => GradingSession::STATUS_OPEN,
                'opened_by_user_id' => $actor->getKey(),
                'opened_at' => now(),
                'version' => 1,
            ]);

            $this->recordAuditEvent->handle('grading.session_opened', [
                'grading_session_id' => $gradingSession->id,
                'exam_session_id' => $lockedExamSession->getKey(),
                'source' => $source,
            ], (string) $actor->getKey());

            return $gradingSession;
        });
    }
}
