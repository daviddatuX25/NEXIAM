<?php

namespace App\Foundation\Support;

use App\Examination\Support\PreflightResult;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Room;

final class RoomUpdateEvaluator
{
    public function evaluate(Room $room, int $newCapacity, bool $newIsExamRoom): PreflightResult
    {
        $exists = $room->exists && $room->newQuery()->whereKey($room->getKey())->exists();
        $futureSessions = $exists
            ? ExamSession::query()
                ->where('room_id', $room->id)
                ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                ->where('effective_end_at', '>', now())
                ->withCount([
                    'assignments as assignments_count' => fn ($query) => $query->whereIn('status', ExamAssignment::ACTIVE_STATUSES),
                ])
                ->get()
            : collect();
        $maxAssignments = (int) $futureSessions->max('assignments_count');
        $capacitySufficient = $newCapacity >= $maxAssignments;
        $examRoomSuitable = $futureSessions->isEmpty() || $newIsExamRoom;

        $checks = [
            [
                'key' => 'room_exists',
                'label' => 'Room',
                'status' => $exists ? 'pass' : 'fail',
                'detail' => $exists ? 'Room exists' : 'Room does not exist',
            ],
            [
                'key' => 'future_session_capacity',
                'label' => 'Future session capacity',
                'status' => $capacitySufficient ? 'pass' : 'fail',
                'detail' => $capacitySufficient
                    ? 'Capacity covers all assigned examinees in future sessions'
                    : "Capacity must be at least {$maxAssignments} for a future session",
            ],
            [
                'key' => 'future_session_exam_room',
                'label' => 'Exam room suitability',
                'status' => $examRoomSuitable ? 'pass' : 'fail',
                'detail' => $examRoomSuitable
                    ? 'Room remains suitable for its future exam sessions'
                    : 'Room with future exam sessions must remain an exam room',
            ],
        ];

        return new PreflightResult(
            outcome: $exists && $capacitySufficient && $examRoomSuitable ? 'pass' : 'fail',
            checks: $checks,
            diff: [[
                'key' => 'capacity',
                'label' => 'Capacity',
                'before' => (string) $room->capacity,
                'after' => (string) $newCapacity,
            ]],
        );
    }
}
