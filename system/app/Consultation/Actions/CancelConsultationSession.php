<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelConsultationSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion, string $reason): ConsultationSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Cancel requires a reason.');
        }

        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale consultation session version.');
            }

            if (in_array($session->status, [ConsultationSession::STATUS_COMPLETED, ConsultationSession::STATUS_CANCELLED], true)) {
                throw new RuntimeException('Session is already terminal.');
            }

            if ($session->status === ConsultationSession::STATUS_IN_PROGRESS) {
                throw new RuntimeException('In-progress sessions must be completed, not cancelled.');
            }

            ConsultationAssignment::query()
                ->active()
                ->where('consultation_session_id', $session->id)
                ->lockForUpdate()
                ->get()
                ->each(function (ConsultationAssignment $assignment) use ($reason): void {
                    $assignment->update([
                        'status' => ConsultationAssignment::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                        'cancel_reason' => $reason,
                        'version' => $assignment->version + 1,
                    ]);
                });

            $session->update([
                'status' => ConsultationSession::STATUS_CANCELLED,
                'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
                'reservation_closed_at' => $session->isOpenForReservation() ? now() : $session->reservation_closed_at,
                'cancelled_at' => now(),
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('consultation.session_cancelled', [
                'consultation_session_id' => $session->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
