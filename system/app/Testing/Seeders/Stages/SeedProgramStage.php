<?php

namespace App\Testing\Seeders\Stages;

class SeedProgramStage extends BaseStageSeeder
{
    public function seed(): array
    {
        return $this->buildStage('program');
    }
}
