<?php

namespace App\Jobs\Assessment;

use App\Assessment\Actions\Omr\Verification\SubmitOmrPhysicalCapture;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Analyze one already-claimed immutable physical capture attempt. */
final class AnalyzeOmrTemplateVerificationCaptureJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $captureId,
        public readonly bool $autoSelect = false,
    ) {}

    public function handle(SubmitOmrPhysicalCapture $submission): void
    {
        $submission->analyzeAttempt($this->captureId, $this->autoSelect);
    }
}
