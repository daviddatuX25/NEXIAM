<?php

namespace Database\Factories;

use App\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Applicant>
 */
class ApplicantFactory extends Factory
{
    protected $model = Applicant::class;

    public function definition(): array
    {
        return [
            'application_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'setup_token' => null,
            'setup_token_expires_at' => null,
        ];
    }
}
