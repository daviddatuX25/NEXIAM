<?php

namespace Database\Seeders;

use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use Illuminate\Database\Seeder;

class RoomSeatingProfileSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'ITBR-104' => [
                'name' => 'ITBR 104 30-desk examination layout',
                'rows' => 5,
                'columns' => 6,
                'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
            ],
            'ITBR-105' => [
                'name' => 'ITBR 105 40-desk examination layout',
                'rows' => 5,
                'columns' => 8,
                'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
            ],
            'RM-101' => [
                'name' => 'Main examination layout',
                'rows' => 5,
                'columns' => 6,
                'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
            ],
            'RM-LabA' => [
                'name' => 'Laboratory layout',
                'rows' => 4,
                'columns' => 6,
                'booklet_pattern' => SeatingArrangement::PATTERN_LATIN_FOUR,
            ],
            'ITBR-106' => [
                'name' => 'ITBR 106 40-desk examination layout',
                'rows' => 5,
                'columns' => 8,
                'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
            ],
        ] as $roomCode => $definition) {
            $room = Room::query()->where('code', $roomCode)->first();
            if ($room === null) {
                continue;
            }
            $arrangement = SeatingArrangement::query()->firstOrCreate(
                [
                    'normalized_name' => mb_strtolower($definition['name']),
                ],
                [
                    ...$definition,
                    'availability_mode' => in_array($roomCode, ['ITBR-104', 'RM-101'], true)
                        ? SeatingArrangement::AVAILABILITY_ALL_ROOMS
                        : SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
                    'is_active' => true,
                    'lock_version' => 1,
                ],
            );
            $arrangement->rooms()->sync(
                $arrangement->availability_mode === SeatingArrangement::AVAILABILITY_SELECTED_ROOMS ? [$room->id] : [],
            );
            if ($room->default_seating_arrangement_id === null) {
                $room->update(['default_seating_arrangement_id' => $arrangement->id]);
            }
        }
    }
}
