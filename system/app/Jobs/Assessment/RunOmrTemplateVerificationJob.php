<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_equals;
use function implode;
use function is_array;
use function json_encode;
use function preg_replace;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Durable digital verification run (OpenSpec 35.24, AD-6).
 *
 * Executes the pinned run through truthful phases — `preparing_proof`,
 * `clean_recovery`, `variant_matrix` — persisting progress and evidence
 * AFTER each actual variant, comparing the clean vector EXACTLY to the plan,
 * requiring the declared typed outcomes, and setting `passed|failed` on the
 * run row. The run/evidence rows are the sole resume authority; this job
 * never reconstructs progress from operations and never returns a result
 * over a long synchronous HTTP request.
 */
final class RunOmrTemplateVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly int $runId,
    ) {
    }

    public function handle(
        CanonicalArtifactMaterializer $artifacts,
        OmrTemplateAssetStore $assetStore,
        OmrNoGoMatrixRunner $matrix,
        OmrDigitalProofGenerator $proofs,
        OmrProofPlanner $planner,
        OmrVerificationRunService $runs,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $run = OmrTemplateVerificationRun::query()->find($this->runId);
        if ($run === null || $run->status !== OmrTemplateVerificationRun::STATUS_QUEUED) {
            return; // idempotent replay: already claimed or already finished
        }
        if ($run->draft_id === null) {
            $runs->fail($run->id, 'target_kind_unsupported', 'Digital verification runs currently require a draft candidate target; compiled-version runs are created by the physical challenge flow.');
            return;
        }
        if (! $runs->transition($run->id, OmrTemplateVerificationRun::STATUS_QUEUED, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF, [
            'last_heartbeat_at' => now(),
            'phase_detail' => ['stage' => 'preparing_proof', 'current_variant' => null],
        ])) {
            return; // another worker claimed it
        }

        $cleanPath = null;
        $createdAssets = [];
        try {
            [$draft, $artifact, $plan, $proof] = $this->prepareProof($run, $artifacts, $planner, $proofs, $runs);
            $proofAssets = $this->storeProofAssets($run, $draft, $assetStore, $proof, $createdAssets);
            $total = count(OmrNoGoMatrixRunner::VARIANTS);
            if (! $runs->transition($run->id, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF, OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY, [
                'proof_hash' => $proof['proof_hash'],
                'progress_done' => 0,
                'progress_total' => $total,
                'phase_detail' => ['stage' => 'clean_recovery', 'current_variant' => 'clean'],
                'last_heartbeat_at' => now(),
            ])) {
                return;
            }

            $cleanBytes = (string) $proof['pages'][0]['bytes'];
            $cleanPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-run-');
            if ($cleanPath === false || file_put_contents($cleanPath, $cleanBytes) === false) {
                throw new RuntimeException('verification_clean_source_write_failed');
            }
            unset($cleanBytes);

            $persistEvidence = $this->evidencePersister($run, $draft, $assetStore, $proof, $createdAssets);
            $expectedVector = $plan->expectedVector;

            // Phase 2: clean_recovery — the clean proof page MUST recover the
            // exact plan vector (compare, never synthesize a pass).
            $cleanEntry = $matrix->runVariant('clean', $cleanPath, $artifact, $proof, $persistEvidence);
            $detectedVector = OmrNoGoMatrixRunner::detectedVector($expectedVector, (array) ($cleanEntry['detected_items'] ?? []));
            $detectedVectorHash = hash('sha256', json_encode($detectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $vectorMatch = OmrNoGoMatrixRunner::answerVectorMatches($expectedVector, (array) ($cleanEntry['detected_items'] ?? []));
            $this->storeCapture($run, 'clean', $cleanEntry, $proofAssets, $detectedVectorHash, $vectorMatch);
            if ($cleanEntry['status'] !== 'pass') {
                $runs->fail($run->id, $cleanEntry['error_code'] ?? 'clean_recovery_failed', 'The clean answer vector was not recovered exactly; completed evidence is preserved.', [
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => 'clean'],
                ]);
                $recordAuditEvent->handle('omr.verification.failed', [
                    'run_id' => $run->id,
                    'attempt' => $run->attempt,
                    'plan_hash' => $run->plan_hash,
                    'error_code' => $cleanEntry['error_code'] ?? 'clean_recovery_failed',
                ], (string) $run->started_by_user_id);
                return;
            }
            if (! $runs->transition($run->id, OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, [
                'clean_vector_hash' => $detectedVectorHash,
                'progress_done' => 1,
                'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => null],
                'last_heartbeat_at' => now(),
            ])) {
                return;
            }

            // Phase 3: variant_matrix — every non-clean variant must produce
            // its declared typed outcome; progress + heartbeat update after
            // EACH actual variant.
            $failed = [];
            foreach (array_diff(OmrNoGoMatrixRunner::VARIANTS, ['clean']) as $variant) {
                $run = $run->fresh();
                $entry = $matrix->runVariant($variant, $cleanPath, $artifact, $proof, $persistEvidence);
                $this->storeCapture($run, $variant, $entry, [], null, null);
                $done = (int) $run->progress_done + 1;
                if ($entry['status'] !== 'pass') {
                    $failed[$variant] = $entry;
                }
                if (! $runs->transition($run->id, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, [
                    'progress_done' => $done,
                    'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => $variant],
                    'last_heartbeat_at' => now(),
                ])) {
                    return;
                }
            }

            $summary = $this->resultSummary($run->fresh(), $proof, $detectedVectorHash);
            if ($failed !== []) {
                $runs->fail($run->id, 'no_go_variant_failed', 'Variants failed the declared outcome: '.implode(', ', array_keys($failed)).'. Completed evidence is preserved.', [
                    'result_summary' => $summary,
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null],
                ]);
                $recordAuditEvent->handle('omr.verification.failed', [
                    'run_id' => $run->id,
                    'attempt' => $run->attempt,
                    'plan_hash' => $run->plan_hash,
                    'error_code' => 'no_go_variant_failed',
                    'failed_variants' => array_keys($failed),
                ], (string) $run->started_by_user_id);
                return;
            }

            if (! $this->promoteDraft($draft, $run, $proof, $summary, $detectedVectorHash)) {
                $runs->fail($run->id, 'stale_target', 'The draft changed while verification was running; completed evidence is preserved and the run can be retried or a fresh run started.', [
                    'result_summary' => $summary,
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null],
                ]);
                $recordAuditEvent->handle('omr.verification.failed', [
                    'run_id' => $run->id,
                    'attempt' => $run->attempt,
                    'plan_hash' => $run->plan_hash,
                    'error_code' => 'stale_target',
                ], (string) $run->started_by_user_id);
                return;
            }

            $run = $run->fresh();
            if (! $runs->transition($run->id, OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX, OmrTemplateVerificationRun::STATUS_PASSED, [
                'progress_done' => $run->progress_total,
                'result_summary' => $summary,
                'phase_detail' => ['stage' => 'passed', 'current_variant' => null],
                'finished_at' => now(),
                'last_heartbeat_at' => now(),
            ])) {
                return;
            }
            $recordAuditEvent->handle('omr.verification.passed', [
                'run_id' => $run->id,
                'attempt' => $run->attempt,
                'plan_hash' => $run->plan_hash,
                'matrix_hash' => $summary['matrix_hash'],
                'proof_hash' => $proof['proof_hash'],
                'draft_id' => $draft->id,
            ], (string) $run->started_by_user_id);
        } catch (OmrDiagnosticTargetException $exception) {
            $this->cleanupUnreferenced($createdAssets);
            $runs->fail($run->id, $exception->errorCode, $exception->getMessage(), ['phase_detail' => ['stage' => 'failed', 'current_variant' => null]]);
            $recordAuditEvent->handle('omr.verification.failed', [
                'run_id' => $run->id,
                'attempt' => $run->attempt,
                'plan_hash' => $run->plan_hash,
                'error_code' => $run->fresh()->error_code ?? $exception->errorCode,
            ], (string) $run->started_by_user_id);
        } catch (Throwable $exception) {
            $this->cleanupUnreferenced($createdAssets);
            $code = $this->safeErrorCode($exception);
            $runs->fail($run->id, $code, 'The verification worker failed before finishing; completed evidence is preserved and the run can be retried.', ['phase_detail' => ['stage' => 'failed', 'current_variant' => null]]);
            $recordAuditEvent->handle('omr.verification.failed', [
                'run_id' => $run->id,
                'attempt' => $run->attempt,
                'plan_hash' => $run->plan_hash,
                'error_code' => $run->fresh()->error_code ?? $code,
            ], (string) $run->started_by_user_id);
            throw $exception;
        } finally {
            if ($cleanPath !== null) {
                @unlink($cleanPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            $run = OmrTemplateVerificationRun::query()->find($this->runId);
            if ($run === null || $run->isTerminal()) {
                return;
            }
            $code = $this->safeErrorCode($exception);
            $detail = $code === 'worker_timeout'
                ? 'The verification worker timed out; completed evidence is preserved and the run can be retried.'
                : 'The verification worker failed before finishing; completed evidence is preserved and the run can be retried.';
            OmrVerificationRunService::make()->fail($run->id, $code, $detail);
        } catch (Throwable) {
            // The run row remains the authority; a failed finalize must not mask the original failure.
        }
    }

    /**
     * @return array{0: OmrTemplateAuthoringDraft, 1: \App\Assessment\Omr\Canonical\CanonicalArtifactSet, 2: \App\Assessment\Omr\Diagnostics\OmrProofPlan, 3: array<string,mixed>}
     */
    private function prepareProof(
        OmrTemplateVerificationRun $run,
        CanonicalArtifactMaterializer $artifacts,
        OmrProofPlanner $planner,
        OmrDigitalProofGenerator $proofs,
        OmrVerificationRunService $runs,
    ): array {
        $draft = OmrTemplateAuthoringDraft::query()->with(['markerProfile', 'template', 'assets'])->findOrFail($run->draft_id);
        if ($draft->isCompiled()) {
            throw new OmrDiagnosticTargetException('candidate_draft_compiled', 'The draft is compiled; its immutable version target owns diagnostics now.');
        }
        $profile = $draft->markerProfile;
        if ($profile === null) {
            throw new OmrDiagnosticTargetException('candidate_marker_profile_mismatch', 'The draft marker profile is no longer selected; the run cannot verify this target.');
        }
        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        $artifact = $artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        $persistedArtifactHash = (string) (($draft->authoring_json['canonical']['artifact_set']['artifact_hash'] ?? ''));
        if ($persistedArtifactHash !== '' && ! hash_equals($persistedArtifactHash, $artifact->artifactHash)) {
            $runs->fail($run->id, 'stale_target', 'The draft artifact set changed after the run was pinned; rematerialize and start a new run.');
            throw new OmrDiagnosticTargetException('candidate_artifact_stale', 'The draft artifact set changed after the run was pinned.');
        }

        $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, $profile);
        $plan = $planner->plan($target, ProofPurpose::Digital);
        if (! hash_equals($run->plan_hash, $plan->planHash())) {
            $runs->fail($run->id, 'stale_target', 'The proof plan for the current target no longer matches the pinned plan; start a new run.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The proof plan for the current target no longer matches the pinned plan.');
        }
        if (! $plan->bindsTo($run->pinnedTargetHashes())) {
            $runs->fail($run->id, 'stale_target', 'The target hashes drifted from the pinned run; start a new run.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The target hashes drifted from the pinned run.');
        }

        return [$draft, $artifact, $plan, $proofs->generate($draft, $plan)];
    }

    /**
     * @param  array<string,mixed>  $proof
     * @param  list<OmrTemplateAsset>  $createdAssets
     * @return list<array{page_no:int,asset_id:int,sha256:string}>
     */
    private function storeProofAssets(
        OmrTemplateVerificationRun $run,
        OmrTemplateAuthoringDraft $draft,
        OmrTemplateAssetStore $assetStore,
        array $proof,
        array &$createdAssets,
    ): array {
        $refs = [];
        foreach ($proof['pages'] as $page) {
            $asset = $assetStore->putDraftAsset(
                $draft,
                OmrTemplateAsset::KIND_DIGITAL_PROOF,
                (string) $page['bytes'],
                'image/png',
                (int) $page['page_no'],
                ['run_id' => $run->id, 'attempt' => $run->attempt, 'proof_hash' => $proof['proof_hash']],
            );
            if ($asset->wasRecentlyCreated) {
                $createdAssets[] = $asset;
            }
            $refs[] = ['page_no' => (int) $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        }

        return $refs;
    }

    /**
     * @param  array<string,mixed>  $proof
     * @param  list<OmrTemplateAsset>  $createdAssets
     */
    private function evidencePersister(
        OmrTemplateVerificationRun $run,
        OmrTemplateAuthoringDraft $draft,
        OmrTemplateAssetStore $assetStore,
        array $proof,
        array &$createdAssets,
    ): callable {
        return function (string $variantName, string $fileName, string $bytes) use ($run, $draft, $assetStore, $proof, &$createdAssets): array {
            $asset = $assetStore->putDraftAsset(
                $draft,
                OmrTemplateAsset::KIND_EVIDENCE,
                $bytes,
                'image/png',
                1,
                ['run_id' => $run->id, 'attempt' => $run->attempt, 'proof_hash' => $proof['proof_hash'], 'variant' => $variantName, 'role' => $fileName],
            );
            if ($asset->wasRecentlyCreated) {
                $createdAssets[] = $asset;
            }

            return ['role' => $fileName, 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        };
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  list<array{page_no:int,asset_id:int,sha256:string}>  $proofAssets
     */
    private function storeCapture(
        OmrTemplateVerificationRun $run,
        string $variant,
        array $entry,
        array $proofAssets,
        ?string $detectedVectorHash,
        ?bool $vectorMatch,
    ): OmrTemplateVerificationCapture {
        $assets = [];
        foreach ((array) ($entry['evidence_assets'] ?? []) as $evidence) {
            $assets[(string) ($evidence['role'] ?? '')] = (int) ($evidence['asset_id'] ?? 0);
        }
        $summary = [
            'execution' => $entry['execution'] ?? null,
            'detail' => $entry['detail'] ?? null,
            'gates' => $entry['gates'] ?? null,
            'marker_matches' => $entry['marker_matches'] ?? null,
            'field_block_shifts' => $entry['field_block_shifts'] ?? null,
            'timings' => $entry['timings'] ?? null,
            'answer_vector_match' => $entry['answer_vector_match'] ?? null,
        ];
        $evidenceHash = hash('sha256', json_encode([
            'variant' => $variant,
            'actual' => $entry['actual'] ?? null,
            'status' => $entry['status'] ?? null,
            'error_code' => $entry['error_code'] ?? null,
            'input_hash' => $entry['input_hash'] ?? null,
            'aligned_hash' => $entry['aligned_hash'] ?? null,
            'overlay_hash' => $entry['overlay_hash'] ?? null,
            'vector_match' => $vectorMatch,
            'summary' => $summary,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'attempt' => $run->attempt,
            'page_no' => 1,
            'mode' => $run->mode,
            'variant' => $variant,
            'expected' => $entry['expected'] ?? null,
            'actual' => $entry['actual'] ?? null,
            'status' => $entry['status'] ?? 'fail',
            'error_code' => $entry['error_code'] ?? null,
            'input_hash' => (string) ($entry['input_hash'] ?? ''),
            'input_asset_id' => $proofAssets[0]['asset_id'] ?? null,
            'aligned_asset_id' => $assets['aligned.png'] ?? null,
            'overlay_asset_id' => $assets['layout-overlay.png'] ?? null,
            'evidence_hash' => $evidenceHash,
            'expected_vector_hash' => $run->expected_vector_hash,
            'detected_vector_hash' => $detectedVectorHash,
            'vector_match' => $vectorMatch,
            'summary' => $summary,
            'captured_by_user_id' => $run->started_by_user_id,
            'captured_at' => now(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $proof
     * @return array<string,mixed>
     */
    private function resultSummary(OmrTemplateVerificationRun $run, array $proof, string $detectedVectorHash): array
    {
        $captures = $run->captures()->with('alignedAsset')->with('overlayAsset')->orderBy('variant')->get();
        $variants = [];
        foreach ($captures as $capture) {
            if ($capture->variant === null) {
                continue;
            }
            $variants[$capture->variant] = [
                'expected' => $capture->expected,
                'actual' => $capture->actual,
                'status' => $capture->status,
                'error_code' => $capture->error_code,
                'execution' => $capture->summary['execution'] ?? null,
                'detail' => $capture->summary['detail'] ?? null,
                'input_hash' => $capture->input_hash,
                'answer_vector_match' => $capture->vector_match,
                'aligned_hash' => $capture->alignedAsset?->sha256,
                'overlay_hash' => $capture->overlayAsset?->sha256,
            ];
        }

        return [
            'matrix_hash' => hash('sha256', json_encode($variants, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'variants' => $variants,
            'proof_hash' => $proof['proof_hash'],
            'expected_vector_hash' => $run->expected_vector_hash,
            'clean_vector_hash' => $detectedVectorHash,
            'threshold_status' => 'Unqualified',
        ];
    }

    /**
     * @param  array<string,mixed>  $proof
     * @param  array<string,mixed>  $summary
     */
    private function promoteDraft(
        OmrTemplateAuthoringDraft $draft,
        OmrTemplateVerificationRun $run,
        array $proof,
        array $summary,
        string $detectedVectorHash,
    ): bool {
        $verificationHash = hash('sha256', json_encode([
            'run_id' => $run->id,
            'attempt' => $run->attempt,
            'plan_hash' => $run->plan_hash,
            'artifact_hash' => $run->artifact_hash,
            'proof_hash' => $proof['proof_hash'],
            'matrix_hash' => $summary['matrix_hash'],
            'clean_vector_hash' => $detectedVectorHash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $proofAssets = [];
        foreach ($proof['pages'] as $page) {
            $asset = OmrTemplateAsset::query()
                ->where('authoring_draft_id', $draft->id)
                ->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)
                ->where('page_no', (int) $page['page_no'])
                ->where('sha256', (string) $page['sha256'])
                ->first();
            if ($asset !== null) {
                $proofAssets[] = ['page_no' => (int) $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
            }
        }
        $verification = [
            'status' => 'passed',
            'artifact_hash' => $run->artifact_hash,
            'proof_hash' => $proof['proof_hash'],
            'proof_assets' => $proofAssets,
            'answer_vector' => $proof['answer_vector'],
            'matrix' => $summary,
            'hashes' => ['digital_verification' => $verificationHash],
            'threshold_status' => 'Unqualified',
            'run_id' => $run->id,
        ];

        return DB::transaction(function () use ($draft, $run, $verification): bool {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->find($draft->id);
            if ($locked === null) {
                return false;
            }
            $authoring = (array) $locked->authoring_json;
            $currentArtifactHash = (string) (($authoring['canonical']['artifact_set']['artifact_hash'] ?? ''));
            if ($locked->stage !== OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE
                || $currentArtifactHash === ''
                || ! hash_equals($currentArtifactHash, $run->artifact_hash)) {
                return false;
            }
            $authoring['verification'] = $verification;
            $locked->forceFill([
                'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
                'authoring_json' => $authoring,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();

            return true;
        });
    }

    /**
     * AD-10: on failure delete ONLY newly-created assets that no capture or
     * challenge row references; evidence rows are the resume authority.
     *
     * @param  list<OmrTemplateAsset>  $createdAssets
     */
    private function cleanupUnreferenced(array $createdAssets): void
    {
        if ($createdAssets === []) {
            return;
        }
        $ids = array_map(static fn (OmrTemplateAsset $asset): int => $asset->id, $createdAssets);
        $referenced = array_unique(array_values(array_filter(array_merge(
            OmrTemplateVerificationCapture::query()->whereIn('input_asset_id', $ids)->pluck('input_asset_id')->all(),
            OmrTemplateVerificationCapture::query()->whereIn('aligned_asset_id', $ids)->pluck('aligned_asset_id')->all(),
            OmrTemplateVerificationCapture::query()->whereIn('overlay_asset_id', $ids)->pluck('overlay_asset_id')->all(),
        ))));
        $referenced = array_flip($referenced);
        foreach ($createdAssets as $asset) {
            if (isset($referenced[$asset->id])) {
                continue;
            }
            Storage::disk($asset->disk)->delete($asset->path);
            $asset->delete();
        }
    }

    private function safeErrorCode(Throwable $exception): string
    {
        if ($exception instanceof \Illuminate\Queue\TimeoutExceededException
            || $exception instanceof \Illuminate\Queue\MaxAttemptsExceededException
            || stripos($exception->getMessage(), 'timed out') !== false) {
            return 'worker_timeout';
        }
        [$code] = explode(':', $exception->getMessage(), 2);
        $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));

        return $code !== '' ? $code : 'worker_unavailable';
    }
}
