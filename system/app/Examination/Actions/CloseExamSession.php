<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CloseExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion): ExamSession
    {
        return DB::transaction(function () use ($session, $actor, $expectedVersion): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status === ExamSession::STATUS_COMPLETED) {
                return $session;
            }

            if (! in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
                throw new RuntimeException('Session cannot be closed from current state.');
            }

            $session->update([
                'status' => ExamSession::STATUS_COMPLETED,
                'closed_at' => now(),
                'version' => $session->version + 1,
            ]);

            GradingSession::query()->firstOrCreate(
                ['exam_session_id' => $session->id],
                [
                    'status' => GradingSession::STATUS_OPEN,
                    'opened_at' => now(),
                ],
            );

            $this->recordAuditEvent->handle('exam.session_closed', [
                'exam_session_id' => $session->id,
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
