<?php

namespace App\Examination\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Scheduling\AssignmentInvariant;
use Illuminate\Support\Collection;

/**
 * Applicant slot calendar — the ONE projection for both the manual schedule
 * page (ApplicantScheduleController::show) and the Xiam skill kernel row
 * exams.slots.list@1 (Slice 9). Never duplicate this query.
 */
final class ApplicantSlotCalendar
{
    /**
     * Sessions open for reservation in the attempt's cycle.
     *
     * @return Collection<int, ExamSession>
     */
    public function openSlots(AdmissionAttempt $attempt, ?int $excludeSessionId = null): Collection
    {
        $query = ExamSession::query()->forPracticeSet($attempt->practice_fixture_set_id)
            ->with('room:id,code,name,capacity,is_active')
            ->openForReservation()
            ->where('status', ExamSession::STATUS_DRAFT)
            ->where('academic_cycle_id', $attempt->academic_cycle_id);

        if ($excludeSessionId !== null) {
            $query->where('id', '!=', $excludeSessionId);
        }

        return $query->orderBy('starts_at')->get();
    }

    /**
     * Calendar-days shape used by the manual page (date => slots[]).
     *
     * @return list<array{date: string, slots: list<array<string, mixed>>}>
     */
    public function calendarDays(AdmissionAttempt $attempt, ?int $excludeSessionId = null): array
    {
        return $this->openSlots($attempt, $excludeSessionId)
            ->groupBy(fn (ExamSession $session) => $session->starts_at?->toDateString() ?? 'unknown')
            ->map(fn (Collection $daySessions, string $date) => [
                'date' => $date,
                'slots' => $daySessions->map(fn (ExamSession $session) => $this->slotPayload($session))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Flat slot list for the Xiam skill (exams.slots.list@1).
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
     * @return array{id: int, code: string, date: ?string, starts_at: ?string, start_time: ?string, end_time: ?string, room: ?string, capacity: int, remaining: int, full: bool}
     */
    private function slotPayload(ExamSession $session): array
    {
        $assigned = ExamAssignment::query()
            ->active()
            ->where('exam_session_id', $session->id)
            ->count();

        $capacity = app(AssignmentInvariant::class)->capacityLimit($session);
        $remaining = max(0, $capacity - $assigned);

        return [
            'id' => $session->id,
            'code' => $session->code,
            'date' => $session->starts_at?->format('Y-m-d'),
            'starts_at' => $session->starts_at?->toIso8601String(),
            'start_time' => $session->starts_at?->format('H:i'),
            'end_time' => $session->effective_end_at?->format('H:i'),
            'room' => $session->room?->name ?: $session->room?->code,
            'capacity' => $capacity,
            'remaining' => $remaining,
            'full' => $remaining < 1,
        ];
    }
}
