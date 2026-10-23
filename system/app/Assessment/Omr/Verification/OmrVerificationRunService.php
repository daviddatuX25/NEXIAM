<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_search;
use function config;
use function count;
use function in_array;

/**
 * Durable run state machine (OpenSpec 35.21/35.25, AD-6).
 *
 * Every transition is guarded by the row's current status AND optimistic
 * version so concurrent workers/status polls can never double-advance a run.
 * The run row is the sole resume authority: nothing here reads operations or
 * job payloads, and every mutation bumps `optimistic_version`.
 */
final class OmrVerificationRunService
{
    public function __construct(
        private readonly int $timeoutSeconds,
    ) {}

    public static function make(): self
    {
        return new self((int) config('omr.verification.run_timeout_seconds', 600));
    }

    public function activeRunForDraft(
        int $draftId,
        ?string $mode = null,
        array $targetHashes = [],
    ): ?OmrTemplateVerificationRun {
        return OmrTemplateVerificationRun::query()
            ->where('draft_id', $draftId)
            ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
            ->when($mode !== null, fn ($query) => $query->where('mode', $mode))
            ->when(isset($targetHashes['artifact']), fn ($query) => $query->where('artifact_hash', $targetHashes['artifact']))
            ->when(isset($targetHashes['mapping']), fn ($query) => $query->where('mapping_hash', $targetHashes['mapping']))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Atomically advance a run from one status to another. Returns false when
     * the run is no longer in `$from` (idempotent replay / concurrent write).
     *
     * @param  array<string, mixed>  $fields
     */
    public function transition(int $runId, string $from, string $to, array $fields = []): bool
    {
        return DB::transaction(function () use ($runId, $from, $to, $fields): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->status !== $from) {
                return false;
            }
            if ($run->isTerminal()) {
                return false; // terminal statuses are immutable; retry() owns failed->queued
            }
            $this->apply($run, ['status' => $to, ...$fields]);

            return true;
        });
    }

    /**
     * Synchronize a physical-assurance run with its aggregate child state.
     *
     * @param  array<string, mixed>  $fields
     */
    public function syncPhysicalState(int $runId, string $status, array $fields = []): bool
    {
        return DB::transaction(function () use ($runId, $status, $fields): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null) {
                return false;
            }
            if (in_array($run->status, [
                OmrTemplateVerificationRun::STATUS_PASSED,
                OmrTemplateVerificationRun::STATUS_FAILED,
            ], true) && $run->status !== $status) {
                return false;
            }
            $this->apply($run, ['status' => $status, ...$fields]);

            return true;
        });
    }

    /**
     * Advance live worker telemetry only while the named attempt remains active.
     *
     * @param  array<string, mixed>  $fields
     */
    public function syncPhysicalWorkerPhase(int $runId, int $captureId, string $status, array $fields = []): bool
    {
        return DB::transaction(function () use ($runId, $captureId, $status, $fields): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            $capture = OmrTemplateVerificationCapture::query()
                ->whereKey($captureId)
                ->where('run_id', $runId)
                ->lockForUpdate()
                ->first();
            if ($run === null
                || $run->isTerminal()
                || $capture === null
                || $capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_ACTIVE) {
                return false;
            }
            $this->apply($run, ['status' => $status, ...$fields]);

            return true;
        });
    }

    /** Recover an eligible historical physical failure before aggregate re-derivation. */
    public function recoverPhysicalFailure(int $runId): bool
    {
        return DB::transaction(function () use ($runId): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->status !== OmrTemplateVerificationRun::STATUS_FAILED) {
                return false;
            }
            $this->apply($run, [
                'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
                'error_code' => null,
                'safe_error' => null,
                'finished_at' => null,
                'phase_detail' => ['stage' => 'awaiting_capture', 'current_variant' => null],
            ]);

            return true;
        });
    }

    /**
     * Claim the exact variant named by the run row. The optimistic version is
     * part of the queue message, so a stale/duplicate delivery cannot claim a
     * later attempt or race the current worker.
     */
    public function claimVariant(int $runId, int $attempt, string $variant, ?int $expectedVersion = null): ?OmrTemplateVerificationRun
    {
        return DB::transaction(function () use ($runId, $attempt, $variant, $expectedVersion): ?OmrTemplateVerificationRun {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null) {
                return null;
            }
            $variantSequence = $this->variantSequence($run);
            $index = array_search($variant, $variantSequence, true);
            if ($index === false || $run->isTerminal()
                || (int) $run->attempt !== $attempt
                || ($expectedVersion !== null && (int) $run->optimistic_version !== $expectedVersion)) {
                return null;
            }
            $phase = (array) $run->phase_detail;
            if (($phase['current_variant'] ?? null) !== $variant || ($phase['state'] ?? 'ready') !== 'ready'
                || (int) $run->progress_done !== $index) {
                return null;
            }
            $expectedStatus = $index === 0
                ? OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY
                : OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX;
            if ($run->status !== $expectedStatus) {
                return null;
            }

            $phase['state'] = 'running';
            $phase['stage'] = $expectedStatus === OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY
                ? 'clean_recovery'
                : 'variant_matrix';
            $this->apply($run, ['phase_detail' => $phase, 'last_heartbeat_at' => now()]);

            return $run->fresh();
        });
    }

    /**
     * Persist exactly one claimed variant and advance the run's cursor. The
     * callback executes inside the lock/transaction, so capture insertion and
     *
     * @param  callable(OmrTemplateVerificationRun):void  $persistCapture
     * @param  array<string,mixed>  $fields
     */
    public function completeVariant(
        int $runId,
        int $attempt,
        string $variant,
        int $expectedVersion,
        callable $persistCapture,
        array $fields = [],
    ): ?OmrTemplateVerificationRun {
        return DB::transaction(function () use ($runId, $attempt, $variant, $expectedVersion, $persistCapture, $fields): ?OmrTemplateVerificationRun {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null) {
                return null;
            }
            $variantSequence = $this->variantSequence($run);
            $index = array_search($variant, $variantSequence, true);
            if ($index === false || $run->isTerminal()
                || (int) $run->attempt !== $attempt
                || (int) $run->optimistic_version !== $expectedVersion) {
                return null;
            }
            $phase = (array) $run->phase_detail;
            $expectedStatus = $index === 0
                ? OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY
                : OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX;
            if ($run->status !== $expectedStatus
                || ($phase['current_variant'] ?? null) !== $variant
                || ($phase['state'] ?? null) !== 'running'
                || (int) $run->progress_done !== $index
                || $run->captures()->where('attempt', $attempt)->where('variant', $variant)->exists()) {
                return null;
            }

            $persistCapture($run);
            $next = $variantSequence[$index + 1] ?? null;
            if ($next === null) {
                $this->apply($run, [
                    ...$fields,
                    'progress_done' => count($variantSequence),
                    'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => null, 'state' => 'finalizing'],
                    'last_heartbeat_at' => now(),
                ]);
            } else {
                $this->apply($run, [
                    ...$fields,
                    'status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX,
                    'progress_done' => $index + 1,
                    'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => $next, 'state' => 'ready'],
                    'last_heartbeat_at' => now(),
                ]);
            }

            return $run->fresh();
        });
    }

    /** Heartbeat only; no status change. */
    public function touchHeartbeat(int $runId): void
    {
        DB::transaction(function () use ($runId): void {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null) {
                return;
            }
            $this->apply($run, ['last_heartbeat_at' => now()]);
        });
    }

    /**
     * Mark an active run failed (worker failure, variant failure, stale
     * target, or timeout). Completed evidence rows are NEVER touched.
     *
     * @param  array<string, mixed>  $fields
     */
    public function fail(int $runId, string $errorCode, string $safeError, array $fields = []): bool
    {
        return DB::transaction(function () use ($runId, $errorCode, $safeError, $fields): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->isTerminal()) {
                return false;
            }
            $this->apply($run, [
                'status' => OmrTemplateVerificationRun::STATUS_FAILED,
                'error_code' => $errorCode,
                'safe_error' => $safeError,
                'finished_at' => now(),
                ...$fields,
            ]);

            return true;
        });
    }

    /**
     * Human review of a physical capture (OpenSpec 35.28): a run parked in
     * `needs_review` is resolved to `passed` (staff confirmed the evidence)
     * or `failed` (staff rejected it). Needs-review is terminal in the
     * schema, so this is the ONLY transition out of it — retry/fail refuse
     * terminal runs on purpose.
     *
     * @param  array<string, mixed>  $fields
     */
    public function resolveReview(int $runId, bool $passed, string $safeError = '', array $fields = []): bool
    {
        return DB::transaction(function () use ($runId, $passed, $safeError, $fields): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->status !== OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW) {
                return false;
            }
            $this->apply($run, [
                'status' => $passed
                    ? OmrTemplateVerificationRun::STATUS_PASSED
                    : OmrTemplateVerificationRun::STATUS_FAILED,
                'finished_at' => now(),
                'last_heartbeat_at' => now(),
                ...($passed ? [] : [
                    'error_code' => 'review_rejected',
                    'safe_error' => $safeError !== '' ? $safeError : 'Staff rejected the reviewed capture evidence.',
                ]),
                ...$fields,
            ]);

            return true;
        });
    }

    /**
     * Re-open a reviewed run when the accepted attempt still needs an
     * explicit method/page selection or another method's capture. Review is a
     * terminal read state for worker timeout purposes, but selection remains
     * the transition authority for the overall pass gate.
     *
     * @param  array<string, mixed>  $fields
     */
    public function resumeAfterReview(int $runId, array $fields = []): bool
    {
        return DB::transaction(function () use ($runId, $fields): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->status !== OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW) {
                return false;
            }
            $this->apply($run, [
                'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
                'finished_at' => null,
                'error_code' => null,
                'safe_error' => null,
                'last_heartbeat_at' => now(),
                ...$fields,
            ]);

            return true;
        });
    }

    /**
     * Worker liveness finalization: an active, worker-owned run whose
     * heartbeat is older than the timeout window is failed closed as
     * `worker_timeout`. Human-controlled print/capture waiting states are
     * deliberately excluded, so a staff pause never becomes a timeout.
     * Completed evidence stays intact and retry is exposed.
     */
    public function finalizeTimedOut(int $runId): bool
    {
        return DB::transaction(function () use ($runId): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || ! in_array($run->status, OmrTemplateVerificationRun::TIMEOUT_ELIGIBLE_STATUSES, true)) {
                return false;
            }
            $deadline = now()->subSeconds($this->timeoutSeconds);
            $lastSeen = $run->last_heartbeat_at ?? $run->started_at ?? $run->created_at;
            if ($lastSeen === null || $lastSeen->gt($deadline)) {
                return false;
            }

            $this->apply($run, [
                'status' => OmrTemplateVerificationRun::STATUS_FAILED,
                'error_code' => 'worker_timeout',
                'safe_error' => 'The verification worker stopped before finishing; completed evidence is preserved and the run can be retried.',
                'finished_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Retry = the next attempt against the SAME pinned plan/hashes. Only a
     * failed run can be retried, and only when no other run is active for
     * the same target (the DB partial unique index enforces this too).
     *
     * Physical challenge runs resume from their persisted challenge state
     * instead of entering the digital queue. The existing challenge row,
     * deterministic code, and prior evidence are retained.
     */
    public function retry(int $runId): OmrTemplateVerificationRun
    {
        return DB::transaction(function () use ($runId): OmrTemplateVerificationRun {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->findOrFail($runId);
            if ($run->status !== OmrTemplateVerificationRun::STATUS_FAILED) {
                throw new RuntimeException('verification_retry_status_invalid: Only a failed verification run can be retried.');
            }
            $conflict = OmrTemplateVerificationRun::query()
                ->where('id', '!=', $run->id)
                ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
                ->when($run->draft_id !== null, fn ($q) => $q->where('draft_id', $run->draft_id))
                ->when($run->draft_id === null, fn ($q) => $q->where('template_version_id', $run->template_version_id))
                ->exists();
            if ($conflict) {
                throw new RuntimeException('verification_active_run_in_progress: Another verification run is already active for this target.');
            }

            $physical = in_array($run->mode, [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ], true);
            $challenge = $physical
                ? OmrPhysicalChallenge::query()
                    ->where('run_id', $run->id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first()
                : null;
            if ($physical && $challenge === null) {
                throw new RuntimeException('physical_challenge_missing: The failed physical run has no persisted challenge to resume.');
            }

            $resumeStatus = $physical
                ? $this->physicalResumeStatus($challenge)
                : null;
            $nextAttempt = (int) $run->attempt + 1;
            $challengeCode = $physical ? ($challenge?->challenge_code ?? $run->challenge_code) : null;

            $progressTotal = $run->mode === OmrTemplateVerificationRun::MODE_DIGITAL
                ? count($this->variantSequence($run, false))
                : (int) $run->progress_total;

            $this->apply($run, [
                'status' => $physical ? $resumeStatus : OmrTemplateVerificationRun::STATUS_QUEUED,
                'attempt' => $nextAttempt,
                'progress_done' => 0,
                'progress_total' => $progressTotal,
                'phase_detail' => $physical ? ['stage' => $resumeStatus, 'current_variant' => null] : null,
                'result_summary' => null,
                'error_code' => null,
                'safe_error' => null,
                'proof_hash' => null,
                'clean_vector_hash' => null,
                'challenge_code' => $challengeCode,
                // Physical retries must retain the deterministic challenge
                // code so the existing QR remains valid for capture.
                'finished_at' => null,
                'started_at' => now(),
                'last_heartbeat_at' => now(),
            ]);

            return $run->fresh();
        });
    }

    /** @return list<string> */
    private function variantSequence(OmrTemplateVerificationRun $run, bool $requireProgressMatch = true): array
    {
        $draft = $run->draft;
        if ($run->mode !== OmrTemplateVerificationRun::MODE_DIGITAL
            || $draft === null
            || ! $draft->registration_mode instanceof OmrRegistrationMode
            || $draft->requested_page_count < 1
            || $draft->requested_page_count > 4) {
            throw new RuntimeException('verification_policy_missing');
        }

        $sequence = OmrNoGoMatrixRunner::variantKeys($draft->requested_page_count, $draft->registration_mode);
        if ($requireProgressMatch && (int) $run->progress_total !== count($sequence)) {
            throw new RuntimeException('verification_matrix_policy_mismatch');
        }

        return $sequence;
    }

    /**
     * A created pack has not been printed and must return to the print
     * acknowledgment step. Every persisted capture/challenge state after
     * creation indicates that the existing sheet can resume at capture.
     */
    private function physicalResumeStatus(?OmrPhysicalChallenge $challenge): string
    {
        if ($challenge === null) {
            return OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE;
        }

        $hasCapture = $challenge->captures()->exists();
        if ($challenge->status === OmrPhysicalChallenge::STATUS_CREATED && ! $hasCapture) {
            return OmrTemplateVerificationRun::STATUS_AWAITING_PRINT;
        }

        return OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function apply(OmrTemplateVerificationRun $run, array $fields): void
    {
        $run->forceFill([...$fields, 'optimistic_version' => (int) $run->optimistic_version + 1]);
        $run->save();
    }
}
