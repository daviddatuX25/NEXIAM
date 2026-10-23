<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\Operation;
use App\Models\User;

/**
 * Retry semantics (OpenSpec 35.25): retry creates the NEXT attempt against
 * the SAME pinned plan/hashes. Only a failed run can be retried and only
 * when no other run is active for the same target. Digital runs dispatch the
 * durable job after commit, while physical runs resume their persisted
 * challenge at the print or capture boundary. Completed evidence from prior
 * attempts is preserved.
 */
final class RetryOmrTemplateVerification
{
    public function __construct(
        private readonly OmrVerificationRunService $runs,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
        private readonly CommandIdempotency $idempotency,
    ) {}

    public function handle(OmrTemplateVerificationRun $run, User $actor, ?string $idempotencyKey = null): OmrTemplateVerificationRun
    {
        $operation = null;
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $operation = $this->idempotency->claim($actor, 'omr.canonical.retry_verification', 'run:'.$run->id, ['run_id' => $run->id], $idempotencyKey);
            $saved = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
            if (! $operation->wasRecentlyCreated && $saved !== null) {
                return OmrTemplateVerificationRun::query()->findOrFail((int) $saved['run_id']);
            }
            if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_PROCESSING) {
                throw new \RuntimeException('idempotency_in_progress: The original retry request is still being completed.');
            }
            $operation->forceFill(['status' => Operation::STATUS_PROCESSING, 'attempts' => (int) $operation->attempts + 1, 'finished_at' => null])->save();
        }
        try {
            $physical = in_array($run->mode, [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ], true);
            if ($physical && in_array($run->status, [
                OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
            ], true)) {
                $retried = $this->coordinator->reconcileRetriedRun($run, $actor);
            } elseif ($physical) {
                $retried = $this->coordinator->retryRun($run, $actor);
            } else {
                $retried = $this->runs->retry($run->id);
            }
            if ($operation !== null) {
                $operation->forceFill([
                    'status' => Operation::STATUS_SUCCEEDED,
                    'finished_at' => now(),
                    'payload' => [...(array) $operation->payload, 'result' => ['run_id' => (int) $retried->id]],
                ])->save();
            }
        } catch (\Throwable $exception) {
            if ($operation !== null) {
                $operation->forceFill(['status' => Operation::STATUS_FAILED, 'last_error' => $exception->getMessage(), 'finished_at' => now()])->save();
            }
            throw $exception;
        }

        if (! $physical) {
            $this->recordAuditEvent->handle('omr.verification.retried', [
                'run_id' => $retried->id,
                'attempt' => $retried->attempt,
                'plan_hash' => $retried->plan_hash,
                'mode' => $retried->mode,
            ], (string) $actor->id);
        }

        // Physical challenge runs never execute the digital verification job;
        // the service has already parked them at the durable print/capture
        // resume boundary.
        if (! $physical) {
            RunOmrTemplateVerificationJob::dispatch($retried->id, (int) $retried->attempt, (int) $retried->optimistic_version)->onQueue('omr-verification')->afterCommit();
        }

        return $retried->fresh();
    }
}
