<?php

namespace Database\Factories\Consultation;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationAssignment>
 */
class ConsultationAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_session_id' => fn (): int => ConsultationSession::query()->firstOrFail()->id,
            'admission_attempt_id' => fn (): int => AdmissionAttempt::query()->firstOrFail()->id,
            'status' => ConsultationAssignment::STATUS_ASSIGNED,
            'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
            'origin' => ConsultationAssignment::ORIGIN_MANUAL,
            'version' => 1,
        ];
    }

    public function batch(): static
    {
        return $this->state(fn (): array => ['origin' => ConsultationAssignment::ORIGIN_BATCH]);
    }

    public function continuous(): static
    {
        return $this->state(fn (): array => ['origin' => ConsultationAssignment::ORIGIN_CONTINUOUS]);
    }

    public function reservation(): static
    {
        return $this->state(fn (): array => [
            'source' => ConsultationAssignment::SOURCE_APPLICANT_RESERVED,
            'origin' => ConsultationAssignment::ORIGIN_RESERVATION,
        ]);
    }
}
