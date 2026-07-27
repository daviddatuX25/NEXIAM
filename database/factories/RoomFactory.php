<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roomNum = fake()->numberBetween(1, 999);
        $building = fake()->randomElement(['ITBR', 'MAIN', 'SCIENCE', 'ENG']);

        return [
            'name' => "Room {$roomNum}",
            'building' => $building,
            'floor' => fake()->optional(0.7)->randomElement(['1st Floor', '2nd Floor', '3rd Floor']),
            'capacity' => fake()->numberBetween(20, 100),
            'is_active' => true,
        ];
    }
}
