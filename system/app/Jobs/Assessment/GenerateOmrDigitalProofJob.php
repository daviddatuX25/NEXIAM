<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
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

    public function __construct(public readonly int $draftId)
    {
    }

    public function handle(OmrDigitalProofGenerator $generator): void
    {
        $generator->generate(OmrTemplateAuthoringDraft::query()->findOrFail($this->draftId));
    }
}
