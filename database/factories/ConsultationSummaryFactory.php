<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\ConsultationSummary;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationSummary>
 */
class ConsultationSummaryFactory extends Factory
{
    protected $model = ConsultationSummary::class;

    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'status' => 'draft',
            'recommended_course_id' => Course::factory(),
            'counselor_comments' => $this->faker->optional()->paragraph(),
        ];
    }
}
