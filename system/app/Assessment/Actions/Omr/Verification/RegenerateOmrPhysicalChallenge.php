<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function array_map;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function round;

/**
 * Idempotent physical challenge reference regeneration (OpenSpec 35.27 repair).
 *
 * Repairs stale or pre-evidence legacy challenge artifacts (missing renderer version,
 * legacy upper-left dot, missing guide JSON) without mutating any verification
 * decisions or historical capture evidence.
 *
 * Requirements:
 *  - Locks run and challenge rows for update.
 *  - Authorizes draft owner / permission capability.
 *  - Requires physical verification mode (quick rehearsal or full qualification).
 *  - Requires zero evidence captures; refuses with typed exception if captures exist.
 *  - Accepts only awaiting_print, awaiting_capture, or failed pre-evidence states.
 *  - Idempotent replay: if the challenge is already current, returns unchanged.
 *  - Atomically swaps sheet/guide assets and updates guide_json, hashes, and audit log.
 *  - On failure, cleans up only newly created unreferenced assets.
 */
final class RegenerateOmrPhysicalChallenge
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly CanonicalArtifactMaterializer $artifacts,
        private readonly OmrProofPlanner $planner,
        private readonly OmrPhysicalChallengeRenderer $renderer,
        private readonly OmrTemplateAssetStore $assets,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
        private readonly OmrVerificationTargetLoader $targets,
    ) {}

    /**
     * @return array{
     *     run: OmrTemplateVerificationRun,
     *     challenge: OmrPhysicalChallenge,
     *     print_url: string,
     *     guide_url: string,
     *     regenerated: bool,
     * }
     */
    public function handle(OmrTemplateVerificationRun $run, User $actor): array
    {
        OmrSourcePolicy::assertSystemDesignedRun($run);
        // 1. Authorize owner
        if ($run->draft_id !== null) {
            $draft = $run->draft;
            if ($draft === null) {
                throw new OmrAuthoringDraftException('draft_missing', 'The authoring draft is no longer available.');
            }
            $this->drafts->assertOwner($draft, $actor);
        }

        // 2. Physical mode validation
        if (! in_array($run->mode, [OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL, OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION], true)) {
            throw new OmrAuthoringDraftException('challenge_regeneration_not_physical', 'Challenge regeneration is only supported for physical verification runs.');
        }

        // 3. Pre-evidence state check
        $allowedStatuses = [
            OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
            OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
            OmrTemplateVerificationRun::STATUS_FAILED,
        ];
        if (! in_array($run->status, $allowedStatuses, true)) {
            throw new OmrAuthoringDraftException('challenge_regeneration_invalid_state', "Challenge regeneration is not permitted in run status '{$run->status}'.");
        }

        // 4. Capture / evidence guard
        if ($run->captures()->count() > 0) {
            throw new OmrAuthoringDraftException('challenge_regeneration_evidence_exists', 'Cannot regenerate challenge reference once evidence captures exist.');
        }

        $challenge = $run->challenges()->latest('id')->first();
        if ($challenge === null) {
            throw new OmrAuthoringDraftException('challenge_missing', 'No physical challenge found for this verification run.');
        }

        if ($challenge->captures()->count() > 0) {
            throw new OmrAuthoringDraftException('challenge_regeneration_evidence_exists', 'Cannot regenerate challenge reference once evidence captures exist.');
        }
        $methodName = (string) ($challenge->method ?: OmrPhysicalChallenge::METHOD_PRE_SHADED);
        $this->targets->loadPhysical($run, $methodName);

        // 5. Idempotent check: if already current, return existing unchanged
        if ($this->isChallengeCurrent($challenge)) {
            return [
                'run' => $run,
                'challenge' => $challenge,
                'print_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $challenge->sheet_asset_id,
                ]),
                'guide_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $challenge->guide_asset_id,
                ]),
                'regenerated' => false,
            ];
        }

        // 6. Resolve target & plan from pinned run metadata
        $purpose = $run->mode === OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION
            ? ProofPurpose::FullQualification
            : ProofPurpose::QuickRehearsal;

        [$target, $runTarget, $pdfBytes, $insetsPx, $draft] = $this->resolveTarget($run, $purpose);
        $method = $run->methods()->where('method', $methodName)->first();
        $planVersion = (string) ($method?->plan_version ?? $run->plan_version ?? 'v1');
        $plan = $this->planner->plan($target, $purpose, $planVersion, $methodName);
        $expectedVectorHash = $plan->expectedVectorHash;
        $pinnedPlanHash = (string) ($method?->plan_hash ?? $run->plan_hash);
        $pinnedExpectedVectorHash = (string) ($method?->expected_vector_hash ?? $run->expected_vector_hash);

        // Enforce the method's pinned plan boundary. The run retains the
        // first-created method's summary for compatibility; the method row is
        // authoritative when the other pack is repaired.
        if (! $plan->bindsTo($run->pinnedTargetHashes())
            || $plan->planHash() !== $pinnedPlanHash
            || $expectedVectorHash !== $pinnedExpectedVectorHash
        ) {
            throw new OmrAuthoringDraftException('plan_target_mismatch', 'The draft or compiled template has drifted since the verification plan was pinned; repair is refused.');
        }
        // 7. Render fresh challenge pack
        $rendered = $this->renderer->render($target, $purpose, $pdfBytes, $insetsPx, $planVersion, $methodName);
        unset($pdfBytes, $insetsPx, $target);

        // 8. Create new assets and atomically swap references under transaction lock
        $createdAssets = [];
        try {
            $sheetPageRefs = [];
            $sheetPageHashes = [];
            $sheetHash = (string) ($rendered['sheet']['sheet_hash'] ?? '');
            $challengeCode = (string) $plan->challengeCode;
            $planHash = $plan->planHash();

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
            $guidePdf = $this->putAsset($draft, $run, $runTarget, OmrTemplateAsset::KIND_EVIDENCE, $guidePdfBytes, 'application/pdf', null, [
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
            $diagnosticAttributes = [
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
            ];

            $sheetPdfId = (int) $sheetPdf->id;
            $guidePdfId = (int) $guidePdf->id;
            $sheetPdfHash = (string) $sheetPdf->sha256;
            $guidePdfHash = (string) $guidePdf->sha256;
            $pageManifest = [];
            foreach ($sheetPageRefs as $pageNo => $assetId) {
                $pageManifest[] = [
                    'page_no' => (int) $pageNo,
                    'asset_id' => (int) $assetId,
                    'asset_hash' => (string) ($sheetPageHashes[$pageNo] ?? ''),
                ];
            }
            $pageManifestHash = hash('sha256', json_encode($pageManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $printMetadata = [
                'method' => $methodName,
                'plan_hash' => $planHash,
                'expected_vector_hash' => $expectedVectorHash,
                'plan_seed' => $plan->seed,
                'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
                'sheet_hash' => $sheetHash,
                'sheet_asset_hash' => $sheetPdfHash,
                'guide_hash' => $guideHash,
                'guide_asset_hash' => $guidePdfHash,
                'regenerated' => true,
            ];
            $runId = (int) $run->id;
            $challengeId = (int) $challenge->id;
            $challengeCode = (string) $challenge->challenge_code;
            $challengeMode = (string) $challenge->mode;
            $targetKind = (string) $runTarget['kind'];
            $targetId = (int) $runTarget['id'];
            $targetHashes = $plan->targetHashes;
            $actorIdStr = (string) $actor->id;

            unset($rendered, $sheetPdf, $guidePdf, $guideHtml, $plan, $draft);

            $updatedChallenge = DB::transaction(function () use (
                $runId,
                $challengeId,
                $sheetPdfId,
                $guidePdfId,
                $sheetPdfHash,
                $guidePdfHash,
                $guideJson,
                $firstPayloadHash,
                $printMetadata,
                $pageManifest,
                $pageManifestHash,
                $diagnosticAttributes,
                $targetKind,
                $targetId,
                $targetHashes,
                $actor,
            ): OmrPhysicalChallenge {
                $lockedRun = OmrTemplateVerificationRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();
                $lockedChallenge = OmrPhysicalChallenge::query()->whereKey($challengeId)->lockForUpdate()->firstOrFail();

                $allowedStatuses = [
                    OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                    OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
                    OmrTemplateVerificationRun::STATUS_FAILED,
                ];
                if (! in_array($lockedRun->status, $allowedStatuses, true)) {
                    throw new OmrAuthoringDraftException('challenge_regeneration_invalid_state', "Challenge regeneration is not permitted in run status '{$lockedRun->status}'.");
                }
                if ((int) $lockedChallenge->run_id !== (int) $lockedRun->id || $lockedChallenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED) {
                    throw new OmrAuthoringDraftException('challenge_regeneration_invalid_state', 'This physical challenge is no longer the active reference for its run.');
                }

                if ($targetKind === 'draft') {
                    $lockedDraft = OmrTemplateAuthoringDraft::query()->whereKey($targetId)->lockForUpdate()->firstOrFail();
                    $lockedCanonical = (array) (($lockedDraft->authoring_json['canonical'] ?? []) ?: []);
                    $lockedArtifactSet = (array) (($lockedCanonical['artifact_set'] ?? []) ?: []);
                    $lockedArtifactHash = (string) ($lockedArtifactSet['artifact_hash'] ?? $lockedCanonical['artifact_hash'] ?? '');
                    try {
                        $lockedGeometryIdentity = OmrCanonicalGeometryIdentity::forDraft($lockedDraft);
                    } catch (\Throwable) {
                        $lockedGeometryIdentity = '';
                    }
                    if ($lockedArtifactHash === ''
                        || ! hash_equals((string) ($targetHashes['artifact'] ?? ''), $lockedArtifactHash)
                        || $lockedGeometryIdentity === ''
                        || ! hash_equals((string) ($targetHashes['mapping'] ?? ''), $lockedGeometryIdentity)) {
                        throw new OmrAuthoringDraftException('plan_target_mismatch', 'The draft changed while the challenge reference was being repaired; start a new rehearsal.');
                    }
                }

                // Re-verify capture count under lock
                if ($lockedRun->captures()->count() > 0 || $lockedChallenge->captures()->count() > 0) {
                    throw new OmrAuthoringDraftException('challenge_regeneration_evidence_exists', 'Cannot regenerate challenge reference once evidence captures exist.');
                }

                $printMetadata['reason'] = 'regenerated';

                return $this->coordinator->updateChallengeAssets(
                    run: $lockedRun,
                    challenge: $lockedChallenge,
                    actor: $actor,
                    attributes: [
                        'page_manifest' => $pageManifest,
                        'page_manifest_hash' => $pageManifestHash,
                        ...$diagnosticAttributes,
                        'sheet_asset_id' => $sheetPdfId,
                        'sheet_asset_hash' => $sheetPdfHash,
                        'guide_asset_id' => $guidePdfId,
                        'guide_asset_hash' => $guidePdfHash,
                        'guide_json' => $guideJson,
                        'qr_payload_hash' => $firstPayloadHash,
                        'print_metadata' => $printMetadata,
                    ],
                    action: 'omr.challenge.regenerated',
                );
            });

            return [
                'run' => $run->fresh(),
                'challenge' => $updatedChallenge,
                'print_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $updatedChallenge->sheet_asset_id,
                ]),
                'guide_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                    'run' => $run->id,
                    'asset' => $updatedChallenge->guide_asset_id,
                ]),
                'regenerated' => true,
            ];
        } catch (\Throwable $exception) {
            $this->cleanupUnreferenced($createdAssets);
            throw $exception;
        }
    }

    public function isChallengeCurrent(OmrPhysicalChallenge $challenge): bool
    {
        $sheetAsset = $challenge->sheetAsset;
        if ($sheetAsset === null) {
            return false;
        }

        $rendererVersion = $sheetAsset->metadata['renderer_version'] ?? null;
        if ($rendererVersion !== OmrManifestBubbleShader::RENDERER_VERSION) {
            return false;
        }

        if ($challenge->guide_json === null || ! is_array($challenge->guide_json) || $challenge->guide_json === []) {
            return false;
        }

        if ($challenge->guide_asset_id === null) {
            return false;
        }

        return true;
    }

    /**
     * @return array{0: OmrDiagnosticTarget, 1: array{kind:string,id:int}, 2: string, 3: array<string,int>, 4: ?OmrTemplateAuthoringDraft}
     */
    private function resolveTarget(OmrTemplateVerificationRun $run, ProofPurpose $purpose): array
    {
        if ($run->template_version_id !== null) {
            $version = OmrAnswerSheetTemplateVersion::query()->find($run->template_version_id);
            if ($version === null) {
                throw new OmrAuthoringDraftException('compiled_version_missing', 'The compiled template version for this verification run is no longer available.');
            }
            $target = new CompiledVersionDiagnosticTarget($version);
            $manifest = (array) $version->authoring_manifest;
            $pdfAsset = $version->canonicalAssets()
                ->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)
                ->orderByDesc('id')
                ->first();
            if ($pdfAsset === null) {
                throw new OmrAuthoringDraftException('version_print_asset_missing', 'The compiled version has no pinned print PDF asset.');
            }
            $pdfBytes = Storage::disk($pdfAsset->disk)->get($pdfAsset->path);
            if (! is_string($pdfBytes) || hash('sha256', $pdfBytes) !== $pdfAsset->sha256) {
                throw new OmrAuthoringDraftException('version_print_asset_corrupt', 'The compiled version print PDF failed its integrity check.');
            }
            $insetsMm = (array) ($manifest['marker_insets_mm'] ?? []);
            $insetsPx = [];
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $insetsPx[$side] = (int) round((float) ($insetsMm[$side] ?? 0) * 300 / 25.4);
            }

            return [$target, ['kind' => 'version', 'id' => (int) $version->id], $pdfBytes, $insetsPx, null];
        }

        $draft = $run->draft;
        if ($draft === null) {
            throw new OmrAuthoringDraftException('draft_missing', 'The authoring draft is no longer available.');
        }

        $profile = $draft->markerProfile;
        if ($profile === null) {
            throw new OmrAuthoringDraftException('marker_profile_not_selected', 'Select a marker profile before regenerating a challenge.');
        }
        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        $materialized = $this->artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        $pdfBytes = $materialized->pdfBytes;
        $insetsPx = array_map('intval', (array) (($materialized->definition['insets_px'] ?? []) ?: []));
        $target = new CandidateArtifactDiagnosticTarget($draft, $materialized->forDiagnostics(), $profile);
        unset($materialized);

        return [$target, ['kind' => 'draft', 'id' => (int) $draft->id], $pdfBytes, $insetsPx, $draft];
    }

    /**
     * @param  array{kind:string,id:int}  $runTarget
     * @param  array<string, mixed>  $metadata
     */
    private function putAsset(
        ?OmrTemplateAuthoringDraft $draft,
        OmrTemplateVerificationRun $run,
        array $runTarget,
        string $kind,
        string $bytes,
        string $mime,
        ?int $pageNo,
        array $metadata,
    ): OmrTemplateAsset {
        $asset = $runTarget['kind'] === 'version'
            ? $this->assets->putVersionAsset(OmrAnswerSheetTemplateVersion::query()->findOrFail($runTarget['id']), $kind, $bytes, $mime, $pageNo, $metadata)
            : $this->assets->putDraftAsset($draft ?? throw new OmrAuthoringDraftException('draft_missing', 'Draft missing when creating asset.'), $kind, $bytes, $mime, $pageNo, $metadata);

        if (($asset->metadata['renderer_version'] ?? null) !== OmrManifestBubbleShader::RENDERER_VERSION) {
            OmrTemplateAsset::withoutEvents(function () use ($asset, $metadata): void {
                $merged = array_merge((array) ($asset->metadata ?? []), $metadata);
                $asset->forceFill(['metadata' => $merged])->saveQuietly();
            });
        }

        return $asset;
    }

    /**
     * On failure delete ONLY newly-created assets that no challenge or capture row references.
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
