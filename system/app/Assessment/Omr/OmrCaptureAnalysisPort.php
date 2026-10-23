<?php

namespace App\Assessment\Omr;

/** Narrow seam consumed by canonical and grading caller adapters. */
interface OmrCaptureAnalysisPort
{
    public function analyze(OmrCaptureAnalysisRequest $request): OmrPreparedCaptureEvidence;
}
