<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CloseConsultationSessionReservation
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion, ?string $reason = null): ConsultationSession
    {
        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale Release Day session version.');
            }

            if (! $session->isOpenForReservation()) {
                return $session;
            }

            $session->update([
                'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                'reservation_closed_at' => now(),
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('consultation.session_reservation_closed', [
                'consultation_session_id' => $session->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $session->fresh(['room', 'academicCycle']);
        });
    }
}
