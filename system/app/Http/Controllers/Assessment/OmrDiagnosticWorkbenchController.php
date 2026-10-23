<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\ConfirmOmrPendingCaptureIdentity;
use App\Assessment\Actions\Omr\Operational\DiscardOmrDiagnosticCapture;
use App\Assessment\Actions\Omr\Operational\OmrDiagnosticEvidenceQuery;
use App\Assessment\Actions\Omr\Operational\StartOmrDiagnosticRun;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Omr\Canonical\OmrCanonicalSheetPageResolver;
use App\Assessment\Omr\Operational\DiagnosticOmrAuthorityAdapter;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Assessment\Omr\Operational\OmrPendingCaptureTarget;
use App\Assessment\Omr\Operational\OmrWorkbenchCommand;
use App\Assessment\Support\TestKitMockSession;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\DiscardOmrDiagnosticCaptureRequest;
use App\Http\Requests\Assessment\CancelOmrDiagnosticRunRequest;
use App\Http\Requests\Assessment\RetryOmrDiagnosticCaptureRequest;
use App\Http\Requests\Assessment\ReviewOmrDiagnosticItemRequest;
use App\Http\Requests\Assessment\ShowOmrDiagnosticWorkbenchRequest;
use App\Http\Requests\Assessment\StartOmrDiagnosticCaptureSetRequest;
use App\Http\Requests\Assessment\StartOmrDiagnosticRunRequest;
use App\Http\Requests\Assessment\StoreOmrDiagnosticCaptureRequest;
use App\Http\Requests\Assessment\TransitionOmrDiagnosticRunRequest;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use ValueError;

use function array_key_exists;
use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

