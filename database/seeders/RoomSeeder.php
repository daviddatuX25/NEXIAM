<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['name' => 'Room 101', 'building' => 'Main Building', 'floor' => '1st Floor', 'capacity' => 30],
            ['name' => 'Room 102', 'building' => 'Main Building', 'floor' => '1st Floor', 'capacity' => 30],
            ['name' => 'Room 201', 'building' => 'Main Building', 'floor' => '2nd Floor', 'capacity' => 30],
            ['name' => 'Lab Room 1', 'building' => 'ITBR', 'floor' => 'Ground Floor', 'capacity' => 25],
        ];

        foreach ($rooms as $r) {
            Room::firstOrCreate(
                ['building' => $r['building'], 'name' => $r['name']],
                ['floor' => $r['floor'], 'capacity' => $r['capacity'], 'is_active' => true]
            );
        }
    }
}
