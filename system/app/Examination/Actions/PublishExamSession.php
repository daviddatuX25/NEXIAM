<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PublishExamSession
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

            if ($session->status !== ExamSession::STATUS_DRAFT) {
                throw new RuntimeException('Only draft sessions may be published.');
            }

            $session->update([
                'status' => ExamSession::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_published', [
                'exam_session_id' => $session->id,
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
