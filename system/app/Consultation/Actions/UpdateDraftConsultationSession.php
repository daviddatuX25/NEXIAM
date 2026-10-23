<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateDraftConsultationSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ConsultationSession $session,
        AcademicCycle $cycle,
        Room $room,
        string $code,
        string $scheduledAt,
        string $bookingMode,
        User $actor,
        int $expectedVersion,
        ?bool $autoAssignEnabled = null,
    ): ConsultationSession {
        return DB::transaction(function () use ($session, $cycle, $room, $code, $scheduledAt, $bookingMode, $actor, $expectedVersion, $autoAssignEnabled): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale Release Day session version.');
            }

            if ($session->status !== ConsultationSession::STATUS_DRAFT) {
                throw new RuntimeException('Only draft Release Day sessions may be edited.');
            }

            if ($session->isOpenForReservation()) {
                throw new RuntimeException('Close Release Day session reservations before editing schedule fields.');
            }

            if (! in_array($bookingMode, ConsultationSession::BOOKING_MODES, true)) {
                throw new RuntimeException('Invalid consultation booking mode.');
            }

            $session->update([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'scheduled_at' => $scheduledAt,
                'booking_mode' => $bookingMode,
                'auto_assign_enabled' => $autoAssignEnabled ?? $session->auto_assign_enabled,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('consultation.session_updated', [
                'consultation_session_id' => $session->id,
                'code' => $code,
                'booking_mode' => $bookingMode,
            ], (string) $actor->id);

            return $session->fresh(['room', 'academicCycle']);
        });
    }
}
