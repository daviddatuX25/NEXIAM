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
use App\Assessment\Actions\Omr\Canonical\RunOmrDigitalVerification;
use App\Assessment\Actions\Omr\Canonical\RunOmrPhysicalQualification;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalMapping;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalOverlay;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Actions\Omr\Canonical\SelectOmrMarkerProfile;
use App\Assessment\Actions\Omr\Canonical\SupersedeOmrBlankInstanceSet;
use App\Assessment\Actions\Omr\Canonical\VoidOmrBlankInstanceSet;
use App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection;
use App\Assessment\Actions\Omr\Canonical\RectifyOmrSourcePage;
use App\Http\Controllers\Controller;
use App\Foundation\Actions\RecordAuditEvent;
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
use App\Http\Requests\Omr\Canonical\RectifyOmrSourcePageRequest;
use App\Http\Requests\Omr\Canonical\SelectOmrMarkerProfileRequest;
use App\Http\Requests\Omr\Canonical\RunOmrDigitalVerificationRequest;
use App\Http\Requests\Omr\Canonical\SupersedeOmrBlankInstanceRequest;
use App\Http\Requests\Omr\Canonical\VoidOmrBlankInstanceRequest;
use App\Http\Requests\Omr\Canonical\PrintOmrBlankInstancesRequest;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrTemplateAsset;
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

        if ($draft === null) {
            $draft = OmrTemplateAuthoringDraft::query()
                ->where('owner_user_id', $actor->id)
                ->orderByDesc('id')
                ->first()
                ?? OmrTemplateAuthoringDraft::query()->orderByDesc('id')->first();
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

        return Inertia::render('Setup/OmrConfiguration/Canonical/CanonicalWorkflow', $projection->workflowProps($draft, $actor));
    }

    public function asset(OmrTemplateAuthoringDraft $draft, OmrTemplateAsset $asset): StreamedResponse
    {
        abort_unless((int) $asset->authoring_draft_id === (int) $draft->id, 404);

        return Storage::disk($asset->disk)->response($asset->path, null, [
            'Content-Type' => $asset->mime,
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

        return response()->json([
            'status' => 'ready',
            'draft_id' => $saved->id,
            'stage' => $saved->stage,
            'lock_version' => $saved->lock_version,
            'composition' => $saved->authoring_json['canonical'] ?? null,
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
        $saved = $action->handle(
            $draft,
            $actor,
            $payload['overlay'],
            $payload['mapping'] ?? null,
            $payload['studio'] ?? null,
            (int) $payload['lock_version'],
        );

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
        RunOmrDigitalVerificationRequest $request,
        OmrTemplateAuthoringDraft $draft,
        RunOmrDigitalVerification $action,
        CommandIdempotency $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $operation = $idempotency->claim($actor, 'omr.canonical.digital_verification', (string) $draft->id, ['draft_id' => $draft->id], (string) $payload['idempotency_key']);
        $saved = $operation->wasRecentlyCreated ? $action->handle($draft, $actor) : $draft->fresh();

        return response()->json(['status' => $saved->stage, 'operation_id' => $operation->id, 'verification' => $saved->authoring_json['verification'] ?? null]);
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
        $saved = $operation->wasRecentlyCreated ? $action->handle($draft, $actor) : $draft->fresh();

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
