<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompleteConsultationSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion): ConsultationSession
    {
        return DB::transaction(function () use ($session, $actor, $expectedVersion): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale consultation session version.');
            }

            if ($session->status === ConsultationSession::STATUS_COMPLETED) {
                return $session;
            }

            if (! in_array($session->status, [ConsultationSession::STATUS_PUBLISHED, ConsultationSession::STATUS_IN_PROGRESS], true)) {
                throw new RuntimeException('Session cannot be completed from current state.');
            }

            $session->update([
                'status' => ConsultationSession::STATUS_COMPLETED,
                'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                'reservation_closed_at' => $session->isOpenForReservation() ? now() : $session->reservation_closed_at,
                'completed_at' => now(),
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('consultation.session_completed', [
                'consultation_session_id' => $session->id,
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
