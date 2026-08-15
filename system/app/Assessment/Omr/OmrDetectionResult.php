<?php

namespace App\Assessment\Omr;

/**
 * The detection result envelope. One of a typed outcome plus (on success) the
 * payload, a safe diagnostic code for failure outcomes, and timing metadata.
 */
final readonly class OmrDetectionResult
{
    public function __construct(
        public OmrDetectionOutcome $outcome,
        public ?OmrDetectionSuccess $success = null,
        public ?string $safeDiagnosticCode = null,
        public ?string $safeDiagnosticMessage = null,
        public ?OmrDetectionTimings $timings = null,
    ) {
    }

    public static function succeeded(
        OmrDetectionSuccess $success,
        ?OmrDetectionTimings $timings = null,
    ): self {
        return new self(OmrDetectionOutcome::Succeeded, $success, timings: $timings);
    }

    public static function failed(
        OmrDetectionOutcome $outcome,
        string $safeDiagnosticCode,
        ?string $safeDiagnosticMessage = null,
        ?OmrDetectionTimings $timings = null,
    ): self {
        return new self($outcome, safeDiagnosticCode: $safeDiagnosticCode, safeDiagnosticMessage: $safeDiagnosticMessage, timings: $timings);
    }
}
