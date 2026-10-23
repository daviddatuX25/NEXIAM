<?php

namespace App\Examination\Queries;

use App\Models\Examination\ExamSession;
use App\Models\Foundation\Room;
use Illuminate\Database\Eloquent\Builder;

final class AvailableExamRoomsQuery
{
    /**
     * @return list<array{id: int, name: string, code: string, capacity: int, is_available: bool}>
     */
    public function handle(int $institutionId, ?string $startsAt = null, ?string $effectiveEndAt = null): array
    {
        $rooms = Room::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->examRooms()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'capacity']);

        $occupiedRoomIds = [];
        if ($startsAt !== null && $effectiveEndAt !== null) {
            $occupiedRoomIds = ExamSession::query()->forWorkspace()
                ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                ->whereNotNull('room_id')
                ->where('starts_at', '<', $effectiveEndAt)
                ->where('effective_end_at', '>', $startsAt)
                ->whereHas('academicCycle', fn (Builder $query): Builder => $query->where('institution_id', $institutionId))
                ->pluck('room_id')
                ->map(static fn (int|string $roomId): int => (int) $roomId)
                ->all();
        }

        return $rooms
            ->map(static function (Room $room) use ($occupiedRoomIds): array {
                return [
                    'id' => (int) $room->id,
                    'name' => (string) $room->name,
                    'code' => (string) $room->code,
                    'capacity' => (int) $room->capacity,
                    'is_available' => ! in_array((int) $room->id, $occupiedRoomIds, true),
                ];
            })
            ->all();
    }
}
