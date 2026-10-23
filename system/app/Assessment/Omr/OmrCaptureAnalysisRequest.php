<?php

namespace App\Assessment\Omr;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use Closure;

/** Immutable input to the shared capture-analysis pipeline. */
final readonly class OmrCaptureAnalysisRequest
{
    public readonly ?OmrCaptureInput $capture;

    public readonly ?OmrCaptureInput $input;

    public readonly ?OmrDiagnosticTarget $target;

    public readonly ?OmrProofPlan $plan;

    public readonly int $pageNo;

    public readonly ?string $expectedVectorHash;

    public readonly ?string $challengeCode;

    /** @var array<string, mixed> */
    public readonly array $diagnosticOptions;

    /** @var null|Closure(string): void */
    public readonly ?Closure $onPhase;

    public function __construct(
        ?OmrCaptureInput $capture = null,
        ?OmrDiagnosticTarget $target = null,
        ?OmrCaptureInput $input = null,
        ?OmrProofPlan $plan = null,
        ?int $pageNo = null,
        ?string $expectedVectorHash = null,
        array $diagnosticOptions = [],
        ?string $challengeCode = null,
        ?Closure $onPhase = null,
    ) {
        $this->capture = $capture ?? $input;
        $this->input = $this->capture;
        $this->target = $target;
        $this->plan = $plan;
        $this->pageNo = $pageNo ?? $this->capture?->pageNo ?? 1;
        $this->expectedVectorHash = $expectedVectorHash;
        $challengeCode = $challengeCode !== null ? trim($challengeCode) : null;
        $this->challengeCode = $challengeCode !== '' ? $challengeCode : null;
        $this->diagnosticOptions = $diagnosticOptions;
        $this->onPhase = $onPhase;
    }
}
