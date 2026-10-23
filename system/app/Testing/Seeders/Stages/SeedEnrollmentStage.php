<?php

namespace App\Testing\Seeders\Stages;

class SeedEnrollmentStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('enrollment');
    }
}
