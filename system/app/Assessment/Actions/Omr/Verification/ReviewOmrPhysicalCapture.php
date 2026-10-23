<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\Operation;
use App\Models\User;
use RuntimeException;

/**
 * Records a staff recapture request without promoting failed machine evidence.
 * Exact passing captures are accepted through SelectOmrPhysicalAttempt.
 */
final class ReviewOmrPhysicalCapture
{
    public function __construct(
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
        private readonly CommandIdempotency $idempotency,
    ) {}

    public function handle(
        OmrTemplateVerificationRun $run,
        User $actor,
        bool $passed,
        ?string $note = null,
        ?int $captureId = null,
        ?string $idempotencyKey = null,
    ): OmrTemplateVerificationRun {
        $capture = $captureId !== null
            ? $run->captures()->whereKey($captureId)->first()
            : $run->captures()->latest('id')->first();
        if ($capture === null) {
            throw new RuntimeException('verification_capture_missing');
        }
        $idempotency = null;
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $idempotency = $this->idempotency->claim($actor, 'omr.canonical.review_physical_capture', 'run:'.$run->id, [
                'run_id' => $run->id,
                'capture_id' => $capture->id,
                'decision' => $passed ? 'pass' : 'fail',
                'note' => $note,
            ], $idempotencyKey);
            $saved = is_array($idempotency->payload['result'] ?? null) ? $idempotency->payload['result'] : null;
            if (! $idempotency->wasRecentlyCreated && $saved !== null) {
                return $run->fresh() ?? $run;
            }
            if (! $idempotency->wasRecentlyCreated && $idempotency->status === Operation::STATUS_PROCESSING) {
                throw new RuntimeException('idempotency_in_progress: The original review request is still being completed.');
            }
            $idempotency->forceFill(['status' => Operation::STATUS_PROCESSING, 'attempts' => (int) $idempotency->attempts + 1, 'finished_at' => null])->save();
        }
        if ($run->status === OmrTemplateVerificationRun::STATUS_PASSED) {
            throw new RuntimeException('verification_run_not_reviewable: A passed physical verification run cannot be reviewed.');
        }
        try {
            $reviewed = $this->coordinator->reviewCapture($run, $actor, (int) $capture->id, $passed, $note);
            if ($idempotency !== null) {
                $idempotency->forceFill([
                    'status' => Operation::STATUS_SUCCEEDED,
                    'finished_at' => now(),
                    'payload' => [...(array) $idempotency->payload, 'result' => ['run_id' => (int) $reviewed->id]],
                ])->save();
            }

            return $reviewed;
        } catch (\Throwable $exception) {
            if ($idempotency !== null) {
                $idempotency->forceFill(['status' => Operation::STATUS_FAILED, 'last_error' => $exception->getMessage(), 'finished_at' => now()])->save();
            }
            throw $exception;
        }
    }
}
