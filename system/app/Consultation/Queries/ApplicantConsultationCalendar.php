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
     * Draft sessions open for reservation in the attempt's cycle.
     *
     * @return Collection<int, ConsultationSession>
     */
    public function openSessions(AdmissionAttempt $attempt, ?int $excludeSessionId = null): Collection
    {
        return ConsultationSession::query()
            ->with('room:id,code,name,capacity')
            ->openForReservation()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->where('status', ConsultationSession::STATUS_DRAFT)
            ->when($excludeSessionId !== null, fn ($query) => $query->where('id', '!=', $excludeSessionId))
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Calendar-days shape used by the portal consultation page (date => slots[]).
     *
     * @return list<array{date: string, slots: list<array<string, mixed>>}>
     */
    public function calendarDays(AdmissionAttempt $attempt, ?int $excludeSessionId = null): array
    {
        return $this->openSessions($attempt, $excludeSessionId)
            ->groupBy(fn (ConsultationSession $session) => $session->scheduled_at?->toDateString() ?? 'unknown')
            ->map(fn (Collection $daySessions, string $date) => [
                'date' => $date,
                'slots' => $daySessions->map(fn (ConsultationSession $session) => $this->slotPayload($session))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, code: string, date: ?string, scheduled_at: ?string, start_time: ?string, time_label: string, room: ?string, capacity: int, remaining: int, full: bool}
     */
    public function slotPayload(ConsultationSession $session): array
    {
        $assigned = ConsultationAssignment::query()
            ->active()
            ->where('consultation_session_id', $session->id)
            ->count();

        $capacity = (int) ($session->room?->capacity ?? 0);
        if ($capacity <= 0) {
            $capacity = 50;
        }
        $remaining = max(0, $capacity - $assigned);

        return [
            'id' => $session->id,
            'code' => $session->code,
            'date' => $session->scheduled_at?->toDateString(),
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'start_time' => $session->scheduled_at?->format('H:i'),
            'time_label' => $session->scheduled_at ? $session->scheduled_at->format('h:i A') : 'Time TBD',
            'room' => $session->room?->name ?: $session->room?->code,
            'capacity' => $capacity,
            'remaining' => $remaining,
            'full' => $remaining < 1,
        ];
    }
}
