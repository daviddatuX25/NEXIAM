<?php

namespace App\Examination\Support;

use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use Carbon\CarbonImmutable;

final class CreateExamSessionEvaluator
{
    public function evaluate(
        AcademicCycle $cycle,
        Room $room,
        CarbonImmutable $startsAt,
        CarbonImmutable $effectiveEndAt,
        ?string $code = null,
        ?int $ignoreSessionId = null,
    ): PreflightResult {
        $chronologyValid = $startsAt->lessThan($effectiveEndAt);
        $roomSuitable = $room->isExamRoom();
        $overlapQuery = ExamSession::query()->forWorkspace()
            ->where('room_id', $room->id)
            ->where('status', '!=', ExamSession::STATUS_CANCELLED)
            ->where('starts_at', '<', $effectiveEndAt)
            ->where('effective_end_at', '>', $startsAt);
        if ($ignoreSessionId !== null) {
            $overlapQuery->where('id', '!=', $ignoreSessionId);
        }
        $roomCollisionFree = ! $overlapQuery->exists();

        $checks = [
            [
                'key' => 'chronology_valid',
                'label' => 'Schedule chronology',
                'status' => $chronologyValid ? 'pass' : 'fail',
                'detail' => $chronologyValid ? 'Start precedes end' : 'Start must precede end',
            ],
            [
                'key' => 'room_suitable',
                'label' => 'Room suitability',
                'status' => $roomSuitable ? 'pass' : 'fail',
                'detail' => $roomSuitable ? 'Configured for exam sessions' : 'Room is not configured for examination sessions',
            ],
            [
                'key' => 'no_room_collision',
                'label' => 'Schedule collision',
                'status' => $roomCollisionFree ? 'pass' : 'fail',
                'detail' => $roomCollisionFree ? 'No conflicts in room' : 'Overlaps with existing session',
            ],
        ];
        $diff = [
            ['key' => 'session', 'label' => 'Session', 'before' => null, 'after' => $code ?? 'Unassigned'],
            ['key' => 'room', 'label' => 'Room', 'before' => null, 'after' => "{$room->name} (Capacity: {$room->capacity})"],
            ['key' => 'schedule', 'label' => 'Schedule', 'before' => null, 'after' => "{$startsAt->toIso8601String()} to {$effectiveEndAt->toIso8601String()}"],
            ['key' => 'cycle', 'label' => 'Academic cycle', 'before' => null, 'after' => $cycle->code],
        ];

        return new PreflightResult(
            outcome: $chronologyValid && $roomSuitable && $roomCollisionFree ? 'pass' : 'fail',
            checks: $checks,
            diff: $diff,
        );
    }
}
