<?php

namespace App\Foundation\Actions;

use App\Foundation\SeatingArrangementEligibility;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SetRoomDefaultSeatingArrangement
{
    public function __construct(
        private readonly SeatingArrangementEligibility $eligibility,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Room $room, ?SeatingArrangement $arrangement, User $actor): Room
    {
        return DB::transaction(function () use ($room, $arrangement, $actor): Room {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            $arrangement = $arrangement === null
                ? null
                : SeatingArrangement::query()->lockForUpdate()->findOrFail($arrangement->id);

            if ($arrangement !== null) {
                $this->eligibility->assertEligible($room, $arrangement, 'default_seating_arrangement_id');
            }

            $room->update(['default_seating_arrangement_id' => $arrangement?->id]);

            $this->audit->handle('setup.room_default_seating_arrangement_changed', [
                'room_id' => $room->id,
                'seating_arrangement_id' => $arrangement?->id,
            ], (string) $actor->id);

            return $room->fresh('defaultSeatingArrangement');
        });
    }
}
