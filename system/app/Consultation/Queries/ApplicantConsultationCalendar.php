<?php

namespace App\Consultation\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use Illuminate\Support\Collection;

/**
 * Applicant consultation calendar — the ONE projection for the portal
 * consultation page (ApplicantConsultationController::show).
 */
final class ApplicantConsultationCalendar
{
    /**
     * Published sessions open for reservation in the attempt's cycle.
     *
     * @return Collection<int, ConsultationSession>
     */
    public function openSessions(AdmissionAttempt $attempt): Collection
    {
        return ConsultationSession::query()
            ->with('room:id,code,name,capacity')
            ->openForReservation()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->where('status', ConsultationSession::STATUS_PUBLISHED)
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * @return array{id: int, code: string, scheduled_at: ?string, room: ?string, capacity: int, remaining: int, full: bool}
     */
    public function slotPayload(ConsultationSession $session): array
    {
        $assigned = ConsultationAssignment::query()
            ->active()
            ->where('consultation_session_id', $session->id)
            ->count();

        $capacity = (int) $session->room->capacity;
        $remaining = max(0, $capacity - $assigned);

        return [
            'id' => $session->id,
            'code' => $session->code,
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'room' => $session->room?->name ?: $session->room?->code,
            'capacity' => $capacity,
            'remaining' => $remaining,
            'full' => $remaining < 1,
        ];
    }
}
