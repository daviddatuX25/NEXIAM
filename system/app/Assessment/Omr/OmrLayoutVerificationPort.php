<?php

namespace App\Assessment\Omr;

interface OmrLayoutVerificationPort
{
    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult;
}
