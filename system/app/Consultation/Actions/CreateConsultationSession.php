<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateConsultationSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        AcademicCycle $cycle,
        Room $room,
        string $code,
        string $scheduledAt,
        string $bookingMode,
        User $actor,
    ): ConsultationSession {
        return DB::transaction(function () use ($cycle, $room, $code, $scheduledAt, $bookingMode, $actor): ConsultationSession {
            if (! in_array($bookingMode, ConsultationSession::BOOKING_MODES, true)) {
                throw new RuntimeException('Invalid consultation booking mode.');
            }

            // Single scheduled_at; no duration window, so no room-overlap check
            // (founder: time is whatever the admin sets; venue is just a room).
            $session = ConsultationSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'code' => $code,
                'status' => ConsultationSession::STATUS_DRAFT,
                'booking_mode' => $bookingMode,
                'scheduled_at' => $scheduledAt,
            ]);

            $this->recordAuditEvent->handle('consultation.session_created', [
                'consultation_session_id' => $session->id,
                'code' => $code,
                'booking_mode' => $bookingMode,
            ], (string) $actor->id);

            return $session;
        });
    }
}
