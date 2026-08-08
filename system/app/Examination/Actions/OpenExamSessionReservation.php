<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OpenExamSessionReservation
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

            if (in_array($session->status, [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Terminal sessions cannot open for reservation.');
            }

            if ($session->isOpenForReservation()) {
                return $session;
            }

            if ($session->room_id === null || $session->starts_at === null || $session->effective_end_at === null) {
                throw new RuntimeException('Room and schedule times are required before opening reservation.');
            }

            $session->update([
                'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
                'reservation_opened_at' => now(),
                'reservation_closed_at' => null,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('exam.session_reservation_opened', [
                'exam_session_id' => $session->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $session->fresh(['room', 'academicCycle']);
        });
    }
}
