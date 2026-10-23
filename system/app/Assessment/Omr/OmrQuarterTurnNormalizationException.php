<?php

namespace App\Assessment\Omr;

use RuntimeException;

/** Safe failure raised while producing bounded quarter-turn rasters. */
final class OmrQuarterTurnNormalizationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $detail,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($detail, 0, $previous);
    }
}
