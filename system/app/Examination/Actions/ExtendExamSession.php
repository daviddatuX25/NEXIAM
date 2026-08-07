<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Single effective deadline (REQ-VS-020). */
class ExtendExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion, CarbonInterface $effectiveEndAt): ExamSession
    {
        return DB::transaction(function () use ($session, $actor, $expectedVersion, $effectiveEndAt): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if (! in_array($session->status, [
                ExamSession::STATUS_PUBLISHED,
                ExamSession::STATUS_IN_PROGRESS,
            ], true)) {
                throw new RuntimeException('Session cannot be extended from current state.');
            }

            if ($effectiveEndAt->lte($session->effective_end_at)) {
                throw new RuntimeException('New effective end must be after the current effective end.');
            }

            $session->update([
                'effective_end_at' => $effectiveEndAt,
                'ends_at' => $effectiveEndAt,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_extended', [
                'exam_session_id' => $session->id,
                'effective_end_at' => $effectiveEndAt->toIso8601String(),
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
