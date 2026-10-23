<?php

namespace App\Jobs\Ai;

use App\Ai\Orchestration\ProcessXiamRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessXiamRunJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public string $runId) {}

    public function uniqueId(): string
    {
        return $this->runId;
    }

    public function handle(ProcessXiamRun $processor): void
    {
        $processor->handle($this->runId);
    }
}
