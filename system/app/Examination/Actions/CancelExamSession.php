<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelExamSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion, string $reason): ExamSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Cancel requires a reason.');
        }

        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if (in_array($session->status, [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Session is already terminal.');
            }

            if ($session->status === ExamSession::STATUS_IN_PROGRESS) {
                throw new RuntimeException('In-progress sessions must be closed, not cancelled via CancelExamSession.');
            }

            ExamAssignment::query()
                ->active()
                ->where('exam_session_id', $session->id)
                ->lockForUpdate()
                ->get()
                ->each(function (ExamAssignment $assignment) use ($reason): void {
                    $assignment->update([
                        'status' => ExamAssignment::STATUS_CANCELLED,
                        'ended_at' => now(),
                        'end_reason' => $reason,
                        'version' => $assignment->version + 1,
                    ]);
                });

            $session->update([
                'status' => ExamSession::STATUS_CANCELLED,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'reservation_closed_at' => $session->isOpenForReservation() ? now() : $session->reservation_closed_at,
                'closed_at' => now(),
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_cancelled', [
                'exam_session_id' => $session->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
