<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assessment;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraProtocol;
use App\Assessment\Omr\Operational\DiagnosticOmrAuthorityAdapter;
use App\Assessment\Omr\Operational\OmrExecutionKind;
use App\Assessment\Omr\Operational\OmrWorkbenchProjection;
use App\Assessment\Omr\Operational\ProductionOmrAuthorityAdapter;
use App\Assessment\Omr\Queries\OmrCameraCaptureTargetQuery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\OmrCameraAcquiredRequest;
use App\Http\Requests\Assessment\OmrCameraCommandRequest;
use App\Http\Requests\Assessment\OmrCameraHeartbeatRequest;
use App\Http\Requests\Assessment\OmrCameraJoinRequest;
use App\Http\Requests\Assessment\OmrCameraNodeRejoinRequest;
use App\Http\Requests\Assessment\OmrCameraPairRequest;
use App\Http\Requests\Assessment\OmrCameraSignalRequest;
use App\Http\Requests\Assessment\OmrCameraStageFrameRequest;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class OmrCameraSensorController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly DiagnosticOmrAuthorityAdapter $diagnosticAuthority,
        private readonly ProductionOmrAuthorityAdapter $productionAuthority,
        private readonly OmrCameraProtocol $protocol,
        private readonly OmrCameraFrameFinalization $frameFinalization,
        private readonly OmrCameraCaptureTargetQuery $captureTargets,
        private readonly SensitiveAssetStore $sensitiveAssets,
    ) {}

    public function show(Request $request): mixed
    {
        $actor = $this->operator($request);
        $pairingId = (string) $request->query('pairingId', '');
        $pairing = null;
        if ($pairingId !== '') {
            $pairing = OmrCameraPairing::query()->where('public_id', $pairingId)->firstOrFail();
            $this->assertBoundOperator($pairing, $actor);
        }

        return Inertia::render('Assessment/Omr/CameraSensor', [
            'pairingId' => $pairing?->public_id,
            'pairCode' => null,
            'endpoints' => $pairing === null ? [] : $this->endpoints((string) $pairing->public_id),
            'protocolVersion' => config('omr-camera.protocol_version'),
        ]);
    }

    public function createPair(OmrCameraPairRequest $request): JsonResponse
    {
        $actor = $this->operator($request);
        if (! (bool) config('omr-camera.remote_enabled', false)) {
            return response()->json([
                'code' => 'omr_camera_remote_disabled',
                'message' => 'Remote camera pairing is disabled by deployment policy.',
            ], 503)->header('Cache-Control', 'no-store, private');
        }
        $workbenchId = (string) $request->validated('workbench_id');
        $data = $request->validated();
        $requestedPage = isset($data['capture_page_no']) ? (int) $data['capture_page_no'] : null;
        [$projection, $batchId, $runId] = $this->workbench($actor, $workbenchId, $requestedPage);
        $institutionId = $this->institutionId($actor);
        abort_if($institutionId < 1 || (int) ($projection->context['institution_id'] ?? $institutionId) !== $institutionId, 404);
        $cycleId = (int) ($projection->context['academic_cycle_id'] ?? $projection->context['cycle_id'] ?? 0);
        if ($cycleId < 1 && $batchId !== null) {
            $batch = OmrCaptureBatch::query()->with('gradingSession.examSession')->findOrFail($batchId);
            $cycleId = (int) ($batch->gradingSession?->examSession?->academic_cycle_id ?? 0);
        }
        if ($cycleId < 1 && $runId !== null) {
            $cycleId = (int) OmrDiagnosticRun::query()->whereKey($runId)->value('academic_cycle_id');
        }
        abort_if($cycleId < 1, 404);
        $target = $this->captureTargets->forProjection($projection, $actor,
            isset($data['capture_sheet_id']) ? (int) $data['capture_sheet_id'] : null, $requestedPage);
        abort_unless($requestedPage === null || $projection->executionKind === OmrExecutionKind::Diagnostic
            && ($projection->context['diagnostic_purpose'] ?? null) !== OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL, 422);
        if ($requestedPage !== null) {
            abort_unless($target !== null && (int) ($target['pageNo'] ?? 0) === $requestedPage, 422);
        } elseif ($target === null && ! isset($data['capture_sheet_id'])) {
            $target = $this->captureTargets->forObservationTarget($projection, $actor);
        }
        abort_unless(is_array($target), 409, 'The current workbench has no eligible camera target.');
        $activePairing = OmrCameraPairing::query()->where('workbench_key', hash('sha256', $workbenchId))
            ->whereNull('revoked_at')->where('absolute_expires_at', '>', now())->latest('id')->first();
        $expectedGeneration = isset($data['expected_controller_generation']) ? (int) $data['expected_controller_generation'] : null;
        if ($activePairing !== null && (! ($data['takeover'] ?? false)
            || $expectedGeneration !== (int) $activePairing->controller_generation)) {
            return response()->json([
                'code' => 'omr_camera_controller_active',
                'controller_generation' => (int) $activePairing->controller_generation,
                'message' => 'A desktop controller is already active. Confirm takeover to continue.',
            ], 409)->header('Cache-Control', 'no-store, private');
        }
        $this->protocol->persistTargetGrant($actor, $workbenchId, $target);
        $result = $this->protocol->create($actor, $workbenchId, $institutionId, $cycleId, $batchId, $runId, $target,
            (bool) ($data['takeover'] ?? false), isset($data['expected_controller_generation']) ? (int) $data['expected_controller_generation'] : null);
        $pairing = $result['pairing'];

        return response()->json([
            'pairing_id' => $pairing->public_id,
            'pair_code' => $result['pair_code'],
            'sensor_url' => route('grading.omr.sensor.show', ['pairingId' => $pairing->public_id], absolute: true),
            'protocol_version' => config('omr-camera.protocol_version'),
            'generation' => (int) $pairing->controller_generation,
            'controller_generation' => (int) $pairing->controller_generation,
            'source_generation' => (int) $pairing->source_generation,
            'config_revision' => (int) $pairing->config_revision,
            'config' => $pairing->config_json,
            'controller_token' => $result['controller_token'],
            'target' => $target,
            'endpoints' => $this->endpoints((string) $pairing->public_id),
            'ice_servers' => $this->iceServers(),
        ], 201)->header('Cache-Control', 'no-store, private');
    }

    public function joinPair(OmrCameraJoinRequest $request): JsonResponse
    {
        $actor = $this->operator($request);
        $code = strtoupper((string) $request->validated('pair_code'));
        $candidate = OmrCameraPairing::query()->where('pair_code_hash', hash('sha256', $code))->firstOrFail();
        $this->assertBoundOperator($candidate, $actor);
        $result = $this->protocol->join($actor, $code);
        $pairing = $result['pairing'];
        $this->assertBoundOperator($pairing, $actor);

        return response()->json([
            'pairing_id' => $pairing->public_id, 'node_id' => $pairing->node_id, 'node_token' => $result['node_token'],
            'generation' => (int) $pairing->node_generation, 'node_generation' => (int) $pairing->node_generation,
            'controller_generation' => (int) $pairing->controller_generation, 'source_generation' => (int) $pairing->source_generation,
            'config_revision' => (int) $pairing->config_revision, 'config' => $pairing->config_json,
            'endpoints' => $this->endpoints((string) $pairing->public_id), 'ice_servers' => $this->iceServers(),
            'protocol_version' => config('omr-camera.protocol_version'),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function poll(Request $request, string $pairing): JsonResponse
    {
        $record = $this->authorizedPairing($request, $pairing);
        $after = max(0, (int) $request->query('after_sequence', 0));
        $afterObservation = max(0, (int) $request->query('after_observation_sequence', 0));
        $afterSignal = max(0, (int) $request->query('after_signal_sequence', 0));
        $role = (string) $request->header('X-Omr-Camera-Role');
        $node = $role === 'node';
        $commands = $record->commands()->where('sequence', '>', $after)
            ->where('controller_generation', $record->controller_generation)->where('source_generation', $record->source_generation)
            ->where(function ($query): void {
                $query->whereNull('execution_expires_at')->orWhere('execution_expires_at', '>', now());
            })->orderBy('sequence')->limit(100)->get()->map(fn ($command): array => [...$this->safeCommand($command), 'config' => $record->config_json])->values();
        $observations = $node ? [] : array_values(array_map(static function (array $observation): array {
            $received = isset($observation['received_at']) ? Carbon::parse($observation['received_at']) : now();
            $queueAgeMs = max(0, (int) $received->diffInMilliseconds(now(), false));
            $observation['age_ms'] = $queueAgeMs + (int) ($observation['observed_at_age_ms'] ?? 0);

            return $observation;
        }, array_filter((array) $record->observations_json, static fn (array $observation): bool => (int) ($observation['sequence'] ?? 0) > $afterObservation)));
        $signals = array_values(array_filter((array) $record->signals_json, static fn (array $signal): bool => (int) ($signal['sequence'] ?? 0) > $afterSignal && ($signal['role'] ?? null) !== $role
            && (int) ($signal['generation'] ?? 0) === (int) $record->controller_generation));
        $staged = $node ? null : $record->frames()->with('command')
            ->where('state', OmrCameraFrame::STATE_STAGED)->where('node_generation', $record->node_generation)
            ->where('source_generation', $record->source_generation)->latest('id')->first();

        return response()->json([
            'pairing_id' => $pairing, 'generation' => $node ? (int) $record->node_generation : (int) $record->controller_generation,
            'source_generation' => (int) $record->source_generation, 'config_revision' => (int) $record->config_revision,
            'config' => $record->config_json, 'commands' => $commands,
            'observations' => $observations, 'signals' => $signals,
            'staged_frame' => $staged === null ? null : [
                'frame_id' => $staged->public_id,
                'command_id' => $staged->command->command_id,
                'target_key' => $staged->target_key,
                'source_generation' => (int) $staged->source_generation,
                'config_revision' => (int) $staged->config_revision,
                'input_hash' => $staged->input_sha256,
                'width' => (int) $staged->width, 'height' => (int) $staged->height,
                'orientation' => (int) $staged->orientation,
                'preview_url' => route('grading.omr.sensor.pair.frames.show', [$pairing, $staged->public_id]),
            ],
            'last_ack' => $record->last_ack_json,
            'qr_supported' => count(array_filter((array) $record->observations_json,
                static fn (array $observation): bool => ($observation['qr_supported'] ?? false) === true)) > 0,
            'heartbeat_at' => $record->node_heartbeat_at?->toIso8601String(),
            'controller_heartbeat_at' => $record->controller_heartbeat_at?->toIso8601String(),
            'heartbeat_age_ms' => $record->node_heartbeat_at === null ? null
                : max(0, (int) $record->node_heartbeat_at->diffInMilliseconds(now(), false)),
            'controller_heartbeat_age_ms' => $record->controller_heartbeat_at === null ? null
                : max(0, (int) $record->controller_heartbeat_at->diffInMilliseconds(now(), false)),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function sendCommand(OmrCameraCommandRequest $request, string $pairing): JsonResponse
    {
        $record = $this->authorizedPairing($request, $pairing, 'controller');
        $data = $request->validated();
        $kind = (string) $data['command'];
        $targetAssertion = (array) ($data['target'] ?? []);
        $diagnosticPageNo = $record->diagnostic_run_id === null ? null
            : (int) ($targetAssertion['pageNo'] ?? $record->config_json['target']['pageNo'] ?? 0);
        [$projection] = $this->workbench($request->user(), (string) $record->workbench_id,
            $diagnosticPageNo > 0 ? $diagnosticPageNo : null);
        if ($kind !== 'CAPTURE') {
            $data['operation_id'] = $data['command_id'];
        }
        if ($kind === 'SET_CONFIG' || $kind === 'PAUSE') {
            $configuration = (array) ($data['config'] ?? []);
            foreach ([
                'registration_mode' => 'registrationMode', 'page_count' => 'pageCount',
                'batch_trigger' => 'batchTrigger', 'steady_delay_ms' => 'steadyDelayMs',
                'qr_trigger_enabled' => 'qrTriggerEnabled',
            ] as $wireKey => $canonicalKey) {
                if (array_key_exists($wireKey, $configuration)) {
                    $configuration[$canonicalKey] = $configuration[$wireKey];
                    unset($configuration[$wireKey]);
                }
            }
            $data['config'] = $configuration;
        }
        $existing = $record->commands()->where('command_id', $data['command_id'])->first();
        if ($kind === 'CAPTURE') {
            $data['mode'] = $existing?->payload_json['payload']['mode']
                ?? (string) ($record->config_json['mode'] ?? 'manual');
            if (! array_key_exists('trigger', $data) || $data['trigger'] === null) {
                $data['trigger'] = $existing?->payload_json['payload']['trigger'] ?? 'shutter';
            }
        }
        if ($existing !== null) {
            $submittedTarget = (array) ($data['target'] ?? []);
            $storedTarget = (array) $existing->target_json;
            if ($submittedTarget !== []) {
                foreach (['workbenchId', 'clientCaptureId', 'pageNo', 'captureRevision', 'instanceId', 'sheetId', 'targetKey'] as $field) {
                    abort_unless(($submittedTarget[$field] ?? null) === ($storedTarget[$field] ?? null), 409);
                }
            }
            if (isset($data['target_key'])) {
                abort_unless(hash_equals((string) ($storedTarget['targetKey'] ?? ''), (string) $data['target_key']), 409);
            }
            $data['target'] = $storedTarget;
        } else {
            $targetAssertion = is_array($data['target'] ?? null) ? $data['target'] : [];
            $currentTarget = $this->captureTargets->forProjection($projection, $request->user(),
                isset($targetAssertion['sheetId']) ? (int) $targetAssertion['sheetId'] : null, $diagnosticPageNo);
            $observationTarget = $kind === 'SET_CONFIG'
                ? $this->captureTargets->forObservationTarget($projection, $request->user(), $diagnosticPageNo)
                : null;
            if ($kind === 'CAPTURE') {
                abort_unless(is_array($currentTarget) && $targetAssertion !== [], 409);
                try {
                    $data['target'] = $this->captureTargets->assertCurrentTarget($projection, $request->user(), $targetAssertion);
                } catch (\RuntimeException) {
                    abort(409);
                }
            } else {
                $data['target'] = $currentTarget ?? $observationTarget ?? (array) ($record->config_json['target'] ?? []);
            }
            if (is_array($currentTarget)) {
                $this->protocol->persistTargetGrant($request->user(), (string) $record->workbench_id, $currentTarget);
            }
        }
        $command = $this->protocol->command($record, $kind, (string) $data['command_id'], (string) $data['operation_id'], $data);
        $freshPairing = $record->fresh();

        return response()->json([...$this->safeCommand($command), 'config' => $freshPairing->config_json,
            'config_revision' => (int) $freshPairing->config_revision,
            'source_generation' => (int) $freshPairing->source_generation], $command->wasRecentlyCreated ? 202 : 200)
            ->header('Cache-Control', 'no-store, private');
    }

    public function acknowledge(OmrCameraAcquiredRequest $request, string $pairing): JsonResponse
    {
        $record = $this->authorizedPairing($request, $pairing, 'node');
        $data = $request->validated();
        if ($data['status'] === 'rejected') {
            $command = $this->protocol->acknowledgeRejected($record, $data);

            return response()->json(['command_id' => $command->command_id, 'status' => 'rejected'])
                ->header('Cache-Control', 'no-store, private');
        }
        if ($data['status'] === 'applied') {
            $command = $this->protocol->acknowledgeApplied($record, $data);

            return response()->json(['command_id' => $command->command_id, 'status' => 'applied'])
                ->header('Cache-Control', 'no-store, private');
        }
        $frame = $this->protocol->acknowledgeAcquired($record, $data);

        return response()->json(['frame_id' => $frame->public_id,
            'upload_expires_at' => $frame->upload_expires_at?->toIso8601String()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function stageFrame(OmrCameraStageFrameRequest $request, string $pairing): JsonResponse
    {
        $record = $this->authorizedPairing($request, $pairing, 'node');
        $data = $request->validated();
        $frame = OmrCameraFrame::query()->where('public_id', $data['frame_id'])->where('pairing_id', $record->id)->firstOrFail();
        abort_unless($frame->command?->command_id === $data['command_id']
            && (int) $frame->node_generation === (int) $record->node_generation
            && hash_equals((string) $frame->target_key, (string) $data['target_key'])
            && in_array($frame->state, [OmrCameraFrame::STATE_ACQUIRED, OmrCameraFrame::STATE_UPLOADING, OmrCameraFrame::STATE_STAGED], true)
            && $frame->upload_expires_at?->isFuture(), 409);
        $file = $request->file('frame');
        $bytes = $file?->getContent();
        abort_unless(is_string($bytes) && strlen($bytes) === (int) $frame->byte_count
            && hash_equals((string) $frame->input_sha256, hash('sha256', $bytes)), 409);
        $image = @getimagesizefromstring($bytes);
        abort_unless(is_array($image) && (int) $image[0] === (int) $frame->width
            && (int) $image[1] === (int) $frame->height, 422);
        $quad = isset($data['client_quad']) ? json_decode($data['client_quad'], true) : null;
        abort_unless($quad === null || (is_array($quad) && array_is_list($quad) && count($quad) === 4), 422);
        if (is_array($quad)) {
            foreach ($quad as $point) {
                abort_unless(is_array($point) && count($point) === 2 && array_key_exists('x', $point)
                    && array_key_exists('y', $point) && is_numeric($point['x']) && is_numeric($point['y'])
                    && $point['x'] >= 0 && $point['x'] <= 1 && $point['y'] >= 0 && $point['y'] <= 1, 422);
            }
        }
        $disk = 'sensitive';
        $path = 'omr/camera/'.$record->public_id.'/'.$frame->public_id;
        $assetHmac = $this->sensitiveAssets->hmac($bytes);
        $writeIntent = DB::transaction(function () use ($frame, $record, $data, $path, $disk, $assetHmac, $quad, $bytes): bool {
            $lockedPair = OmrCameraPairing::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $lockedCommand = OmrCameraCommand::query()->whereKey($frame->command_id)->lockForUpdate()->firstOrFail();
            $lockedFrame = OmrCameraFrame::query()->whereKey($frame->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPair->isLive() && (int) $lockedFrame->node_generation === (int) $lockedPair->node_generation
                && $lockedFrame->upload_expires_at?->isFuture()
                && $lockedCommand->command_id === $data['command_id'] && $lockedCommand->kind === 'CAPTURE'
                && $lockedCommand->device_outcome === 'acquired'
                && (int) $lockedCommand->controller_generation === (int) $lockedPair->controller_generation
                && (int) $lockedCommand->source_generation === (int) $lockedPair->source_generation
                && (int) $lockedCommand->config_revision === (int) $lockedPair->config_revision
                && hash_equals((string) $lockedCommand->target_json['targetKey'], (string) $lockedFrame->target_key)
                && hash_equals((string) $lockedFrame->input_sha256, hash('sha256', $bytes)), 409);
            if ($lockedFrame->state === OmrCameraFrame::STATE_ACQUIRED) {
                $lockedFrame->forceFill(['asset_disk' => $disk, 'asset_path' => $path, 'asset_hmac' => $assetHmac,
                    'asset_deleted_at' => null, 'preview_mime' => $lockedFrame->mime_type,
                    'client_quad_json' => $quad, 'state' => OmrCameraFrame::STATE_UPLOADING])->save();

                return true;
            }
            abort_unless(in_array($lockedFrame->state, [OmrCameraFrame::STATE_UPLOADING, OmrCameraFrame::STATE_STAGED], true)
                && $lockedFrame->asset_disk === $disk && $lockedFrame->asset_path === $path
                && is_string($lockedFrame->asset_hmac) && hash_equals($lockedFrame->asset_hmac, $assetHmac)
                && json_encode($lockedFrame->client_quad_json) === json_encode($quad)
                && $lockedFrame->asset_deleted_at === null, 409);

            return false;
        }, 3);
        if (Storage::disk($disk)->exists($path)) {
            abort_unless(hash_equals($bytes, $this->sensitiveAssets->read($path, $assetHmac)), 409);
        } else {
            $this->sensitiveAssets->store($bytes, $path);
        }
        DB::transaction(function () use ($frame, $record, $data, $path, $disk, $assetHmac, $quad): void {
            $lockedPair = OmrCameraPairing::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $lockedCommand = OmrCameraCommand::query()->whereKey($frame->command_id)->lockForUpdate()->firstOrFail();
            $lockedFrame = OmrCameraFrame::query()->whereKey($frame->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPair->isLive() && (int) $lockedFrame->node_generation === (int) $lockedPair->node_generation
                && $lockedFrame->upload_expires_at?->isFuture() && $lockedFrame->asset_disk === $disk
                && $lockedFrame->asset_path === $path && is_string($lockedFrame->asset_hmac)
                && hash_equals($lockedFrame->asset_hmac, $assetHmac)
                && json_encode($lockedFrame->client_quad_json) === json_encode($quad)
                && $lockedCommand->command_id === $data['command_id'] && $lockedCommand->kind === 'CAPTURE'
                && $lockedCommand->device_outcome === 'acquired'
                && (int) $lockedCommand->controller_generation === (int) $lockedPair->controller_generation
                && (int) $lockedCommand->source_generation === (int) $lockedPair->source_generation
                && (int) $lockedCommand->config_revision === (int) $lockedPair->config_revision
                && $lockedFrame->asset_deleted_at === null
                && in_array($lockedFrame->state, [OmrCameraFrame::STATE_UPLOADING, OmrCameraFrame::STATE_STAGED], true), 409);
            if ($lockedFrame->state === OmrCameraFrame::STATE_UPLOADING) {
                $lockedFrame->forceFill(['state' => OmrCameraFrame::STATE_STAGED, 'staged_at' => now()])->save();
            }
            $lockedPair->forceFill(['node_heartbeat_at' => now()])->save();
        }, 3);

        return response()->json(['frame_id' => $frame->public_id,
            'preview_url' => route('grading.omr.sensor.pair.frames.show', [$pairing, $frame->public_id]),
            'upload_expires_at' => $frame->upload_expires_at?->toIso8601String()], $writeIntent ? 201 : 200)
            ->header('Cache-Control', 'no-store, private');
    }

    public function showFrame(Request $request, string $pairing, string $frame): Response
    {
        $record = $this->authorizedPairing($request, $pairing);
        $asset = OmrCameraFrame::query()->where('public_id', $frame)->where('pairing_id', $record->id)
            ->whereIn('state', [OmrCameraFrame::STATE_STAGED, OmrCameraFrame::STATE_FINALIZING, OmrCameraFrame::STATE_CONSUMED])->firstOrFail();
        $bytes = $this->frameFinalization->resolveFrameOriginal($asset);

        return response($bytes, 200, ['Content-Type' => $asset->preview_mime ?? $asset->mime_type,
            'Content-Length' => (string) strlen($bytes), 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function heartbeat(OmrCameraHeartbeatRequest $request, string $pairing): JsonResponse
    {
        $role = $this->requestRole($request);
        $record = $this->authorizedPairing($request, $pairing, $role);
        $data = $request->validated();
        abort_if(strlen($request->getContent()) > (int) config('omr-camera.max_observation_bytes', 8192), 413);
        $this->protocol->heartbeat($record, $role, $data);

        return response()->json(['heartbeat_at' => now()->toIso8601String()])->header('Cache-Control', 'no-store, private');
    }

    public function signal(OmrCameraSignalRequest $request, string $pairing): JsonResponse
    {
        $role = $this->requestRole($request);
        $record = $this->authorizedPairing($request, $pairing, $role);
        $data = $request->validated();
        abort_unless((int) $data['generation'] === (int) $record->controller_generation, 409);
        abort_if(strlen(json_encode($data['payload'], JSON_THROW_ON_ERROR)) > (int) config('omr-camera.max_signal_bytes', 65536), 413);
        abort_unless(($data['kind'] === 'offer' && $role === 'controller')
            || ($data['kind'] === 'answer' && $role === 'node') || $data['kind'] === 'ice', 403);
        $sequence = DB::transaction(function () use ($record, $role, $data): int {
            $locked = OmrCameraPairing::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isLive() && (int) $locked->controller_generation === (int) $data['generation'], 409);
            $signals = (array) $locked->signals_json;
            $sequence = (int) ($signals === [] ? 0 : end($signals)['sequence']) + 1;
            $signals[] = ['sequence' => $sequence, 'kind' => $data['kind'], 'role' => $role,
                'generation' => $data['generation'], 'payload' => $data['payload']];
            $locked->forceFill(['signals_json' => array_slice($signals, -(int) config('omr-camera.max_signals_per_pair', 64))])->save();

            return $sequence;
        }, 3);

        return response()->json(['sequence' => $sequence], 202)->header('Cache-Control', 'no-store, private');
    }

    public function status(Request $request, string $pairing): JsonResponse
    {
        $record = $this->authorizedPairing($request, $pairing);

        return response()->json(['pairing_id' => $record->public_id, 'state' => $record->isLive() ? 'active' : 'expired',
            'generation' => (int) $record->controller_generation, 'node_connected' => $record->node_id !== null
                && $record->node_heartbeat_at?->greaterThan(now()->subSeconds((int) config('omr-camera.heartbeat_timeout_seconds', 15))),
            'last_ack' => $record->last_ack_json])->header('Cache-Control', 'no-store, private');
    }

    public function revoke(Request $request, string $pairing): JsonResponse
    {
        $record = $this->authorizedPairing($request, $pairing, 'controller');
        $this->protocol->revoke($record, 'controller_revoked');

        return response()->json(['revoked' => true]);
    }

    public function rejoinNode(OmrCameraNodeRejoinRequest $request, string $pairing): JsonResponse
    {
        $record = $this->authorizedPairing($request, $pairing, 'controller');
        $storedTarget = (array) ($record->config_json['target'] ?? []);
        $storedPageNo = (int) ($storedTarget['pageNo'] ?? 0);
        $requestPageNo = $request->validated('capture_page_no');
        $diagnosticPageNo = $record->diagnostic_run_id !== null
            && ($storedTarget['diagnostic_purpose'] ?? null) !== OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL
                ? ($requestPageNo === null ? $storedPageNo : (int) $requestPageNo)
                : null;
        abort_unless($requestPageNo === null || ($diagnosticPageNo !== null && $diagnosticPageNo === $storedPageNo), 409);
        [$projection] = $this->workbench($request->user(), (string) $record->workbench_id, $diagnosticPageNo);
        $target = $this->captureTargets->forObservationTarget($projection, $request->user(), $diagnosticPageNo);
        abort_unless(is_array($target), 409);
        $pairCode = $this->protocol->reissueNodePairCode($record, $target);
        $record = $record->fresh();

        return response()->json(['pairing_id' => $record->public_id, 'pair_code' => $pairCode,
            'generation' => (int) $record->node_generation, 'node_generation' => (int) $record->node_generation,
            'controller_generation' => (int) $record->controller_generation, 'source_generation' => (int) $record->source_generation,
            'config_revision' => (int) $record->config_revision, 'config' => $record->config_json,
            'protocol_version' => config('omr-camera.protocol_version'),
            'sensor_url' => route('grading.omr.sensor.show', ['pairingId' => $record->public_id], absolute: true),
            'endpoints' => $this->endpoints((string) $record->public_id), 'ice_servers' => $this->iceServers()])
            ->header('Cache-Control', 'no-store, private');
    }

    private function operator(Request $request): User
    {
        abort_unless(Schema::hasTable('omr_camera_pairings')
            && Schema::hasTable('omr_camera_commands') && Schema::hasTable('omr_camera_frames'), 503, 'The OMR camera protocol is not provisioned.');
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $this->authorizer->authorize($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);

        return $actor;
    }

    private function assertBoundOperator(OmrCameraPairing $pairing, User $actor): void
    {
        abort_unless((int) $pairing->operator_id === (int) $actor->getAuthIdentifier()
            && (int) $pairing->institution_id === $this->institutionId($actor), 404);
        $this->authorizer->authorize($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, ['institution_id' => (int) $pairing->institution_id]);
        $this->workbench($actor, (string) $pairing->workbench_id);
    }

    private function authorizedPairing(Request $request, string $id, ?string $requiredRole = null): OmrCameraPairing
    {
        $actor = $this->operator($request);
        $role = $this->requestRole($request);
        abort_unless($requiredRole === null || $role === $requiredRole, 403);
        $token = (string) $request->header('X-Omr-Camera-Token', '');
        $generation = (int) $request->header('X-Omr-Camera-Generation', 0);
        $record = $this->protocol->authenticate($actor, $id, $role, $token, $generation);
        $this->assertBoundOperator($record, $actor);

        return $record;
    }

    private function requestRole(Request $request): string
    {
        $role = (string) $request->header('X-Omr-Camera-Role', '');
        abort_unless(in_array($role, ['controller', 'node'], true), 403);

        return $role;
    }

    /** @return array{0:OmrWorkbenchProjection,1:?int,2:?int} */
    private function workbench(User $actor, string $workbenchId, ?int $diagnosticPageNo = null): array
    {
        if (str_starts_with($workbenchId, 'diagnostic:')) {
            $projection = $this->diagnosticAuthority->project($workbenchId, (int) $actor->getAuthIdentifier(),
                diagnosticPageNo: $diagnosticPageNo);
            $runId = OmrDiagnosticRun::query()->where('public_id', substr($workbenchId, strlen('diagnostic:')))->value('id');
            abort_unless($runId !== null && $projection->workbenchId === $workbenchId, 404);

            return [$projection, null, (int) $runId];
        }
        if (preg_match('/^production:[1-9][0-9]*:[1-9][0-9]*$/', $workbenchId) !== 1) {
            abort(404);
        }
        $projection = $this->productionAuthority->project($workbenchId, (int) $actor->getAuthIdentifier());
        [$sessionId, $batchId] = array_map('intval', array_slice(explode(':', $workbenchId), 1));
        $batch = OmrCaptureBatch::query()->whereKey($batchId)->where('grading_session_id', $sessionId)->firstOrFail();
        abort_unless($projection->workbenchId === $workbenchId, 404);

        return [$projection, (int) $batch->id, null];
    }

    /** @return array<string, string> */
    private function endpoints(string $id): array
    {
        return [
            'create' => route('grading.omr.sensor.pair.create'), 'join' => route('grading.omr.sensor.pair.join'),
            'poll' => route('grading.omr.sensor.pair.poll', $id), 'signal' => route('grading.omr.sensor.pair.signal', $id),
            'command' => route('grading.omr.sensor.pair.command', $id), 'ack' => route('grading.omr.sensor.pair.ack', $id),
            'heartbeat' => route('grading.omr.sensor.pair.heartbeat', $id), 'status' => route('grading.omr.sensor.pair.status', $id),
            'revoke' => route('grading.omr.sensor.pair.revoke', $id),
            'rejoin' => route('grading.omr.sensor.pair.node.rejoin', $id),
            'frames' => route('grading.omr.sensor.pair.frames.store', $id),
            'frameUrl' => route('grading.omr.sensor.pair.frames.show', [$id, '00000000-0000-4000-8000-000000000000']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function iceServers(): array
    {
        $urls = (array) config('omr-camera.turn.urls', []);
        $secret = (string) config('omr-camera.turn.shared_secret', '');
        if ($urls === [] || strlen($secret) < 32) {
            return (array) config('omr-camera.ice_servers', []);
        }
        $expiry = now()->addSeconds((int) config('omr-camera.turn.ttl_seconds', 600))->timestamp;
        $username = $expiry.':'.Str::random(8);
        $credential = base64_encode(hash_hmac('sha1', $username, $secret, true));

        return [['urls' => $urls, 'username' => $username, 'credential' => $credential]];
    }

    private function institutionId(User $actor): int
    {
        return (int) ($actor->staffProfile?->institution_id ?? 0);
    }

    /** @return array<string, mixed> */
    private function safeCommand($command): array
    {
        $payload = (array) ($command->payload_json['payload'] ?? []);

        return ['command_id' => $command->command_id, 'operation_id' => $command->operation_id, 'sequence' => $command->sequence,
            'command' => $command->kind, 'target' => $command->target_json,
            'target_key' => $command->target_json['targetKey'] ?? null,
            'config_revision' => $command->config_revision, 'source_generation' => $command->source_generation,
            'config' => $payload['config'] ?? null, 'trigger' => $payload['trigger'] ?? null, 'mode' => $payload['mode'] ?? null,
            'issued_at' => $command->issued_at?->toIso8601String(),
            'expires_at' => $command->execution_expires_at?->toIso8601String(), 'device_outcome' => $command->device_outcome,
            'domain_outcome' => $command->domain_outcome];
    }
}
