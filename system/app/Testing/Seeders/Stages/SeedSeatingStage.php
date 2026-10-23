<?php

namespace App\Testing\Seeders\Stages;

class SeedSeatingStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('seating');
    }
}
