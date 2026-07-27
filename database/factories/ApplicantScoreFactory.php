<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\AptitudeArea;
use App\Models\GradingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicantScore>
 */
class ApplicantScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grading_session_id' => GradingSession::factory(),
            'applicant_id' => Applicant::factory(),
            'aptitude_area_id' => AptitudeArea::factory(),
            'raw_score' => $this->faker->numberBetween(1, 50),
            'max_score' => 50,
            'normalized_score' => null,
            'scored_by' => User::factory(),
            'scored_at' => now(),
        ];
    }
}
