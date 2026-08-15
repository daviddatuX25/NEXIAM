<?php

namespace App\Assessment\Omr;

/**
 * Typed outcome of a detection run. Success is the only outcome with a
 * payload; the rest carry a safe diagnostic code that may be surfaced to
 * staff but never raw provider internals.
 */
enum OmrDetectionOutcome: string
{
    case Succeeded = 'succeeded';
    case Unsupported = 'unsupported';
    case InvalidInput = 'invalid_input';
    case RetryableFailure = 'retryable_failure';
    case TerminalFailure = 'terminal_failure';
}
