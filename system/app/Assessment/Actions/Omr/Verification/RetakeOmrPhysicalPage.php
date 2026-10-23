<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;

final class RetakeOmrPhysicalPage
{
    public function __construct(private readonly OmrPhysicalAssuranceCoordinator $coordinator) {}

    public function handle(
        OmrTemplateVerificationRun $run,
        User $actor,
        string $method,
        int $pageNo,
        int $pageVersion,
        string $idempotencyKey,
    ): OmrTemplateVerificationMethodPage {
        return $this->coordinator->retakePage(
            run: $run,
            actor: $actor,
            methodName: $method,
            pageNo: $pageNo,
            expectedPageVersion: $pageVersion,
            idempotencyKey: $idempotencyKey,
        );
    }
}
