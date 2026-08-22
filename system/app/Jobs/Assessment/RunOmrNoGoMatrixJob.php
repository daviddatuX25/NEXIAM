<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunOmrNoGoMatrixJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /** @param array<string,mixed> $proof */
    public function __construct(public readonly array $proof)
    {
    }

    public function handle(OmrNoGoMatrixRunner $runner): void
    {
        $runner->run($this->proof);
    }
}
