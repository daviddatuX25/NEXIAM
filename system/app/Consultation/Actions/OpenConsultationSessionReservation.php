<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OpenConsultationSessionReservation
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion, ?string $reason = null): ConsultationSession
    {
        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale consultation session version.');
            }

            if (in_array($session->status, [ConsultationSession::STATUS_COMPLETED, ConsultationSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Terminal sessions cannot open for reservation.');
            }

            if ($session->isOpenForReservation()) {
                return $session;
            }

            if ($session->room_id === null || $session->scheduled_at === null) {
                throw new RuntimeException('Room and schedule time are required before opening reservation.');
            }

            $session->update([
                'booking_mode' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
                'reservation_opened_at' => now(),
                'reservation_closed_at' => null,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('consultation.session_reservation_opened', [
                'consultation_session_id' => $session->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $session->fresh(['room', 'academicCycle']);
        });
    }
}
