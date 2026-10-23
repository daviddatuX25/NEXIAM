<?php

namespace Database\Factories\Examination;

use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_cycle_id' => fn (): int => AcademicCycle::query()->firstOrFail()->id,
            'room_id' => fn (): int => Room::query()->where('is_active', true)->firstOrFail()->id,
            'code' => strtoupper(fake()->unique()->bothify('EX-####')),
            'status' => ExamSession::STATUS_DRAFT,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
            'starts_at' => now()->addDays(fake()->numberBetween(1, 30))->startOfHour(),
            'effective_end_at' => fn (array $attributes): Carbon => Carbon::parse($attributes['starts_at'])->addHours(2),
            'version' => 1,
        ];
    }

    public function autoAssignEnabled(): static
    {
        return $this->state(fn (): array => ['auto_assign_enabled' => true]);
    }
}
