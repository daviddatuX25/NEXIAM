<?php

namespace App\Assessment\Omr;

use RuntimeException;

/**
 * A sheet could not be analyzed. Carries the provider's safe diagnostic code
 * (never raw internals) so downstream failure handling can record it.
 */
class OmrAnalysisFailure extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $safeDiagnosticCode = null,
    ) {
        parent::__construct($message);
    }

    public function safeDiagnosticCode(): ?string
    {
        return $this->safeDiagnosticCode;
    }
}
