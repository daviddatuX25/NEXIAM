<?php

namespace App\Assessment\Omr\Verification;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateVerificationRun;

/**
 * Read-only presentation adapter for the qualification checklist.
 * Workflow status and method/evidence rows remain authoritative; this class
 * only exposes the bounded derivation to status and canonical projections.
 */
final class OmrPhysicalAssuranceStatus
{
    public function __construct(
        private readonly OmrPhysicalQualificationDeriver $deriver,
    ) {}

    /** @return array<string,mixed> */
    public function get(
        ?OmrTemplateVerificationRun $run,
        ?OmrAnswerSheetTemplateVersion $version = null,
        array $measurementEvidence = [],
    ): array {
        return $this->deriver->derive($run, $version, $measurementEvidence);
    }

    /** @return array<string,mixed> */
    public function derive(
        ?OmrTemplateVerificationRun $run,
        ?OmrAnswerSheetTemplateVersion $version = null,
        array $measurementEvidence = [],
    ): array {
        return $this->get($run, $version, $measurementEvidence);
    }
}
