<?php

namespace Database\Factories\Foundation;

use App\Models\Foundation\SeatingArrangement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingArrangement>
 */
class SeatingArrangementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'rows' => 5,
            'columns' => 6,
            'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
            'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
            'is_active' => true,
            'lock_version' => 1,
            'legacy_room_seating_profile_id' => null,
        ];
    }
}
