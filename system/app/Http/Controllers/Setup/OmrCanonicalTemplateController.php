<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Actions\Omr\Canonical\ActivateOmrCanonicalTemplate;
use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\ComposeOmrCanonicalPages;
use App\Assessment\Actions\Omr\Canonical\ConfirmOmrSourcePages;
use App\Assessment\Actions\Omr\Canonical\CreateOmrAuthoringDraft;
use App\Assessment\Actions\Omr\Canonical\GenerateOmrMarkerCalibrationArtifact;
use App\Assessment\Actions\Omr\Canonical\IngestOmrSourceDocument;
use App\Assessment\Actions\Omr\Canonical\PrintOmrBlankInstances;
use App\Assessment\Actions\Omr\Canonical\QualifyOmrMarkerProfile;
use App\Assessment\Actions\Omr\Canonical\RunOmrPhysicalQualification;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalMapping;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalOverlay;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\PreviewOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\SelectOmrMarkerProfile;
use App\Assessment\Actions\Omr\Canonical\SupersedeOmrBlankInstanceSet;
use App\Assessment\Actions\Omr\Canonical\VoidOmrBlankInstanceSet;
use App\Assessment\Actions\Omr\Verification\RetryOmrTemplateVerification;
use App\Assessment\Actions\Omr\Verification\StartOmrTemplateVerification;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection;
use App\Assessment\Actions\Omr\Canonical\RectifyOmrSourcePage;
use App\Assessment\Omr\NativeBundle;
use App\Http\Controllers\Controller;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\Operation;
use App\Http\Requests\Omr\Canonical\CancelOmrSourceRunRequest;
use App\Http\Requests\Omr\Canonical\CompileOmrCanonicalDraftRequest;
use App\Http\Requests\Omr\Canonical\ComposeOmrCanonicalRequest;
use App\Http\Requests\Omr\Canonical\ConfirmOmrSourcePagesRequest;
use App\Http\Requests\Omr\Canonical\CreateOmrAuthoringDraftRequest;
use App\Http\Requests\Omr\Canonical\GenerateOmrMarkerCalibrationArtifactRequest;
use App\Http\Requests\Omr\Canonical\IngestOmrSourceRequest;
use App\Http\Requests\Omr\Canonical\QualifyOmrMarkerProfileRequest;
use App\Http\Requests\Omr\Canonical\RunOmrPhysicalQualificationRequest;
use App\Http\Requests\Omr\Canonical\SaveOmrMappingRequest;
use App\Http\Requests\Omr\Canonical\SaveOmrOverlayRequest;
use App\Http\Requests\Omr\Canonical\SaveOmrStudioStateRequest;
use App\Http\Requests\Omr\Canonical\MaterializeOmrCanonicalDraftRequest;
use App\Http\Requests\Omr\Canonical\RectifyOmrSourcePageRequest;
use App\Http\Requests\Omr\Canonical\SelectOmrMarkerProfileRequest;
use App\Http\Requests\Omr\Canonical\SupersedeOmrBlankInstanceRequest;
use App\Http\Requests\Omr\Canonical\VoidOmrBlankInstanceRequest;
use App\Http\Requests\Omr\Canonical\PrintOmrBlankInstancesRequest;
use App\Http\Requests\Omr\Canonical\RetryOmrTemplateVerificationRequest;
use App\Http\Requests\Omr\Canonical\StartOmrTemplateVerificationRequest;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\UploadedFile;

