<?php

namespace Database\Factories;

use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradingSessionFactory extends Factory
{
    protected $model = GradingSession::class;

    public function definition(): array
    {
        return [
            'exam_session_id' => ExamSession::factory(),
            'status' => 'in_progress',
            'opened_at' => now(),
            'opened_by' => User::factory(),
            'finalized_at' => null,
            'finalized_by' => null,
        ];
    }
}
