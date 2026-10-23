<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateOmrDigitalProofJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /** @param array<string, mixed> $planPayload canonical OmrProofPlan payload (35.18) */
    public function __construct(
        public readonly int $draftId,
        public readonly array $planPayload,
    ) {}

    public function handle(OmrDigitalProofGenerator $generator): void
    {
        $generator->generate(
            OmrTemplateAuthoringDraft::query()->findOrFail($this->draftId),
            OmrProofPlan::fromArray($this->planPayload),
        );
    }
}
