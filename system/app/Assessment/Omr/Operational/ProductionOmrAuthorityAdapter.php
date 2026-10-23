<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

use App\Assessment\Actions\Omr\AdoptOmrPendingCaptureSet;
use App\Assessment\Actions\Omr\Camera\ExecuteOmrWorkbenchCaptureAction;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\CaptureOmrPendingPage;
use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\ResolveOmrApplicantException;
use App\Assessment\Actions\Omr\ResolveOmrDuplicateDisposition;
use App\Assessment\Actions\Omr\RetryOmrCaptureSheet;
use App\Assessment\Actions\Omr\ReviewOmrCaptureSheet;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\Queries\OmrCameraCaptureTargetQuery;
use App\Assessment\Support\GradingRoster;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use Illuminate\Database\ConnectionException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function ctype_digit;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Production-only authority boundary for the shared OMR workbench.
 *
 * The workbench identity is server-owned and contains the exact production
 * grading-session and capture-batch identifiers. Every write is routed to one
 * named, typed production action; this class is not an arbitrary dispatcher.
 */
final class ProductionOmrAuthorityAdapter implements OmrAuthorityAdapter
{
    public function __construct(
        private readonly OmrProductionWorkbenchProjector $projector,
        private readonly OmrPendingCaptureTarget $pendingCaptureTarget,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly UploadOmrCaptureSheet $uploadCaptureSheet,
        private readonly CaptureOmrPendingPage $capturePendingPage,
        private readonly AdoptOmrPendingCaptureSet $adoptPendingSet,
        private readonly RetryOmrCaptureSheet $retryCaptureSheet,
        private readonly ReviewOmrCaptureSheet $reviewCaptureSheet,
        private readonly ResolveOmrApplicantException $resolveApplicant,
        private readonly ResolveOmrDuplicateDisposition $resolveDuplicate,
        private readonly ConfirmOmrCaptureBatch $confirmCaptureBatch,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
        private readonly OmrCameraCaptureTargetQuery $cameraCaptureTarget,
        private readonly ExecuteOmrWorkbenchCaptureAction $captureTransitions,
    ) {}

