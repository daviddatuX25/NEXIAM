<?php

namespace App\Assessment\Omr;

use RuntimeException;

/** Safe, bounded failure raised when an OMR renderer dependency is unavailable. */
final class OmrRenderException extends RuntimeException
{
    public const ERROR_CODE = 'omr_renderer_unavailable';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('The OMR renderer is temporarily unavailable.', 0, $previous);
    }
}
