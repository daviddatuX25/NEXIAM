<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Actions\Omr\AnswerSheetImportAuthorization;
use App\Assessment\Actions\Omr\AnswerSheetImportStatus;
use App\Assessment\Actions\Omr\CancelAnswerSheetImport;
use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\ComposeOmrCanonicalPages;
use App\Assessment\Actions\Omr\Canonical\ConfirmOmrSourcePages;
use App\Assessment\Actions\Omr\Canonical\CreateOmrAuthoringDraft;
use App\Assessment\Actions\Omr\Canonical\DiscardOmrSandboxDraft;
use App\Assessment\Actions\Omr\Canonical\GenerateOmrMarkerCalibrationArtifact;
use App\Assessment\Actions\Omr\Canonical\IngestOmrSourceDocument;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\PreviewOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\QualifyOmrMarkerProfile;
use App\Assessment\Actions\Omr\Canonical\RectifyOmrSourcePage;
use App\Assessment\Actions\Omr\Canonical\RunOmrPhysicalQualification;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalMapping;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalOverlay;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Actions\Omr\Canonical\SelectOmrMarkerProfile;
use App\Assessment\Actions\Omr\Canonical\SupersedeOmrBlankInstanceSet;
use App\Assessment\Actions\Omr\Canonical\UpdateOmrDraftPages;
use App\Assessment\Actions\Omr\Canonical\VoidOmrBlankInstanceSet;
use App\Assessment\Actions\Omr\ConfirmAnswerSheetImport;
use App\Assessment\Actions\Omr\CreateAnswerSheetImportOperation;
use App\Assessment\Actions\Omr\ExportAnswerSheetBundle;
use App\Assessment\Actions\Omr\RetryAnswerSheetImport;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\DiscardOmrPhysicalAttempt;
use App\Assessment\Actions\Omr\Verification\MarkOmrChallengePrinted;
use App\Assessment\Actions\Omr\Verification\PrepareOmrPhysicalChallengePrint;
use App\Assessment\Actions\Omr\Verification\RegenerateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\RestartOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\RetakeOmrPhysicalPage;
use App\Assessment\Actions\Omr\Verification\RetryOmrTemplateVerification;
use App\Assessment\Actions\Omr\Verification\ReviewOmrPhysicalCapture;
use App\Assessment\Actions\Omr\Verification\SelectOmrPhysicalAttempt;
use App\Assessment\Actions\Omr\Verification\StartOmrTemplateVerification;
use App\Assessment\Actions\Omr\Verification\SubmitOmrPhysicalCapture;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrCanonicalCompileException;
use App\Assessment\Omr\Canonical\OmrCanonicalCompositionException;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrCanonicalMappingGuideGeometry;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticReportService;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrRenderException;
use App\Assessment\Omr\Queries\OmrEvidenceCache;
use App\Assessment\Omr\Queries\OmrEvidencePage;
use App\Assessment\Omr\Queries\OmrEvidenceQuery;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Assessment\Support\TestKitNavigationContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Omr\Canonical\CancelOmrSourceRunRequest;
use App\Http\Requests\Omr\Canonical\CompileOmrCanonicalDraftRequest;
use App\Http\Requests\Omr\Canonical\ComposeOmrCanonicalRequest;
use App\Http\Requests\Omr\Canonical\ConfirmOmrSourcePagesRequest;
use App\Http\Requests\Omr\Canonical\CreateOmrAuthoringDraftRequest;
use App\Http\Requests\Omr\Canonical\CreateOmrPhysicalChallengeRequest;
use App\Http\Requests\Omr\Canonical\DiscardOmrPhysicalAttemptRequest;
use App\Http\Requests\Omr\Canonical\GenerateOmrMarkerCalibrationArtifactRequest;
use App\Http\Requests\Omr\Canonical\IngestOmrSourceRequest;
use App\Http\Requests\Omr\Canonical\MarkOmrChallengePrintedRequest;
use App\Http\Requests\Omr\Canonical\MaterializeOmrCanonicalDraftRequest;
use App\Http\Requests\Omr\Canonical\PrepareOmrChallengePrintRequest;
use App\Http\Requests\Omr\Canonical\QualifyOmrMarkerProfileRequest;
use App\Http\Requests\Omr\Canonical\RectifyOmrSourcePageRequest;
use App\Http\Requests\Omr\Canonical\RegenerateOmrPhysicalChallengeRequest;
use App\Http\Requests\Omr\Canonical\RestartOmrPhysicalChallengeRequest;
use App\Http\Requests\Omr\Canonical\RetakeOmrPhysicalPageRequest;
use App\Http\Requests\Omr\Canonical\RetryOmrTemplateVerificationRequest;
use App\Http\Requests\Omr\Canonical\ReviewOmrPhysicalCaptureRequest;
use App\Http\Requests\Omr\Canonical\RunOmrPhysicalQualificationRequest;
use App\Http\Requests\Omr\Canonical\SaveOmrMappingRequest;
use App\Http\Requests\Omr\Canonical\SaveOmrOverlayRequest;
use App\Http\Requests\Omr\Canonical\SaveOmrStudioStateRequest;
use App\Http\Requests\Omr\Canonical\SelectOmrMarkerProfileRequest;
use App\Http\Requests\Omr\Canonical\SelectOmrPhysicalAttemptRequest;
use App\Http\Requests\Omr\Canonical\StartOmrTemplateVerificationRequest;
use App\Http\Requests\Omr\Canonical\SubmitOmrPhysicalCaptureRequest;
use App\Http\Requests\Omr\Canonical\SupersedeOmrBlankInstanceRequest;
use App\Http\Requests\Omr\Canonical\UpdateOmrDraftPagesRequest;
use App\Http\Requests\Omr\Canonical\VoidOmrBlankInstanceRequest;
use App\Http\Requests\Setup\CancelAnswerSheetImportRequest;
use App\Http\Requests\Setup\ConfirmAnswerSheetImportRequest;
use App\Http\Requests\Setup\ImportAnswerSheetBundleRequest;
use App\Http\Requests\Setup\ReplaceAnswerSheetImportRequest;
use App\Http\Requests\Setup\RetryAnswerSheetImportRequest;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OmrCanonicalTemplateController extends Controller
{
    public function index(
        Request $request,
        OmrCanonicalWorkflowProjection $projection,
        TestKitNavigationContext $kitNavigationContext,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        $kitContext = $kitNavigationContext->resolve($actor, $request);
        $props = $projection->indexProps($actor);
        $props['kit_context'] = $kitContext;
        $props['selected_template_version_id'] = $kitContext['template_version_id'] ?? null;
        if ($kitContext !== null && ! $kitContext['read_only']) {
            $props['create_url'] = $this->withKitContext($props['create_url'], $kitContext);
        }

        return Inertia::render('Setup/OmrConfiguration/Canonical/Index', $props);
    }

    public function studioDirect(
        Request $request,
        TestKitNavigationContext $kitNavigationContext,
        ?OmrTemplateAuthoringDraft $draft = null,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $kitContext = $kitNavigationContext->resolve($actor, $request);
        if ($draft === null) {
            return redirect()->route(
                'setup.exam-materials.answer-sheets.index',
                $this->kitContextRouteParameters($kitContext),
            );
        }

        abort_unless((int) $draft->owner_user_id === (int) $actor->id, 404);
        $kitContext = $this->kitContextForDraft($kitContext, $draft);

        return redirect()->route(
            'setup.exam-materials.answer-sheets.edit',
            ['draft' => $draft->getKey()] + $this->kitContextRouteParameters($kitContext),
        );
    }

    public function show(
        Request $request,
        OmrTemplateAuthoringDraft $draft,
        OmrCanonicalWorkflowProjection $projection,
        TestKitNavigationContext $kitNavigationContext,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless((int) $draft->owner_user_id === (int) $actor->id, 404);
        $kitContext = $this->kitContextForDraft(
            $kitNavigationContext->resolve($actor, $request),
            $draft,
        );
        $props = $projection->workflowProps($draft, $actor);
        $props['kit_context'] = $kitContext;

        return Inertia::render('Setup/OmrConfiguration/Canonical/CanonicalWorkflow', $props);
    }

    public function createEditableCopy(
        Request $request,
        OmrTemplateAuthoringDraft $draft,
        OmrTemplateAssetStore $assetStore,
        RecordAuditEvent $audit,
    ): JsonResponse|RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless((int) $draft->owner_user_id === (int) $actor->id, 404);

        $copy = DB::transaction(function () use ($draft, $actor, $assetStore, $audit): OmrTemplateAuthoringDraft {
            $draft = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            abort_unless((int) $draft->owner_user_id === (int) $actor->id, 404);
            $baseCode = (string) preg_replace('/(?:-COPY-\\d+|-V\\d+)$/', '', $draft->code);

            for ($version = 2; ; $version++) {
                $suffix = '-V'.$version;
                $candidateCode = substr($baseCode, 0, 64 - strlen($suffix)).$suffix;
                $draftCodeExists = OmrTemplateAuthoringDraft::query()
                    ->where('institution_id', $draft->institution_id)
                    ->where('code', $candidateCode)
                    ->exists();
                $templateCodeExists = OmrAnswerSheetTemplate::query()
                    ->where('institution_id', $draft->institution_id)
                    ->where('code', $candidateCode)
                    ->exists();

                if (! $draftCodeExists && ! $templateCodeExists) {
                    break;
                }
            }

            $candidateName = (string) preg_replace(
                '/\\s*\\(Editable Copy(?:\\s+\\d+)?\\)$/',
                '',
                (string) $draft->name,
            ).' (Editable Copy)';
            $authoring = (array) $draft->authoring_json;
            $copy = OmrTemplateAuthoringDraft::query()->create([
                'institution_id' => $draft->institution_id,
                'is_synthetic' => $draft->is_synthetic,
                'owner_user_id' => $actor->id,
                'template_id' => null,
                'compiled_version_id' => null,
                'marker_profile_id' => $draft->marker_profile_id,
                'test_instrument_version_id' => $draft->test_instrument_version_id,
                'code' => $candidateCode,
                'name' => $candidateName,
                'source_kind' => $draft->source_kind,
                'registration_mode' => $draft->registration_mode,
                'qr_mode' => $draft->qr_mode,
                'requested_page_count' => $draft->requested_page_count,
                'stage' => isset($authoring['mapping']) && is_array($authoring['mapping'])
                    ? OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE
                    : OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
                'authoring_json' => [],
                'lock_version' => 1,
            ]);

            $assetIdMap = [];
            $assets = OmrTemplateAsset::query()
                ->where('authoring_draft_id', $draft->id)
                ->whereIn('kind', [
                    OmrTemplateAsset::KIND_SOURCE,
                    OmrTemplateAsset::KIND_INTERMEDIATE_PAGE,
                    OmrTemplateAsset::KIND_RASTER_PAGE,
                ])
                ->orderBy('id')
                ->get();

            foreach ($assets as $oldAsset) {
                $bytes = Storage::disk($oldAsset->disk)->get($oldAsset->path);
                $newAsset = $assetStore->putDraftAsset(
                    $copy,
                    $oldAsset->kind,
                    $bytes,
                    $oldAsset->mime,
                    $oldAsset->page_no,
                    (array) $oldAsset->metadata,
                );
                $assetIdMap[(int) $oldAsset->id] = (int) $newAsset->id;
            }

            if (isset($authoring['source']) && is_array($authoring['source'])) {
                $this->remapAssetReference($authoring['source'], 'asset_id', $assetIdMap);

                if (isset($authoring['source']['metadata']) && is_array($authoring['source']['metadata'])) {
                    foreach (['original_asset_id', 'rectified_asset_id'] as $assetKey) {
                        $this->remapAssetReference($authoring['source']['metadata'], $assetKey, $assetIdMap);
                    }
                    foreach ((array) ($authoring['source']['metadata']['original_asset_ids'] ?? []) as $pageNo => $oldAssetId) {
                        if (isset($assetIdMap[(int) $oldAssetId])) {
                            $authoring['source']['metadata']['original_asset_ids'][$pageNo] = $assetIdMap[(int) $oldAssetId];
                        }
                    }
                }
            }

            if (isset($authoring['source_run']) && is_array($authoring['source_run'])) {
                $this->remapAssetReference($authoring['source_run'], 'source_asset_id', $assetIdMap);
            }
            if (isset($authoring['studio']) && is_array($authoring['studio'])) {
                $this->remapAssetReference($authoring['studio'], 'reviewed_crop_asset_id', $assetIdMap);
            }

            foreach ((array) ($authoring['pages'] ?? []) as $pageIndex => $page) {
                if (! is_array($page)) {
                    continue;
                }
                foreach (['asset_id', 'original_asset_id', 'rectified_asset_id'] as $assetKey) {
                    $this->remapAssetReference($page, $assetKey, $assetIdMap);
                }
                $authoring['pages'][$pageIndex] = $page;
            }

            $existingCanonical = is_array($authoring['canonical'] ?? null) ? $authoring['canonical'] : [];
            $remappedRasterAssets = [];
            foreach ((array) ($existingCanonical['raster_assets'] ?? []) as $rIdx => $rEntry) {
                if (is_array($rEntry)) {
                    $this->remapAssetReference($rEntry, 'asset_id', $assetIdMap);
                    $remappedRasterAssets[$rIdx] = $rEntry;
                }
            }
            $authoring['canonical'] = [
                'settings' => $existingCanonical['settings'] ?? null,
                'artifact_set' => null,
                'artifact_hash' => null,
                'raster_assets' => $remappedRasterAssets,
                'pdf_asset_id' => null,
                'superseded_artifact_sets' => [],
            ];
            $authoring['compiled'] = null;
            $authoring['lineage'] = [
                'copied_from_draft_id' => $draft->id,
                'copied_from_version_id' => $draft->compiled_version_id,
            ];
            $authoring['studio'] = (array) ($authoring['studio'] ?? []);
            $authoring['studio']['current_stage'] = 3;
            $authoring['studio']['active_page_no'] = (int) ($authoring['studio']['active_page_no'] ?? 1);

            $copy->forceFill(['authoring_json' => $authoring])->save();
            $audit->handle('omr.canonical.draft_copied', [
                'source_draft_id' => $draft->id,
                'new_draft_id' => $copy->id,
                'code' => $copy->code,
            ], (string) $actor->id);

            return $copy;
        });

        $editUrl = route('setup.exam-materials.answer-sheets.edit', $copy, absolute: false);
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'status' => 'created',
                'draft_id' => $copy->id,
                'redirect_url' => $editUrl,
            ], 201);
        }

        return redirect()->to($editUrl);
    }

    public function unlockDraft(
        Request $request,
        OmrTemplateAuthoringDraft $draft,
        RecordAuditEvent $audit,
    ): JsonResponse|RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $result = DB::transaction(function () use ($draft, $actor, $audit): array|JsonResponse {
            $draft = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $compiledVersion = $draft->compiled_version_id !== null
                ? OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->find($draft->compiled_version_id)
                : null;
            $eligibility = OmrCanonicalWorkflowProjection::evaluateUnlockEligibility($draft, $compiledVersion, $actor);
            if (! $eligibility['can_unlock']) {
                return response()->json([
                    'code' => 'draft_unlock_blocked',
                    'message' => $eligibility['reason'] ?? 'This draft cannot be unlocked in place.',
                ], 409);
            }

            $authoring = (array) $draft->authoring_json;
            $authoring['lineage'] = (array) ($authoring['lineage'] ?? []);
            $authoring['lineage']['unlocked_from_version_id'] = $draft->compiled_version_id;
            $authoring['canonical'] = (array) ($authoring['canonical'] ?? []);
            $authoring['canonical']['artifact_set'] = null;
            $authoring['canonical']['artifact_hash'] = null;
            $authoring['canonical']['superseded_artifact_sets'] = [];
            $authoring['verification'] = null;
            $authoring['compiled'] = null;
            $authoring['studio'] = (array) ($authoring['studio'] ?? []);
            $authoring['studio']['current_stage'] = 3;

            $draft->forceFill([
                'stage' => isset($authoring['mapping']) && is_array($authoring['mapping'])
                    ? OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE
                    : OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
                'compiled_version_id' => null,
                'authoring_json' => $authoring,
                'lock_version' => (int) $draft->lock_version + 1,
            ])->save();
            $audit->handle('omr.canonical.draft_unlocked', [
                'draft_id' => $draft->id,
                'unlocked_from_version_id' => $authoring['lineage']['unlocked_from_version_id'],
            ], (string) $actor->id);

            return [
                'status' => 'unlocked',
                'draft_id' => $draft->id,
                'redirect_url' => route('setup.exam-materials.answer-sheets.edit', $draft, absolute: false),
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }
        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->to($result['redirect_url']);
    }

    /**
     * @param  array<string, mixed>  $container
     * @param  array<int, int>  $assetIdMap
     */
    private function remapAssetReference(array &$container, string $key, array $assetIdMap): void
    {
        $oldAssetId = (int) ($container[$key] ?? 0);
        if (isset($assetIdMap[$oldAssetId])) {
            $container[$key] = $assetIdMap[$oldAssetId];
        }
    }

    public function asset(Request $request, OmrTemplateAuthoringDraft $draft, OmrTemplateAsset $asset): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 404);

        $isOwner = (int) $draft->owner_user_id === (int) $actor->getAuthIdentifier();
        $isCompiled = $draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED || $draft->compiled_version_id !== null;
        abort_unless($isOwner || $isCompiled, 404);

        $belongsToDraft = (int) $asset->authoring_draft_id === (int) $draft->id
            || ($draft->compiled_version_id !== null && (int) $asset->template_version_id === (int) $draft->compiled_version_id);
        abort_unless($belongsToDraft, 404);
        if (OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL
            && in_array($asset->kind, [OmrTemplateAsset::KIND_PRINT_PDF, OmrTemplateAsset::KIND_DIGITAL_PROOF], true)) {
            abort(403, 'uploaded_original_print_forbidden');
        }

        return Storage::disk($asset->disk)->response($asset->path, null, [
            'Content-Type' => $asset->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function nativeTemplate(Request $request, OmrAnswerSheetTemplateVersion $templateVersion): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($request->user() !== null, 403);
        $asset = $templateVersion->canonicalAssets()->where('kind', OmrTemplateAsset::KIND_NATIVE_TEMPLATE)->latest('id')->first();
        $bytes = $asset !== null ? Storage::disk($asset->disk)->get($asset->path) : json_encode($templateVersion->native_bundle_json['template'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        abort_unless(is_string($bytes) && $bytes !== '', 404);

        return response($bytes, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="template.json"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportDraft(
        Request $request,
        OmrTemplateAuthoringDraft $draft,
        ExportAnswerSheetBundle $action,
        CapabilityAuthorizer $authorizer,
    ): BinaryFileResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR), 403);
        $result = $action->handle($draft, $request->user());

        return response()->download($result['zip_path'], $result['filename'])->deleteFileAfterSend(true);
    }

    public function exportVersion(
        Request $request,
        OmrAnswerSheetTemplateVersion $templateVersion,
        ExportAnswerSheetBundle $action,
        CapabilityAuthorizer $authorizer,
    ): BinaryFileResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR), 403);
        $result = $action->handle($templateVersion, $request->user());

        return response()->download($result['zip_path'], $result['filename'])->deleteFileAfterSend(true);
    }

    public function import(
        ImportAnswerSheetBundleRequest $request,
        CreateAnswerSheetImportOperation $action,
        CapabilityAuthorizer $authorizer,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR), 403);

        try {
            $operation = $action->handle(
                $request->file('bundle'),
                $request->file('recovery_ledger'),
                $request->user(),
            );
        } catch (InvalidArgumentException|\RuntimeException $exception) {
            return back()->withErrors(['bundle' => $exception->getMessage()]);
        }

        $kitContext = $kitNavigationContext->resolve($request->user(), $request);

        return redirect()
            ->route(
                'setup.exam-materials.answer-sheets.imports.show',
                ['operation' => $operation->id] + $this->kitContextRouteParameters($kitContext),
            )
            ->with('status', 'Answer Sheet bundle uploaded. Checking the archive now.');
    }

    public function importStatusPage(
        Request $request,
        AnswerSheetImportOperation $operation,
        AnswerSheetImportAuthorization $authorization,
        AnswerSheetImportStatus $status,
        TestKitNavigationContext $kitNavigationContext,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        $authorization->assertOwner($actor, $operation);
        $kitContext = $kitNavigationContext->resolve($actor, $request);

        return Inertia::render('Setup/OmrConfiguration/Canonical/Import', [
            'operation' => $status->project($operation),
            'kit_context' => $kitContext,
        ]);
    }

    public function importStatus(
        Request $request,
        AnswerSheetImportOperation $operation,
        AnswerSheetImportAuthorization $authorization,
        AnswerSheetImportStatus $status,
    ): JsonResponse {
        $authorization->assertOwner($request->user(), $operation);

        return response()->json($status->project($operation), 200, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function confirmImport(
        ConfirmAnswerSheetImportRequest $request,
        AnswerSheetImportOperation $operation,
        ConfirmAnswerSheetImport $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        $validated = $request->validated();
        $action->handle(
            $request->user(),
            $operation,
            (int) $validated['review_revision'],
            (string) $validated['review_token'],
        );
        $kitContext = $kitNavigationContext->resolve($request->user(), $request);

        return redirect()->route(
            'setup.exam-materials.answer-sheets.imports.show',
            ['operation' => $operation->id] + $this->kitContextRouteParameters($kitContext),
        );
    }

    public function retryImport(
        RetryAnswerSheetImportRequest $request,
        AnswerSheetImportOperation $operation,
        RetryAnswerSheetImport $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        $action->handle($request->user(), $operation, (int) $request->validated('generation'));
        $kitContext = $kitNavigationContext->resolve($request->user(), $request);

        return redirect()->route(
            'setup.exam-materials.answer-sheets.imports.show',
            ['operation' => $operation->id] + $this->kitContextRouteParameters($kitContext),
        );
    }

    public function cancelImport(
        CancelAnswerSheetImportRequest $request,
        AnswerSheetImportOperation $operation,
        CancelAnswerSheetImport $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        $action->handle($request->user(), $operation, (int) $request->validated('generation'));
        $kitContext = $kitNavigationContext->resolve($request->user(), $request);

        return redirect()->route(
            'setup.exam-materials.answer-sheets.imports.show',
            ['operation' => $operation->id] + $this->kitContextRouteParameters($kitContext),
        );
    }

    public function replaceImportSource(
        ReplaceAnswerSheetImportRequest $request,
        AnswerSheetImportOperation $operation,
        CreateAnswerSheetImportOperation $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        $replacement = $action->handle(
            $request->file('bundle'),
            $request->file('recovery_ledger'),
            $request->user(),
            $operation,
        );
        $kitContext = $kitNavigationContext->resolve($request->user(), $request);

        return redirect()->route(
            'setup.exam-materials.answer-sheets.imports.show',
            ['operation' => $replacement->id] + $this->kitContextRouteParameters($kitContext),
        )->with('status', 'Replacement archive linked. Checking it now.');
    }

    public function bookletOptions(
        Request $request,
        OmrTemplateAuthoringDraft $draft,
        OmrCanonicalWorkflowProjection $projection,
    ): JsonResponse {
        abort_unless((int) $draft->owner_user_id === (int) $request->user()?->getAuthIdentifier(), 404);

        return response()->json([
            'contract' => 'omr-booklet-options.v1',
            'booklets' => $projection->bookletOptions(),
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(
        Request $request,
        OmrTemplateAuthoringDraft $draft,
        DiscardOmrSandboxDraft $discardAction,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $discardAction->handle($draft, $actor);

        return redirect()->route('setup.exam-materials.answer-sheets.index')->with('status', 'Answer-sheet draft discarded.');
    }

    public function diagnostics(): JsonResponse
    {
        return response()->json([
            'contract' => 'omr-canonical-template-authoring.v1',
            'limits' => config('omr.canonical.limits'),
            'marker_profiles' => [
                'candidates' => OmrMarkerProfile::query()->where('status', OmrMarkerProfile::STATUS_CANDIDATE)->count(),
                'qualified' => OmrMarkerProfile::query()->where('status', OmrMarkerProfile::STATUS_QUALIFIED)->count(),
            ],
            'drafts_by_stage' => OmrTemplateAuthoringDraft::query()
                ->selectRaw('stage, count(*) AS aggregate')
                ->groupBy('stage')
                ->pluck('aggregate', 'stage'),
            'qr_renderer' => 'endroid/qr-code 6.1.3',
            'html_sanitizer' => 'ezyang/htmlpurifier 4.19.0',
            'icc_policy' => 'reject_non_srgb',
        ]);
    }

    public function create(
        CreateOmrAuthoringDraftRequest $request,
        CreateOmrAuthoringDraft $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $draft = $action->handle(
            $actor,
            $payload['code'],
            $payload['name'],
            OmrTemplateAuthoringDraft::SOURCE_HTML,
            OmrRegistrationMode::from($payload['registration_mode']),
            OmrQrMode::from($payload['qr_mode']),
            (int) $payload['requested_page_count'],
        );

        $kitContext = $kitNavigationContext->resolve($actor, $request);

        return redirect()
            ->route(
                'setup.exam-materials.answer-sheets.edit',
                ['draft' => $draft->getKey()] + $this->kitContextRouteParameters($kitContext),
            )
            ->with('status', 'Answer-sheet draft created.');
    }

    public function ingest(
        IngestOmrSourceRequest $request,
        OmrTemplateAuthoringDraft $draft,
        IngestOmrSourceDocument $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $source = $request->file('source');
        abort_unless($source instanceof UploadedFile, 422);
        $options = $payload;
        unset($options['source'], $options['source_kind'], $options['idempotency_key']);
        try {
            $operation = $action->handle(
                $draft,
                $actor,
                $source,
                $payload['source_kind'],
                $payload['idempotency_key'],
                $options,
            );
        } catch (\LogicException|\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code));
            $detail = trim($detail);

            return response()->json([
                'code' => $code,
                'detail' => $detail,
                'message' => $detail,
                'errors' => [],
                'lock_version' => $draft->fresh()?->lock_version,
            ], 422);
        }

        return response()->json([
            'status' => 'queued',
            'operation_id' => $operation->id,
            'draft_id' => $draft->id,
        ], 202);
    }

    public function rectify(
        RectifyOmrSourcePageRequest $request,
        OmrTemplateAuthoringDraft $draft,
        RectifyOmrSourcePage $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $source = $request->file('source');
        abort_unless($source instanceof UploadedFile, 422);
        $pageNo = (int) ($payload['page_no'] ?? 1);
        try {
            $operation = $action->handle(
                $draft,
                $actor,
                $source,
                $payload['points'],
                (int) $payload['target_width'],
                (int) $payload['target_height'],
                (int) $payload['lock_version'],
                (string) $payload['idempotency_key'],
                $pageNo,
            );
        } catch (OmrAuthoringDraftException $exception) {
            if ($exception->errorCode === 'draft_forbidden') {
                abort(404);
            }

            $status = match ($exception->errorCode) {
                'concurrency_conflict', 'idempotency_conflict' => 409,
                'source_rectification_busy' => 503,
                default => 422,
            };

            return response()->json([
                'code' => $exception->errorCode,
                'detail' => $exception->getMessage(),
            ], $status);
        } catch (\LogicException|\RuntimeException $exception) {
            $code = $exception->getMessage();
            if (preg_match('/^[a-z0-9_]+$/', $code) !== 1) {
                $code = 'source_rectification_failed';
            }

            return response()->json([
                'code' => $code,
                'detail' => $code === 'worker_unavailable'
                    ? 'The image processor is unavailable. Try again later.'
                    : 'The page could not be straightened. Adjust the corners and try again.',
            ], $code === 'worker_unavailable' ? 503 : 422);
        }

        $saved = $draft->fresh();
        $pages = (array) ($saved->authoring_json['pages'] ?? []);
        $targetPage = collect($pages)->firstWhere('page_no', $pageNo);
        $assetId = $targetPage['asset_id'] ?? null;

        return response()->json([
            'status' => 'succeeded',
            'operation_id' => $operation->id,
            'draft_id' => $draft->id,
            'page_no' => $pageNo,
            'lock_version' => $saved->lock_version,
            'pages' => $pages,
            'pages_rectified' => count($pages),
            'requested_page_count' => (int) $saved->requested_page_count,
            'all_rectified' => count($pages) === (int) $saved->requested_page_count,
            'preview_url' => $assetId
                ? route('setup.omr.templates.canonical.drafts.assets.show', [$saved, $assetId])
                : null,
        ], 202);
    }

    public function sourceRun(Request $request, OmrTemplateAuthoringDraft $draft): JsonResponse
    {
        abort_unless((int) $draft->owner_user_id === (int) $request->user()?->getAuthIdentifier(), 404);

        return response()->json([
            'run' => $draft->authoring_json['source_run'] ?? null,
            'pages' => $draft->authoring_json['pages'] ?? [],
            'requested_page_count' => $draft->requested_page_count,
            'lock_version' => $draft->lock_version,
        ]);
    }

    public function cancelSourceRun(
        CancelOmrSourceRunRequest $request,
        OmrTemplateAuthoringDraft $draft,
        RecordAuditEvent $recordAuditEvent,
    ): JsonResponse {
        abort_unless((int) $draft->owner_user_id === (int) $request->user()?->getAuthIdentifier(), 404);
        $authoring = (array) $draft->authoring_json;
        $run = (array) ($authoring['source_run'] ?? []);
        abort_unless(in_array($run['status'] ?? null, ['queued', 'processing'], true), 409);
        $authoring['source_run'] = [...$run, 'cancel_requested' => true, 'status' => 'cancel_requested'];
        $draft->forceFill(['authoring_json' => $authoring])->save();
        $recordAuditEvent->handle('omr.canonical.source_conversion_cancel_requested', ['draft_id' => $draft->id, 'operation_id' => $run['operation_id'] ?? null], (string) $request->user()?->getAuthIdentifier());

        return response()->json(['run' => $draft->authoring_json['source_run']]);
    }

    public function confirmPages(
        ConfirmOmrSourcePagesRequest $request,
        OmrTemplateAuthoringDraft $draft,
        ConfirmOmrSourcePages $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $saved = $action->handle($draft, $actor, $payload['pages'], (int) $payload['lock_version']);

        return response()->json(['status' => $saved->stage, 'draft_id' => $saved->id, 'lock_version' => $saved->lock_version, 'pages' => $saved->authoring_json['pages'] ?? []]);
    }

    public function updatePages(
        UpdateOmrDraftPagesRequest $request,
        OmrTemplateAuthoringDraft $draft,
        UpdateOmrDraftPages $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        try {
            $saved = $action->handle(
                $draft,
                $actor,
                (string) $payload['operation'],
                (int) $payload['lock_version'],
                isset($payload['page_no']) ? (int) $payload['page_no'] : null,
                $payload['order'] ?? [],
            );
        } catch (\LogicException|\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code));
            $detail = trim($detail);

            return response()->json([
                'code' => $code,
                'detail' => $detail,
                'message' => $detail,
                'errors' => [],
                'lock_version' => $draft->fresh()?->lock_version,
            ], 422);
        }

        return response()->json([
            'status' => 'saved',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
            'requested_page_count' => $saved->requested_page_count,
            'pages' => $saved->authoring_json['pages'] ?? [],
        ]);
    }

    public function compose(
        ComposeOmrCanonicalRequest $request,
        OmrTemplateAuthoringDraft $draft,
        ComposeOmrCanonicalPages $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        unset($payload['idempotency_key']);
        try {
            $saved = $action->handle($draft, $actor, $payload);
            $composition = (array) ($saved->authoring_json['canonical'] ?? []);
            $rasterAssetId = (int) ($composition['raster_assets'][0]['asset_id'] ?? 0);

            return response()->json([
                'status' => 'ready',
                'draft_id' => $saved->id,
                'stage' => $saved->stage,
                'lock_version' => $saved->lock_version,
                'raster_url' => $rasterAssetId > 0
                    ? route('setup.omr.templates.canonical.drafts.assets.show', [$saved, $rasterAssetId], absolute: false)
                    : null,
                'composition' => $composition,
            ]);
        } catch (OmrCanonicalCompositionException $exception) {
            return response()->json([
                'outcome' => 'failed',
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
                'context' => $exception->context,
            ], 422);
        } catch (\Throwable $exception) {
            return response()->json([
                'outcome' => 'failed',
                'code' => 'composition_failed',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function overlay(
        SaveOmrOverlayRequest $request,
        OmrTemplateAuthoringDraft $draft,
        SaveOmrCanonicalOverlay $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $saved = $action->handle($draft, $actor, $payload['overlay'], (int) $payload['lock_version']);

        return response()->json(['status' => 'saved', 'draft_id' => $saved->id, 'stage' => $saved->stage, 'lock_version' => $saved->lock_version, 'overlay' => $saved->authoring_json['overlay'] ?? null]);
    }

    public function mapping(
        SaveOmrMappingRequest $request,
        OmrTemplateAuthoringDraft $draft,
        SaveOmrCanonicalMapping $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        try {
            $saved = $action->handle($draft, $actor, $payload['mapping'], (int) $payload['lock_version']);
        } catch (\LogicException|\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code));
            $detail = trim($detail);

            return response()->json([
                'code' => $code,
                'detail' => $detail,
                'message' => $detail,
                'errors' => [],
                'lock_version' => $draft->fresh()?->lock_version,
            ], 422);
        }

        return response()->json(['status' => 'saved', 'draft_id' => $saved->id, 'stage' => $saved->stage, 'lock_version' => $saved->lock_version, 'mapping' => $saved->authoring_json['mapping'] ?? null]);
    }

    public function studio(
        SaveOmrStudioStateRequest $request,
        OmrTemplateAuthoringDraft $draft,
        SaveOmrCanonicalStudioState $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        try {
            $saved = $action->handle(
                $draft,
                $actor,
                $payload['overlay'],
                $payload['mapping'] ?? null,
                $payload['studio'] ?? null,
                (int) $payload['lock_version'],
                $payload['source_kind'] ?? null,
            );
        } catch (\LogicException|\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code));
            $detail = trim($detail);
            $errors = [];
            if ($code === 'mapping_invalid') {
                $decoded = json_decode($detail, true);
                if (is_array($decoded)) {
                    $errors = array_values(array_filter($decoded, 'is_array'));
                }
            }
            $message = (string) ($errors[0]['message'] ?? $detail);

            return response()->json([
                'code' => $code,
                'detail' => $message,
                'message' => $message,
                'errors' => $errors,
                'lock_version' => $draft->fresh()?->lock_version,
            ], 422);
        }

        $mappingCurrent = in_array((string) $saved->stage, [
            OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
            OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
            OmrTemplateAuthoringDraft::STAGE_COMPILED,
        ], true);

        return response()->json([
            'status' => 'saved',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
            'mapping_current' => $mappingCurrent,
            'target_current' => true,
            'mapping' => $saved->authoring_json['mapping'] ?? null,
            'overlay' => $saved->authoring_json['overlay'] ?? null,
            'studio' => $saved->authoring_json['studio'] ?? null,
        ]);
    }

    public function materialize(
        MaterializeOmrCanonicalDraftRequest $request,
        OmrTemplateAuthoringDraft $draft,
        MaterializeOmrCanonicalDraft $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        try {
            $saved = $action->handle($draft, $actor, (int) $request->validated()['lock_version']);
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json(['outcome' => 'failed', 'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)), 'detail' => $detail, 'lock_version' => $draft->fresh()?->lock_version], 422);
        }
        $artifactSet = (array) ($saved->authoring_json['canonical']['artifact_set'] ?? []);

        return response()->json([
            'status' => 'materialized',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
            'artifact_set' => [
                'artifact_hash' => $artifactSet['artifact_hash'] ?? null,
                'definition_hash' => $artifactSet['definition_hash'] ?? null,
                'review_manifest_hash' => $artifactSet['review_manifest_hash'] ?? null,
                'raster_assets' => $artifactSet['raster_assets'] ?? [],
            ],
        ]);
    }

    public function preview(
        MaterializeOmrCanonicalDraftRequest $request,
        OmrTemplateAuthoringDraft $draft,
        PreviewOmrCanonicalDraft $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $pageNo = $request->integer('page_no', 1);
        try {
            $preview = $action->handle($draft, $actor, (int) $request->validated()['lock_version'], $pageNo);
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json(['outcome' => 'failed', 'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)), 'detail' => $detail, 'lock_version' => $draft->fresh()?->lock_version], 422);
        }
        $result = $preview['result'];
        if (! $result->succeeded()) {
            return response()->json([
                'outcome' => 'failed',
                'code' => $result->code,
                'detail' => $result->detail,
            ], 422);
        }
        $token = (string) Str::uuid();
        $directory = 'omr-diagnostics/'.$token;
        $disk = Storage::disk('local');
        $disk->put($directory.'/aligned.png', (string) $result->alignedBytes);
        $disk->put($directory.'/layout-overlay.png', (string) $result->overlayBytes);
        $saved = $preview['draft'];
        $artifact = $preview['artifact'];
        $rasterAssets = (array) ($saved->authoring_json['canonical']['artifact_set']['raster_assets']
            ?? $saved->authoring_json['canonical']['raster_assets']
            ?? $saved->authoring_json['pages']
            ?? []);
        $pages = [];
        foreach ($rasterAssets as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $pNo = (int) ($entry['page_no'] ?? 0);
            $assetId = (int) ($entry['asset_id'] ?? 0);
            if ($pNo > 0 && $assetId > 0) {
                $pages[] = [
                    'page_no' => $pNo,
                    'asset_id' => $assetId,
                    'canonical_url' => route('setup.omr.templates.canonical.drafts.assets.show', [$saved, $assetId]),
                    'sha256' => (string) ($entry['sha256'] ?? ''),
                ];
            }
        }
        $activePreviewPage = collect($pages)->firstWhere('page_no', $preview['preview_page_no'] ?? $pageNo) ?? ($pages[0] ?? null);
        $canonicalUrl = $activePreviewPage['canonical_url'] ?? ($pages[0]['canonical_url'] ?? null);

        return response()->json([
            'outcome' => 'succeeded',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
            'page_no' => $preview['preview_page_no'] ?? $pageNo,
            'artifact' => [
                'artifact_hash' => $artifact->artifactHash,
                'definition_hash' => $artifact->definition['source_hash'],
                'template_hash' => $artifact->templateHash,
                'config_hash' => $artifact->configHash,
                'review_manifest_hash' => $artifact->reviewManifestHash,
                'canonical_url' => $canonicalUrl,
                'pages' => $pages,
                'paper' => $artifact->definition['paper'] ?? null,
                'insets_mm' => $artifact->definition['insets_mm'] ?? null,
            ],
            'aligned_url' => route('setup.omr.templates.test_capture_asset', ['token' => $token, 'file' => 'aligned.png']),
            'overlay_url' => route('setup.omr.templates.test_capture_asset', ['token' => $token, 'file' => 'layout-overlay.png']),
            'aligned_image' => $result->alignedImage,
            'overlay_image' => $result->overlayImage,
            'marker_matches' => $result->markerMatches,
            'field_block_shifts' => $result->fieldBlockShifts,
            'bubble_means' => $result->bubbleMeans,
            'item_evidence' => $result->itemEvidence,
            'gates' => $result->gates,
            'calibration_status' => 'unqualified',
            'timings' => ['preprocess_ms' => $result->preprocessMs, 'verification_ms' => $result->verificationMs],
            'mapping_guides' => OmrCanonicalMappingGuideGeometry::resolve($artifact->template, (array) ($artifact->definition['insets_px'] ?? [])),
        ]);
    }

    public function selectMarkerProfile(
        SelectOmrMarkerProfileRequest $request,
        OmrTemplateAuthoringDraft $draft,
        SelectOmrMarkerProfile $action,
        OmrMarkerProfile $profile,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $saved = $action->handle($draft, $actor, $profile, $payload['insets'] ?? null, (int) $payload['lock_version']);

        return response()->json(['status' => 'saved', 'draft_id' => $saved->id, 'marker_profile_id' => $saved->marker_profile_id, 'lock_version' => $saved->lock_version]);
    }

    public function digitalVerification(
        StartOmrTemplateVerificationRequest $request,
        OmrTemplateAuthoringDraft $draft,
        StartOmrTemplateVerification $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.canonical.verification_run', (string) $draft->id, ['draft_id' => $draft->id], (string) $payload['idempotency_key']);
        $shouldExecute = $operation->wasRecentlyCreated || $operation->status !== Operation::STATUS_SUCCEEDED;
        if ($shouldExecute) {
            $operation->forceFill([
                'status' => Operation::STATUS_PROCESSING,
                'attempts' => (int) $operation->attempts + 1,
                'last_error' => null,
                'finished_at' => null,
            ])->save();
        }
        try {
            // Replays must resolve only the current digital target. The
            // generic latest-run query can otherwise resurrect an older
            // physical run after the draft artifact or mapping changed.
            $run = $shouldExecute ? $action->handle($draft, $actor) : $this->latestCurrentDigitalVerificationRun($draft);
            if ($run === null) {
                throw new \RuntimeException('verification_run_missing: The prior verification run is no longer available; start with a new idempotency key.');
            }
            if ($shouldExecute) {
                $operation->forceFill(['status' => Operation::STATUS_SUCCEEDED, 'last_error' => null, 'finished_at' => now()])->save();
            }
        } catch (\Throwable $exception) {
            $operation->forceFill(['status' => Operation::STATUS_FAILED, 'last_error' => $exception->getMessage(), 'finished_at' => now()])->save();
            if (! $exception instanceof \RuntimeException) {
                throw $exception;
            }
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json([
                'outcome' => 'failed',
                'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)),
                'detail' => trim($detail),
            ], 422);
        }

        return response()->json([
            'run_id' => $run->id,
            'status' => $run->status,
            'mode' => $run->mode,
            'target_current' => true,
            'target_stale' => false,
            'digital_verification_current' => false,
            'target' => [
                'kind' => 'candidate',
                'id' => $draft->id,
                'hashes' => $run->pinnedTargetHashes(),
            ],
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
        ], 202);
    }

    public function verificationRunStatus(
        Request $request,
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationStatus $status,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);

        return response()->json($status->get($run, $actor));
    }

    public function verificationEvidence(
        Request $request,
        OmrTemplateVerificationRun $run,
        OmrEvidenceQuery $evidence,
        OmrEvidenceCache $cache,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $parameters = [
            'method' => $request->query('method'),
            'capture_id' => $request->query('capture_id'),
            'area' => $request->query('area', 'all'),
            'filter' => $request->query('filter'),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', OmrEvidencePage::DEFAULT_PER_PAGE),
        ];

        try {
            $page = $evidence->paginate($run, $actor, $parameters);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'evidence_query_invalid',
                'detail' => $exception->getMessage(),
            ], 422);
        }

        $etag = $cache->etag($page);
        $headers = [
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'ETag' => $etag,
            'Vary' => 'Accept, If-None-Match',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $ifNoneMatch = trim((string) $request->header('If-None-Match', ''));
        if ($ifNoneMatch === '*' || $ifNoneMatch === $etag) {
            return response()->json(null, 304, $headers);
        }

        return response()->json($page->toArray(), 200, $headers);
    }

    public function diagnosticReport(
        Request $request,
        OmrTemplateVerificationRun $run,
        OmrDiagnosticReportService $reportService,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);

        $report = $reportService->buildReport($run);

        return response()->json($report, 200, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportDiagnosticReport(
        Request $request,
        OmrTemplateVerificationRun $run,
        OmrDiagnosticReportService $reportService,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);

        $redacted = $reportService->exportRedactedReport($run);

        return response()->json($redacted, 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="omr-diagnostic-report-'.$run->id.'.json"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function retryVerificationRun(
        RetryOmrTemplateVerificationRequest $request,
        OmrTemplateVerificationRun $run,
        RetryOmrTemplateVerification $action,
        OmrTemplateVerificationStatus $status,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        $retried = $action->handle($run, $actor, $payload['idempotency_key'] ?? null);

        return response()->json($status->get($retried, $actor), 202);
    }

    public function verificationRunAsset(
        Request $request,
        OmrTemplateVerificationRun $run,
        OmrTemplateAsset $asset,
        CapabilityAuthorizer $authorizer,
        ?string $sha256 = null,
    ): \Illuminate\Http\Response {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR), 403);
        $this->assertVerificationRunAccessible($run, $actor);

        $captureReferenced = OmrTemplateVerificationCapture::query()
            ->where('run_id', $run->id)
            ->where(fn ($query) => $query->where('input_asset_id', $asset->id)
                ->orWhere('aligned_asset_id', $asset->id)
                ->orWhere('overlay_asset_id', $asset->id))
            ->exists();
        $itemReferenced = OmrTemplateVerificationItemEvidence::query()
            ->where('crop_asset_id', $asset->id)
            ->whereHas('capture', fn ($query) => $query->where('run_id', $run->id))
            ->exists();
        $sheetReferenced = $run->challenges()->where('sheet_asset_id', $asset->id)->exists();
        $guideReferenced = $run->challenges()->where('guide_asset_id', $asset->id)->exists();
        $challengeReferenced = $sheetReferenced || $guideReferenced;
        $isChallengeGuidePdf = $guideReferenced
            && (int) ($asset->metadata['run_id'] ?? 0) === (int) $run->id
            && ($asset->metadata['role'] ?? null) === 'challenge_guide_pdf'
            && strtolower((string) $asset->mime) === 'application/pdf'
            && in_array($asset->kind, [OmrTemplateAsset::KIND_EVIDENCE, OmrTemplateAsset::KIND_PRINT_PDF], true);
        $isPinnedReferenceRaster = $this->isPinnedReferenceRasterForRun($run, $asset);
        $runOwned = is_array($asset->metadata) && (int) ($asset->metadata['run_id'] ?? 0) === (int) $run->id;
        if (! $captureReferenced && ! $itemReferenced && ! $challengeReferenced && ! $runOwned && ! $isPinnedReferenceRaster) {
            abort(404);
        }
        if (OmrSourcePolicy::sourceKindForRun($run) === OmrSourcePolicy::UPLOADED_ORIGINAL
            && ($sheetReferenced
                || $asset->kind === OmrTemplateAsset::KIND_DIGITAL_PROOF
                || ($asset->kind === OmrTemplateAsset::KIND_PRINT_PDF && ! $isChallengeGuidePdf))) {
            abort(403, 'uploaded_original_print_forbidden');
        }

        $assetHash = strtolower((string) $asset->sha256);
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $assetHash) === 1, 404);
        if ($sha256 !== null) {
            abort_unless(preg_match('/^[a-f0-9]{64}$/i', $sha256) === 1 && hash_equals($assetHash, strtolower($sha256)), 404);
        }
        $etag = '"'.$assetHash.'"';
        $headers = [
            // Legacy id-only links remain no-store; new evidence links carry
            // the immutable hash and may be safely revalidated by clients.
            'Cache-Control' => $sha256 !== null
                ? 'private, max-age=31536000, immutable'
                : 'private, no-store',
            'ETag' => $etag,
            'Vary' => 'Accept, If-None-Match',
            'X-Content-Type-Options' => 'nosniff',
        ];

        try {
            $bytes = Storage::disk($asset->disk)->get($asset->path);
        } catch (\Throwable) {
            abort(404);
        }
        abort_unless(is_string($bytes) && $bytes !== '' && hash_equals($assetHash, hash('sha256', $bytes)), 404);
        $ifNoneMatch = trim((string) $request->header('If-None-Match', ''));
        if ($ifNoneMatch === '*' || $ifNoneMatch === $etag) {
            return response('', 304, $headers);
        }

        $mime = match (strtolower((string) $asset->mime)) {
            'image/png' => 'image/png',
            'image/jpeg', 'image/jpg' => 'image/jpeg',
            'image/svg+xml' => 'image/svg+xml',
            'application/pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
        $extension = match ($mime) {
            'image/png' => '.png',
            'image/jpeg' => '.jpg',
            'image/svg+xml' => '.svg',
            'application/pdf' => '.pdf',
            default => '.bin',
        };

        return response($bytes, 200, [
            ...$headers,
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'inline; filename="omr-evidence-'.$assetHash.$extension.'"',
        ]);
    }

    private function isPinnedReferenceRasterForRun(OmrTemplateVerificationRun $run, OmrTemplateAsset $asset): bool
    {
        if ($asset->kind !== OmrTemplateAsset::KIND_RASTER_PAGE) {
            return false;
        }

        $belongsToTarget = ($run->template_version_id !== null && (int) $asset->template_version_id === (int) $run->template_version_id)
            || ($run->draft_id !== null && (int) $asset->authoring_draft_id === (int) $run->draft_id);
        if (! $belongsToTarget) {
            return false;
        }

        $rasterAssets = (array) (
            $run->templateVersion?->authoring_manifest['artifact_set']['raster_assets']
            ?? $run->draft?->authoring_json['canonical']['artifact_set']['raster_assets']
            ?? []
        );

        $assetPageNo = (int) ($asset->page_no ?? 0);
        $assetHash = strtolower((string) $asset->sha256);
        $assetId = (int) $asset->id;
        $pinnedFromId = (int) ($asset->metadata['pinned_from_asset_id'] ?? 0);

        foreach ($rasterAssets as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if ((int) ($entry['page_no'] ?? 0) !== $assetPageNo) {
                continue;
            }

            if (strtolower((string) ($entry['sha256'] ?? '')) !== $assetHash) {
                continue;
            }

            $entryId = (int) ($entry['asset_id'] ?? 0);
            if ($entryId === $assetId || ($pinnedFromId > 0 && $entryId === $pinnedFromId)) {
                return true;
            }
        }

        return false;
    }

    private function latestVerificationRun(OmrTemplateAuthoringDraft $draft): ?OmrTemplateVerificationRun
    {
        return OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->latest('id')->first();
    }

    private function latestCurrentDigitalVerificationRun(OmrTemplateAuthoringDraft $draft): ?OmrTemplateVerificationRun
    {
        $draft = $draft->fresh();
        if ($draft === null) {
            return null;
        }
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $artifactSet = (array) ($canonical['artifact_set'] ?? []);
        $artifactHash = (string) ($artifactSet['artifact_hash'] ?? $canonical['artifact_hash'] ?? '');
        if ($artifactHash === '') {
            return null;
        }
        try {
            $mappingHash = OmrCanonicalGeometryIdentity::forDraft($draft);
        } catch (\Throwable) {
            return null;
        }
        if ($mappingHash === '') {
            return null;
        }

        return OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
            ->where('artifact_hash', $artifactHash)
            ->where('mapping_hash', $mappingHash)
            ->latest('id')
            ->first();
    }

    private function assertVerificationRunAccessible(OmrTemplateVerificationRun $run, ?User $actor): void
    {
        abort_unless($actor !== null, 403);
        if ($run->draft_id === null) {
            return; // version-targeted runs are capability-gated by the route middleware
        }
        $draft = $run->draft;
        abort_unless($draft !== null && (int) $draft->owner_user_id === (int) $actor->id, 404);
    }

    public function compile(
        CompileOmrCanonicalDraftRequest $request,
        OmrTemplateAuthoringDraft $draft,
        CompileOmrCanonicalDraft $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.canonical.compile', (string) $draft->id, ['draft_id' => $draft->id], (string) $payload['idempotency_key']);
        $shouldExecute = $operation->wasRecentlyCreated || $operation->status !== Operation::STATUS_SUCCEEDED;
        if ($shouldExecute) {
            $operation->forceFill([
                'status' => Operation::STATUS_PROCESSING,
                'attempts' => (int) $operation->attempts + 1,
                'last_error' => null,
                'finished_at' => null,
            ])->save();
        }
        try {
            $saved = $shouldExecute ? $action->handle($draft, $actor) : $draft->fresh();
            if (! $shouldExecute && ($saved->stage !== OmrTemplateAuthoringDraft::STAGE_COMPILED || $saved->compiled_version_id === null)) {
                return response()->json(['outcome' => 'failed', 'code' => 'idempotency_result_stale', 'detail' => 'The prior compile result is no longer current; compile with a new idempotency key.'], 409);
            }
            if ($shouldExecute) {
                $operation->forceFill(['status' => Operation::STATUS_SUCCEEDED, 'last_error' => null, 'finished_at' => now()])->save();
            }
        } catch (\Throwable $exception) {
            $operation->forceFill(['status' => Operation::STATUS_FAILED, 'last_error' => $exception->getMessage(), 'finished_at' => now()])->save();
            if ($exception instanceof OmrCanonicalCompileException
                || $exception instanceof OmrAuthoringDraftException
                || $exception instanceof \LogicException) {
                [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
                $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));
                $detail = trim($detail);

                return response()->json([
                    'code' => $code,
                    'detail' => $detail,
                    'message' => $detail,
                    'errors' => [$code => [$detail]],
                    'lock_version' => $draft->fresh()?->lock_version,
                ], 422);
            }
            throw $exception;
        }

        return response()->json([
            'status' => $saved->stage,
            'operation_id' => $operation->id,
            'draft_id' => $saved->id,
            'template_version_id' => $saved->compiled_version_id,
        ]);
    }

    public function physicalQualification(
        RunOmrPhysicalQualificationRequest $request,
        OmrTemplateVerificationRun $run,
        RunOmrPhysicalQualification $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $this->assertVerificationRunAccessible($run, $actor);
        $operation = $idempotency->claim($actor, 'omr.canonical.physical_qualification', 'run:'.$run->id, ['run_id' => $run->id, ...$payload], (string) $payload['idempotency_key']);
        $version = $operation->wasRecentlyCreated
            ? $action->handle($run->fresh() ?? $run, $actor, array_diff_key($payload, ['idempotency_key' => true]))
            : OmrAnswerSheetTemplateVersion::query()->find($run->template_version_id);

        return response()->json(['status' => ($version?->authoring_manifest['physical_qualification']['status'] ?? null) ?? 'pending', 'operation_id' => $operation->id]);
    }

    public function createPhysicalChallenge(
        CreateOmrPhysicalChallengeRequest $request,
        OmrTemplateAuthoringDraft $draft,
        CreateOmrPhysicalChallenge $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless((int) $draft->owner_user_id === (int) $actor->id, 404);
        $payload = $request->validated();
        $mode = (string) $payload['mode'];
        $methodWasExplicit = array_key_exists('method', $payload);
        $method = (string) ($payload['method'] ?? OmrPhysicalChallenge::METHOD_PRE_SHADED);
        $idempotencyPayload = ['draft_id' => $draft->id, 'mode' => $mode];
        if ($methodWasExplicit) {
            $idempotencyPayload['method'] = $method;
        }

        try {
            $operation = $idempotency->claim(
                $actor,
                'omr.canonical.physical_challenge',
                (string) $draft->id,
                $idempotencyPayload,
                (string) $payload['idempotency_key'],
            );
        } catch (\Throwable) {
            return response()->json([
                'outcome' => 'failed',
                'code' => 'idempotency_key_conflict',
                'detail' => 'This idempotency key is already bound to a different physical challenge request.',
            ], 409);
        }

        if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_SUCCEEDED) {
            $result = $this->challengeResultFromOperation($operation, $draft, $mode, $method);
            if ($result === null) {
                return response()->json([
                    'outcome' => 'failed',
                    'code' => 'idempotency_result_stale',
                    'detail' => 'The prior challenge result is no longer available; create with a new idempotency key.',
                ], 409);
            }

            return response()->json($this->challengeResponse($result, $operation), 202);
        }

        if (! $operation->wasRecentlyCreated && $operation->status === Operation::STATUS_PROCESSING) {
            return response()->json([
                'outcome' => 'failed',
                'code' => 'idempotency_in_progress',
                'detail' => 'The original physical challenge request is still being completed; retry with the same idempotency key.',
                'operation_id' => $operation->id,
            ], 409);
        }

        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'last_error' => null,
            'finished_at' => null,
        ])->save();

        $result = null;
        try {
            $result = $action->handle($draft, $actor, $mode, $method);
        } catch (\Throwable $exception) {
            [$code, $detail, $status, $safe] = $this->physicalChallengeFailure($exception);
            $activeDigitalRun = $this->activeDigitalRun($draft);
            $activeDigitalRefusal = false;
            if ($code === 'digital_pass_required' && $activeDigitalRun !== null) {
                $code = 'verification_active_run_in_progress';
                $detail = 'A digital verification run is already active for this draft; wait for it to finish before creating a physical challenge.';
                $activeDigitalRefusal = true;
            }
            if (! $activeDigitalRefusal && $code === 'verification_active_run_in_progress' && $activeDigitalRun === null) {
                $result = $this->latestPhysicalChallenge($draft, $mode, $method);
            } elseif ($code === 'challenge_exists_resume_required') {
                $result = $this->latestPhysicalChallenge($draft, $mode, $method);
            }
            if ($result === null) {
                $this->failPhysicalChallengeOperation($operation, $code, $detail);
                if (! $safe) {
                    throw $exception;
                }

                return response()->json([
                    'outcome' => 'failed',
                    'code' => $code,
                    'detail' => $detail,
                ], $status);
            }
        }

        $operation->forceFill([
            'status' => Operation::STATUS_SUCCEEDED,
            'payload' => [
                ...(array) $operation->payload,
                'result' => [
                    'run_id' => (int) $result['run']->id,
                    'challenge_id' => (int) $result['challenge']->id,
                    'mode' => $mode,
                    'method' => $method,
                    'target_kind' => $result['run']->draft_id !== null ? 'draft' : 'version',
                    'target_id' => (int) ($result['run']->draft_id ?? $result['run']->template_version_id),
                ],
            ],
            'last_error' => null,
            'finished_at' => now(),
        ])->save();

        return response()->json($this->challengeResponse($result, $operation), 202);
    }

    public function preparePrint(
        PrepareOmrChallengePrintRequest $request,
        OmrTemplateVerificationRun $run,
        PrepareOmrPhysicalChallengePrint $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        $method = array_key_exists('method', $payload) ? (string) $payload['method'] : null;
        $idempotencyPayload = ['run_id' => $run->id];
        if ($method !== null) {
            $idempotencyPayload['method'] = $method;
        }
        $operation = $idempotency->claim(
            $actor,
            'omr.canonical.prepare_print',
            (string) $run->id,
            $idempotencyPayload,
            (string) $payload['idempotency_key'],
        );
        $shouldExecute = $operation->wasRecentlyCreated || $operation->status !== Operation::STATUS_SUCCEEDED;
        if ($shouldExecute) {
            $operation->forceFill([
                'status' => Operation::STATUS_PROCESSING,
                'attempts' => (int) $operation->attempts + 1,
                'last_error' => null,
                'finished_at' => null,
            ])->save();
        }
        try {
            if ($shouldExecute) {
                $result = $action->handle($run, $actor, $method);
                $operation->forceFill(['status' => Operation::STATUS_SUCCEEDED, 'last_error' => null, 'finished_at' => now()])->save();
            } else {
                $challenge = $run->challenges()
                    ->when($method !== null, fn ($query) => $query->where('method', $method))
                    ->latest('id')
                    ->first();
                $result = [
                    'run_id' => (int) $run->id,
                    'challenge_id' => (int) ($challenge?->id ?? 0),
                    'challenge_code' => (string) ($challenge?->challenge_code ?? $run->challenge_code ?? ''),
                    'status' => (string) $run->status,
                    'print_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                        'run' => $run->id,
                        'asset' => $challenge?->sheet_asset_id,
                    ]),
                    'guide_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                        'run' => $run->id,
                        'asset' => $challenge?->guide_asset_id,
                    ]),
                    'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
                ];
            }
        } catch (\Throwable $exception) {
            $operation->forceFill(['status' => Operation::STATUS_FAILED, 'last_error' => $exception->getMessage(), 'finished_at' => now()])->save();
            if (! $exception instanceof \RuntimeException) {
                throw $exception;
            }
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json([
                'outcome' => 'failed',
                'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)),
                'detail' => trim($detail),
            ], 422);
        }

        return response()->json([
            'run_id' => $result['run_id'],
            'challenge_id' => $result['challenge_id'],
            'challenge_code' => $result['challenge_code'],
            'status' => $result['status'],
            'method' => $result['method'] ?? $method,
            'print_url' => $result['print_url'],
            'guide_url' => $result['guide_url'],
            'status_url' => $result['status_url'],
        ], 200);
    }

    public function regeneratePhysicalChallenge(
        RegenerateOmrPhysicalChallengeRequest $request,
        OmrTemplateVerificationRun $run,
        RegenerateOmrPhysicalChallenge $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        $idempotencyKey = (string) $payload['idempotency_key'];
        $operation = $idempotency->claim(
            $actor,
            'omr.canonical.regenerate_challenge',
            (string) $run->id,
            ['run_id' => $run->id],
            $idempotencyKey,
        );
        $shouldExecute = $operation->wasRecentlyCreated || $operation->status !== Operation::STATUS_SUCCEEDED;
        if ($shouldExecute) {
            $operation->forceFill([
                'status' => Operation::STATUS_PROCESSING,
                'attempts' => (int) $operation->attempts + 1,
                'last_error' => null,
                'finished_at' => null,
            ])->save();
        }
        try {
            if ($shouldExecute) {
                $result = $action->handle($run, $actor);
                $operation->forceFill(['status' => Operation::STATUS_SUCCEEDED, 'last_error' => null, 'finished_at' => now()])->save();
            } else {
                $challenge = $run->challenges()->latest('id')->first();
                $result = [
                    'run' => $run->fresh() ?? $run,
                    'challenge' => $challenge,
                    'print_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                        'run' => $run->id,
                        'asset' => $challenge?->sheet_asset_id,
                    ]),
                    'guide_url' => route('setup.omr.templates.canonical.verification_runs.assets.show', [
                        'run' => $run->id,
                        'asset' => $challenge?->guide_asset_id,
                    ]),
                    'regenerated' => false,
                ];
            }
        } catch (\Throwable $exception) {
            $operation->forceFill(['status' => Operation::STATUS_FAILED, 'last_error' => $exception->getMessage(), 'finished_at' => now()])->save();
            if (! $exception instanceof \RuntimeException) {
                throw $exception;
            }
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json([
                'outcome' => 'failed',
                'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code))),
                'detail' => trim($detail),
            ], 422);
        }

        return response()->json([
            'run_id' => $result['run']->id,
            'challenge_id' => $result['challenge']->id,
            'challenge_code' => $result['challenge']->challenge_code,
            'status' => $result['run']->status,
            'print_url' => $result['print_url'],
            'guide_url' => $result['guide_url'],
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $result['run']->id]),
            'regenerated' => $result['regenerated'] ?? true,
        ], 200);
    }

    public function restartPhysicalChallenge(
        RestartOmrPhysicalChallengeRequest $request,
        OmrTemplateVerificationRun $run,
        RestartOmrPhysicalChallenge $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        $idempotencyKey = (string) $payload['idempotency_key'];
        $operation = $idempotency->claim(
            $actor,
            'omr.canonical.restart_challenge',
            (string) $run->id,
            ['run_id' => $run->id],
            $idempotencyKey,
        );
        $shouldExecute = $operation->wasRecentlyCreated || $operation->status !== Operation::STATUS_SUCCEEDED;
        if ($shouldExecute) {
            $operation->forceFill([
                'status' => Operation::STATUS_PROCESSING,
                'attempts' => (int) $operation->attempts + 1,
                'last_error' => null,
                'finished_at' => null,
            ])->save();
        }
        try {
            if ($shouldExecute) {
                $result = $action->handle($run, $actor);
                $resultPayload = [
                    'run_id' => (int) $result['run_id'],
                    'status' => (string) $result['status'],
                    'status_url' => (string) $result['status_url'],
                    'challenge_id' => $result['challenge_id'] ?? null,
                    'challenge_code' => $result['challenge_code'] ?? null,
                    'digital_reverification' => (bool) ($result['digital_reverification'] ?? false),
                    'lock_version' => $result['lock_version'] ?? null,
                ];
                $operation->forceFill([
                    'status' => Operation::STATUS_SUCCEEDED,
                    'last_error' => null,
                    'finished_at' => now(),
                    'payload' => [...(array) $operation->payload, 'result' => $resultPayload],
                ])->save();
            } else {
                $result = is_array($operation->payload['result'] ?? null) ? $operation->payload['result'] : null;
                if ($result === null) {
                    $draft = $run->draft;
                    $activeRun = $draft !== null ? $this->latestVerificationRun($draft) : $run;
                    $activeChallenge = $activeRun?->challenges()->latest('id')->first();
                    if ($activeRun === null || $activeChallenge === null) {
                        return response()->json(['outcome' => 'failed', 'code' => 'idempotency_result_stale', 'detail' => 'The prior restart result is no longer available; restart with a new idempotency key.'], 409);
                    }
                    $result = [
                        'run_id' => (int) $activeRun->id,
                        'status' => (string) $activeRun->status,
                        'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $activeRun->id]),
                        'challenge_id' => null,
                        'challenge_code' => null,
                        'digital_reverification' => true,
                    ];
                }
            }
        } catch (\Throwable $exception) {
            $operation->forceFill(['status' => Operation::STATUS_FAILED, 'last_error' => $exception->getMessage(), 'finished_at' => now()])->save();
            if (! $exception instanceof \RuntimeException) {
                throw $exception;
            }
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json([
                'outcome' => 'failed',
                'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code))),
                'detail' => trim($detail),
                'lock_version' => $run->draft?->fresh()?->lock_version,
            ], 422);
        }

        return response()->json([
            'run_id' => $result['run_id'],
            'status' => $result['status'],
            'status_url' => $result['status_url'],
            'challenge_id' => $result['challenge_id'] ?? null,
            'challenge_code' => $result['challenge_code'] ?? null,
            'digital_reverification' => (bool) ($result['digital_reverification'] ?? false),
            'lock_version' => $result['lock_version'] ?? null,
        ], 200);
    }

    public function markChallengePrinted(
        MarkOmrChallengePrintedRequest $request,
        OmrTemplateVerificationRun $run,
        MarkOmrChallengePrinted $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();

        try {
            $result = $action->handle(
                run: $run,
                actor: $actor,
                methodName: (string) $payload['method'],
                challengeId: (int) $payload['challenge_id'],
                targetHash: isset($payload['target_hash']) ? (string) $payload['target_hash'] : null,
                planHash: isset($payload['plan_hash']) ? (string) $payload['plan_hash'] : null,
                expectedVectorHash: isset($payload['expected_vector_hash']) ? (string) $payload['expected_vector_hash'] : null,
                idempotencyKey: (string) $payload['idempotency_key'],
            );
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));
            $conflictCodes = [
                'idempotency_key_conflict', 'idempotency_in_progress', 'wrong_challenge',
                'challenge_method_mismatch', 'challenge_superseded', 'verification_print_binding_mismatch',
                'verification_challenge_state_invalid', 'verification_run_not_printable', 'stale_target_hash',
                'stale_plan_hash', 'stale_expected_vector_hash', 'verification_method_missing',
            ];

            return response()->json([
                'outcome' => 'failed',
                'code' => $code,
                'detail' => trim($detail),
            ], in_array($code, $conflictCodes, true) ? 409 : 422);
        }

        $printedRun = $result['run'];

        return response()->json([
            'run_id' => $printedRun->id,
            'status' => $printedRun->status,
            'method' => $payload['method'],
            'challenge_id' => (int) $payload['challenge_id'],
            'operation_id' => $result['operation']->id,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $printedRun->id]),
        ], 202);
    }

    public function submitPhysicalCapture(
        SubmitOmrPhysicalCaptureRequest $request,
        OmrTemplateVerificationRun $run,
        SubmitOmrPhysicalCapture $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        $file = $request->file('image');
        if ($file === null) {
            return response()->json(['error' => 'image_required'], 422);
        }
        try {
            $result = $action->handle(
                run: $run,
                actor: $actor,
                bytes: (string) $file->get(),
                mime: (string) $file->getMimeType(),
                captureSource: (string) $payload['capture_source'],
                pageNo: (int) ($payload['page_no'] ?? 1),
                qrPayload: isset($payload['qr_payload']) ? (string) $payload['qr_payload'] : null,
                methodName: isset($payload['method']) ? (string) $payload['method'] : null,
                challengeId: isset($payload['challenge_id']) ? (int) $payload['challenge_id'] : null,
                targetHash: isset($payload['target_hash']) ? (string) $payload['target_hash'] : null,
                planHash: isset($payload['plan_hash']) ? (string) $payload['plan_hash'] : null,
                expectedVectorHash: isset($payload['expected_vector_hash']) ? (string) $payload['expected_vector_hash'] : null,
                medium: array_key_exists('medium', $payload) ? $payload['medium'] : null,
                idempotencyKey: (string) $payload['idempotency_key'],
                autoSelect: ! array_key_exists('method', $payload),
                cropPolicy: isset($payload['crop_policy']) ? (string) $payload['crop_policy'] : 'auto_document_quad',
            );
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));
            $conflictCodes = [
                'idempotency_key_conflict', 'idempotency_in_progress',
                'challenge_method_mismatch', 'challenge_superseded', 'verification_capture_active',
                'challenge_method_binding_mismatch', 'stale_target', 'stale_plan',
                'stale_expected_vector', 'verification_print_binding_mismatch',
                'verification_capture_retake_required',
            ];

            $detailMessage = trim($detail);
            if ($code === 'verification_capture_retake_required' && $detailMessage === 'verification_capture_retake_required') {
                $detailMessage = 'This page already has analyzed evidence. Start a retake before uploading another image';
            }

            return response()->json([
                'outcome' => 'failed',
                'code' => $code,
                'detail' => $detailMessage,
                'page_no' => (int) ($payload['page_no'] ?? 1),
            ], in_array($code, $conflictCodes, true) ? 409 : 422);
        }

        return response()->json([
            'run_id' => $result['run']->id,
            'capture_id' => $result['capture']->id,
            'status' => $result['run']->status,
            'method' => $payload['method'],
            'challenge_id' => (int) $payload['challenge_id'],
            'operation_id' => $result['operation']->id,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $result['run']->id]),
        ], 201);
    }

    public function reviewPhysicalCapture(
        ReviewOmrPhysicalCaptureRequest $request,
        OmrTemplateVerificationRun $run,
        ReviewOmrPhysicalCapture $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        try {
            $reviewed = $action->handle(
                run: $run,
                actor: $actor,
                passed: $payload['decision'] === 'pass',
                note: $payload['note'] ?? null,
                captureId: isset($payload['capture_id']) ? (int) $payload['capture_id'] : null,
                idempotencyKey: (string) $payload['idempotency_key'],
            );
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));

            return response()->json([
                'outcome' => 'failed',
                'code' => $code,
                'detail' => trim($detail),
            ], 409);
        }

        return response()->json([
            'run_id' => $reviewed->id,
            'status' => $reviewed->status,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $reviewed->id]),
        ]);
    }

    public function retakePhysicalPage(
        RetakeOmrPhysicalPageRequest $request,
        OmrTemplateVerificationRun $run,
        string $method,
        int $page,
        RetakeOmrPhysicalPage $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        try {
            $retaken = $action->handle(
                run: $run,
                actor: $actor,
                method: $method,
                pageNo: $page,
                pageVersion: (int) $payload['page_version'],
                idempotencyKey: (string) $payload['idempotency_key'],
            );
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json([
                'outcome' => 'failed',
                'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code))),
                'detail' => trim($detail),
            ], 409);
        }

        return response()->json([
            'run_id' => (int) $run->id,
            'status' => $run->fresh()->status,
            'method' => $method,
            'page_no' => (int) $retaken->page_no,
            'page_state' => $retaken->page_state,
            'page_version' => (int) $retaken->page_version,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
        ]);
    }

    public function discardPhysicalAttempt(
        DiscardOmrPhysicalAttemptRequest $request,
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        DiscardOmrPhysicalAttempt $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();

        try {
            $receipt = $action->handle($run, $capture, $actor, $payload);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));
            $conflictCodes = [
                'discard_selected_attempt', 'discard_attempt_active', 'discard_stale',
                'discard_boundary_mismatch', 'stale_target', 'stale_plan',
                'stale_expected_vector', 'idempotency_key_conflict',
                'idempotency_in_progress',
            ];

            return response()->json([
                'outcome' => 'failed',
                'code' => $code,
                'detail' => trim($detail),
            ], in_array($code, $conflictCodes, true) ? 409 : 422);
        }

        return response()->json($receipt, 200);
    }

    public function selectPhysicalAttempt(
        SelectOmrPhysicalAttemptRequest $request,
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationCapture $capture,
        SelectOmrPhysicalAttempt $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $payload = $request->validated();
        try {
            $page = $action->handle(
                run: $run,
                actor: $actor,
                method: (string) $payload['method'],
                pageNo: (int) $payload['page_no'],
                captureId: (int) $capture->id,
                reason: (string) ($payload['reason'] ?? ''),
                targetHash: $payload['target_hash'] ?? null,
                planHash: $payload['plan_hash'] ?? null,
                expectedVectorHash: $payload['expected_vector_hash'] ?? null,
                expectedPageVersion: isset($payload['page_version']) ? (int) $payload['page_version'] : null,
                idempotencyKey: (string) $payload['idempotency_key'],
            );
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());
            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($code)));
            $conflictCodes = [
                'idempotency_key_conflict', 'idempotency_in_progress', 'selection_boundary_mismatch',
                'selection_attempt_not_passed', 'selection_stale', 'selection_existing_pointer_invalid',
                'verification_capture_exact_recovery_required',
                'replacement_reason_required', 'stale_target', 'stale_plan', 'stale_expected_vector',
                'verification_method_missing', 'verification_page_invalid',
            ];

            return response()->json([
                'outcome' => 'failed',
                'code' => $code,
                'detail' => trim($detail),
            ], in_array($code, $conflictCodes, true) ? 409 : 422);
        }

        return response()->json([
            'run_id' => $run->id,
            'method' => $payload['method'],
            'page_no' => $page->page_no,
            'selected_attempt_id' => $page->selected_attempt_id,
            'page_state' => $page->page_state,
            'page_version' => $page->page_version,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
        ]);
    }

    /** @return array{0: string, 1: string, 2: int, 3: bool} */
    private function physicalChallengeFailure(\Throwable $exception): array
    {
        if ($exception instanceof OmrRenderException) {
            return [OmrRenderException::ERROR_CODE, 'The OMR renderer is temporarily unavailable.', 503, true];
        }

        if ($exception instanceof OmrAuthoringDraftException) {
            $detail = $exception->getMessage();
            $prefix = $exception->errorCode.': ';
            if (str_starts_with($detail, $prefix)) {
                $detail = substr($detail, strlen($prefix));
            }

            $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($exception->errorCode)) ?: 'challenge_failed';

            return [$code, $this->boundedPhysicalChallengeDetail($detail), 422, true];
        }

        return ['challenge_failed', 'The physical challenge could not be created.', 422, $exception instanceof \RuntimeException];
    }

    private function boundedPhysicalChallengeDetail(string $detail): string
    {
        $detail = trim($detail);

        return strlen($detail) > 240 ? substr($detail, 0, 237).'...' : $detail;
    }

    private function failPhysicalChallengeOperation(Operation $operation, string $code, string $detail): void
    {
        $code = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)) ?: 'challenge_failed';
        $detail = $this->boundedPhysicalChallengeDetail($detail);

        $operation->forceFill([
            'status' => Operation::STATUS_FAILED,
            'last_error' => $code.': '.$detail,
            'finished_at' => now(),
        ])->save();
    }

    private function activeDigitalRun(OmrTemplateAuthoringDraft $draft): ?OmrTemplateVerificationRun
    {
        return OmrTemplateVerificationRun::query()
            ->where('draft_id', (int) $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
            ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    /** @param array{run: OmrTemplateVerificationRun, challenge: OmrPhysicalChallenge, print_url: string, guide_url: string} $result */
    private function challengeResponse(array $result, Operation $operation): array
    {
        $run = $result['run'];
        $challenge = $result['challenge']->loadMissing(['sheetAsset', 'guideAsset']);
        $printMime = $challenge->sheetAsset?->mime;
        $guideMime = $challenge->guideAsset?->mime;
        $availableActions = match ($run->status) {
            OmrTemplateVerificationRun::STATUS_AWAITING_PRINT => $printMime === 'application/pdf'
                ? ['print_challenge']
                : ['prepare_print', 'print_challenge'],
            OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE => ['submit_capture'],
            OmrTemplateVerificationRun::STATUS_NEEDS_REVIEW => ['review_capture'],
            OmrTemplateVerificationRun::STATUS_FAILED => ['retry'],
            default => [],
        };
        $statusUrl = route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]);
        $sheetPages = app(OmrTemplateVerificationStatus::class)->sheetPages($run, $challenge);
        $challengePayload = [
            'challenge_id' => (int) $challenge->id,
            'method' => (string) $challenge->method,
            'challenge_code' => (string) $challenge->challenge_code,
            'mode' => (string) $challenge->mode,
            'status' => (string) $challenge->status,
            'plan_hash' => (string) $challenge->plan_hash,
            'expected_vector_hash' => (string) $challenge->expected_vector_hash,
            'created_at' => $challenge->created_at?->toIso8601String(),
            'print_url' => $result['print_url'],
            'print_mime' => $printMime,
            'pdf_ready' => $printMime === 'application/pdf',
            'guide_url' => $result['guide_url'],
            'guide_pdf_url' => $guideMime === 'application/pdf' ? $result['guide_url'] : null,
            'guide' => is_array($challenge->guide_json) ? $challenge->guide_json : null,
            'sheet_pages' => $sheetPages,
        ];

        return [
            'run_id' => (int) $run->id,
            'challenge_id' => (int) $challenge->id,
            'challenge_code' => (string) $challenge->challenge_code,
            'status' => (string) $run->status,
            'mode' => (string) $run->mode,
            'available_actions' => $availableActions,
            'print_url' => $result['print_url'],
            'print_mime' => $printMime,
            'pdf_ready' => $printMime === 'application/pdf',
            'guide_url' => $result['guide_url'],
            'guide_pdf_url' => $guideMime === 'application/pdf' ? $result['guide_url'] : null,
            'sheet_pages' => $sheetPages,
            'status_url' => $statusUrl,
            'challenge' => $challengePayload,
            'operation_id' => $operation->id,
        ];
    }

    /** @return array{run: OmrTemplateVerificationRun, challenge: OmrPhysicalChallenge, print_url: string, guide_url: string}|null */
    private function challengeResultFromOperation(
        Operation $operation,
        OmrTemplateAuthoringDraft $draft,
        string $mode,
        string $method = OmrPhysicalChallenge::METHOD_PRE_SHADED,
    ): ?array {
        $operationPayload = is_array($operation->payload) ? $operation->payload : [];
        $saved = is_array($operationPayload['result'] ?? null) ? $operationPayload['result'] : [];
        if (($saved['mode'] ?? null) !== $mode || (($saved['method'] ?? $method) !== $method)) {
            return null;
        }
        $runId = (int) ($saved['run_id'] ?? 0);
        $challengeId = (int) ($saved['challenge_id'] ?? 0);
        if ($runId <= 0 || $challengeId <= 0) {
            return null;
        }

        $targetKind = (string) ($saved['target_kind'] ?? '');
        $targetId = (int) ($saved['target_id'] ?? 0);
        if (! in_array($targetKind, ['draft', 'version'], true) || $targetId <= 0
            || ($targetKind === 'draft' && $targetId !== (int) $draft->id)) {
            return null;
        }

        $runQuery = OmrTemplateVerificationRun::query()
            ->whereKey($runId)
            ->where('mode', $this->runModeForChallengeMode($mode))
            ->where(fn ($query) => $query->whereNull('error_code')->orWhere('error_code', '!=', 'target_changed_superseded'));
        if ($targetKind === 'draft') {
            $runQuery->where('draft_id', $targetId);
        } else {
            $runQuery->where('template_version_id', $targetId);
        }
        $run = $runQuery->first();
        $challenge = $run?->challenges()
            ->with(['sheetAsset', 'guideAsset'])
            ->whereKey($challengeId)
            ->where('mode', $mode)
            ->where('status', '!=', OmrPhysicalChallenge::STATUS_SUPERSEDED)
            ->first();
        if ($run === null || $challenge === null) {
            return null;
        }

        return [
            'run' => $run,
            'challenge' => $challenge,
            'print_url' => $challenge->sheet_asset_id !== null
                ? route('setup.omr.templates.canonical.verification_runs.assets.show', ['run' => $run->id, 'asset' => $challenge->sheet_asset_id])
                : null,
            'guide_url' => $challenge->guide_asset_id !== null
                ? route('setup.omr.templates.canonical.verification_runs.assets.show', ['run' => $run->id, 'asset' => $challenge->guide_asset_id])
                : null,
        ];
    }

    private function runModeForChallengeMode(string $mode): string
    {
        return $mode === OmrPhysicalChallenge::MODE_FULL
            ? OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION
            : OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL;
    }

    /** @return array{run: OmrTemplateVerificationRun, challenge: OmrPhysicalChallenge, print_url: ?string, guide_url: ?string}|null */
    private function latestPhysicalChallenge(
        OmrTemplateAuthoringDraft $draft,
        ?string $mode = null,
        string $method = OmrPhysicalChallenge::METHOD_PRE_SHADED,
    ): ?array {
        $runQuery = OmrTemplateVerificationRun::query()
            ->when($mode !== null, fn ($query) => $query->where('mode', $this->runModeForChallengeMode($mode)))
            ->where(fn ($query) => $query->whereNull('error_code')->orWhere('error_code', '!=', 'target_changed_superseded'))
            ->whereHas('challenges', fn ($query) => $query
                ->when($mode !== null, fn ($q) => $q->where('mode', $mode))
                ->where('method', $method)
                ->where('status', '!=', OmrPhysicalChallenge::STATUS_SUPERSEDED));
        if ($draft->compiled_version_id !== null) {
            $runQuery->where('template_version_id', (int) $draft->compiled_version_id);
        } else {
            $runQuery->where('draft_id', (int) $draft->id);
        }
        $run = $runQuery->latest('id')->first();
        if ($run === null && $mode !== null) {
            return $this->latestPhysicalChallenge($draft, null, $method);
        }
        $challenge = $run?->challenges()
            ->with(['sheetAsset', 'guideAsset'])
            ->when($mode !== null, fn ($q) => $q->where('mode', $mode))
            ->where('method', $method)
            ->where('status', '!=', OmrPhysicalChallenge::STATUS_SUPERSEDED)
            ->latest('id')
            ->first();
        if ($run === null || $challenge === null) {
            return null;
        }

        return [
            'run' => $run,
            'challenge' => $challenge,
            'print_url' => $challenge->sheet_asset_id !== null
                ? route('setup.omr.templates.canonical.verification_runs.assets.show', ['run' => $run->id, 'asset' => $challenge->sheet_asset_id])
                : null,
            'guide_url' => $challenge->guide_asset_id !== null
                ? route('setup.omr.templates.canonical.verification_runs.assets.show', ['run' => $run->id, 'asset' => $challenge->guide_asset_id])
                : null,
        ];
    }

    public function blankInstances(
        Request $request,
        OmrAnswerSheetTemplateVersion $templateVersion,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $scope = $this->omrScopeContext($actor, $authorizer);
        abort_unless($scope !== null, 403);

        $instances = $templateVersion->blankInstances()
            ->where('institution_id', $scope['institution_id'])
            ->where('academic_cycle_id', $scope['academic_cycle_id'])
            ->with(['pages.claims', 'pages.reproductionAuthorizations'])
            ->orderByDesc('page_set_no')
            ->limit(100)
            ->get()
            ->map(fn (OmrBlankInstance $instance): array => [
                'id' => $instance->id,
                'display_code' => $instance->display_code,
                'page_set_no' => (int) $instance->page_set_no,
                'page_count' => (int) $instance->page_count,
                'status' => $instance->status,
                'copy_policy' => $instance->copy_policy,
                'intended_copy_count' => (int) $instance->intended_copy_count,
                'printed_at' => $instance->printed_at?->toIso8601String(),
                'pages' => $instance->pages->sortBy('page_no')->map(fn ($page): array => [
                    'id' => $page->id,
                    'page_no' => (int) $page->page_no,
                    'status' => $page->status,
                    'claim_count' => $page->claims->count(),
                    'active_authorization_count' => $page->reproductionAuthorizations
                        ->where('status', 'open')
                        ->filter(fn ($authorization): bool => $authorization->expires_at?->isFuture() === true)
                        ->count(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
        $qrDisabledIssuances = OmrQrDisabledIssuance::query()
            ->with('printJob')
            ->where('template_version_id', $templateVersion->id)
            ->where('institution_id', $scope['institution_id'])
            ->where('academic_cycle_id', $scope['academic_cycle_id'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (OmrQrDisabledIssuance $issuance): array => [
                'id' => $issuance->id,
                'issuance_code' => $issuance->issuance_code,
                'status' => $issuance->status,
                'page_count' => (int) $issuance->page_count,
                'copy_policy' => $issuance->copy_policy,
                'intended_copy_count' => (int) $issuance->intended_copy_count,
                'artifact_manifest_hash' => $issuance->artifact_manifest_hash,
                'failure_code' => $issuance->failure_code,
                'created_at' => $issuance->created_at?->toIso8601String(),
                'printed_at' => $issuance->printed_at?->toIso8601String(),
                'failed_at' => $issuance->failed_at?->toIso8601String(),
                'print_job_id' => $issuance->print_job_id,
                'print_job_status' => $issuance->printJob?->status,
                'download_sha256' => $issuance->printJob?->file_checksum,
            ])
            ->values()
            ->all();

        return response()->json([
            'qr_mode' => $templateVersion->qr_mode->value,
            'instances' => $instances,
            'qr_disabled_issuances' => $qrDisabledIssuances,
        ]);
    }

    public function supersedeBlankInstance(
        SupersedeOmrBlankInstanceRequest $request,
        OmrAnswerSheetTemplateVersion $templateVersion,
        OmrBlankInstance $instance,
        SupersedeOmrBlankInstanceSet $action,
        CommandIdempotency $idempotency,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        abort_unless((int) $instance->template_version_id === (int) $templateVersion->id, 404);
        /** @var User $actor */
        $actor = $request->user();
        $this->assertOmrInstanceScope($actor, $instance, $authorizer);
        $payload = $request->validated();
        $replacement = OmrBlankInstance::query()->findOrFail($payload['replacement_instance_id']);
        abort_unless((int) $replacement->template_version_id === (int) $templateVersion->id, 404);
        $this->assertOmrInstanceScope($actor, $replacement, $authorizer);
        $operation = $idempotency->claim($actor, 'omr.blank_instance.supersede', (string) $instance->id, [
            'instance_id' => $instance->id,
            'replacement_instance_id' => $replacement->id,
        ], (string) $payload['idempotency_key']);
        $saved = $operation->wasRecentlyCreated ? $action->handle($instance, $replacement, $actor) : $instance->fresh();

        return response()->json(['status' => $saved->status, 'operation_id' => $operation->id]);
    }

    public function voidBlankInstance(
        VoidOmrBlankInstanceRequest $request,
        OmrAnswerSheetTemplateVersion $templateVersion,
        OmrBlankInstance $instance,
        VoidOmrBlankInstanceSet $action,
        CommandIdempotency $idempotency,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        abort_unless((int) $instance->template_version_id === (int) $templateVersion->id, 404);
        /** @var User $actor */
        $actor = $request->user();
        $this->assertOmrInstanceScope($actor, $instance, $authorizer);
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.blank_instance.void', (string) $instance->id, ['instance_id' => $instance->id], (string) $payload['idempotency_key']);
        $saved = $operation->wasRecentlyCreated ? $action->handle($instance, $actor) : $instance->fresh();

        return response()->json(['status' => $saved->status, 'operation_id' => $operation->id]);
    }

    public function calibrationArtifact(
        GenerateOmrMarkerCalibrationArtifactRequest $request,
        OmrMarkerProfile $profile,
        GenerateOmrMarkerCalibrationArtifact $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim(
            $actor,
            'omr.marker_calibration_artifact',
            (string) $profile->id,
            ['profile_id' => $profile->id, ...$payload],
            (string) $payload['idempotency_key'],
        );

        if ($operation->wasRecentlyCreated) {
            unset($payload['idempotency_key']);
            $action->handle($profile, $payload, $actor);
        }

        return response()->json([
            'status' => 'queued',
            'operation_id' => $operation->id,
            'marker_profile_id' => $profile->id,
        ], 202);
    }

    public function qualifyMarkerProfile(
        QualifyOmrMarkerProfileRequest $request,
        OmrMarkerProfile $profile,
        QualifyOmrMarkerProfile $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim(
            $actor,
            'omr.marker_profile_qualify',
            (string) $profile->id,
            ['profile_id' => $profile->id, ...$payload],
            (string) $payload['idempotency_key'],
        );
        if ($operation->wasRecentlyCreated) {
            unset($payload['idempotency_key']);
            $qualified = $action->handle($profile, $payload, $actor);
        } else {
            $qualified = $profile->fresh();
        }

        return response()->json([
            'status' => $qualified->status,
            'marker_profile_id' => $qualified->id,
            'operation_id' => $operation->id,
        ]);
    }

    /**
     * @return array{institution_id: int, academic_cycle_id: int, cycle_code: string}|null
     */
    private function omrScopeContext(
        User $actor,
        CapabilityAuthorizer $authorizer,
        ?int $institutionId = null,
        ?int $academicCycleId = null,
    ): ?array {
        if (($institutionId === null) !== ($academicCycleId === null)) {
            return null;
        }

        if ($institutionId === null) {
            $actorScope = $actor->actorScope();
            if ($actorScope->office !== 'guidance'
                || $actorScope->institutionId === null
                || trim((string) $actorScope->activeCycleCode) === '') {
                return null;
            }

            $cycle = AcademicCycle::query()
                ->where('institution_id', $actorScope->institutionId)
                ->where('code', trim((string) $actorScope->activeCycleCode))
                ->first();
        } else {
            $cycle = AcademicCycle::query()
                ->whereKey($academicCycleId)
                ->where('institution_id', $institutionId)
                ->first();
        }

        if ($cycle === null) {
            return null;
        }

        $context = [
            'office' => 'guidance',
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => (string) $cycle->code,
        ];
        if (! $authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, $context)->allowed) {
            return null;
        }

        return [
            'institution_id' => (int) $cycle->institution_id,
            'academic_cycle_id' => (int) $cycle->id,
            'cycle_code' => (string) $cycle->code,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    private function kitContextForDraft(?array $context, OmrTemplateAuthoringDraft $draft): ?array
    {
        if ($context === null) {
            return null;
        }

        if ($context['read_only']) {
            return (int) $draft->compiled_version_id === (int) ($context['template_version_id'] ?? 0)
                ? $context
                : null;
        }

        $selectedDraftId = $context['answer_sheet_draft_id'];

        return $selectedDraftId === null || (int) $selectedDraftId === (int) $draft->getKey()
            ? $context
            : null;
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, int>
     */
    private function kitContextRouteParameters(?array $context): array
    {
        if ($context === null) {
            return [];
        }

        $parameters = ['test_kit_id' => (int) $context['test_kit_id']];
        if ($context['preparation_id'] !== null) {
            $parameters['preparation_id'] = (int) $context['preparation_id'];
        }
        if ($context['test_kit_version_id'] !== null) {
            $parameters['test_kit_version_id'] = (int) $context['test_kit_version_id'];
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function withKitContext(string $url, ?array $context): string
    {
        if ($context === null) {
            return $url;
        }

        $parameters = $this->kitContextRouteParameters($context);

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($parameters);
    }

    private function assertOmrInstanceScope(
        User $actor,
        OmrBlankInstance $instance,
        CapabilityAuthorizer $authorizer,
    ): void {
        abort_unless(
            $this->omrScopeContext(
                $actor,
                $authorizer,
                (int) $instance->institution_id,
                (int) $instance->academic_cycle_id,
            ) !== null,
            403,
        );
    }
}