class OmrCanonicalTemplateController extends Controller
{
    public function index(Request $request, OmrCanonicalWorkflowProjection $projection): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Setup/OmrConfiguration/Canonical/Index', $projection->indexProps($actor));
    }

    public function studioDirect(
        Request $request,
        OmrCanonicalWorkflowProjection $projection,
        ?OmrTemplateAuthoringDraft $draft = null,
    ): Response|\Illuminate\Http\RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        if ($draft !== null) {
            abort_unless((int) $draft->owner_user_id === (int) $actor->id, 404);
        }

        if ($draft === null) {
            $draft = OmrTemplateAuthoringDraft::query()
                ->where('owner_user_id', $actor->id)
                ->orderByDesc('id')
                ->first();
        }

        if ($draft !== null) {
            return Inertia::render('Setup/OmrConfiguration/Canonical/CanonicalWorkflow', $projection->workflowProps($draft, $actor));
        }

        return redirect()->route('setup.omr.templates.canonical.index');
    }

    public function show(
        Request $request,
        OmrTemplateAuthoringDraft $draft,
        OmrCanonicalWorkflowProjection $projection,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless((int) $draft->owner_user_id === (int) $actor->id, 404);

        return Inertia::render('Setup/OmrConfiguration/Canonical/CanonicalWorkflow', $projection->workflowProps($draft, $actor));
    }

    public function asset(Request $request, OmrTemplateAuthoringDraft $draft, OmrTemplateAsset $asset): StreamedResponse
    {
        abort_unless((int) $draft->owner_user_id === (int) $request->user()?->getAuthIdentifier(), 404);
        abort_unless((int) $asset->authoring_draft_id === (int) $draft->id, 404);

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
        RecordAuditEvent $recordAuditEvent,
    ): RedirectResponse {
        abort_unless((int) $draft->owner_user_id === (int) $request->user()?->getAuthIdentifier(), 404);
        abort_if($draft->compiled_version_id !== null, 409, 'Compiled drafts are immutable.');
        $draftId = $draft->id;
        $recordAuditEvent->handle('omr.canonical_draft_abandoned', ['draft_id' => $draftId], (string) $request->user()?->getAuthIdentifier());
        $draft->delete();

        return redirect()->route('setup.omr.templates.canonical.index')->with('status', 'Canonical draft abandoned.');
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
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $draft = $action->handle($actor, $payload['code'], $payload['name'], $payload['source_kind']);

        return redirect()
            ->route('setup.omr.templates.canonical.drafts.show', $draft)
            ->with('status', 'Canonical authoring draft created.');
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
        $operation = $action->handle(
            $draft,
            $actor,
            $source,
            $payload['source_kind'],
            $payload['idempotency_key'],
            $options,
        );

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
        $operation = $action->handle(
            $draft,
            $actor,
            $source,
            $payload['points'],
            (int) $payload['target_width'],
            (int) $payload['target_height'],
            (string) $payload['idempotency_key'],
        );

        return response()->json(['status' => 'queued', 'operation_id' => $operation->id, 'draft_id' => $draft->id], 202);
    }

    public function sourceRun(OmrTemplateAuthoringDraft $draft): JsonResponse
    {
        return response()->json(['run' => $draft->authoring_json['source_run'] ?? null]);
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
        $saved = $action->handle($draft, $actor, $request->validated()['pages']);

        return response()->json(['status' => $saved->stage, 'draft_id' => $saved->id, 'lock_version' => $saved->lock_version, 'pages' => $saved->authoring_json['pages'] ?? []]);
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
        $saved = $action->handle($draft, $actor, $payload);
        $composition = (array) ($saved->authoring_json['canonical'] ?? []);
        $rasterAssetId = (int) ($composition['raster_assets'][0]['asset_id'] ?? 0);

        return response()->json([
            'status' => 'ready',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
            'raster_url' => $rasterAssetId > 0
                ? route('setup.omr.templates.canonical.drafts.assets.show', [$saved, $rasterAssetId])
                : null,
            'composition' => $composition,
        ]);
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
        $saved = $action->handle($draft, $actor, $payload['mapping'], (int) $payload['lock_version']);

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
        } catch (\RuntimeException $exception) {
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
            ], 422);
        }

        return response()->json([
            'status' => 'saved',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
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

            return response()->json(['outcome' => 'failed', 'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)), 'detail' => $detail], 422);
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
        try {
            $preview = $action->handle($draft, $actor, (int) $request->validated()['lock_version']);
        } catch (\RuntimeException $exception) {
            [$code, $detail] = array_pad(explode(':', $exception->getMessage(), 2), 2, $exception->getMessage());

            return response()->json(['outcome' => 'failed', 'code' => preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)), 'detail' => $detail], 422);
        }
        $result = $preview['result'];
        if (! $result->succeeded()) {
            return response()->json([
                'outcome' => 'failed',
                'code' => $result->code,
                'detail' => $result->detail,
            ], 422);
        }
        $token = (string) \Illuminate\Support\Str::uuid();
        $directory = 'omr-diagnostics/'.$token;
        $disk = Storage::disk('local');
        $disk->put($directory.'/aligned.png', (string) $result->alignedBytes);
        $disk->put($directory.'/layout-overlay.png', (string) $result->overlayBytes);
        $saved = $preview['draft'];
        $artifact = $preview['artifact'];
        $rasterAssetId = (int) (($saved->authoring_json['canonical']['artifact_set']['raster_assets'][0]['asset_id'] ?? 0));

        return response()->json([
            'outcome' => 'succeeded',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
            'artifact' => [
                'artifact_hash' => $artifact->artifactHash,
                'definition_hash' => $artifact->definition['source_hash'],
                'template_hash' => $artifact->templateHash,
                'config_hash' => $artifact->configHash,
                'review_manifest_hash' => $artifact->reviewManifestHash,
                'canonical_url' => $rasterAssetId > 0 ? route('setup.omr.templates.canonical.drafts.assets.show', [$saved, $rasterAssetId]) : null,
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
            'mapping_guides' => $this->mappingGuides($artifact->template, (array) ($artifact->definition['insets_px'] ?? [])),
        ]);
    }

    /** @return list<array{id:string,x_mm:float,y_mm:float,w_mm:float,h_mm:float,bubbles:list<array{x_mm:float,y_mm:float,w_mm:float,h_mm:float}>}> */
    private function mappingGuides(array $template, array $insetsPx): array
    {
        $guides = [];
        $toMm = static fn (int $pixels): float => round($pixels * 25.4 / 300, 3);
        foreach ((array) (($template['pages'][0]['fieldBlocks'] ?? [])) as $id => $block) {
            if (! is_array($block)) {
                continue;
            }
            $origin = (array) ($block['origin'] ?? [0, 0]);
            $bubble = (array) ($block['bubbleDimensions'] ?? [0, 0]);
            $labels = [];
            foreach ((array) ($block['fieldLabels'] ?? []) as $fieldLabel) {
                $labels = [...$labels, ...NativeBundle::expandLabel((string) $fieldLabel)];
            }
            $values = match ((string) ($block['fieldType'] ?? '')) {
                'QTYPE_INT' => 10,
                'QTYPE_MCQ5' => 5,
                'QTYPE_TF' => 2,
                default => 4,
            };
            $vertical = (string) ($block['direction'] ?? 'horizontal') === 'vertical';
            $bubblesGap = (int) ($block['bubblesGap'] ?? 0);
            $labelsGap = (int) ($block['labelsGap'] ?? 0);
            $width = $vertical
                ? (int) ($bubble[0] ?? 0) + max(0, count($labels) - 1) * $labelsGap
                : (int) ($bubble[0] ?? 0) + max(0, $values - 1) * $bubblesGap;
            $height = $vertical
                ? (int) ($bubble[1] ?? 0) + max(0, $values - 1) * $bubblesGap
                : (int) ($bubble[1] ?? 0) + max(0, count($labels) - 1) * $labelsGap;
            $paperOriginX = (int) ($origin[0] ?? 0) + (int) ($insetsPx['left'] ?? 0);
            $paperOriginY = (int) ($origin[1] ?? 0) + (int) ($insetsPx['top'] ?? 0);
            $bubbleGuides = [];
            foreach (array_keys($labels) as $labelIndex) {
                for ($valueIndex = 0; $valueIndex < $values; $valueIndex++) {
                    $x = $paperOriginX + ($vertical ? $labelIndex * $labelsGap : $valueIndex * $bubblesGap);
                    $y = $paperOriginY + ($vertical ? $valueIndex * $bubblesGap : $labelIndex * $labelsGap);
                    $bubbleGuides[] = [
                        'x_mm' => $toMm($x),
                        'y_mm' => $toMm($y),
                        'w_mm' => $toMm((int) ($bubble[0] ?? 0)),
                        'h_mm' => $toMm((int) ($bubble[1] ?? 0)),
                    ];
                }
            }
            $guides[] = [
                'id' => (string) $id,
                'x_mm' => $toMm($paperOriginX),
                'y_mm' => $toMm($paperOriginY),
                'w_mm' => $toMm($width),
                'h_mm' => $toMm($height),
                'bubbles' => $bubbleGuides,
            ];
        }

        return $guides;
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
            $run = $shouldExecute ? $action->handle($draft, $actor) : $this->latestVerificationRun($draft);
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

    public function retryVerificationRun(
        RetryOmrTemplateVerificationRequest $request,
        OmrTemplateVerificationRun $run,
        RetryOmrTemplateVerification $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $retried = $action->handle($run, $actor);

        return response()->json([
            'run_id' => $retried->id,
            'status' => $retried->status,
            'attempt' => $retried->attempt,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $retried->id]),
        ], 202);
    }

    public function verificationRunAsset(
        Request $request,
        OmrTemplateVerificationRun $run,
        OmrTemplateAsset $asset,
    ): StreamedResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->assertVerificationRunAccessible($run, $actor);
        $captureReferenced = OmrTemplateVerificationCapture::query()
            ->where('run_id', $run->id)
            ->where(fn ($query) => $query->where('input_asset_id', $asset->id)
                ->orWhere('aligned_asset_id', $asset->id)
                ->orWhere('overlay_asset_id', $asset->id))
            ->exists();
        $challengeReferenced = OmrPhysicalChallenge::query()
            ->where('run_id', $run->id)
            ->where(fn ($query) => $query->where('sheet_asset_id', $asset->id)->orWhere('guide_asset_id', $asset->id))
            ->exists();
        $runOwned = is_array($asset->metadata) && (int) ($asset->metadata['run_id'] ?? 0) === (int) $run->id;
        abort_unless($captureReferenced || $challengeReferenced || $runOwned, 404);
        $bytes = Storage::disk($asset->disk)->get($asset->path);
        abort_unless(is_string($bytes) && $bytes !== '', 404);

        $response = response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, basename((string) $asset->path), [
            'Content-Type' => $asset->mime,
            'Content-Length' => strlen($bytes),
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function latestVerificationRun(OmrTemplateAuthoringDraft $draft): ?OmrTemplateVerificationRun
    {
        return OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->latest('id')->first();
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
        OmrAnswerSheetTemplateVersion $templateVersion,
        RunOmrPhysicalQualification $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.canonical.physical_qualification', (string) $templateVersion->id, ['template_version_id' => $templateVersion->id, ...$payload], (string) $payload['idempotency_key']);
        $version = $operation->wasRecentlyCreated
            ? $action->handle($templateVersion, $actor, array_diff_key($payload, ['idempotency_key' => true]))
            : $templateVersion->fresh();

        return response()->json(['status' => $version->authoring_manifest['physical_qualification']['status'] ?? 'pending', 'operation_id' => $operation->id]);
    }

    public function activate(
        ActivateOmrCanonicalTemplateRequest $request,
        OmrAnswerSheetTemplateVersion $templateVersion,
        ActivateOmrCanonicalTemplate $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.canonical.activate', (string) $templateVersion->id, ['template_version_id' => $templateVersion->id], (string) $payload['idempotency_key']);
        $version = $operation->wasRecentlyCreated ? $action->handle($templateVersion, $actor) : $templateVersion->fresh();

        return response()->json(['status' => $version->status, 'operation_id' => $operation->id, 'template_version_id' => $version->id]);
    }

    public function printBlankInstances(
        PrintOmrBlankInstancesRequest $request,
        OmrAnswerSheetTemplateVersion $templateVersion,
        PrintOmrBlankInstances $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $action->handle($templateVersion, $actor, (int) $payload['quantity'], (string) $payload['idempotency_key']);

        return response()->json(['status' => 'queued', 'operation_id' => $operation->id, 'template_version_id' => $templateVersion->id], 202);
    }

    public function blankInstances(OmrAnswerSheetTemplateVersion $templateVersion): JsonResponse
    {
        return response()->json([
            'instances' => $templateVersion->blankInstances()
                ->with('pages')
                ->orderByDesc('page_set_no')
                ->limit(100)
                ->get(),
        ]);
    }

    public function supersedeBlankInstance(
        SupersedeOmrBlankInstanceRequest $request,
        OmrAnswerSheetTemplateVersion $templateVersion,
        OmrBlankInstance $instance,
        SupersedeOmrBlankInstanceSet $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        abort_unless((int) $instance->template_version_id === (int) $templateVersion->id, 404);
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $replacement = OmrBlankInstance::query()->findOrFail($payload['replacement_instance_id']);
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
    ): JsonResponse {
        abort_unless((int) $instance->template_version_id === (int) $templateVersion->id, 404);
        /** @var User $actor */
        $actor = $request->user();
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
}
