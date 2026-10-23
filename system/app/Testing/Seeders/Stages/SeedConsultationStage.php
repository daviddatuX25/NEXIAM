<?php

namespace App\Testing\Seeders\Stages;

class SeedConsultationStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('consultation');
    }
}
