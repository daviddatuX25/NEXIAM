<?php

namespace App\Reports\Queries;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\ContextualReadToolHandler;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;

final class RoomUtilizationDataQuery implements ContextualReadToolHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handleWithContext(array $input, User $actor, InvocationContext $context): array
    {
        $institutionId = (int) ($context->institutionId ?? $actor->actorScope()->institutionId ?? 0);
        $cycleId = isset($input['academic_cycle_id']) && is_numeric($input['academic_cycle_id'])
            ? (int) $input['academic_cycle_id']
            : null;

        return $this->forXiamSafe($institutionId, $cycleId);
    }

    /**
     * @return array{
     *   institution_id: int,
     *   cycle_id: ?int,
     *   cycle_name: string,
     *   total_rooms: int,
     *   total_capacity: int,
     *   total_seats_utilized: int,
     *   overall_utilization_rate_percent: float,
     *   rooms: list<array{
     *     room_code: string,
     *     room_name: string,
     *     building: string,
     *     capacity: int,
     *     sessions_hosted: int,
     *     seats_utilized: int,
     *     utilization_rate_percent: float,
     *     status: string
     *   }>,
     *   as_of: string
     * }
     */
    public function forXiamSafe(int $institutionId, ?int $cycleId = null): array
    {
        $cycle = $cycleId === null
            ? AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->first()
            : AcademicCycle::query()->where('institution_id', $institutionId)->where('id', $cycleId)->first();
        $cycleId = $cycle?->id;

        $rooms = Room::query()
            ->where('institution_id', $institutionId)
            ->orderBy('name')
            ->get();
        $sessions = $cycleId === null
            ? collect()
            : ExamSession::query()
                ->forPracticeSet(null)
                ->where('academic_cycle_id', $cycleId)
                ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
                ->with('assignments')
                ->get();

        $roomRows = [];
        $totalCapacity = 0;
        $totalSeatsUtilized = 0;

        foreach ($rooms as $room) {
            $roomSessions = $sessions->where('room_id', $room->id);
            $sessionCount = $roomSessions->count();
            $capacity = (int) $room->capacity;
            $seatsUtilized = $roomSessions->sum(fn ($session) => $session->assignments->count());
            $availableSeats = $sessionCount * $capacity;

            $totalCapacity += $capacity;
            $totalSeatsUtilized += $seatsUtilized;

            $roomRows[] = [
                'room_code' => (string) ($room->code ?? "RM-{$room->id}"),
                'room_name' => (string) $room->name,
                'building' => (string) ($room->building ?? 'Main Campus'),
                'capacity' => $capacity,
                'sessions_hosted' => $sessionCount,
                'seats_utilized' => $seatsUtilized,
                'utilization_rate_percent' => $availableSeats > 0
                    ? round(($seatsUtilized / $availableSeats) * 100, 1)
                    : 0.0,
                'status' => $room->is_active ? 'Active' : 'Inactive',
            ];
        }

        return [
            'institution_id' => $institutionId,
            'cycle_id' => $cycleId,
            'cycle_name' => $cycle?->staffLabel() ?? '',
            'total_rooms' => count($roomRows),
            'total_capacity' => $totalCapacity,
            'total_seats_utilized' => $totalSeatsUtilized,
            'overall_utilization_rate_percent' => $totalCapacity > 0
                ? round(($totalSeatsUtilized / $totalCapacity) * 100, 1)
                : 0.0,
            'rooms' => $roomRows,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $cycleId = isset($filters['academic_cycle_id'])
            ? (int) $filters['academic_cycle_id']
            : AcademicCycle::active()?->id;

        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);

        $rooms = Room::query()->orderBy('name')->get();
        $sessions = $cycleId === null ? collect() : ExamSession::query()->forPracticeSet(null)
            ->with(['assignments'])
            ->where('academic_cycle_id', $cycleId)
            ->get();

        $roomRows = [];
        $totalCapacity = 0;
        $totalSeated = 0;

        foreach ($rooms as $index => $room) {
            $roomSessions = $sessions->where('room_id', $room->id);
            $sessionCount = $roomSessions->count();
            $roomCapacity = (int) $room->capacity;
            $seatsUtilized = $roomSessions->sum(fn ($s) => $s->assignments->count());
            $maxPotentialSeats = $sessionCount * $roomCapacity;
            $utilizationRate = $maxPotentialSeats > 0
                ? number_format(($seatsUtilized / $maxPotentialSeats) * 100, 1).'%'
                : '0.0%';

            $totalCapacity += $roomCapacity;
            $totalSeated += $seatsUtilized;

            $roomRows[] = [
                'row_no' => $index + 1,
                'room_code' => $this->escapeText($room->code ?? "RM-{$room->id}"),
                'room_name' => $this->escapeText($room->name),
                'building' => $this->escapeText($room->building ?? 'Main Campus'),
                'floor' => $this->escapeText((string) ($room->floor ?? '1')),
                'capacity' => $roomCapacity,
                'sessions_hosted' => $sessionCount,
                'seats_utilized' => $seatsUtilized,
                'utilization_rate' => $utilizationRate,
                'status' => $room->is_active ? 'Active' : 'Inactive',
            ];
        }

        $avgRate = count($roomRows) > 0 && $totalCapacity > 0
            ? number_format(($totalSeated / max(1, $sessions->count() * ($totalCapacity / max(1, count($roomRows))))) * 100, 1).'%'
            : '0.0%';

        return [
            'institution_name' => $this->escapeText((string) ($cycle?->institution?->name ?? Institution::query()->value('name') ?? 'Institution')),
            'institution_code' => $this->escapeText((string) ($cycle?->institution?->code ?? Institution::query()->value('code') ?? '')),
            'academic_cycle' => $this->escapeText($cycle?->staffLabel() ?? ''),
            'report_date' => now()->toDateString(),
            'total_rooms' => count($roomRows),
            'total_capacity' => $totalCapacity,
            'total_examinees_seated' => $totalSeated,
            'average_utilization_rate' => $avgRate,
            'rooms' => $roomRows,
            'prepared_by' => 'Logistics Officer',
            'verified_by' => 'Facility Supervisor',
            'approved_by' => 'Head of Guidance & Testing',
        ];
    }

    private function escapeText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
