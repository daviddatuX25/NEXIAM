<?php

namespace Database\Factories\Foundation;

use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => fn (): int => Institution::query()->create([
                'code' => strtoupper(fake()->unique()->bothify('TEST-??-###')),
                'name' => fake()->company(),
            ])->id,
            'code' => strtoupper(fake()->unique()->bothify('R-###')),
            'name' => fake()->words(2, true),
            'capacity' => 30,
            'is_active' => true,
        ];
    }

    public function spatial(): static
    {
        return $this->state(fn (): array => [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'building_name' => 'Main Building',
            'floor_level' => '2nd Floor',
            'directions_hint' => 'Near the east staircase.',
        ]);
    }
}
