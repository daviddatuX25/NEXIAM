<?php

namespace Database\Factories;

use App\Models\AptitudeArea;
use Illuminate\Database\Eloquent\Factories\Factory;

class AptitudeAreaFactory extends Factory
{
    protected $model = AptitudeArea::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'code' => strtoupper($this->faker->unique()->bothify('??')),
            'description' => $this->faker->sentence(),
            'max_items' => $this->faker->numberBetween(5, 20),
            'formula' => '(x / max_items) * 100',
            'scoring_method' => 'formula',
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
