<?php

namespace App\Examination\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use Illuminate\Support\Collection;

/**
 * Applicant slot calendar — the ONE projection for both the manual schedule
 * page (ApplicantScheduleController::show) and the Nexi skill kernel row
 * exams.slots.list@1 (Slice 9). Never duplicate this query.
 */
final class ApplicantSlotCalendar
{
    /**
     * Sessions open for reservation in the attempt's cycle.
     *
     * @return Collection<int, ExamSession>
     */
    public function openSlots(AdmissionAttempt $attempt): Collection
    {
        return ExamSession::query()
            ->with('room:id,code,name,capacity')
            ->openForReservation()
            ->where('academic_cycle_id', $attempt->academic_cycle_id)
            ->whereNotIn('status', [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Calendar-days shape used by the manual page (date => slots[]).
     *
     * @return list<array{date: string, slots: list<array<string, mixed>>}>
     */
    public function calendarDays(AdmissionAttempt $attempt): array
    {
        return $this->openSlots($attempt)
            ->groupBy(fn (ExamSession $session) => $session->starts_at?->toDateString() ?? 'unknown')
            ->map(fn (Collection $daySessions, string $date) => [
                'date' => $date,
                'slots' => $daySessions->map(fn (ExamSession $session) => $this->slotPayload($session))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Flat slot list for the Nexi skill (exams.slots.list@1).
     *
     * @return list<array<string, mixed>>
     */
    public function flatSlots(AdmissionAttempt $attempt): array
    {
        return $this->openSlots($attempt)
            ->map(fn (ExamSession $session) => $this->slotPayload($session))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, code: string, start_time: ?string, end_time: ?string, room: string, capacity: int, remaining: int, full: bool}
     */
    private function slotPayload(ExamSession $session): array
    {
        $assigned = ExamAssignment::query()
            ->active()
            ->where('exam_session_id', $session->id)
            ->count();

        $capacity = (int) ($session->room?->capacity ?? 0);
        $remaining = max(0, $capacity - $assigned);

        return [
            'id' => $session->id,
            'code' => $session->code,
            'start_time' => $session->starts_at?->format('H:i'),
            'end_time' => $session->effective_end_at?->format('H:i'),
            'room' => $session->room?->name ?: $session->room?->code,
            'capacity' => $capacity,
            'remaining' => $remaining,
            'full' => $remaining < 1,
        ];
    }
}
