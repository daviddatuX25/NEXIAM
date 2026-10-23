<?php

namespace App\Testing\Seeders\Stages;

class SeedReviewStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('review');
    }
}
