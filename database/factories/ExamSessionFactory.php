<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'room_id' => Room::factory(),
            'date' => $this->faker->dateTimeBetween('+1 days', '+30 days')->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'status' => ExamSession::STATUS_DRAFT,
            'type' => ExamSession::TYPE_SCHEDULED,
            'created_by' => User::factory(),
        ];
    }

    public function direct(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ExamSession::TYPE_DIRECT,
            'room_id' => null,
            'date' => now()->format('Y-m-d'),
            'start_time' => now()->format('H:i:s'),
            'end_time' => null,
            'status' => ExamSession::STATUS_COMPLETED,
            'label' => 'Walk-in '.$this->faker->numberBetween(1, 99),
        ]);
    }
}
