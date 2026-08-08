<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnpublishExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion, string $reason): ExamSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Unpublish requires a reason.');
        }

        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if ($session->status !== ExamSession::STATUS_PUBLISHED) {
                throw new RuntimeException('Only published sessions may be unpublished.');
            }

            $session->update([
                'status' => ExamSession::STATUS_DRAFT,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_unpublished', [
                'exam_session_id' => $session->id,
                'reason' => $reason,
                'prior_published_at' => $session->published_at?->toIso8601String(),
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
