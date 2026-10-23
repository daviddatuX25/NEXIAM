<?php

namespace App\Foundation;

use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use Illuminate\Validation\ValidationException;

final class SeatingArrangementEligibility
{
    public function assertEligible(
        Room $room,
        SeatingArrangement $arrangement,
        string $field = 'seating_arrangement_id',
        bool $requireActive = true,
    ): void {
        if (! $room->allowsSeatingArrangement()) {
            throw ValidationException::withMessages([
                $field => "{$room->name} cannot be assigned a seating arrangement because it is an assembly venue or service point.",
            ]);
        }

        if ($requireActive && ! $arrangement->is_active) {
            throw ValidationException::withMessages([
                $field => 'Select an active seating arrangement.',
            ]);
        }

        if ((int) $arrangement->rows * (int) $arrangement->columns > (int) $room->capacity) {
            throw ValidationException::withMessages([
                $field => "{$arrangement->name} needs {$arrangement->desk_count} desks, but {$room->name} has capacity {$room->capacity}.",
            ]);
        }

        if ($arrangement->availability_mode === SeatingArrangement::AVAILABILITY_SELECTED_ROOMS
            && ! $arrangement->rooms()->whereKey($room->id)->exists()) {
            throw ValidationException::withMessages([
                $field => "{$arrangement->name} is not available for {$room->name}.",
            ]);
        }
    }
}
