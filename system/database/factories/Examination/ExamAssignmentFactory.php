<?php

namespace Database\Factories\Examination;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAssignment>
 */
class ExamAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_session_id' => fn (): int => ExamSession::query()->firstOrFail()->id,
            'admission_attempt_id' => fn (): int => AdmissionAttempt::query()->firstOrFail()->id,
            'status' => ExamAssignment::STATUS_ASSIGNED,
            'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            'origin' => ExamAssignment::ORIGIN_MANUAL,
            'version' => 1,
        ];
    }

    public function batch(): static
    {
        return $this->state(fn (): array => ['origin' => ExamAssignment::ORIGIN_BATCH]);
    }

    public function continuous(): static
    {
        return $this->state(fn (): array => ['origin' => ExamAssignment::ORIGIN_CONTINUOUS]);
    }

    public function reservation(): static
    {
        return $this->state(fn (): array => [
            'source' => ExamAssignment::SOURCE_APPLICANT_RESERVED,
            'origin' => ExamAssignment::ORIGIN_RESERVATION,
        ]);
    }
}
