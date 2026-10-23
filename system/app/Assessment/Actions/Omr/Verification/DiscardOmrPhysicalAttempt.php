<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class DiscardOmrPhysicalAttempt
{
    public function __construct(
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        User $actor,
        array $payload,
    ): array {
        if (! $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)->allowed) {
            throw new AuthorizationException('OMR attempt disposition is not authorized.');
        }

        return $this->coordinator->discardAttempt(
            $run,
            $capture,
            $actor,
            $payload,
            (string) $payload['idempotency_key'],
        );
    }
}
