<?php

namespace App\Assessment\Omr;

/**
 * Single seam for future real CV/AI detection (G-OMR gate, master §8.6).
 * Only the fake implementation ships this cut; the port is the boundary that
 * keeps detection out of the write path.
 */
interface OmrDetectionPort
{
    public function detect(OmrDetectionRequest $request): OmrDetectionResult;
}
