<?php

namespace App\Assessment\Omr\Diagnostics;

interface OmrPhysicalChallengeRendererPort
{
    /**
     * @return array{
     *     plan: OmrProofPlan,
     *     sheet: array<string, mixed>,
     *     guide: array<string, mixed>,
     *     sections: list<array<string, mixed>>,
     *     guide_json: array<string, mixed>,
     * }
     */
    public function render(
        OmrDiagnosticTarget $target,
        ProofPurpose $purpose,
        string $pdfBytes,
        array $insetsPx = [],
        string $planVersion = OmrProofPlanner::CURRENT_PLAN_VERSION,
        ?string $method = null,
    ): array;
}
