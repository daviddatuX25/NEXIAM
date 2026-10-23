<?php

namespace App\Testing\Seeders\Stages;

class SeedReservationStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('reservation');
    }
}
