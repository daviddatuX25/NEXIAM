<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CloseExamSessionReservation
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, User $actor, int $expectedVersion, ?string $reason = null): ExamSession
    {
        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ExamSession {
            $session = ExamSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale exam session version.');
            }

            if (! $session->isOpenForReservation()) {
                return $session;
            }

            $session->update([
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'reservation_closed_at' => now(),
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_reservation_closed', [
                'exam_session_id' => $session->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $session->fresh(['room', 'academicCycle']);
        });
    }
}
