<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use RuntimeException;

use function round;

/**
 * Status projection over the durable run (OpenSpec 35.23, AD-6).
 *
 * Reads ONLY the run/evidence rows — never operations, jobs, or browser
 * state. Worker liveness is finalized here (stale heartbeat => failed
 * `worker_timeout`) so a dead worker surfaces a retryable failure instead of
 * a stuck `variant_matrix`.
 */
final class OmrTemplateVerificationStatus
{
    public function __construct(
        private readonly OmrVerificationRunService $runs,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(OmrTemplateVerificationRun $run, User $actor): array
    {
        $this->assertAccessible($run, $actor);
        $this->runs->finalizeTimedOut($run->id);
        $run = $run->fresh();
        if ($run === null) {
            throw new RuntimeException('verification_run_missing');
        }
        $captures = $run->captures()
            ->with(['inputAsset', 'alignedAsset', 'overlayAsset'])
            ->orderBy('id')
            ->get();

        $total = max(0, (int) $run->progress_total);
        $done = min($total, max(0, (int) $run->progress_done));
        $phaseDetail = (array) ($run->phase_detail ?? []);

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'phase' => $run->status,
            'phase_detail' => $phaseDetail,
            'mode' => $run->mode,
            'attempt' => (int) $run->attempt,
            'progress' => [
                'done' => $done,
                'total' => $total,
                'percent' => $total > 0 ? round($done / $total * 100, 1) : 0.0,
            ],
            'current_variant' => $phaseDetail['current_variant'] ?? null,
            'safe_issue' => $run->error_code !== null
                ? ['code' => $run->error_code, 'detail' => $run->safe_error]
                : null,
            'result_summary' => $run->result_summary,
            'available_actions' => $this->availableActions($run),
            'run_version' => (int) $run->optimistic_version,
            'plan' => [
                'plan_hash' => $run->plan_hash,
                'plan_version' => $run->plan_version,
                'challenge_code' => $run->challenge_code,
                'expected_vector_hash' => $run->expected_vector_hash,
            ],
            'target' => [
                'kind' => $run->draft_id !== null ? OmrDiagnosticTarget::KIND_CANDIDATE : OmrDiagnosticTarget::KIND_COMPILED_VERSION,
                'id' => $run->draft_id ?? $run->template_version_id,
                'hashes' => $run->pinnedTargetHashes(),
            ],
            'timestamps' => [
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'last_heartbeat_at' => $run->last_heartbeat_at?->toIso8601String(),
            ],
            'evidence' => $captures->map(fn (OmrTemplateVerificationCapture $capture): array => [
                'capture_id' => $capture->id,
                'attempt' => (int) $capture->attempt,
                'page_no' => (int) $capture->page_no,
                'variant' => $capture->variant,
                'expected' => $capture->expected,
                'actual' => $capture->actual,
                'status' => $capture->status,
                'error_code' => $capture->error_code,
                'input_hash' => $capture->input_hash,
                'evidence_hash' => $capture->evidence_hash,
                'expected_vector_hash' => $capture->expected_vector_hash,
                'detected_vector_hash' => $capture->detected_vector_hash,
                'vector_match' => $capture->vector_match,
                'summary' => $capture->summary,
                'captured_at' => $capture->captured_at?->toIso8601String(),
                'urls' => [
                    'input' => $capture->inputAsset !== null ? $this->assetUrl($run, $capture->inputAsset) : null,
                    'aligned' => $capture->alignedAsset !== null ? $this->assetUrl($run, $capture->alignedAsset) : null,
                    'overlay' => $capture->overlayAsset !== null ? $this->assetUrl($run, $capture->overlayAsset) : null,
                ],
            ])->values()->all(),
        ];
    }

    public function assertAccessible(OmrTemplateVerificationRun $run, User $actor): void
    {
        if ($run->draft_id === null) {
            return; // version-targeted runs are capability-gated by the route middleware
        }
        $draft = $run->draft;
        if ($draft === null || (int) $draft->owner_user_id !== (int) $actor->id) {
            throw new RuntimeException('verification_run_forbidden');
        }
    }

    /**
     * Available challenge/capture actions derived from the run state machine.
     * Digital run phases expose no challenge/capture actions; physical
     * phases advertise exactly the action that owns the next transition.
     *
     * @return list<string>
     */
    private function availableActions(OmrTemplateVerificationRun $run): array
    {
        return match ($run->status) {
            OmrTemplateVerificationRun::STATUS_FAILED => ['retry'],
            OmrTemplateVerificationRun::STATUS_PASSED => $run->template_version_id !== null ? ['create_challenge'] : [],
            OmrTemplateVerificationRun::STATUS_AWAITING_PRINT => ['print_challenge'],
            OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE => ['submit_capture'],
            OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW => ['review_capture'],
            default => [],
        };
    }

    private function assetUrl(OmrTemplateVerificationRun $run, \App\Models\Assessment\OmrTemplateAsset $asset): string
    {
        return route('setup.omr.templates.canonical.verification_runs.assets.show', [
            'run' => $run->id,
            'asset' => $asset->id,
        ]);
    }
}
