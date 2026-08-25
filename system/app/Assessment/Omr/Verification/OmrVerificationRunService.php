<?php

namespace App\Assessment\Omr\Verification;

use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function config;

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
    ) {
    }

    public static function make(): self
    {
        return new self((int) config('omr.verification.run_timeout_seconds', 600));
    }

    public function activeRunForDraft(int $draftId): ?OmrTemplateVerificationRun
    {
        return OmrTemplateVerificationRun::query()
            ->where('draft_id', $draftId)
            ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Atomically advance a run from one status to another. Returns false when
     * the run is no longer in `$from` (idempotent replay / concurrent write).
     *
     * @param array<string, mixed> $fields
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
     * @param array<string, mixed> $fields
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
     * Fail-closed transition for a stale target: the pinned hashes no longer
     * match the current target, so the run can never pass (AD-2).
     */
    public function failStaleTarget(int $runId, string $detail): bool
    {
        return $this->fail($runId, 'stale_target', $detail);
    }

    /**
     * Worker liveness finalization: an active run whose heartbeat is older
     * than the timeout window is failed closed as `worker_timeout`. Its
     * completed evidence stays intact and retry is exposed.
     */
    public function finalizeTimedOut(int $runId): bool
    {
        return DB::transaction(function () use ($runId): bool {
            $run = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || ! $run->isActive()) {
                return false;
            }
            $deadline = now()->subSeconds($this->timeoutSeconds);
            $lastSeen = $run->last_heartbeat_at ?? $run->started_at ?? $run->created_at;
            if ($lastSeen === null || $lastSeen->gt($deadline)) {
                return false;
            }

            return $this->fail($runId, 'worker_timeout', 'The verification worker stopped before finishing; completed evidence is preserved and the run can be retried.');
        });
    }

    /**
     * Retry = the next attempt against the SAME pinned plan/hashes. Only a
     * failed run can be retried, and only when no other run is active for
     * the same target (the DB partial unique index enforces this too).
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
            $this->apply($run, [
                'status' => OmrTemplateVerificationRun::STATUS_QUEUED,
                'attempt' => (int) $run->attempt + 1,
                'progress_done' => 0,
                'progress_total' => 0,
                'phase_detail' => null,
                'result_summary' => null,
                'error_code' => null,
                'safe_error' => null,
                'proof_hash' => null,
                'clean_vector_hash' => null,
                'challenge_code' => null,
                'finished_at' => null,
                'started_at' => now(),
                'last_heartbeat_at' => now(),
            ]);

            return $run->fresh();
        });
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function apply(OmrTemplateVerificationRun $run, array $fields): void
    {
        $run->forceFill([...$fields, 'optimistic_version' => (int) $run->optimistic_version + 1]);
        $run->save();
    }
}
