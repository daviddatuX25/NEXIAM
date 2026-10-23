<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRendererPort;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrRenderException;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Omr\Verification\OmrDigitalVerificationMatch;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function array_is_list;
use function array_map;
use function hash;
use function hash_equals;
use function is_array;
use function is_string;
use function json_encode;
use function range;
use function round;
use function str_starts_with;

/**
 * Physical challenge pack creation (OpenSpec 35.27, AD-7).
 *
 * Quick/full challenge modes both require a CURRENT durable digital pass
 * (a passing `CurrentOmrDigitalVerification` match). Quick rehearsal may
 * target the materialized draft candidate; full qualification requires the
 * compiled immutable version — a candidate or uncompiled draft gets a typed
 * refusal, never a fabricated sheet.
 *
 * The action renders the exact printable sheet + human shading guide from
 * the deterministic plan, persists them as immutable assets, pins the
 * expected vector/plan hash/signed calibration-purpose QR into the
 * challenge row, and moves the run to `awaiting_print` (quick) or
 * `awaiting_capture` (full). The run row is the sole resume authority;
 * nothing here reads operations or browser state.
 */
final class CreateOmrPhysicalChallenge
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly OmrVerificationTargetLoader $targets,
        private readonly OmrProofPlanner $planner,
        private readonly OmrPhysicalChallengeRendererPort $renderer,
        private readonly OmrTemplateAssetStore $assets,
        private readonly CurrentOmrDigitalVerification $verification,
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
    ) {}

    /**
     * @return array{
     *     run: OmrTemplateVerificationRun,
     *     challenge: OmrPhysicalChallenge,
     *     print_url: string,
     *     guide_url: string,
     * }
     */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, string $mode, string $methodName = OmrProofPlan::METHOD_PRE_SHADED): array
    {
        $this->drafts->assertOwner($draft, $actor);
        $draft = $draft->fresh(['markerProfile', 'template', 'assets']);
        if ($draft === null) {
            throw new OmrAuthoringDraftException('draft_missing', 'The authoring draft is no longer available.');
        }
        $isUploaded = OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL;
        if (! $isUploaded) {
            OmrSourcePolicy::assertSystemDesignedDraft($draft);
        }
        $digitalPass = $this->resolveDigitalPass($draft);
        if (! in_array($methodName, OmrProofPlan::METHODS, true)) {
            throw new OmrAuthoringDraftException('verification_method_invalid', 'Physical challenge method must be pre_shaded or hand_shaded.');
        }
        $existingRun = OmrTemplateVerificationRun::query()
            ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
            ->when($draft->compiled_version_id !== null, fn ($q) => $q->where('template_version_id', (int) $draft->compiled_version_id))
            ->when($draft->compiled_version_id === null, fn ($q) => $q->where('draft_id', (int) $draft->id))
            ->first();
        if ($existingRun !== null) {
            $mode = $existingRun->mode === OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION
                ? OmrPhysicalChallenge::MODE_FULL
                : OmrPhysicalChallenge::MODE_QUICK;
        }

        $purpose = $mode === OmrPhysicalChallenge::MODE_FULL
            ? ProofPurpose::FullQualification
            : ProofPurpose::QuickRehearsal;

        [$target, $runTarget, $pdfBytes, $insetsPx] = $this->target($draft, $purpose, $digitalPass);
        $this->assertTargetArtifactMatches($target, $digitalPass);
        $methodPlans = [];
        foreach (OmrProofPlan::METHODS as $requiredMethod) {
            $methodPlans[$requiredMethod] = $this->planner->plan($target, $purpose, OmrProofPlanner::CURRENT_PLAN_VERSION, $requiredMethod);
        }
        $plan = $methodPlans[$methodName];
        $expectedVectorHash = hash('sha256', json_encode($plan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $pinned = $plan->targetHashes;
        $pageCount = $target->pageCount();
        if (! $isUploaded) {
            try {
                $rendered = $this->renderer->render($target, $purpose, $pdfBytes, $insetsPx, OmrProofPlanner::CURRENT_PLAN_VERSION, $methodName);
            } catch (OmrDiagnosticTargetException|OmrRenderException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new OmrRenderException($exception);
            }
            $this->assertRenderedPageSet($rendered, $plan);
        } else {
            $rendered = null;
        }
        unset($pdfBytes, $insetsPx);

        $targetVersion = (string) (
            $draft->compiled_version_id
            ?? ($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? $plan->planVersion)
        );
        $run = DB::transaction(function () use ($actor, $mode, $runTarget, $plan, $expectedVectorHash, $pinned, $pageCount, $methodName, $isUploaded): OmrTemplateVerificationRun {
            // Serialize challenge creation against the target row. The
            // partial unique index remains the final race backstop.
            if ($runTarget['kind'] === 'draft') {
                $lockedDraft = OmrTemplateAuthoringDraft::query()->whereKey($runTarget['id'])->lockForUpdate()->firstOrFail();
                $lockedCanonical = (array) (($lockedDraft->authoring_json['canonical'] ?? []) ?: []);
                $lockedArtifactSet = (array) (($lockedCanonical['artifact_set'] ?? []) ?: []);
                $lockedArtifactHash = (string) ($lockedArtifactSet['artifact_hash'] ?? $lockedCanonical['artifact_hash'] ?? '');
                try {
                    $lockedGeometryIdentity = OmrCanonicalGeometryIdentity::forDraft($lockedDraft);
                } catch (\Throwable) {
                    $lockedGeometryIdentity = '';
                }
                if ($lockedArtifactHash === ''
                    || ! hash_equals((string) ($pinned['artifact'] ?? ''), $lockedArtifactHash)
                    || $lockedGeometryIdentity === ''
                    || ! hash_equals((string) ($pinned['mapping'] ?? ''), $lockedGeometryIdentity)) {
                    throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The draft changed while the physical challenge was being created; rematerialize and verify again.');
                }
            } else {
                OmrAnswerSheetTemplateVersion::query()->whereKey($runTarget['id'])->lockForUpdate()->firstOrFail();
            }

            $runMode = $mode === OmrPhysicalChallenge::MODE_FULL
                ? OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION
                : OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL;
            $conflict = OmrTemplateVerificationRun::query()
                ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
                ->when($runTarget['kind'] === 'draft', fn ($query) => $query->where('draft_id', $runTarget['id']))
                ->when($runTarget['kind'] === 'version', fn ($query) => $query->where('template_version_id', $runTarget['id']))
                ->lockForUpdate()
                ->first();
            if ($conflict !== null) {
                $runMode = (string) $conflict->mode;

                $existingMethod = $conflict->methods()->where('method', $methodName)->lockForUpdate()->first();
                if ($existingMethod?->challenge_id !== null) {
                    return $conflict;
                }

                return $conflict;
            }

            return OmrTemplateVerificationRun::query()->create([
                'draft_id' => $runTarget['kind'] === 'draft' ? $runTarget['id'] : null,
                'template_version_id' => $runTarget['kind'] === 'version' ? $runTarget['id'] : null,
                'status' => ($isUploaded || $mode === OmrPhysicalChallenge::MODE_FULL)
                    ? OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE
                    : OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                'mode' => $runMode,
                'attempt' => 1,
                'plan_version' => $plan->planVersion,
                'plan_hash' => $plan->planHash(),
                'expected_vector_hash' => $expectedVectorHash,
                'artifact_hash' => $pinned['artifact'],
                'native_bundle_hash' => $pinned['native_bundle'],
                'manifest_hash' => $pinned['manifest'],
                'config_hash' => $pinned['config'],
                'mapping_hash' => $pinned['mapping'],
                'challenge_code' => $plan->challengeCode,
                'progress_done' => 0,
                'progress_total' => $pageCount * count(OmrProofPlan::METHODS),
                'started_by_user_id' => $actor->id,
                'started_at' => now(),
                'last_heartbeat_at' => now(),
            ]);
        });

        $methods = $this->coordinator->ensureMethodRows(
            run: $run,
            plans: array_values($methodPlans),
            targetKind: (string) $target->kind(),
            targetId: (int) $target->targetId(),
            targetVersion: $targetVersion,
            requiredPageCount: $pageCount,
        );
        unset($methods);

        try {
            $challenge = $this->storeChallenge($run, $draft, $actor, $mode, $plan, $expectedVectorHash, $rendered, $runTarget, $target);
        } catch (\Throwable $exception) {
            if (! $run->challenges()->exists()) {
                $this->coordinator->discardUnmaterializedRun($run->id);
            }
            throw $exception;
        }

        return [
            'run' => $run->fresh(),
            'challenge' => $challenge,
            'print_url' => $challenge->sheet_asset_id !== null
                ? route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $challenge->sheet_asset_id,
                ])
                : null,
            'guide_url' => $challenge->guide_asset_id !== null
                ? route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $challenge->guide_asset_id,
                ])
                : null,
        ];
    }

    private function resolveDigitalPass(OmrTemplateAuthoringDraft $draft): OmrDigitalVerificationMatch
    {
        $match = $this->verification->find($draft);
        if ($match === null) {
            throw new OmrAuthoringDraftException('digital_pass_required', 'A current passing digital verification is required before a physical challenge can be created.');
        }

        return $match;
    }

    private function assertPersistedArtifactMatches(OmrTemplateAuthoringDraft $draft, OmrDigitalVerificationMatch $match): void
    {
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $artifactSet = (array) ($canonical['artifact_set'] ?? []);
        $persistedArtifactHash = (string) ($artifactSet['artifact_hash'] ?? $canonical['artifact_hash'] ?? '');
        if ($persistedArtifactHash === '' || ! hash_equals($match->artifactHash, $persistedArtifactHash)) {
            throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The current persisted artifact set does not match the passing digital verification; rematerialize before creating a challenge.');
        }
    }

    /**
     * @param  array<string, mixed>  $rendered
     */
    private function assertRenderedPageSet(array $rendered, OmrProofPlan $plan): void
    {
        $renderedPlan = $rendered['plan'] ?? null;
        $sheet = $rendered['sheet'] ?? null;
        $guide = $rendered['guide'] ?? null;
        $pages = is_array($sheet) ? ($sheet['pages'] ?? null) : null;
        if (! $renderedPlan instanceof OmrProofPlan
            || ! hash_equals($plan->planHash(), $renderedPlan->planHash())
            || ! is_array($sheet)
            || ! is_array($guide)
            || ! is_array($pages)
            || ! array_is_list($pages)
            || count($pages) !== $plan->requiredPageCount) {
            throw new \RuntimeException('challenge_sheet_page_manifest_incomplete');
        }

        foreach ($plan->compiledPages as $index => $expectedPage) {
            $page = $pages[$index] ?? null;
            $pageBytes = is_array($page) ? ($page['bytes'] ?? null) : null;
            $pageHash = is_array($page) ? ($page['sha256'] ?? null) : null;
            if (! is_array($page)
                || ! is_int($page['page_no'] ?? null)
                || $page['page_no'] !== (int) $expectedPage['page_no']
                || ! is_string($pageBytes)
                || $pageBytes === ''
                || ! is_string($pageHash)
                || ! hash_equals(hash('sha256', $pageBytes), $pageHash)) {
                throw new \RuntimeException('challenge_sheet_page_manifest_incomplete');
            }
        }

        $sheetPdf = $sheet['pdf_bytes'] ?? null;
        $sheetPdfHash = $sheet['pdf_sha256'] ?? null;
        $sheetHash = $sheet['sheet_hash'] ?? null;
        $qrPayloads = $sheet['qr_payloads'] ?? null;
        $guidePdf = $guide['pdf'] ?? null;
        $guideHtml = $guide['html'] ?? null;
        $guideHash = $guide['sha256'] ?? null;
        $guideJson = $rendered['guide_json'] ?? ($guide['json'] ?? null);
        $expectedGuideMode = $plan->purpose === ProofPurpose::FullQualification ? 'full' : 'quick';
        if (! is_string($sheetPdf)
            || ! str_starts_with($sheetPdf, '%PDF-')
            || ! is_string($sheetPdfHash)
            || ! hash_equals(hash('sha256', $sheetPdf), $sheetPdfHash)
            || ! is_string($sheetHash)
            || strlen($sheetHash) !== 64
            || ($sheet['challenge_code'] ?? null) !== $plan->challengeCode
            || ! is_array($qrPayloads)
            || ! array_is_list($qrPayloads)
            || count($qrPayloads) !== $plan->requiredPageCount
            || ! is_string($guidePdf)
            || ! str_starts_with($guidePdf, '%PDF-')
            || ! is_string($guideHtml)
            || $guideHtml === ''
            || ! is_string($guideHash)
            || ! hash_equals(hash('sha256', $guideHtml), $guideHash)
            || ($guide['mode'] ?? null) !== $expectedGuideMode
            || ! is_array($guideJson)
            || ($guideJson['plan_hash'] ?? null) !== $plan->planHash()
            || ($guideJson['expected_vector_hash'] ?? null) !== $plan->expectedVectorHash
            || ($guideJson['required_page_count'] ?? null) !== $plan->requiredPageCount
            || ($guideJson['mode'] ?? null) !== $expectedGuideMode) {
            throw new \RuntimeException('challenge_sheet_page_manifest_incomplete');
        }

        foreach ($plan->compiledPages as $index => $expectedPage) {
            $qr = $qrPayloads[$index] ?? null;
            $payload = is_array($qr) ? ($qr['payload'] ?? null) : null;
            $payloadHash = is_array($qr) ? ($qr['payload_hash'] ?? null) : null;
            if (! is_array($qr)
                || (int) ($qr['page_no'] ?? 0) !== (int) $expectedPage['page_no']
                || ! is_string($payload)
                || $payload === ''
                || ! is_string($payloadHash)
                || ! hash_equals(hash('sha256', $payload), $payloadHash)) {
                throw new \RuntimeException('challenge_sheet_page_manifest_incomplete');
            }
        }
    }

    private function assertTargetArtifactMatches(OmrDiagnosticTarget $target, OmrDigitalVerificationMatch $match): void
    {
        $targetArtifactHash = $target->artifactHash();
        if ($targetArtifactHash === '' || ! hash_equals($match->artifactHash, $targetArtifactHash)) {
            throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The materialized challenge target does not match the passing digital verification; rematerialize before creating a challenge.');
        }
    }

    private function assertCompiledVersionMatches(
        OmrTemplateAuthoringDraft $draft,
        OmrAnswerSheetTemplateVersion $version,
        OmrDigitalVerificationMatch $digitalPass,
    ): void {
        $manifest = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
        $artifactSet = $manifest['artifact_set'] ?? null;
        $artifactHash = is_array($artifactSet) ? (string) ($artifactSet['artifact_hash'] ?? '') : '';
        $verificationHashes = $manifest['verification_hashes'] ?? null;
        $verificationHashes = is_array($verificationHashes) ? $verificationHashes : [];

        if ((int) $draft->compiled_version_id !== (int) $version->id
            || (string) $version->template?->code !== (string) $draft->code
            || $artifactHash === ''
            || ! hash_equals($digitalPass->artifactHash, $artifactHash)) {
            throw new OmrAuthoringDraftException('compiled_version_mismatch', 'The compiled template version is not bound to this draft and its current digital verification.');
        }

        $requiredHashes = [
            'digital_verification' => $digitalPass->verificationHash,
            'matrix_hash' => $digitalPass->matrixHash,
            'proof_hash' => $digitalPass->proofHash,
        ];
        foreach ($requiredHashes as $key => $expected) {
            $actual = (string) ($verificationHashes[$key] ?? '');
            if ($expected === '' || $actual === '' || ! hash_equals($expected, $actual)) {
                throw new OmrAuthoringDraftException('compiled_version_mismatch', 'The compiled template version is not bound to the current digital verification.');
            }
        }
        if ($draft->registration_mode === OmrRegistrationMode::DocumentBound) {
            $expectedPages = array_map(
                static fn (int $pageNo): array => ['page_no' => $pageNo],
                range(1, (int) $draft->requested_page_count),
            );
            if ($version->registration_mode !== OmrRegistrationMode::DocumentBound
                || $version->qr_mode !== $draft->qr_mode
                || (int) $version->page_count !== (int) $draft->requested_page_count
                || ($manifest['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
                || ($manifest['qr_mode'] ?? null) !== $draft->qr_mode?->value
                || ($manifest['compiled_pages'] ?? null) !== $expectedPages
                || ($manifest['page_count'] ?? null) !== count($expectedPages)
                || ! is_array($artifactSet)
                || ($artifactSet['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
                || ($artifactSet['qr_mode'] ?? null) !== $draft->qr_mode?->value
                || ($artifactSet['compiled_pages'] ?? null) !== $expectedPages) {
                throw new OmrAuthoringDraftException('compiled_version_mismatch', 'The compiled document-bound version is not bound to the draft mode and ordered pages.');
            }
        }
    }

    /**
     * @return array{0: OmrDiagnosticTarget, 1: array{kind:string,id:int}, 2: string, 3: array<string,int>}
     */
    private function target(OmrTemplateAuthoringDraft $draft, ProofPurpose $purpose, OmrDigitalVerificationMatch $digitalPass): array
    {
        if ($draft->isCompiled()) {
            $versionId = (int) $draft->compiled_version_id;
            $version = OmrAnswerSheetTemplateVersion::query()->find($versionId);
            if ($version === null) {
                throw new OmrAuthoringDraftException('compiled_version_missing', 'The compiled template version for this draft is no longer available.');
            }
            $this->assertCompiledVersionMatches($draft, $version, $digitalPass);
            $target = new CompiledVersionDiagnosticTarget($version);
            $manifest = (array) $version->authoring_manifest;
            $artifactSet = (array) ($manifest['artifact_set'] ?? []);
            $isUploaded = ($manifest['source_kind'] ?? null) === OmrSourcePolicy::UPLOADED_ORIGINAL
                || OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL;
            if ($isUploaded) {
                return [$target, ['kind' => 'version', 'id' => (int) $version->id], null, []];
            }
            $pdfQuery = $version->canonicalAssets()->where('kind', OmrTemplateAsset::KIND_PRINT_PDF);
            if ($target->registrationMode() === OmrRegistrationMode::DocumentBound) {
                $sourcePdfAssetId = (int) ($artifactSet['pdf_asset_id'] ?? 0);
                if ($sourcePdfAssetId <= 0) {
                    throw new OmrAuthoringDraftException('version_print_asset_missing', 'The compiled document-bound version has no pinned source print PDF identity.');
                }
                $pdfQuery->where('metadata->pinned_from_asset_id', $sourcePdfAssetId);
            } else {
                $pdfQuery->orderByDesc('id');
            }
            $pdfAsset = $pdfQuery->first();
            if ($pdfAsset === null) {
                throw new OmrAuthoringDraftException('version_print_asset_missing', 'The compiled version has no pinned print PDF asset.');
            }
            $pdfBytes = Storage::disk($pdfAsset->disk)->get($pdfAsset->path);
            $expectedPdfHash = $target->registrationMode() === OmrRegistrationMode::DocumentBound
                ? (string) ($artifactSet['pdf_hash'] ?? '')
                : (string) $pdfAsset->sha256;
            if (! is_string($pdfBytes)
                || $expectedPdfHash === ''
                || ! hash_equals($expectedPdfHash, (string) $pdfAsset->sha256)
                || ! hash_equals($expectedPdfHash, hash('sha256', $pdfBytes))) {
                throw new OmrAuthoringDraftException('version_print_asset_corrupt', 'The compiled version print PDF failed its integrity check.');
            }
            $insetsMm = (array) ($manifest['marker_insets_mm'] ?? []);
            $insetsPx = [];
            if ($target->registrationMode() === OmrRegistrationMode::DocumentBound) {
                $insetsPx = [];
            } else {
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $insetsPx[$side] = (int) round((float) ($insetsMm[$side] ?? 0) * 300 / 25.4);
                }
            }

            return [$target, ['kind' => 'version', 'id' => (int) $version->id], $pdfBytes, $insetsPx];
        }

        if ($purpose->isQualification()) {
            throw new OmrAuthoringDraftException('full_requires_compiled_version', 'Full qualification requires a compiled template version; compile the draft first.');
        }

        $isUploaded = OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL;
        if ($isUploaded) {
            $loaded = $this->targets->load($digitalPass->run, includePrintPdf: false);
            $target = new CandidateArtifactDiagnosticTarget($loaded['draft'], $loaded['artifact'], null);

            return [$target, ['kind' => 'draft', 'id' => (int) $draft->id], null, []];
        }
        $loaded = $this->targets->load($digitalPass->run, includePrintPdf: true);
        $targetDraft = $loaded['draft'];
        $profile = $targetDraft->markerProfile;
        $artifact = $loaded['artifact'];
        $pdfBytes = $artifact->pdfBytes;
        if ($pdfBytes === '') {
            throw new OmrAuthoringDraftException('version_print_asset_missing', 'The persisted candidate has no pinned print PDF asset.');
        }
        if ($targetDraft->registration_mode === OmrRegistrationMode::FiducialAnchored && $profile === null) {
            throw new OmrAuthoringDraftException('marker_profile_not_selected', 'Select a marker profile before creating a physical challenge.');
        }
        $target = new CandidateArtifactDiagnosticTarget($targetDraft, $artifact, $profile);
        $insetsPx = $target->registrationMode() === OmrRegistrationMode::DocumentBound
            ? []
            : array_map('intval', (array) (($artifact->definition['insets_px'] ?? []) ?: []));
        unset($loaded, $artifact);

        return [$target, ['kind' => 'draft', 'id' => (int) $draft->id], $pdfBytes, $insetsPx];
    }

    /**
     * Deterministic plans produce one challenge code per target/purpose. Once
     * that pack exists, creating another run would violate the challenge-code
     * uniqueness boundary and lose the operator's existing sheet. Force the
     * caller down the retry/resume path before inserting anything.
     *
     * @param  array{kind:string,id:int}  $runTarget
     */
    private function assertNoExistingChallenge(
        array $runTarget,
        string $mode,
        OmrProofPlan $plan,
    ): void {
        $existing = OmrPhysicalChallenge::query()
            ->where('challenge_code', $plan->challengeCode)
            ->where('mode', $mode)
            ->where('status', '!=', OmrPhysicalChallenge::STATUS_SUPERSEDED)
            ->whereHas('run', function ($query) use ($runTarget): void {
                if ($runTarget['kind'] === 'draft') {
                    $query->where('draft_id', $runTarget['id']);
                } else {
                    $query->where('template_version_id', $runTarget['id']);
                }
                $query->where(function ($q): void {
                    $q->whereNull('error_code')
                        ->orWhere('error_code', '!=', 'target_changed_superseded');
                });
            })
            ->latest('id')
            ->lockForUpdate()
            ->first();
        if ($existing === null) {
            return;
        }

        throw new OmrAuthoringDraftException(
            'challenge_exists_resume_required',
            'An existing physical challenge already exists for this target; resume it instead of creating another challenge.',
            [
                'run_id' => (int) $existing->run_id,
                'challenge_id' => (int) $existing->id,
                'challenge_code' => (string) $existing->challenge_code,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $rendered
     * @param  array{kind:string,id:int}  $runTarget
     */
    private function storeChallenge(
        OmrTemplateVerificationRun $run,
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        string $mode,
        OmrProofPlan $plan,
        string $expectedVectorHash,
        ?array $rendered,
        array $runTarget,
        OmrDiagnosticTarget $target,
    ): OmrPhysicalChallenge {
        $createdAssets = [];
        $methodName = (string) ($plan->method ?? OmrProofPlan::METHOD_PRE_SHADED);
        try {
            if ($rendered === null) {
                $challengeCode = (string) $plan->challengeCode;
                $planHash = (string) $plan->planHash();
                $targetVersion = (string) (
                    $draft->compiled_version_id
                    ?? ($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? $plan->planVersion)
                );
                $profile = $draft->markerProfile;
                $guide = $this->renderer->renderGuide(
                    target: $target,
                    plan: $plan,
                    full: $mode === OmrPhysicalChallenge::MODE_FULL,
                    manifest: null,
                );

                $guidePdf = null;
                $guidePdfBytes = (string) ($guide['pdf'] ?? '');
                if ($guidePdfBytes !== '') {
                    $guidePdf = $this->putAsset($draft, $run, $runTarget, OmrTemplateAsset::KIND_EVIDENCE, $guidePdfBytes, 'application/pdf', null, [
                        'run_id' => $run->id,
                        'challenge_code' => $challengeCode,
                        'method' => $methodName,
                        'plan_hash' => $planHash,
                        'expected_vector_hash' => $expectedVectorHash,
                        'role' => 'challenge_guide_pdf',
                        'guide_mode' => (string) ($guide['mode'] ?? ''),
                        'guide_hash' => (string) ($guide['sha256'] ?? ''),
                        'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
                    ]);
                    if ($guidePdf->wasRecentlyCreated) {
                        $createdAssets[] = $guidePdf;
                    }
                }

                $saved = $this->coordinator->persistChallenge(
                    run: $run,
                    actor: $actor,
                    plan: $plan,
                    metadata: [
                        'challenge_mode' => $mode,
                        'target_kind' => $runTarget['kind'] === 'draft' ? 'candidate' : 'compiled_version',
                        'target_id' => (int) $runTarget['id'],
                        'target_version' => $targetVersion,
                        'renderer_version' => 'supplier_original',
                        'renderer_hash' => hash('sha256', 'supplier_original'),
                        'marker_profile_id' => $profile?->id,
                        'marker_profile_version' => $profile?->version,
                        'marker_profile_hash' => $profile?->asset_sha256,
                        'method' => $methodName,
                        'plan_hash' => $planHash,
                        'expected_vector_hash' => $expectedVectorHash,
                        'plan_seed' => $plan->seed,
                        'sheet_hash' => '',
                        'guide_hash' => (string) ($guide['sha256'] ?? ''),
                        'guide_json' => $guide['json'] ?? null,
                        'qr_payload_hash' => null,
                        'page_manifest' => [],
                        'scenario_manifest' => null,
                        'scenario_manifest_hash' => null,
                        'coverage_report' => null,
                        'coverage_hash' => null,
                        'renderer_identity' => null,
                        'profile_identity' => null,
                        'geometry_identity' => null,
                        'source_artifact_hashes' => null,
                        'transformed_artifact_hashes' => null,
                        'scenario_outcomes' => null,
                        'print_metadata' => [
                            'method' => $methodName,
                            'plan_hash' => $planHash,
                            'expected_vector_hash' => $expectedVectorHash,
                            'plan_seed' => $plan->seed,
                            'sheet_hash' => '',
                            'pages' => [],
                        ],
                    ],
                    assets: [
                        'sheet_asset_id' => null,
                        'sheet_asset_hash' => null,
                        'guide_asset_id' => $guidePdf !== null ? (int) $guidePdf->id : null,
                        'guide_asset_hash' => $guidePdf !== null ? (string) $guidePdf->sha256 : null,
                    ],
                );

                return $saved['challenge'];
            }
            $sheetPageRefs = [];
            $sheetPageHashes = [];
            $sheetHash = (string) ($rendered['sheet']['sheet_hash'] ?? '');
            $challengeCode = (string) $plan->challengeCode;
            $planHash = (string) $plan->planHash();

            foreach ((array) ($rendered['sheet']['pages'] ?? []) as $i => $page) {
                $pageBytes = (string) ($page['bytes'] ?? '');
                $pageNo = (int) ($page['page_no'] ?? 1);
                $asset = $this->putAsset($draft, $run, $runTarget, OmrTemplateAsset::KIND_RASTER_PAGE, $pageBytes, 'image/png', $pageNo, [
                    'run_id' => $run->id,
                    'challenge_code' => $challengeCode,
                    'method' => $methodName,
                    'plan_hash' => $planHash,
                    'expected_vector_hash' => $expectedVectorHash,
                    'role' => 'challenge_sheet_page',
                    'sheet_hash' => $sheetHash,
                    'page_hash' => (string) ($page['sha256'] ?? hash('sha256', $pageBytes)),
                    'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
                ]);
                $sheetPageHashes[$pageNo] = (string) ($page['sha256'] ?? hash('sha256', $pageBytes));
                unset($pageBytes, $rendered['sheet']['pages'][$i]['bytes']);
                if ($asset->wasRecentlyCreated) {
                    $createdAssets[] = $asset;
                }
                $sheetPageRefs[$pageNo] = $asset->id;
            }

            $sheetPdfBytes = (string) ($rendered['sheet']['pdf_bytes'] ?? '');
            $sheetPdf = $this->putAsset(
                $draft,
                $run,
                $runTarget,
                OmrTemplateAsset::KIND_PRINT_PDF,
                $sheetPdfBytes,
                'application/pdf',
                null,
                [
                    'run_id' => $run->id,
                    'challenge_code' => $challengeCode,
                    'method' => $methodName,
                    'plan_hash' => $planHash,
                    'expected_vector_hash' => $expectedVectorHash,
                    'role' => 'challenge_sheet',
                    'sheet_hash' => $sheetHash,
                    'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
                ],
            );
            unset($sheetPdfBytes, $rendered['sheet']['pdf_bytes']);
            if ($sheetPdf->wasRecentlyCreated) {
                $createdAssets[] = $sheetPdf;
            }

            $guide = (array) ($rendered['guide'] ?? []);
            $guidePdfBytes = (string) ($guide['pdf'] ?? '');
            $guideHash = (string) ($guide['sha256'] ?? hash('sha256', (string) ($guide['html'] ?? '')));
            $guidePdf = $this->putAsset($draft, $run, $runTarget, OmrTemplateAsset::KIND_PRINT_PDF, $guidePdfBytes, 'application/pdf', null, [
                'run_id' => $run->id,
                'challenge_code' => $challengeCode,
                'method' => $methodName,
                'plan_hash' => $planHash,
                'expected_vector_hash' => $expectedVectorHash,
                'role' => 'challenge_guide_pdf',
                'guide_mode' => (string) ($guide['mode'] ?? ''),
                'guide_hash' => $guideHash,
                'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
            ]);
            unset($guidePdfBytes, $rendered['guide']['pdf']);
            if ($guidePdf->wasRecentlyCreated) {
                $createdAssets[] = $guidePdf;
            }

            $guideHtmlBytes = (string) ($guide['html'] ?? '');
            $guideHtml = $this->putAsset($draft, $run, $runTarget, OmrTemplateAsset::KIND_EVIDENCE, $guideHtmlBytes, 'text/html', null, [
                'run_id' => $run->id,
                'challenge_code' => $challengeCode,
                'method' => $methodName,
                'plan_hash' => $planHash,
                'expected_vector_hash' => $expectedVectorHash,
                'role' => 'challenge_guide_html',
                'guide_hash' => $guideHash,
                'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
            ]);
            unset($guideHtmlBytes, $rendered['guide']['html']);
            if ($guideHtml->wasRecentlyCreated) {
                $createdAssets[] = $guideHtml;
            }

            $payloads = (array) ($rendered['sheet']['qr_payloads'] ?? []);
            $firstPayloadHash = is_array($payloads[0] ?? null) ? (string) ($payloads[0]['payload_hash'] ?? '') : null;
            $guideJson = $rendered['guide_json'] ?? $rendered['guide']['json'] ?? null;

            $runId = (int) $run->id;
            $runAttempt = (int) $run->attempt;
            $actorId = (int) $actor->id;
            $sheetPdfId = (int) $sheetPdf->id;
            $guidePdfId = (int) $guidePdf->id;

            unset($guideHtml);

            $targetPolicyHash = hash('sha256', json_encode([
                'registration_mode' => $plan->registrationMode->value,
                'qr_mode' => $plan->qrMode->value,
                'compiled_pages' => $plan->compiledPages,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $targetVersion = (string) (
                $draft->compiled_version_id
                ?? ($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? $plan->planVersion)
            );
            $expectedPageNos = [];
            foreach ($plan->compiledPages as $compiledPage) {
                $pageNo = (int) $compiledPage['page_no'];
                $expectedPageNos[] = $pageNo;
                $assetId = $sheetPageRefs[$pageNo] ?? null;
                $assetHash = (string) ($sheetPageHashes[$pageNo] ?? '');
                if ($assetId === null || $assetHash === '') {
                    throw new \RuntimeException('challenge_sheet_page_manifest_incomplete');
                }
                $pageManifest[] = [
                    'page_no' => $pageNo,
                    'asset_id' => (int) $assetId,
                    'asset_hash' => $assetHash,
                ];
            }
            if (array_keys($sheetPageRefs) !== $expectedPageNos
                || count($sheetPageRefs) !== $plan->requiredPageCount
                || count($pageManifest) !== $plan->requiredPageCount) {
                throw new \RuntimeException('challenge_sheet_page_manifest_incomplete');
            }
            $pageManifestHash = hash('sha256', json_encode($pageManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $profile = $draft->markerProfile;
            $saved = $this->coordinator->persistChallenge(
                run: $run,
                actor: $actor,
                plan: $plan,
                metadata: [
                    'challenge_mode' => $mode,
                    'target_kind' => $runTarget['kind'] === 'draft' ? 'candidate' : 'compiled_version',
                    'target_id' => (int) $runTarget['id'],
                    'target_version' => $targetVersion,
                    'renderer_version' => (string) ($rendered['sheet']['renderer_version'] ?? OmrManifestBubbleShader::RENDERER_VERSION),
                    'renderer_hash' => hash('sha256', (string) ($rendered['sheet']['renderer_version'] ?? '')),
                    'marker_profile_id' => $profile?->id,
                    'marker_profile_version' => $profile?->version,
                    'marker_profile_hash' => $profile?->asset_sha256,
                    'method' => $methodName,
                    'plan_hash' => $planHash,
                    'expected_vector_hash' => $expectedVectorHash,
                    'plan_seed' => $plan->seed,
                    'sheet_hash' => $sheetHash,
                    'guide_hash' => $guideHash,
                    'guide_json' => $guideJson,
                    'qr_payload_hash' => $firstPayloadHash,
                    'page_manifest' => $pageManifest,
                    'scenario_manifest' => $rendered['scenario_manifest'] ?? null,
                    'scenario_manifest_hash' => $rendered['scenario_manifest_hash'] ?? null,
                    'coverage_report' => $rendered['coverage_report'] ?? null,
                    'coverage_hash' => $rendered['coverage_hash'] ?? null,
                    'renderer_identity' => $rendered['renderer_identity'] ?? null,
                    'profile_identity' => $rendered['profile_identity'] ?? null,
                    'geometry_identity' => $rendered['geometry_identity'] ?? null,
                    'source_artifact_hashes' => $rendered['source_artifact_hashes'] ?? null,
                    'transformed_artifact_hashes' => $rendered['transformed_artifact_hashes'] ?? null,
                    'scenario_outcomes' => $rendered['scenario_outcomes'] ?? null,
                    'print_metadata' => [
                        'method' => $methodName,
                        'plan_hash' => $planHash,
                        'expected_vector_hash' => $expectedVectorHash,
                        'plan_seed' => $plan->seed,
                        'sheet_hash' => $sheetHash,
                        'sheet_asset_hash' => (string) $sheetPdf->sha256,
                        'guide_hash' => $guideHash,
                        'guide_asset_hash' => (string) $guidePdf->sha256,
                        'renderer_version' => (string) ($rendered['sheet']['renderer_version'] ?? OmrManifestBubbleShader::RENDERER_VERSION),
                        'run_mode' => $run->mode,
                        'registration_mode' => $plan->registrationMode->value,
                        'qr_mode' => $plan->qrMode->value,
                        'compiled_pages' => $plan->compiledPages,
                        'target_policy_hash' => $targetPolicyHash,
                        'page_manifest_hash' => $pageManifestHash,
                        'page_manifest' => $pageManifest,
                    ],
                ],
                assets: [
                    'sheet_asset_id' => $sheetPdfId,
                    'sheet_asset_hash' => (string) $sheetPdf->sha256,
                    'guide_asset_id' => $guidePdfId,
                    'guide_asset_hash' => (string) $guidePdf->sha256,
                ],
            );

            return $saved['challenge'];
        } catch (\Throwable $exception) {
            $this->cleanupUnreferenced($createdAssets);
            if ($this->isChallengeCodeConflict($exception)) {
                throw new OmrAuthoringDraftException(
                    'challenge_exists_resume_required',
                    'An existing physical challenge already exists for this target; resume it instead of creating another challenge.',
                    ['challenge_code' => $challengeCode ?? ''],
                );
            }
            throw $exception;
        }
    }

    private function isChallengeCodeConflict(\Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'challenge_code')
            && str_contains($message, 'omr_physical_challenges');
    }

    /**
     * @param  array{kind:string,id:int}  $runTarget
     * @param  array<string, mixed>  $metadata
     */
    private function putAsset(
        OmrTemplateAuthoringDraft $draft,
        OmrTemplateVerificationRun $run,
        array $runTarget,
        string $kind,
        string $bytes,
        string $mime,
        ?int $pageNo,
        array $metadata,
    ): OmrTemplateAsset {
        if ($runTarget['kind'] === 'version') {
            $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($runTarget['id']);

            return $this->assets->putVersionAsset($version, $kind, $bytes, $mime, $pageNo, $metadata);
        }

        return $this->assets->putDraftAsset($draft, $kind, $bytes, $mime, $pageNo, $metadata);
    }

    /**
     * AD-10: on failure delete ONLY newly-created assets that no challenge
     * or capture row references.
     *
     * @param  list<OmrTemplateAsset>  $createdAssets
     */
    private function cleanupUnreferenced(array $createdAssets): void
    {
        foreach ($createdAssets as $asset) {
            $referenced = OmrPhysicalChallenge::query()
                ->where(fn ($query) => $query->where('sheet_asset_id', $asset->id)->orWhere('guide_asset_id', $asset->id))
                ->exists();
            if ($referenced) {
                continue;
            }
            Storage::disk($asset->disk)->delete($asset->path);
            OmrTemplateAsset::withoutEvents(static function () use ($asset): void {
                $asset->delete();
            });
        }
    }
}
