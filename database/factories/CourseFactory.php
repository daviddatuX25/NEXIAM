<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Computer Science',
                'Information Technology',
                'Nursing',
                'Business Administration',
                'Education',
                'Engineering',
            ]) . ' ' . $this->faker->numerify('###'),
            'code' => strtoupper($this->faker->lexify('???')) . $this->faker->numerify('###'),
            'is_active' => true,
        ];
    }
}