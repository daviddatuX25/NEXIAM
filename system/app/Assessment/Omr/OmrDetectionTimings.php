<?php

namespace App\Assessment\Omr;

/**
 * Timing metrics for a detection run. Not authoritative; observability only.
 */
final readonly class OmrDetectionTimings
{
    public function __construct(
        public float $detectionMs,
        public ?float $preprocessMs = null,
    ) {}
}
