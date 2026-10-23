<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\Operation;
use App\Models\User;
use RuntimeException;

use function in_array;
use function is_array;
use function now;

/**
 * Method-bound print acknowledgement for a physical challenge.
 *
 * Each method has its own challenge and print transition. The aggregate run
 * moves from awaiting_print to awaiting_capture once, then remains there while
 * the other method is printed independently.
 */
final class MarkOmrChallengePrinted
{
    public function __construct(
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
        private readonly CommandIdempotency $idempotency,
    ) {}

    /** @return array{run: OmrTemplateVerificationRun, operation: Operation} */
    public function handle(
        OmrTemplateVerificationRun $run,
        User $actor,
        ?string $methodName = null,
        ?int $challengeId = null,
        ?string $targetHash = null,
        ?string $planHash = null,
        ?string $expectedVectorHash = null,
        ?string $idempotencyKey = null,
    ): array {
        OmrSourcePolicy::assertSystemDesignedRun($run);
        $methodName = is_string($methodName) ? trim($methodName) : '';
        if (! in_array($methodName, OmrProofPlan::METHODS, true)) {
            throw new RuntimeException('verification_method_required: A print acknowledgement must name pre_shaded or hand_shaded.');
        }
        if ($challengeId === null || $challengeId < 1) {
            throw new RuntimeException('verification_challenge_required: A print acknowledgement must name its challenge.');
        }
        $key = trim((string) $idempotencyKey);
        $key = $key !== '' ? $key : 'legacy-print-'.$run->id.'-'.$methodName.'-'.$challengeId;
        $operation = $this->idempotency->claim(
            $actor,
            'omr.canonical.mark_printed',
            'run:'.$run->id,
            [
                'run_id' => (int) $run->id,
                'method' => $methodName,
                'challenge_id' => $challengeId,
                'target_hash' => $targetHash,
                'plan_hash' => $planHash,
                'expected_vector_hash' => $expectedVectorHash,
            ],
            $key,
        );
        $saved = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
        if (! $operation->wasRecentlyCreated && $saved !== null) {
            return [
                'run' => OmrTemplateVerificationRun::query()->findOrFail((int) ($saved['run_id'] ?? $run->id)),
                'operation' => $operation,
            ];
        }
        if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_PROCESSING) {
            throw new RuntimeException('idempotency_in_progress');
        }
        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'last_error' => null,
            'finished_at' => null,
        ])->save();
        try {
            $printed = $this->coordinator->markPrinted(
                run: $run,
                actor: $actor,
                methodName: $methodName,
                challengeId: $challengeId,
                targetHash: $targetHash,
                planHash: $planHash,
                expectedVectorHash: $expectedVectorHash,
            );
            $operation->forceFill([
                'status' => Operation::STATUS_SUCCEEDED,
                'finished_at' => now(),
                'payload' => [...(array) $operation->payload, 'result' => [
                    'run_id' => (int) $printed->id,
                    'status' => (string) $printed->status,
                    'method' => $methodName,
                    'challenge_id' => $challengeId,
                    'target_hash' => $targetHash,
                    'plan_hash' => $planHash,
                    'expected_vector_hash' => $expectedVectorHash,
                ]],
            ])->save();

            return ['run' => $printed, 'operation' => $operation->fresh()];
        } catch (\Throwable $exception) {
            $operation->forceFill([
                'status' => Operation::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
            throw $exception;
        }
    }
}
