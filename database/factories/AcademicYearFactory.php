<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $appStart = $this->faker->dateTimeBetween('-30 days', 'now');
        $appEnd = $this->faker->dateTimeBetween($appStart, '+30 days');

        return [
            'academic_year' => $this->faker->year(),
            'semester' => $this->faker->randomElement(['1', '2', 'Summer']),
            'is_active' => false,
            'application_start_date' => $appStart->format('Y-m-d'),
            'application_end_date' => $appEnd->format('Y-m-d'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
