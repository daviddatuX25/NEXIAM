<?php

namespace App\Consultation\Actions;

use App\Consultation\Support\ConsultationReservationEvaluator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OpenConsultationSessionReservation
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ConsultationReservationEvaluator $evaluator,
    ) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion, ?string $reason = null): ConsultationSession
    {
        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            $preflight = $this->evaluator->evaluate($session, $expectedVersion);
            if ($preflight->outcome === 'fail') {
                throw new RuntimeException('Release Day session cannot open for reservation.');
            }

            if ($preflight->outcome === 'noop') {
                return $session;
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
