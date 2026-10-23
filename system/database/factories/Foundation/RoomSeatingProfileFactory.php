<?php

namespace Database\Factories\Foundation;

use App\Models\Foundation\Room;
use App\Models\Foundation\RoomSeatingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomSeatingProfile>
 */
class RoomSeatingProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'room_id' => Room::factory(),
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'rows' => 5,
            'columns' => 6,
            'booklet_pattern' => RoomSeatingProfile::PATTERN_CHECKERBOARD,
            'is_active' => true,
            'is_default' => false,
        ];
    }
}
