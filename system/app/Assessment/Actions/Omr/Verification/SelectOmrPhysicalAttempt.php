<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;

/** Explicit same-method/page passing-attempt selection and supersession. */
final class SelectOmrPhysicalAttempt
{
    public function __construct(private readonly OmrPhysicalAssuranceCoordinator $coordinator) {}

    public function handle(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $method,
        int $pageNo,
        int $captureId,
        string $reason = '',
        ?string $targetHash = null,
        ?string $planHash = null,
        ?string $expectedVectorHash = null,
        ?int $expectedPageVersion = null,
        ?string $idempotencyKey = null,
    ): OmrTemplateVerificationMethodPage {
        return $this->coordinator->selectAttempt(
            run: $run,
            actor: $actor,
            methodName: $method,
            pageNo: $pageNo,
            captureId: $captureId,
            reason: $reason,
            targetHash: $targetHash,
            planHash: $planHash,
            expectedVectorHash: $expectedVectorHash,
            expectedPageVersion: $expectedPageVersion,
            idempotencyKey: $idempotencyKey,
        );
    }
}
