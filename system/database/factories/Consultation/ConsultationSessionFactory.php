<?php

namespace Database\Factories\Consultation;

use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationSession>
 */
class ConsultationSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_cycle_id' => fn (): int => AcademicCycle::query()->firstOrFail()->id,
            'room_id' => fn (): int => Room::query()->where('is_active', true)->firstOrFail()->id,
            'code' => strtoupper(fake()->unique()->bothify('CS-####')),
            'status' => ConsultationSession::STATUS_DRAFT,
            'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
            'auto_assign_enabled' => false,
            'scheduled_at' => now()->addDays(fake()->numberBetween(1, 30))->startOfHour(),
            'version' => 1,
        ];
    }

    public function autoAssignEnabled(): static
    {
        return $this->state(fn (): array => ['auto_assign_enabled' => true]);
    }
}
