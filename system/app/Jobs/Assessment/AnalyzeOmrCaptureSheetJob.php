<?php

namespace App\Jobs\Assessment;

use App\Assessment\Actions\Omr\RunOmrSheetAnalysis;
use App\Models\Assessment\OmrProcessingRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class AnalyzeOmrCaptureSheetJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $runId)
    {
        $this->tries = (int) config('omr.retry.tries', 3);
        $this->timeout = (int) config('omr.retry.timeout', 60);
    }

    public function uniqueId(): string
    {
        return 'omr-processing-run:'.$this->runId;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->dontRelease()->expireAfter(80)];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return array_map('intval', (array) config('omr.retry.backoff', [5, 20, 60]));
    }

    public function handle(RunOmrSheetAnalysis $analysis): void
    {
        $run = $analysis->handle($this->runId);

        if ($run->status !== OmrProcessingRun::STATUS_RETRYABLE_FAILED) {
            return;
        }

        if ((int) $run->attempts >= $this->tries) {
            $analysis->markJobFailure($run->id, $run->diagnostic_code ?: 'retry_exhausted');

            return;
        }

        $delay = max(0, (int) now()->diffInSeconds($run->next_retry_at, false));
        $this->release($delay);
    }

    public function failed(Throwable $exception): void
    {
        app(RunOmrSheetAnalysis::class)->markJobFailure($this->runId);
    }
}