final class OmrDiagnosticWorkbenchController extends Controller
{
    public function mockGuide(
        Request $request,
        TestKitMockSession $mockSession,
        OmrDiagnosticRunStatusSnapshot $snapshot,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $templateVersionId = 0;
            if ($request->filled('run_public_id')) {
                $run = $this->resolveRun((string) $request->query('run_public_id'));
                $snapshot->assertCanProject($run, $actor);
                if (! $run->hasMockContext()) {
                    return $this->failureCode('omr_mock_run_not_found', 404);
                }
                $templateVersionId = (int) $run->template_version_id;
                $guide = $mockSession->guideForRun($run);
                $manifest = is_array($run->mock_manifest_json) ? $run->mock_manifest_json : [];
                $config = [
                    'instance_count' => (int) ($manifest['config']['instance_count'] ?? 1),
                    'pattern_mode' => (string) ($manifest['config']['pattern_mode'] ?? 'guided_mix'),
                ];
                $hash = (string) $run->mock_manifest_sha256;
            } else {
                $payload = $request->validate([
                    'test_kit_version_id' => ['required', 'integer', 'min:1'],
                    'expected_revision' => ['required', 'integer', 'min:0'],
                    'instance_count' => ['required', 'integer', 'in:1,2,3'],
                    'pattern_mode' => ['required', 'string', 'in:guided_mix,perfect_set_a,blank_baseline'],
                ]);
                $version = TestKitVersion::query()
                    ->with('testKit')
                    ->findOrFail((int) $payload['test_kit_version_id']);
                $this->assertKitGuideScope($actor, $version, $authorizer);
                if ((int) $version->revision !== (int) $payload['expected_revision']) {
                    return $this->failureCode('test_kit_version_revision_mismatch', 409);
                }
                $templateVersionId = (int) $version->template_version_id;
                $prepared = $mockSession->prepare(
                    $version,
                    (int) $payload['instance_count'],
                    (string) $payload['pattern_mode'],
                );
                $guide = $prepared['mock_guide'];
                $config = $prepared['config'] ?? [
                    'instance_count' => (int) $payload['instance_count'],
                    'pattern_mode' => (string) $payload['pattern_mode'],
                ];
                $hash = (string) $prepared['sha256'];
            }

            foreach ($guide['pages'] as &$page) {
                $page['reference_url'] = ($page['available'] ?? false) === true
                    ? route('setup.exam-materials.omr-test.runs.reference-asset', [
                        'templateVersion' => $templateVersionId,
                        'pageNo' => (int) $page['page_no'],
                    ])
                    : null;
            }
            unset($page);

            return response()->json([
                'mock_guide' => $guide,
                'mock_config' => $config,
                'manifest_sha256' => $hash,
            ]);
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_mock_material_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function referenceAsset(
        Request $request,
        int $templateVersion,
        int $pageNo,
        TestKitMockSession $mockSession,
        SensitiveAssetStore $assetStore,
        CapabilityAuthorizer $authorizer,
    ): Response {
        $actor = $this->actor($request);
        if (! $actor instanceof User || $pageNo < 1 || $pageNo > 4) {
            return $this->failureCode('omr_mock_reference_not_found', 404);
        }

        try {
            $requestedKitVersionId = $request->integer('test_kit_version_id');
            $version = TestKitVersion::query()
                ->with('testKit')
                ->where('template_version_id', $templateVersion)
                ->when($requestedKitVersionId > 0, static fn ($q) => $q->whereKey($requestedKitVersionId))
                ->orderByDesc('version_number')
                ->firstOrFail();
            $this->assertKitGuideScope($actor, $version, $authorizer);
            $asset = $mockSession->referenceAsset($version, $pageNo);

            return app(OmrCanonicalSheetPageResolver::class)
                ->inlineResponse($asset, 'omr-mock-reference');
        } catch (Throwable) {
            return $this->failureCode('omr_mock_reference_not_found', 404);
        }
    }

    public function start(
        StartOmrDiagnosticRunRequest $request,
        StartOmrDiagnosticRun $action,
        DiagnosticOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $payload = $request->validated();
            $run = $action->handle(
                templateVersion: isset($payload['template_version_id']) ? (int) $payload['template_version_id'] : null,
                bookletVersion: isset($payload['booklet_version_id']) ? (int) $payload['booklet_version_id'] : null,
                declaredSetCode: isset($payload['declared_set_code']) ? (string) $payload['declared_set_code'] : null,
                actor: $actor,
                institutionId: null,
                academicCycleId: null,
                testKitVersion: isset($payload['test_kit_version_id']) ? (int) $payload['test_kit_version_id'] : null,
                acceptancePlan: isset($payload['acceptance_plan_id']) ? (int) $payload['acceptance_plan_id'] : null,
                expectedRevision: isset($payload['expected_revision']) ? (int) $payload['expected_revision'] : null,
                idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
                diagnosticPurpose: (string) $payload['diagnostic_purpose'],
                instanceCount: isset($payload['instance_count']) ? (int) $payload['instance_count'] : null,
                patternMode: isset($payload['pattern_mode']) ? (string) $payload['pattern_mode'] : null,
                expectedMockManifestSha256: isset($payload['expected_mock_manifest_sha256']) ? (string) $payload['expected_mock_manifest_sha256'] : null,
                resumeRunPublicId: isset($payload['resume_run_public_id']) ? (string) $payload['resume_run_public_id'] : null,
            );

            return response()->json(
                $authority->project(
                    $this->workbenchId((string) $run->public_id),
                    (int) $actor->getAuthIdentifier(),
                )->toSafeArray(),
            );
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_material_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function show(
        ShowOmrDiagnosticWorkbenchRequest $request,
        string $run,
        DiagnosticOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $filters = $request->validated();

            return response()->json(
                $authority->project(
                    $this->workbenchId($run),
                    (int) $actor->getAuthIdentifier(),
                    null,
                    isset($filters['capture_operation_id']) ? (string) $filters['capture_operation_id'] : null,
                    isset($filters['capture_idempotency_key']) ? (string) $filters['capture_idempotency_key'] : null,
                    isset($filters['capture_page_no']) ? (int) $filters['capture_page_no'] : null,
                )->toSafeArray(),
            );
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_run_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function startPendingCapture(
        StartOmrDiagnosticCaptureSetRequest $request,
        string $run,
        StartOmrPendingCaptureSet $action,
        DiagnosticOmrAuthorityAdapter $authority,
        OmrPendingCaptureTarget $target,
        TestKitMockSession $mockSession,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $runModel = $this->resolveRun($run);
            $workbenchId = $this->workbenchId($run);
            $actorId = (int) $actor->getAuthIdentifier();
            $payload = $request->validated();

            $mockInstanceKey = null;
            if ($runModel->hasMockContext()) {
                $targetPayload = $mockSession->captureTarget($runModel);
                $mockInstanceKey = is_string($targetPayload['instance_id'] ?? null)
                    ? (string) $targetPayload['instance_id']
                    : 'instance-001';
            }

            $authority->project($workbenchId, $actorId);
            $set = $action->forDiagnostic($runModel, $actor, (string) $payload['idempotency_key'], $mockInstanceKey);

            return response()->json($target->withStartedSet(
                $authority->project($workbenchId, $actorId),
                $set,
            )->toSafeArray());
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_run_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function confirmPendingCaptureIdentity(
        Request $request,
        string $run,
        ConfirmOmrPendingCaptureIdentity $confirmIdentity,
        DiagnosticOmrAuthorityAdapter $authority,
        OmrPendingCaptureTarget $target,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }
        $payload = $request->validate([
            'pending_capture_id' => ['required', 'uuid'],
            'pending_capture_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.page_no' => ['required', 'integer', 'between:1,4'],
            'selections.*.applicant_number' => ['nullable', 'string', 'max:64'],
            'selections.*.admission_attempt_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $runModel = $this->resolveRun($run);
            $workbenchId = $this->workbenchId($run);
            $actorId = (int) $actor->getAuthIdentifier();
            $authority->project($workbenchId, $actorId);
            $set = OmrPendingCaptureSet::query()
                ->where('public_id', $payload['pending_capture_id'])
                ->where('diagnostic_run_id', $runModel->id)
                ->where('created_by_user_id', $actor->id)
                ->firstOrFail();
            $updated = $confirmIdentity->handle(
                $set,
                $actor,
                (int) $payload['pending_capture_revision'],
                $payload['selections'],
                (string) $payload['idempotency_key'],
            );

            return response()->json($target->withStartedSet(
                $authority->project($workbenchId, $actorId),
                $updated->fresh(),
            )->toSafeArray());
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_run_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function capture(
        StoreOmrDiagnosticCaptureRequest $request,
        string $run,
        DiagnosticOmrAuthorityAdapter $authority,
        OmrCameraFrameFinalization $cameraFrames,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $payload = $request->validated();
            if (is_string($payload['staged_frame_id'] ?? null)) {
                $workbenchId = $this->workbenchId($run);
                abort_unless(Schema::hasTable('omr_camera_commands'), 503);
                $existingOperation = $cameraFrames->findOperationForReplay(
                    $actor,
                    $workbenchId,
                    (string) $payload['operation_id'],
                    (string) $payload['idempotency_key'],
                );
                if ($existingOperation?->domain_outcome === 'accepted') {
                    $payload['_camera_controller_authenticated'] = true;
                } else {
                    abort_unless($request->header('X-Omr-Camera-Role') === 'controller', 403);
                    $token = $request->header('X-Omr-Camera-Token');
                    $generation = $request->header('X-Omr-Camera-Generation');
                    abort_unless(is_string($token) && is_string($generation) && ctype_digit($generation), 403);
                    $pairing = $cameraFrames->assertControllerCredential((string) $payload['pairing_id'], $actor, $token, (int) $generation);
                    abort_unless(hash_equals((string) $pairing->workbench_key, hash('sha256', $workbenchId)), 404);
                    $payload['_camera_controller_authenticated'] = true;
                }
            } else {
                /** @var UploadedFile $image */
                $image = $payload['image'];
                unset($payload['image']);
                $payload['bytes'] = (string) $image->get();
                $payload['mime'] = (string) $image->getMimeType();
            }

            return $this->execute(
                $authority,
                $run,
                $actor,
                OmrWorkbenchCommand::CaptureStart,
                $payload,
            );
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_run_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function retry(
        RetryOmrDiagnosticCaptureRequest $request,
        string $run,
        int $capture,
        DiagnosticOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $payload = $request->validated();
            if (array_key_exists('capture_id', $payload) && (int) $payload['capture_id'] !== $capture) {
                return $this->failureCode('omr_diagnostic_capture_route_mismatch', 404);
            }
            $payload['capture_id'] = $capture;

            return $this->execute(
                $authority,
                $run,
                $actor,
                OmrWorkbenchCommand::CaptureRetry,
                $payload,
            );
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_capture_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function discard(
        DiscardOmrDiagnosticCaptureRequest $request,
        string $run,
        int $capture,
        DiagnosticOmrAuthorityAdapter $authority,
        DiscardOmrDiagnosticCapture $action,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $payload = $request->validated();
            $runModel = OmrDiagnosticRun::query()->where('public_id', $run)->firstOrFail();
            $captureModel = OmrDiagnosticCapture::query()
                ->whereKey($capture)
                ->where('diagnostic_run_id', $runModel->id)
                ->firstOrFail();
            $action->handle(
                $runModel,
                $captureModel,
                $actor,
                (int) $payload['expected_revision'],
                (int) $payload['expected_capture_version'],
                isset($payload['reason']) ? trim((string) $payload['reason']) : null,
            );

            return response()->json($authority->project($this->workbenchId($run), (int) $actor->getAuthIdentifier())->toSafeArray());
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_capture_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function review(
        ReviewOmrDiagnosticItemRequest $request,
        string $run,
        int $item,
        DiagnosticOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $payload = $request->validated();
            if (array_key_exists('item_evidence_id', $payload) && (int) $payload['item_evidence_id'] !== $item) {
                return $this->failureCode('omr_diagnostic_item_route_mismatch', 404);
            }
            $payload['item_evidence_id'] = $item;

            return $this->execute(
                $authority,
                $run,
                $actor,
                OmrWorkbenchCommand::ReviewSave,
                $payload,
            );
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_item_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    public function compare(
        TransitionOmrDiagnosticRunRequest $request,
        string $run,
        DiagnosticOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        return $this->execute(
            $authority,
            $run,
            $actor,
            OmrWorkbenchCommand::DiagnosticCompare,
            $request->validated(),
        );
    }

    public function complete(
        TransitionOmrDiagnosticRunRequest $request,
        string $run,
        DiagnosticOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        return $this->execute(
            $authority,
            $run,
            $actor,
            OmrWorkbenchCommand::DiagnosticComplete,
            $request->validated(),
        );
    }

    public function cancel(
        CancelOmrDiagnosticRunRequest $request,
        string $run,
        DiagnosticOmrAuthorityAdapter $authority,
    ): JsonResponse {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        return $this->execute(
            $authority,
            $run,
            $actor,
            OmrWorkbenchCommand::DiagnosticCancel,
            $request->validated(),
        );
    }

    public function asset(
        Request $request,
        string $run,
        int $asset,
        OmrDiagnosticEvidenceQuery $query,
        SensitiveAssetStore $assetStore,
    ): Response {
        $actor = $this->actor($request);
        if (! $actor instanceof User) {
            return $this->failureCode('omr_diagnostic_unauthenticated', 403);
        }

        try {
            $runModel = $this->resolveRun($run);
            $evidence = $query->handle($runModel, $actor);
            if (! $this->assetIsProjected($evidence, $asset)) {
                return $this->failureCode('omr_diagnostic_asset_not_found', 404);
            }

            $stored = OmrTemplateAsset::query()
                ->whereKey($asset)
                ->where('template_version_id', $runModel->template_version_id)
                ->where('kind', OmrTemplateAsset::KIND_EVIDENCE)
                ->firstOrFail();
            $metadata = is_array($stored->metadata) ? $stored->metadata : [];
            if ((int) ($metadata['diagnostic_run_id'] ?? 0) !== (int) $runModel->getKey()
                || ($metadata['source'] ?? null) !== 'omr_diagnostic'
                || ! in_array($metadata['role'] ?? null, [
                    'input',
                    'aligned',
                    'overlay',
                    'capture_input',
                    'capture_aligned',
                    'capture_overlay',
                ], true)
                || (string) $stored->disk !== 'sensitive'
                || ! is_string($stored->path)
                || trim($stored->path) === ''
                || ! is_string($metadata['hmac'] ?? null)
                || trim((string) $metadata['hmac']) === '') {
                return $this->failureCode('omr_diagnostic_asset_not_found', 404);
            }

            try {
                $bytes = $assetStore->read((string) $stored->path, (string) $metadata['hmac']);
            } catch (Throwable) {
                return $this->failureCode('omr_diagnostic_asset_not_found', 404);
            }

            $contentHash = hash('sha256', $bytes);
            if (! is_string($stored->sha256) || ! hash_equals(strtolower($stored->sha256), $contentHash)) {
                return $this->failureCode('omr_diagnostic_asset_integrity_failed', 404);
            }

            [$mime, $extension] = $this->safeMime((string) $stored->mime);

            return response($bytes, 200, [
                'Content-Type' => $mime,
                'Content-Length' => (string) strlen($bytes),
                'Content-Disposition' => 'inline; filename="omr-diagnostic-'.$contentHash.$extension.'"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_asset_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
    }

    private function assertKitGuideScope(
        User $actor,
        TestKitVersion $version,
        CapabilityAuthorizer $authorizer,
    ): void {
        $version->loadMissing('testKit');
        if (! $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, [
            'office' => 'guidance',
            'institution_id' => (int) $version->testKit->institution_id,
        ])) {
            throw new RuntimeException('omr_diagnostic_capability_denied');
        }
    }

    private function actor(Request $request): ?User
    {
        $actor = $request->user();

        return $actor instanceof User ? $actor : null;
    }

    private function resolveRun(string $publicId): OmrDiagnosticRun
    {
        return OmrDiagnosticRun::query()
            ->where('public_id', $publicId)
            ->where('execution_kind', OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function execute(
        DiagnosticOmrAuthorityAdapter $authority,
        string $run,
        User $actor,
        OmrWorkbenchCommand $command,
        array $payload,
    ): JsonResponse {
        try {
            $projection = $authority->execute(
                $this->workbenchId($run),
                $command,
                $payload,
                (int) $actor->getAuthIdentifier(),
            )->toSafeArray();
        } catch (ModelNotFoundException) {
            return $this->failureCode('omr_diagnostic_run_not_found', 404);
        } catch (InvalidArgumentException|RuntimeException|ValueError $exception) {
            return $this->failure($exception);
        }
        $outcome = $projection['recovery']['outcome'] ?? null;
        if (is_array($outcome) && is_string($outcome['code'] ?? null)) {
            $code = (string) $outcome['code'];

            return $this->failureCode($code, $this->statusFor($code));
        }

        return response()->json($projection);
    }

    private function workbenchId(string $run): string
    {
        return 'diagnostic:'.$run;
    }

    protected function projection(
        OmrDiagnosticRun $run,
        User $actor,
        OmrDiagnosticRunStatusSnapshot $snapshot,
    ): JsonResponse {
        $fresh = $run->fresh() ?? $run;

        return response()->json($snapshot->forRun($fresh, $actor));
    }

    /** @param array<string, mixed> $evidence */
    private function assetIsProjected(array $evidence, int $assetId): bool
    {
        foreach ((array) ($evidence['captures'] ?? []) as $capture) {
            if (! is_array($capture)) {
                continue;
            }
            foreach (['input_asset', 'aligned_asset', 'overlay_asset'] as $key) {
                if ((int) (($capture[$key]['id'] ?? 0)) === $assetId) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{0: string, 1: string} */
    private function safeMime(string $mime): array
    {
        return match (strtolower(trim($mime))) {
            'image/png' => ['image/png', '.png'],
            'image/jpeg', 'image/jpg' => ['image/jpeg', '.jpg'],
            'image/webp' => ['image/webp', '.webp'],
            'image/svg+xml' => ['image/svg+xml', '.svg'],
            'application/pdf' => ['application/pdf', '.pdf'],
            default => ['application/octet-stream', '.bin'],
        };
    }

    private function failure(Throwable $exception): JsonResponse
    {
        $candidate = trim($exception->getMessage());
        $isSafeCode = preg_match('/^omr_[a-z0-9_]{2,96}$/iD', $candidate) === 1;
        $code = $isSafeCode ? strtolower($candidate) : 'omr_diagnostic_failed';
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : ($isSafeCode ? $this->statusFor($code) : 500);
        $supportCode = null;

        if (! $isSafeCode) {
            $supportCode = 'OMR-DIAG-HTTP-'.strtoupper(Str::random(8));
            Log::error('Unexpected OMR diagnostic workbench failure.', [
                'support_code' => $supportCode,
                'exception' => $exception,
            ]);
        }

        $stage = str_contains($code, 'analysis') ? 'processing' : 'recovery';
        $message = $code === 'omr_diagnostic_failed'
            ? 'The OMR request could not be completed. Check the run status or share the support code with an administrator.'
            : 'The OMR request was not completed. Review the run status before retrying.';

        return response()->json([
            'outcome' => 'failed',
            'code' => $code,
            'message' => $message,
            'details' => [
                'stage' => $stage,
                'recovery' => $code === 'omr_diagnostic_failed'
                    ? 'Check the run status before retrying.'
                    : 'Review the run status and resolve the reported condition.',
                ...($supportCode === null ? [] : ['support_code' => $supportCode]),
            ],
        ], $status);
    }

    private function failureCode(string $code, int $status): JsonResponse
    {
        return response()->json([
            'outcome' => 'failed',
            'code' => $code,
            'message' => 'The OMR request was not completed. Review the run status before retrying.',
            'details' => [
                'stage' => str_contains($code, 'analysis') ? 'processing' : 'recovery',
                'recovery' => 'Review the run status and resolve the reported condition.',
            ],
        ], $status);
    }

    private function statusFor(string $code): int
    {
        if (str_contains($code, 'not_found')
            || str_contains($code, 'missing')
            || str_contains($code, 'owner_mismatch')
            || str_contains($code, 'scope_mismatch')
            || str_contains($code, 'binding_mismatch')
            || str_contains($code, 'route_mismatch')) {
            return 404;
        }

        if (str_contains($code, 'denied')
            || str_contains($code, 'capability')
            || str_contains($code, 'forbidden')
            || str_contains($code, 'unauthorized')
            || str_contains($code, 'owner_unavailable')) {
            return 403;
        }

        if (str_contains($code, 'stale')
            || str_contains($code, 'mismatch')
            || str_contains($code, 'conflict')
            || str_contains($code, 'in_progress')
            || str_contains($code, 'context_changed')
            || str_contains($code, 'concurrency')
            || str_contains($code, 'terminal')
            || str_contains($code, 'active')
            || str_contains($code, 'outcome_unknown')
            || str_contains($code, 'not_comparable')
            || str_contains($code, 'not_completable')
            || str_contains($code, 'not_reviewable')
            || str_contains($code, 'gate_blocked')
            || str_contains($code, '_version_')) {
            return 409;
        }

        return 422;
    }
}
