<?php

namespace App\Assessment\Omr;

/**
 * Geometry of one answer option on the sheet (part of the pinned template
 * snapshot sent to the detection provider).
 */
final readonly class OmrOptionGeometry
{
    public function __construct(
        public string $code,
        public OmrRegion $region,
        public int $displayOrder,
    ) {}
}