    public function project(
        string $workbenchId,
        int $actorId,
        ?int $captureSheetId = null,
        ?string $captureOperationId = null,
        ?string $captureIdempotencyKey = null,
        ?int $diagnosticPageNo = null,
    ): OmrWorkbenchProjection {
        [$batch, $actor] = $this->context($workbenchId, $actorId);
        $projection = $this->projector->project($workbenchId, $batch);
        $this->assertProjectionContext($projection, $batch);

        $projection = $this->withPendingCapture($projection, $batch, $actor);

        return $this->withCaptureActions(
            $this->withCameraTarget($projection, $actor, $captureSheetId, $captureOperationId, $captureIdempotencyKey),
            $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        string $workbenchId,
        OmrWorkbenchCommand $command,
        array $payload,
        int $actorId,
    ): OmrWorkbenchProjection {
        $this->assertSupportedCommand($command);
        [$batch, $actor] = $this->context($workbenchId, $actorId);
        $this->assertServerContext($workbenchId, $batch, $actorId, $payload);

        $current = $this->projector->project($workbenchId, $batch);
        $this->assertProjectionContext($current, $batch);
        $current = $this->withCameraTarget($this->withPendingCapture($current, $batch, $actor), $actor, $payload['sheet_id'] ?? null, null, null);
        $existingCaptureOperation = $command === OmrWorkbenchCommand::CaptureStart
            ? $this->cameraFrameFinalization->findOperationForReplay(
                $actor,
                $workbenchId,
                $this->requiredString($payload, 'operation_id'),
                $this->requiredString($payload, 'idempotency_key'),
            )
            : null;
        if (! $current->hasCommand($command) && $existingCaptureOperation === null) {
            throw new RuntimeException('The OMR command is not available for this production run.');
        }
        $this->assertCommandPayload($command, $payload, $current, $batch);

        $expectedRevision = $this->expectedRevision($payload);
        if ($expectedRevision !== $current->revision && $existingCaptureOperation === null) {
            throw new RuntimeException('The OMR run changed after validation. Refresh the current status before continuing.');
        }

        match ($command) {
            OmrWorkbenchCommand::CaptureStart => $this->captureStart($workbenchId, $batch, $actor, $payload, $expectedRevision, $current),
            OmrWorkbenchCommand::CaptureRetry => $this->captureRetry($batch, $actor, $payload, $expectedRevision),
            OmrWorkbenchCommand::ReviewSave => $this->reviewSave($batch, $actor, $payload, $expectedRevision),
            OmrWorkbenchCommand::ResolveApplicant => $this->resolveApplicant($batch, $actor, $payload, $expectedRevision),
            OmrWorkbenchCommand::ResolveDuplicate => $this->resolveDuplicate($batch, $actor, $payload),
            OmrWorkbenchCommand::ProductionComplete => $this->productionComplete($batch, $actor, $expectedRevision),
            OmrWorkbenchCommand::ResolveDisplayCode,
            OmrWorkbenchCommand::QuarantineCapture,
            OmrWorkbenchCommand::SelectSession,
            OmrWorkbenchCommand::DiagnosticCompare,
            OmrWorkbenchCommand::DiagnosticComplete => throw new RuntimeException('The requested OMR command is not supported by the production authority.'),
        };

        $freshBatch = OmrCaptureBatch::query()->findOrFail($batch->id);
        $projection = $this->projector->project($workbenchId, $freshBatch);

        $this->assertProjectionContext($projection, $freshBatch);

        if ($command === OmrWorkbenchCommand::CaptureStart
            && $freshBatch->templateVersion?->qr_mode === OmrQrMode::Disabled) {
            $set = OmrPendingCaptureSet::query()
                ->where('production_batch_id', $freshBatch->id)
                ->where('created_by_user_id', $actor->id)
                ->where('public_id', $payload['pending_capture_id'])
                ->firstOrFail();

            return $this->withCaptureActions(
                $this->withCameraTarget(
                    $this->pendingCaptureTarget->withStartedSet(
                        $this->withPendingCapture($projection, $freshBatch, $actor),
                        $set,
                    ),
                    $actor,
                    null,
                    $this->nullableString($payload['operation_id'] ?? null),
                    $this->nullableString($payload['idempotency_key'] ?? null),
                ),
                $actor,
            );
        }

        return $this->withCaptureActions(
            $this->withCameraTarget(
                $this->withPendingCapture($projection, $freshBatch, $actor),
                $actor,
                $this->nullableInt($payload['sheet_id'] ?? null),
                $this->nullableString($payload['operation_id'] ?? null),
                $this->nullableString($payload['idempotency_key'] ?? null),
            ),
            $actor,
        );
    }

    private function assertProjectionContext(OmrWorkbenchProjection $projection, OmrCaptureBatch $batch): void
    {
        if ($projection->executionKind !== OmrExecutionKind::Production) {
            throw new RuntimeException('The production authority received a foreign execution kind.');
        }

        if ($projection->workbenchId !== $this->workbenchIdFor($batch)
            || (int) ($projection->context['run_id'] ?? 0) !== (int) $batch->id
            || (int) ($projection->context['session_id'] ?? 0) !== (int) $batch->grading_session_id) {
            throw new RuntimeException('The production OMR projection does not match the server context.');
        }
    }

    private function withPendingCapture(
        OmrWorkbenchProjection $projection,
        OmrCaptureBatch $batch,
        User $actor,
    ): OmrWorkbenchProjection {
        $context = $projection->context;
        $cameraAvailable = Schema::hasTable('omr_camera_pairings')
            && Schema::hasTable('omr_camera_commands')
            && Schema::hasTable('omr_camera_frames');
        $remoteCameraEnabled = (bool) config('omr-camera.remote_enabled', false);
        $context['camera'] = [
            'protocol_version' => 'nexiam.omr.camera.v1',
            'create_url' => route('grading.omr.sensor.pair.create'),
            'sensor_url' => route('grading.omr.sensor.show'),
            'available' => $cameraAvailable,
            'pairing_available' => $cameraAvailable && $remoteCameraEnabled,
            'pairing_unavailable_reason' => ! $cameraAvailable
                ? 'omr_camera_protocol_unavailable'
                : (! $remoteCameraEnabled ? 'omr_camera_remote_disabled' : null),
            'route_exam_session_id' => (int) ($batch->gradingSession?->exam_session_id ?? 0),
        ];
        if (! $cameraAvailable) {
            $context['camera']['availability_reason'] = 'omr_camera_protocol_unavailable';
        }
        $context['pending_capture'] = $this->pendingCaptureTarget->forProduction($batch, $actor);

        return new OmrWorkbenchProjection(
            workbenchId: $projection->workbenchId,
            executionKind: $projection->executionKind,
            revision: $projection->revision,
            phase: $projection->phase,
            context: $context,
            sheets: $projection->sheets,
            evidence: $projection->evidence,
            recovery: $projection->recovery,
            completion: $projection->completion,
            commands: $projection->commands,
        );
    }

    private function withCameraTarget(
        OmrWorkbenchProjection $projection,
        User $actor,
        ?int $captureSheetId,
        ?string $captureOperationId,
        ?string $captureIdempotencyKey,
    ): OmrWorkbenchProjection {
        $context = $projection->context;
        if (($context['camera']['available'] ?? false) !== true) {
            if ($captureOperationId !== null && $captureIdempotencyKey !== null) {
                $context['capture_receipt'] = [
                    'operation_id' => $captureOperationId,
                    'target_key' => '',
                    'status' => 'pending',
                    'capture_id' => null,
                    'page_id' => null,
                    'page_no' => null,
                    'client_capture_id' => null,
                ];
            }

            return new OmrWorkbenchProjection(
                workbenchId: $projection->workbenchId,
                executionKind: $projection->executionKind,
                revision: $projection->revision,
                phase: $projection->phase,
                context: $context,
                sheets: $projection->sheets,
                evidence: $projection->evidence,
                recovery: $projection->recovery,
                completion: $projection->completion,
                commands: $projection->commands,
            );
        }
        $target = $this->cameraCaptureTarget->forProjection($projection, $actor, $captureSheetId);
        if ($target !== null) {
            $context['capture_target'] = $this->publicCaptureTarget($target);
        }
        if ($captureOperationId !== null && $captureIdempotencyKey !== null) {
            $operation = $this->cameraFrameFinalization->findOperationReceipt(
                $actor,
                $projection->workbenchId,
                $captureOperationId,
                $captureIdempotencyKey,
            );
            $context['capture_receipt'] = $operation === null
                ? [
                    'operation_id' => $captureOperationId,
                    'target_key' => '',
                    'status' => 'pending',
                    'message' => 'No authoritative outcome is available yet. Keep this exact operation held and check again; the original request may still arrive.',
                    'capture_id' => null,
                    'page_id' => null,
                    'page_no' => null,
                    'client_capture_id' => null,
                ]
                : [
                    'operation_id' => $captureOperationId,
                    ...((array) ($operation['receipt'] ?? [])),
                    'status' => (string) $operation['status'],
                ];
        } elseif (($context['camera']['available'] ?? false) === true) {
            $latest = $this->cameraFrameFinalization->findLatestAcceptedOperationReceipt($actor, $projection->workbenchId);
            if ($latest !== null) {
                $context['capture_receipt'] = [
                    'operation_id' => (string) ($latest['receipt']['operation_id'] ?? ''),
                    ...((array) ($latest['receipt'] ?? [])),
                    'status' => 'accepted',
                ];
            }
        }

        return new OmrWorkbenchProjection(
            workbenchId: $projection->workbenchId,
            executionKind: $projection->executionKind,
            revision: $projection->revision,
            phase: $projection->phase,
            context: $context,
            sheets: $projection->sheets,
            evidence: $projection->evidence,
            recovery: $projection->recovery,
            completion: $projection->completion,
            commands: $projection->commands,
        );
    }

    /** @param array<string, mixed> $target @return array<string, mixed> */
    private function publicCaptureTarget(array $target): array
    {
        $targetRevision = is_numeric($target['target_revision'] ?? $target['captureRevision'] ?? $target['capture_revision'] ?? $target['pending_capture_revision'] ?? $target['revision'] ?? null)
            ? (int) ($target['target_revision'] ?? $target['captureRevision'] ?? $target['capture_revision'] ?? $target['pending_capture_revision'] ?? $target['revision'])
            : null;
        $targetKey = (string) ($target['target_key'] ?? $target['targetKey'] ?? '');
        $pageNo = is_numeric($target['page_no'] ?? $target['pageNo'] ?? null)
            ? (int) ($target['page_no'] ?? $target['pageNo'])
            : null;
        $clientCaptureId = (string) ($target['client_capture_id'] ?? $target['clientCaptureId'] ?? '');
        $instanceId = $target['instance_id'] ?? $target['instanceId'] ?? null;
        $sheetId = is_numeric($target['sheet_id'] ?? $target['sheetId'] ?? null)
            ? (int) ($target['sheet_id'] ?? $target['sheetId'])
            : null;
        $pendingCaptureId = $target['pending_capture_id'] ?? null;
        $pendingCaptureRevision = is_numeric($target['pending_capture_revision'] ?? null)
            ? (int) $target['pending_capture_revision']
            : null;

        unset(
            $target['workbenchId'],
            $target['clientCaptureId'],
            $target['pageNo'],
            $target['captureRevision'],
            $target['instanceId'],
            $target['sheetId'],
            $target['targetKey'],
            $target['capture_revision'],
        );

        return [
            ...$target,
            'client_capture_id' => $clientCaptureId !== '' ? $clientCaptureId : null,
            'page_no' => $pageNo,
            'target_revision' => $targetRevision,
            'instance_id' => is_string($instanceId) && $instanceId !== '' ? $instanceId : null,
            'sheet_id' => $sheetId,
            'pending_capture_id' => is_string($pendingCaptureId) && $pendingCaptureId !== '' ? $pendingCaptureId : null,
            'pending_capture_revision' => $pendingCaptureRevision,
            'target_key' => $targetKey !== '' ? $targetKey : null,
        ];
    }

    /** @param array<string, mixed> $target @return array<string, mixed> */
    private function canonicalCaptureTarget(OmrWorkbenchProjection $projection, array $target): array
    {
        $targetRevision = is_numeric($target['target_revision'] ?? $target['captureRevision'] ?? $target['capture_revision'] ?? $target['pending_capture_revision'] ?? $target['revision'] ?? null)
            ? (int) ($target['target_revision'] ?? $target['captureRevision'] ?? $target['capture_revision'] ?? $target['pending_capture_revision'] ?? $target['revision'])
            : null;
        $pageNo = is_numeric($target['page_no'] ?? $target['pageNo'] ?? null)
            ? (int) ($target['page_no'] ?? $target['pageNo'])
            : null;
        $sheetId = is_numeric($target['sheet_id'] ?? $target['sheetId'] ?? null)
            ? (int) ($target['sheet_id'] ?? $target['sheetId'])
            : null;
        $instanceId = $target['instance_id'] ?? $target['instanceId'] ?? null;

        return [
            ...$target,
            'workbenchId' => $projection->workbenchId,
            'clientCaptureId' => (string) ($target['client_capture_id'] ?? $target['clientCaptureId'] ?? ''),
            'pageNo' => $pageNo,
            'captureRevision' => $targetRevision,
            'instanceId' => is_string($instanceId) && $instanceId !== '' ? $instanceId : null,
            'sheetId' => $sheetId,
            'targetKey' => (string) ($target['target_key'] ?? $target['targetKey'] ?? ''),
        ];
    }

    private function withCaptureActions(OmrWorkbenchProjection $projection, User $actor): OmrWorkbenchProjection
    {
        if (($projection->context['camera']['available'] ?? false) === true) {
            $url = route('scoring.sessions.omr.batches.workbench.capture_actions', [
                'examSession' => $projection->context['camera']['route_exam_session_id'] ?? 0,
                'batch' => $projection->context['run_id'],
            ]);
            $context = $projection->context;
            $context['capture_actions'] = $this->captureTransitions->projectAvailableActions(
                $actor,
                $projection->workbenchId,
                $projection,
                ['next_page' => $url, 'next_set' => $url, 'redo_last' => $url],
            );
        } else {
            $context = $projection->context;
        }

        return new OmrWorkbenchProjection(
            workbenchId: $projection->workbenchId,
            executionKind: $projection->executionKind,
            revision: $projection->revision,
            phase: $projection->phase,
            context: $context,
            sheets: $projection->sheets,
            evidence: $projection->evidence,
            recovery: $projection->recovery,
            completion: $projection->completion,
            commands: $projection->commands,
        );
    }

    private function context(string $workbenchId, int $actorId): array
    {
        [$sessionId, $batchId] = $this->identity($workbenchId);
        $batch = OmrCaptureBatch::query()
            ->whereKey($batchId)
            ->where('grading_session_id', $sessionId)
            ->with('gradingSession.examSession.academicCycle')
            ->firstOrFail();
        $actor = User::query()->findOrFail($actorId);
        $cycle = $batch->gradingSession?->examSession?->academicCycle;
        $institutionId = (int) ($cycle?->institution_id ?? 0);
        $cycleCode = trim((string) ($cycle?->code ?? ''));

        if ($institutionId < 1 || $cycleCode === '') {
            throw new RuntimeException('The production OMR batch has no institution and cycle scope.');
        }

        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
            'institution_id' => $institutionId,
            'cycle' => $cycleCode,
            'office' => 'guidance',
        ])) {
            throw new RuntimeException('You are not authorized to operate the production OMR workbench.');
        }

        return [$batch, $actor];
    }

    private function assertServerContext(
        string $workbenchId,
        OmrCaptureBatch $batch,
        int $actorId,
        array $payload,
    ): void {
        if (array_key_exists('workbench_id', $payload)
            && (! is_string($payload['workbench_id']) || $payload['workbench_id'] !== $workbenchId)) {
            throw new RuntimeException('The OMR workbench identity does not match the server context.');
        }

        if (array_key_exists('batch_id', $payload)
            && $this->positiveInt($payload['batch_id'], 'batch_id') !== (int) $batch->id) {
            throw new RuntimeException('The OMR batch does not belong to this workbench.');
        }

        if (array_key_exists('run_id', $payload)
            && $this->positiveInt($payload['run_id'], 'run_id') !== (int) $batch->id) {
            throw new RuntimeException('The OMR run does not belong to this workbench.');
        }

        if (array_key_exists('actor_id', $payload)
            && $this->positiveInt($payload['actor_id'], 'actor_id') !== $actorId) {
            throw new RuntimeException('The OMR actor must come from the authenticated server context.');
        }

        if (array_key_exists('execution_kind', $payload)
            && (! is_string($payload['execution_kind'])
                || strtolower(trim($payload['execution_kind'])) !== OmrExecutionKind::Production->value)) {
            throw new RuntimeException('A production workbench cannot accept a foreign execution kind.');
        }
        foreach (['kind', 'mode'] as $key) {
            if (array_key_exists($key, $payload)) {
                throw new RuntimeException("The OMR {$key} identity alias is unsupported.");
            }
        }

        $sessionIds = [];
        foreach (['session_id', 'grading_session_id', 'route_session_id'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                $sessionIds[] = $this->positiveInt($payload[$key], $key);
            }
        }

        $runIds = [];
        if (array_key_exists('run_id', $payload) && $payload['run_id'] !== null) {
            $runIds[] = $this->positiveInt($payload['run_id'], 'run_id');
        }

        foreach (['route_context', 'context'] as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null) {
                continue;
            }
            if (! is_array($payload[$key])) {
                throw new RuntimeException("The OMR {$key} context is malformed.");
            }

            foreach (['session_id', 'grading_session_id'] as $nestedKey) {
                if (array_key_exists($nestedKey, $payload[$key]) && $payload[$key][$nestedKey] !== null) {
                    $sessionIds[] = $this->positiveInt($payload[$key][$nestedKey], $nestedKey);
                }
            }

            if (array_key_exists('session', $payload[$key]) && $payload[$key]['session'] !== null) {
                if (! is_array($payload[$key]['session'])) {
                    throw new RuntimeException("The OMR {$key} session context is malformed.");
                }
                if (array_key_exists('id', $payload[$key]['session'])
                    && $payload[$key]['session']['id'] !== null) {
                    $sessionIds[] = $this->positiveInt($payload[$key]['session']['id'], 'session_id');
                }
            }

            if (array_key_exists('run', $payload[$key]) && $payload[$key]['run'] !== null) {
                if (! is_array($payload[$key]['run'])) {
                    throw new RuntimeException("The OMR {$key} run context is malformed.");
                }
                if (array_key_exists('id', $payload[$key]['run'])
                    && $payload[$key]['run']['id'] !== null) {
                    $runIds[] = $this->positiveInt($payload[$key]['run']['id'], 'run_id');
                }
            }
        }

        foreach ($sessionIds as $sessionId) {
            if ($sessionId !== (int) $batch->grading_session_id) {
                throw new RuntimeException('The requested session does not match the production OMR batch.');
            }
        }

        foreach ($runIds as $runId) {
            if ($runId !== (int) $batch->id) {
                throw new RuntimeException('The requested run does not match the production OMR batch.');
            }
        }
    }

    private function assertCommandPayload(
        OmrWorkbenchCommand $command,
        array $payload,
        OmrWorkbenchProjection $projection,
        OmrCaptureBatch $batch,
    ): void {
        match ($command) {
            OmrWorkbenchCommand::CaptureStart => $this->assertCapturePayload($payload),
            OmrWorkbenchCommand::CaptureRetry => $this->assertRecoveryAction(
                $projection,
                $payload,
                'retry_scan',
            ),
            OmrWorkbenchCommand::ReviewSave => $this->assertReviewPayload($payload),
            OmrWorkbenchCommand::ResolveApplicant => $this->assertApplicantPayload($projection, $batch, $payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertCapturePayload(array $payload): void
    {
        if (array_key_exists('manual_confirmed', $payload) && ! is_bool($payload['manual_confirmed'])) {
            throw new RuntimeException('The OMR manual confirmation must be a boolean.');
        }

        if (array_key_exists('capture_mode', $payload)
            && (! is_string($payload['capture_mode'])
                || ! in_array($payload['capture_mode'], [
                    OmrCaptureSheet::MODE_AUTO,
                    OmrCaptureSheet::MODE_MANUAL,
                ], true))) {
            throw new RuntimeException('The OMR capture mode is unsupported.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertReviewPayload(array $payload): void
    {
        $action = $payload['action'] ?? null;
        if (! is_string($action) || ! in_array($action, ['confirm', 'abstain'], true)) {
            throw new RuntimeException('The OMR review action must be confirm or abstain.');
        }
        if (array_key_exists('abstain_reason', $payload)
            && $payload['abstain_reason'] !== null
            && ! is_string($payload['abstain_reason'])) {
            throw new RuntimeException('The OMR abstention reason is malformed.');
        }

        if ($action === 'confirm'
            && array_key_exists('abstain_reason', $payload)
            && $payload['abstain_reason'] !== null) {
            throw new RuntimeException('An abstention reason is only valid for an abstain action.');
        }

        if ($action === 'abstain'
            && (! is_string($payload['abstain_reason'] ?? null)
                || trim($payload['abstain_reason']) === '')) {
            throw new RuntimeException('An abstention reason is required.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertApplicantPayload(
        OmrWorkbenchProjection $projection,
        OmrCaptureBatch $batch,
        array $payload,
    ): void {
        $this->assertRecoveryAction($projection, $payload, 'match_applicant');
        $assignmentId = $this->positiveInt($payload['exam_assignment_id'] ?? null, 'exam_assignment_id');
        $gradingSession = $batch->gradingSession;

        if ($gradingSession === null
            || ! GradingRoster::queryFor($gradingSession)->whereKey($assignmentId)->exists()) {
            throw new RuntimeException('Select a present, submitted gradable assignment from this grading session.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertRecoveryAction(
        OmrWorkbenchProjection $projection,
        array $payload,
        string $action,
    ): void {
        $sheetId = $this->sheetId($payload);

        foreach ($projection->sheets as $sheet) {
            if (! is_array($sheet) || (int) ($sheet['id'] ?? 0) !== $sheetId) {
                continue;
            }

            $recovery = $sheet['recovery'] ?? null;
            if (is_array($recovery)
                && (($recovery['primary_action'] ?? null) === $action
                    || ($recovery['secondary_action'] ?? null) === $action)) {
                return;
            }

            throw new RuntimeException("The OMR sheet does not expose the {$action} recovery action.");
        }

        throw new RuntimeException('The OMR sheet is not part of the current production projection.');
    }

    private function assertSupportedCommand(OmrWorkbenchCommand $command): void
    {
        match ($command) {
            OmrWorkbenchCommand::CaptureStart,
            OmrWorkbenchCommand::CaptureRetry,
            OmrWorkbenchCommand::ReviewSave,
            OmrWorkbenchCommand::ResolveApplicant,
            OmrWorkbenchCommand::ResolveDuplicate,
            OmrWorkbenchCommand::ProductionComplete => null,
            OmrWorkbenchCommand::ResolveDisplayCode,
            OmrWorkbenchCommand::QuarantineCapture,
            OmrWorkbenchCommand::SelectSession,
            OmrWorkbenchCommand::DiagnosticCompare,
            OmrWorkbenchCommand::DiagnosticComplete => throw new RuntimeException('The requested OMR command is not supported by the production authority.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function captureStart(
        string $workbenchId,
        OmrCaptureBatch $batch,
        User $actor,
        array $payload,
        int $expectedRevision,
        OmrWorkbenchProjection $projection,
    ): void {
        if (($projection->context['camera']['available'] ?? false) !== true) {
            throw new RuntimeException('omr_camera_protocol_unavailable');
        }

        $isStaged = is_string($payload['staged_frame_id'] ?? null);
        if ($isStaged && ($payload['_camera_controller_authenticated'] ?? false) !== true) {
            throw new RuntimeException('omr_camera_controller_authentication_required');
        }
        $cropPolicy = $this->nullableString($payload['crop_policy'] ?? null) ?? 'auto_document_quad';
        $pairingId = $isStaged ? $this->requiredString($payload, 'pairing_id') : null;
        $commandId = $isStaged ? $this->requiredString($payload, 'camera_command_id') : null;
        $frameId = $isStaged ? $this->requiredString($payload, 'staged_frame_id') : null;
        $frame = null;
        if ($isStaged) {
            $pairing = OmrCameraPairing::query()->where('public_id', $pairingId)->firstOrFail();
            $frame = $pairing->frames()->where('public_id', $frameId)->where('command_id', function ($query) use ($commandId, $pairing): void {
                $query->select('id')->from('omr_camera_commands')->where('pairing_id', $pairing->id)->where('command_id', $commandId)->limit(1);
            })->firstOrFail();
        }
        $bytes = $isStaged
            ? ''
            : $this->requiredString($payload, 'image_bytes');
        $mime = $isStaged
            ? (string) $frame->mime_type
            : $this->requiredString($payload, 'mime');
        $quad = is_array($payload['client_quad'] ?? null)
            ? $payload['client_quad']
            : ($isStaged
                ? (is_array($frame->client_quad_json) ? $frame->client_quad_json : null)
                : null);
        $operationId = $this->requiredString($payload, 'operation_id');
        $idempotencyKey = $this->requiredString($payload, 'idempotency_key');
        $sourceGeneration = $this->positiveInt($payload['source_generation'] ?? null, 'source_generation');
        $configRevision = $this->positiveInt($payload['config_revision'] ?? null, 'config_revision');
        $inputHash = $isStaged ? (string) $frame->input_sha256 : hash('sha256', $bytes);
        $priorOperation = $this->cameraFrameFinalization->findOperationForReplay($actor, $workbenchId, $operationId, $idempotencyKey);
        if ($priorOperation !== null) {
            $target = is_array($priorOperation->target_json) ? $priorOperation->target_json : [];
            if (($payload['target_key'] ?? null) !== ($target['targetKey'] ?? $target['target_key'] ?? null)
                || ($payload['client_capture_id'] ?? null) !== ($target['clientCaptureId'] ?? $target['client_capture_id'] ?? null)
                || (int) ($payload['page_no'] ?? 0) !== (int) ($target['pageNo'] ?? $target['page_no'] ?? 0)) {
                throw new RuntimeException('omr_camera_operation_fingerprint_conflict');
            }
        } else {
            $publicTarget = $projection->context['capture_target'] ?? null;
            if (! is_array($publicTarget)) {
                throw new RuntimeException('omr_camera_capture_target_unavailable');
            }
            $target = $this->canonicalCaptureTarget($projection, $publicTarget);
            $assertion = $target;
            $assertion['targetKey'] = $this->requiredString($payload, 'target_key');
            $target = $this->cameraCaptureTarget->assertCurrentTarget($projection, $actor, $assertion);
            if ($this->requiredString($payload, 'client_capture_id') !== $target['clientCaptureId']
                || $this->positiveInt($payload['page_no'] ?? null, 'page_no') !== $target['pageNo']
                || (($payload['sheet_id'] ?? null) !== null
                    && (int) $payload['sheet_id'] !== (int) ($target['sheetId'] ?? 0))) {
                throw new RuntimeException('omr_camera_capture_target_mismatch');
            }
            if (array_key_exists('target_revision', $payload)
                && $this->positiveInt($payload['target_revision'] ?? null, 'target_revision') !== (int) $target['captureRevision']) {
                throw new RuntimeException('omr_camera_capture_target_mismatch');
            }
        }
        $redoAuthorizationId = $target['redo_authorization_id'] ?? null;
        $replacementReason = $payload['replacement_reason'] ?? null;
        if (is_string($redoAuthorizationId)) {
            if (! is_string($replacementReason) || $replacementReason !== ($target['redo_reason'] ?? null)
                || ($target['redo_source_page_id'] ?? null) === null) {
                throw new RuntimeException('omr_redo_authorization_mismatch');
            }
        } elseif ($replacementReason !== null) {
            throw new RuntimeException('omr_redo_authorization_required');
        }
        if ($isStaged && ((int) $frame->source_generation !== $sourceGeneration
            || (int) $frame->config_revision !== $configRevision
            || ! hash_equals((string) $frame->target_key, (string) $target['targetKey'])
            || ! hash_equals(
                $this->cameraFrameFinalization->fingerprintPayload((array) $frame->target_json),
                $this->cameraFrameFinalization->fingerprintPayload($target),
            ))) {
            throw new RuntimeException('omr_camera_staged_frame_target_mismatch');
        }
        $fingerprint = $this->cameraFrameFinalization->fingerprintPayload([
            'input_hash' => $inputHash,
            'mime' => $mime,
            'target' => $target,
            'operation_id' => $operationId,
            'idempotency_key' => $idempotencyKey,
            'crop_policy' => $cropPolicy,
            'quad' => $quad,
            'source_generation' => $sourceGeneration,
            'config_revision' => $configRevision,
            'capture_mode' => $payload['capture_mode'] ?? OmrCaptureSheet::MODE_AUTO,
            'admission_attempt_id' => $payload['admission_attempt_id'] ?? null,
            'qr_payload' => $payload['qr_payload'] ?? null,
            'qr_state' => $payload['qr_state'] ?? null,
            'display_code' => $payload['display_code'] ?? null,
            'manual_confirmed' => $payload['manual_confirmed'] ?? false,
            'observed_set_code' => $payload['observed_set_code'] ?? null,
            'replacement_reason' => $payload['replacement_reason'] ?? null,
            'pending_capture_id' => $payload['pending_capture_id'] ?? null,
            'pending_capture_revision' => $payload['pending_capture_revision'] ?? null,
            'sheet_id' => $payload['sheet_id'] ?? null,
            'source_type' => $isStaged ? 'staged' : 'raw',
            'frame_byte_count' => $isStaged ? (int) $frame->byte_count : null,
            'frame_width' => $isStaged ? (int) $frame->width : null,
            'frame_height' => $isStaged ? (int) $frame->height : null,
            'frame_orientation' => $isStaged ? (int) $frame->orientation : null,
            'pairing_id' => $pairingId,
            'camera_command_id' => $commandId,
            'staged_frame_id' => $frameId,
        ]);
        $reservation = $this->cameraFrameFinalization->reserveOperation(
            $actor,
            hash('sha256', $workbenchId),
            [
                'workbench_id' => $workbenchId,
                'production_batch_id' => (int) $batch->id,
                'grading_session_id' => (int) $batch->grading_session_id,
                'revision' => $expectedRevision,
            ],
            $target,
            $operationId,
            $idempotencyKey,
            $fingerprint,
            $cropPolicy,
            $quad,
            $pairingId,
            $commandId,
            $frameId,
        );
        if ($reservation->isReplay()) {
            return;
        }

        try {
            if ($isStaged) {
                $bytes = $this->cameraFrameFinalization->resolveOriginal($reservation);
            }
            if (is_string($redoAuthorizationId) && ($target['sheetId'] ?? null) !== null) {
                $this->captureTransitions->captureProductionRedo(
                    $actor,
                    $projection,
                    $target,
                    (string) $replacementReason,
                    $bytes,
                    $mime,
                    $cropPolicy,
                    $quad,
                    $reservation,
                );

                return;
            }

            if ($batch->templateVersion?->qr_mode === OmrQrMode::Disabled) {
                $pendingCaptureId = $target['pending_capture_id'] ?? null;
                if (! is_string($pendingCaptureId) || ($payload['pending_capture_id'] ?? null) !== $pendingCaptureId) {
                    throw new RuntimeException('omr_capture_set_page_target_mismatch');
                }
                $set = OmrPendingCaptureSet::query()
                    ->where('public_id', $pendingCaptureId)
                    ->where('production_batch_id', $batch->id)
                    ->where('created_by_user_id', $actor->id)
                    ->firstOrFail();
                if (($payload['qr_payload'] ?? null) !== null
                    || ($payload['display_code'] ?? null) !== null
                    || ($payload['qr_state'] ?? null) !== null
                    || ($payload['observed_set_code'] ?? null) !== null
                    || ($payload['admission_attempt_id'] ?? null) !== null) {
                    throw new RuntimeException('omr_capture_set_template_substitution');
                }
                if ($this->positiveInt($payload['pending_capture_revision'] ?? null, 'pending_capture_revision')
                    !== $this->positiveInt($target['pending_capture_revision'] ?? null, 'pending_capture_revision')) {
                    throw new RuntimeException('omr_capture_set_revision_stale');
                }
                $captured = $this->capturePendingPage->handle(
                    $set,
                    $actor,
                    $this->positiveInt($target['pending_capture_revision'] ?? null, 'pending_capture_revision'),
                    (int) $target['pageNo'],
                    (string) $target['clientCaptureId'],
                    $bytes,
                    $mime,
                    replacementReason: $this->nullableString($payload['replacement_reason'] ?? null),
                    clientQuad: $quad,
                    cropPolicy: $cropPolicy,
                    cameraReservation: $reservation,
                    expectedSupersedesId: is_string($redoAuthorizationId)
                        ? $this->positiveInt($target['redo_source_page_id'] ?? null, 'redo_source_page_id')
                        : null,
                );
                if ($captured->status === OmrPendingCaptureSet::STATUS_READY) {
                    $this->adoptPendingSet->handle($captured, $actor, (int) $captured->revision);
                }

                return;
            }

            if (array_key_exists('pending_capture_id', $payload)
                || array_key_exists('pending_capture_revision', $payload)
                || array_key_exists('replacement_reason', $payload)) {
                throw new RuntimeException('omr_capture_set_target_not_supported_for_signed_qr');
            }
            $this->uploadCaptureSheet->handle(
                $batch,
                $bytes,
                $mime,
                (string) ($payload['capture_mode'] ?? OmrCaptureSheet::MODE_AUTO),
                (string) $target['clientCaptureId'],
                $actor,
                $this->nullableInt($payload['admission_attempt_id'] ?? null),
                (int) $target['pageNo'],
                $this->nullableString($payload['qr_payload'] ?? null),
                $this->nullableString($payload['qr_state'] ?? null),
                $this->nullableString($payload['display_code'] ?? null),
                (bool) ($payload['manual_confirmed'] ?? false),
                $this->nullableString($payload['observed_set_code'] ?? null),
                expectedRevision: $expectedRevision,
                clientQuad: $quad,
                cropPolicy: $cropPolicy,
                cameraReservation: $reservation,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof ConnectionException) {
                $this->cameraFrameFinalization->markUncertain($reservation);
            } else {
                $this->cameraFrameFinalization->release($reservation, 'domain_capture_failed');
            }
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function captureRetry(OmrCaptureBatch $batch, User $actor, array $payload, int $expectedRevision): void
    {
        $sheet = $this->sheet($batch, $payload);
        $this->retryCaptureSheet->handle(
            $sheet,
            $actor,
            $this->positiveInt($payload['page_no'] ?? 1, 'page_no'),
            expectedRevision: $expectedRevision,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reviewSave(OmrCaptureBatch $batch, User $actor, array $payload, int $expectedRevision): void
    {
        $sheet = $this->sheet($batch, $payload);
        $abstainReason = array_key_exists('abstain_reason', $payload)
            ? $this->nullableString($payload['abstain_reason'])
            : null;

        if (($payload['action'] ?? null) === 'abstain' && ($abstainReason === null || trim($abstainReason) === '')) {
            throw new RuntimeException('An abstention reason is required.');
        }

        $this->reviewCaptureSheet->handle($sheet, $actor, $abstainReason, expectedRevision: $expectedRevision);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveApplicant(OmrCaptureBatch $batch, User $actor, array $payload, int $expectedRevision): void
    {
        $sheet = $this->sheet($batch, $payload);
        $assignment = ExamAssignment::query()
            ->whereKey($this->positiveInt($payload['exam_assignment_id'] ?? null, 'exam_assignment_id'))
            ->firstOrFail();

        $this->resolveApplicant->handle(
            $sheet,
            $assignment,
            $this->requiredString($payload, 'reason'),
            $actor,
            expectedRevision: $expectedRevision,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDuplicate(OmrCaptureBatch $batch, User $actor, array $payload): void
    {
        $survivor = $this->sheet($batch, [
            'sheet_id' => $payload['survivor_sheet_id'] ?? ($payload['sheet_id'] ?? null),
        ]);
        $rejected = OmrCaptureSheet::query()
            ->where('batch_id', $batch->id)
            ->whereKey($this->positiveInt($payload['rejected_sheet_id'] ?? null, 'rejected_sheet_id'))
            ->firstOrFail();

        $this->resolveDuplicate->handle(
            $survivor,
            $rejected,
            $this->requiredString($payload, 'reason'),
            $actor,
            $this->nonNegativeInt($payload['survivor_version'] ?? null, 'survivor_version'),
            $this->nonNegativeInt($payload['rejected_version'] ?? null, 'rejected_version'),
        );
    }

    private function productionComplete(OmrCaptureBatch $batch, User $actor, int $expectedRevision): void
    {
        $this->confirmCaptureBatch->handle($batch, $actor, $expectedRevision);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sheet(OmrCaptureBatch $batch, array $payload): OmrCaptureSheet
    {
        return OmrCaptureSheet::query()
            ->where('batch_id', $batch->id)
            ->whereKey($this->sheetId($payload))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sheetId(array $payload): int
    {
        if (array_key_exists('capture_sheet_id', $payload)) {
            throw new RuntimeException('The OMR command field capture_sheet_id is unsupported.');
        }

        return $this->positiveInt($payload['sheet_id'] ?? null, 'sheet_id');
    }

    private function workbenchIdFor(OmrCaptureBatch $batch): string
    {
        return sprintf('production:%d:%d', (int) $batch->grading_session_id, (int) $batch->id);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function identity(string $workbenchId): array
    {
        $workbenchId = trim($workbenchId);
        if (preg_match('/\Aproduction:([1-9][0-9]*):([1-9][0-9]*)\z/D', $workbenchId, $matches) !== 1) {
            throw new RuntimeException('The production OMR workbench identity is invalid.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function expectedRevision(array $payload): int
    {
        if (! array_key_exists('expected_revision', $payload) || $payload['expected_revision'] === null) {
            throw new RuntimeException('The current OMR revision is required before a production command can run.');
        }

        return $this->positiveInt($payload['expected_revision'], 'expected_revision');
    }

    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("The OMR command field {$key} is required.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new RuntimeException('The OMR command contains malformed text.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function optionalArray(array $payload, string $key): ?array
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }
        if (! is_array($payload[$key])) {
            throw new RuntimeException("The OMR command field {$key} must be an array.");
        }

        return $payload[$key];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->positiveInt($value, 'integer');
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $integer = (int) $value;
        } else {
            $integer = 0;
        }

        if ($integer < 1) {
            throw new RuntimeException("The OMR command field {$field} must be positive.");
        }

        return $integer;
    }

    private function nonNegativeInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new RuntimeException("The OMR command field {$field} is required.");
        }

        $integer = is_int($value)
            ? $value
            : (is_string($value) && ctype_digit($value) ? (int) $value : -1);
        if ($integer < 0) {
            throw new RuntimeException("The OMR command field {$field} must not be negative.");
        }

        return $integer;
    }
}
