<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use RuntimeException;

final class OmrDiagnosticCaptureInProgress extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('omr_diagnostic_capture_idempotency_in_progress');
    }
}
