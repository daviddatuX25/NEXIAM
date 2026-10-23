<?php

namespace App\Testing\Seeders\Stages;

class SeedGradingStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('grading');
    }
}
