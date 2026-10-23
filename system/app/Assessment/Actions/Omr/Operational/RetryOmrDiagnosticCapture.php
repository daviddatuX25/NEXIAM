<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\User;

/**
 * Public action boundary for diagnostic reprocessing.  The capture action owns
 * the shared persistence/analysis seam so retry cannot accidentally diverge
 * from the initial diagnostic capture path.
 */
final class RetryOmrDiagnosticCapture
{
    public function __construct(
        private readonly CaptureOmrDiagnosticRun $capture,
    ) {}

    /**
     * @return array{run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: array<string, mixed>|null, identities: array<string, mixed>}
     */
    public function handle(
        OmrDiagnosticRun|int|string $run,
        OmrDiagnosticCapture|int $capture,
        User $actor,
        int $expectedRevision,
        ?string $idempotencyKey = null,
        ?string $clientCaptureId = null,
    ): array {
        return $this->capture->retry(
            run: $run,
            capture: $capture,
            actor: $actor,
            expectedRevision: $expectedRevision,
            idempotencyKey: $idempotencyKey,
            clientCaptureId: $clientCaptureId,
        );
    }
}
