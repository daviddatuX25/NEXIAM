<?php

namespace App\Assessment\Omr;

use RuntimeException;

/**
 * A detection provider returned output that violates the envelope contract.
 * Thrown when a provider result cannot be trusted for persistence.
 */
class OmrInvalidDetectionOutput extends RuntimeException
{
}
