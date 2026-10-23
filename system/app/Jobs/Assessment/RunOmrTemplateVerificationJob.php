<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\OmrVerificationEvidenceService;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function count;
use function hash_equals;
use function is_array;
use function preg_replace;

/**
 * Prepares and pins the digital proof for a durable run, then queues the
 * first isolated variant step. The proof/artifact bytes remain scoped to this
 * coordinator process; the run row and private asset rows are the sole resume
 * authority for all subsequent variants.
 */
final class RunOmrTemplateVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 75;

    public function __construct(
        public readonly int $runId,
        public readonly ?int $attempt = null,
        public readonly ?int $expectedVersion = null,
    ) {
        $this->onQueue('omr-verification');
    }

    public function handle(
        CanonicalArtifactMaterializer $artifacts,
        OmrVerificationEvidenceService $evidence,
        OmrTemplateAssetStore $assetStore,
        OmrDigitalProofGenerator $proofs,
        OmrProofPlanner $planner,
        OmrVerificationRunService $runs,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $run = OmrTemplateVerificationRun::query()->find($this->runId);
        if ($run === null || $run->status !== OmrTemplateVerificationRun::STATUS_QUEUED
            || ($this->attempt !== null && (int) $run->attempt !== $this->attempt)
            || ($this->expectedVersion !== null && (int) $run->optimistic_version !== $this->expectedVersion)) {
            return; // idempotent or stale delivery
        }
        if ($run->draft_id === null) {
            $runs->fail($run->id, 'target_kind_unsupported', 'Digital verification runs currently require a draft candidate target; compiled-version runs are created by the physical challenge flow.');

            return;
        }
        if (! $runs->transition($run->id, OmrTemplateVerificationRun::STATUS_QUEUED, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF, [
            'last_heartbeat_at' => now(),
            'phase_detail' => ['stage' => 'preparing_proof', 'current_variant' => null, 'state' => 'running'],
        ])) {
            return; // another worker claimed it
        }

        $createdAssets = [];
        try {
            [$draft, $artifact, $plan, $proof] = $this->prepareProof($run, $artifacts, $planner, $proofs, $runs);
            $this->storeProofAssets($run, $draft, $assetStore, $proof, $createdAssets);
            if (! is_array($proof['pages'][0] ?? null) || ! is_string($proof['proof_hash'] ?? null)) {
                throw new RuntimeException('verification_proof_invalid');
            }

            $variantKeys = OmrNoGoMatrixRunner::variantKeys(count($proof['pages']), $plan->registrationMode);
            $total = count($variantKeys);
            $firstVariant = $variantKeys[0];
            if (! $runs->transition($run->id, OmrTemplateVerificationRun::STATUS_PREPARING_PROOF, OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY, [
                'proof_hash' => $proof['proof_hash'],
                'progress_done' => 0,
                'progress_total' => $total,
                'phase_detail' => ['stage' => 'clean_recovery', 'current_variant' => $firstVariant, 'state' => 'ready'],
                'last_heartbeat_at' => now(),
            ])) {
                return;
            }

            // The proof and artifact are intentionally scoped to this
            // coordinator process. The child job reloads only private assets
            // and the pinned target, so no image/blob is serialized in a job.
            $run = $run->fresh();
            $attempt = (int) $run->attempt;
            $version = (int) $run->optimistic_version;
            unset($draft, $artifact, $plan, $proof);
            RunOmrTemplateVerificationVariantJob::dispatch($run->id, $attempt, $firstVariant, $version)
                ->onQueue('omr-verification')
                ->afterCommit();
        } catch (OmrDiagnosticTargetException $exception) {
            $evidence->cleanupUnreferenced($createdAssets);
            $runs->fail($run->id, $exception->errorCode, $exception->getMessage(), ['phase_detail' => ['stage' => 'failed', 'current_variant' => null, 'state' => 'terminal']]);
            $recordAuditEvent->handle('omr.verification.failed', [
                'run_id' => $run->id,
                'attempt' => $run->attempt,
                'plan_hash' => $run->plan_hash,
                'error_code' => $run->fresh()->error_code ?? $exception->errorCode,
            ], (string) $run->started_by_user_id);
        } catch (Throwable $exception) {
            $evidence->cleanupUnreferenced($createdAssets);
            $code = $this->safeErrorCode($exception);
            $runs->fail($run->id, $code, 'The verification worker failed before finishing; completed evidence is preserved and the run can be retried.', ['phase_detail' => ['stage' => 'failed', 'current_variant' => null, 'state' => 'terminal']]);
            $recordAuditEvent->handle('omr.verification.failed', [
                'run_id' => $run->id,
                'attempt' => $run->attempt,
                'plan_hash' => $run->plan_hash,
                'error_code' => $run->fresh()->error_code ?? $code,
            ], (string) $run->started_by_user_id);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            $run = OmrTemplateVerificationRun::query()->find($this->runId);
            if ($run === null || $run->isTerminal()
                || ($this->attempt !== null && (int) $run->attempt !== $this->attempt)) {
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
     * @return array{0: OmrTemplateAuthoringDraft, 1: CanonicalArtifactSet, 2: OmrProofPlan, 3: array<string,mixed>}
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
        $profile = $this->markerProfileForMode($draft);
        $this->assertDocumentBoundMarkerFree($draft);
        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        try {
            $currentMapping = OmrCanonicalGeometryIdentity::forDraft(
                $draft,
                $profile,
                $candidateVersion > 0 ? $candidateVersion : null,
            );
        } catch (Throwable) {
            $currentMapping = '';
        }
        if ($currentMapping === '' || ! hash_equals((string) $run->mapping_hash, $currentMapping)) {
            $runs->fail($run->id, 'stale_target', 'The draft geometry changed after the run was pinned; rematerialize and start a new run.');
            throw new OmrDiagnosticTargetException('candidate_geometry_stale', 'The draft geometry changed after the run was pinned.');
        }

        // Reuse the immutable candidate bundle persisted by the web process.
        // Queue images can carry different raster/PDF dependencies, so
        // rematerializing there can produce a false artifact drift.
        $artifact = $artifacts->loadPersisted($draft, $profile);
        if (! hash_equals((string) $run->artifact_hash, $artifact->artifactHash)) {
            $runs->fail($run->id, 'stale_target', 'The draft artifact changed after the run was pinned; rematerialize and start a new run.');
            throw new OmrDiagnosticTargetException('candidate_artifact_stale', 'The draft artifact changed after the run was pinned.');
        }

        $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, $profile);
        $plan = $planner->plan($target, ProofPurpose::Digital, (string) ($run->plan_version ?? 'v1'));
        if (! hash_equals($run->plan_hash, $plan->planHash())) {
            $runs->fail($run->id, 'stale_target', 'The proof plan for the current target no longer matches the pinned plan; start a new run.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The proof plan for the current target no longer matches the pinned plan.');
        }
        if (! $plan->bindsTo($run->pinnedTargetHashes())) {
            $runs->fail($run->id, 'stale_target', 'The target hashes drifted from the pinned run; start a new run.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The target hashes drifted from the pinned run.');
        }

        $diagnosticArtifact = $artifact->forDiagnostics();
        unset($target, $artifact, $profile);
        $draft->unsetRelation('markerProfile')->unsetRelation('template')->unsetRelation('assets');
        gc_collect_cycles();
        gc_mem_caches();
        $proof = $proofs->generate($draft, $plan);
        if (($proof['registration_mode'] ?? null) !== $plan->registrationMode->value
            || ($proof['qr_mode'] ?? null) !== $plan->qrMode->value) {
            throw new OmrDiagnosticTargetException('digital_proof_policy_mismatch', 'The generated proof does not match the pinned registration and QR policy.');
        }

        return [$draft, $diagnosticArtifact, $plan, $proof];
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
                [
                    'run_id' => $run->id,
                    'attempt' => $run->attempt,
                    'proof_hash' => $proof['proof_hash'],
                    'registration_mode' => $proof['registration_mode'] ?? null,
                    'qr_mode' => $proof['qr_mode'] ?? null,
                    'paper_bounds' => $page['paper_bounds'] ?? null,
                ],
            );
            if ($asset->wasRecentlyCreated) {
                $createdAssets[] = $asset;
            }
            $refs[] = ['page_no' => (int) $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        }

        return $refs;
    }

    private function safeErrorCode(Throwable $exception): string
    {
        if ($exception instanceof TimeoutExceededException
            || $exception instanceof MaxAttemptsExceededException
            || stripos($exception->getMessage(), 'timed out') !== false) {
            return 'worker_timeout';
        }
        [$code] = explode(':', $exception->getMessage(), 2);
        $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));

        return $code !== '' ? substr($code, 0, 64) : 'worker_unavailable';
    }

    private function markerProfileForMode(OmrTemplateAuthoringDraft $draft): ?OmrMarkerProfile
    {
        if ($draft->registration_mode === OmrRegistrationMode::FiducialAnchored) {
            $profile = $draft->markerProfile;
            if ($profile === null || (int) $draft->marker_profile_id !== (int) $profile->id) {
                throw new OmrDiagnosticTargetException('candidate_marker_profile_mismatch', 'The anchored draft marker profile is no longer selected.');
            }
            if ($profile->status === OmrMarkerProfile::STATUS_RETIRED
                || ! in_array($profile->status, [OmrMarkerProfile::STATUS_CANDIDATE, OmrMarkerProfile::STATUS_QUALIFIED], true)) {
                throw new OmrDiagnosticTargetException('candidate_marker_profile_unqualified', 'The anchored draft marker profile is not available for verification.');
            }
            $artifactSet = (array) (($draft->authoring_json['canonical']['artifact_set'] ?? null) ?: []);
            if ($artifactSet !== []) {
                $pinnedProfile = (array) (($artifactSet['manifest']['marker_profile'] ?? null) ?: []);
                if ((int) ($pinnedProfile['id'] ?? 0) !== (int) $profile->id
                    || (int) ($pinnedProfile['version'] ?? 0) !== (int) $profile->version
                    || ! hash_equals((string) ($pinnedProfile['asset_sha256'] ?? ''), (string) $profile->asset_sha256)
                    || (string) ($pinnedProfile['status'] ?? '') !== (string) $profile->status) {
                    throw new OmrDiagnosticTargetException('candidate_marker_profile_mismatch', 'The anchored artifact no longer matches its approved marker profile.');
                }
            }

            return $profile;
        }

        if ($draft->registration_mode !== OmrRegistrationMode::DocumentBound) {
            throw new OmrDiagnosticTargetException('candidate_registration_mode_missing', 'The draft registration policy is not explicit.');
        }

        return null;
    }

    private function assertDocumentBoundMarkerFree(OmrTemplateAuthoringDraft $draft): void
    {
        if ($draft->registration_mode !== OmrRegistrationMode::DocumentBound) {
            return;
        }

        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $settings = (array) ($canonical['settings'] ?? []);
        $artifactSet = (array) ($canonical['artifact_set'] ?? []);
        $manifest = (array) ($artifactSet['manifest'] ?? []);
        $template = (array) ($artifactSet['template'] ?? []);
        $hasMarkerPageState = false;
        foreach ((array) ($template['pages'] ?? []) as $page) {
            $page = (array) $page;
            if (array_key_exists('markers', $page) || ($page['preProcessors'] ?? []) !== []) {
                $hasMarkerPageState = true;
                break;
            }
        }
        if ($draft->marker_profile_id !== null
            || $draft->markerProfile !== null
            || ! empty($authoring['marker'])
            || array_key_exists('marker_inset_mm', $settings)
            || $draft->assets->contains('kind', OmrTemplateAsset::KIND_MARKER_PROFILE)
            || ($artifactSet !== [] && ($artifactSet['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value)
            || (int) ($artifactSet['marker_asset_id'] ?? 0) > 0
            || (string) ($artifactSet['marker_hash'] ?? '') !== ''
            || array_key_exists('marker_profile', $manifest)
            || $hasMarkerPageState) {
            throw new OmrDiagnosticTargetException('candidate_registration_mode_mismatch', 'A document-bound candidate cannot retain marker profile or marker geometry state.');
        }
    }
}
