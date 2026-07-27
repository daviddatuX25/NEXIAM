<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'reference_number' => Application::nextReferenceNumber(),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->lastName(),
            'last_name' => $this->faker->lastName(),
            'suffix' => null,
            'birthdate' => $this->faker->date('Y-m-d', '-18 years'),
            'age' => 18,
            'sex' => $this->faker->randomElement(['Male', 'Female']),
            'applicant_type' => $this->faker->randomElement(['new', 'new', 'new', 'new', 'transferee']),
            'last_school_enrolled' => $this->faker->optional(0.3)->company().($this->faker->optional(0.3)->boolean() ? ' University' : ''),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address_line' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'province' => $this->faker->state(),
            'zip_code' => $this->faker->postcode(),
            'gwa' => $this->faker->randomFloat(2, 1, 5),
            'course_preference_1' => Course::factory(),
            'course_preference_2' => Course::factory(),
            'course_preference_3' => Course::factory(),
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'rejection_reason' => null,
            'appointment_id' => null,
            'submitted_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'processed_by' => 1,
            'processed_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dismissed',
            'processed_by' => 1,
            'processed_at' => now(),
        ]);
    }
}
