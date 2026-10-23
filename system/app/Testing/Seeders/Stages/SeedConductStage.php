<?php

namespace App\Testing\Seeders\Stages;

class SeedConductStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('conduct');
    }
}
