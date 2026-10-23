<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

use App\Assessment\Actions\Omr\Camera\ExecuteOmrWorkbenchCaptureAction;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation;
use App\Assessment\Actions\Omr\Operational\CaptureOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompareOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompleteOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CancelOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\RecordOmrDiagnosticItemReview;
use App\Assessment\Actions\Omr\Operational\RetakeOmrDiagnosticPage;
use App\Assessment\Actions\Omr\Operational\RetryOmrDiagnosticCapture;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\Queries\OmrCameraCaptureTargetQuery;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Support\TestKitMockSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;
use Illuminate\Database\ConnectionException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

use function array_key_exists;
use function ctype_digit;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function trim;

/**
 * Diagnostic-only authority boundary for the shared OMR workbench.
 *
 * Every writable command is selected from the closed enum below and delegates
 * to one diagnostic action. Production batches, claims, scores, releases,
 * activation, and qualification are intentionally not imported or reachable.
 */
final class DiagnosticOmrAuthorityAdapter implements OmrAuthorityAdapter
{
    private readonly TestKitMockSession $mockSessions;

    public function __construct(
        private readonly OmrDiagnosticRunStatusSnapshot $snapshot,
        private readonly OmrPendingCaptureTarget $pendingCaptureTarget,
        private readonly CaptureOmrDiagnosticRun $capture,
        private readonly RetakeOmrDiagnosticPage $retakePage,
        private readonly RetryOmrDiagnosticCapture $retry,
        private readonly RecordOmrDiagnosticItemReview $review,
        private readonly CompareOmrDiagnosticRun $compare,
        private readonly CompleteOmrDiagnosticRun $complete,
        private readonly CancelOmrDiagnosticRun $cancel,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
        private readonly OmrCameraCaptureTargetQuery $cameraCaptureTarget,
        private readonly ExecuteOmrWorkbenchCaptureAction $captureTransitions,
        ?TestKitMockSession $mockSessions = null,
    ) {
        $this->mockSessions = $mockSessions ?? app(TestKitMockSession::class);
    }

    public function project(
        string $workbenchId,
        int $actorId,
        ?int $captureSheetId = null,
        ?string $captureOperationId = null,
        ?string $captureIdempotencyKey = null,
        ?int $diagnosticPageNo = null,
    ): OmrWorkbenchProjection {
        $run = $this->resolveRun($workbenchId);
        $actor = $this->resolveActor($actorId);
        $this->snapshot->assertCanProject($run, $actor);

        return $this->projection($run, $actor, $captureSheetId, $captureOperationId, $captureIdempotencyKey, $diagnosticPageNo);
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
        $actor = $this->resolveActor($actorId);
        $run = $this->findRun($workbenchId);

        if ($run === null) {
            throw (new ModelNotFoundException)->setModel(OmrDiagnosticRun::class);
        }

        $this->snapshot->assertCanProject($run, $actor);
        $current = $this->projection($run, $actor, null, null, null, $run->hasMockContext() ? null : $this->optionalInt($payload, 'page_no'));
        $existingCaptureOperation = $command === OmrWorkbenchCommand::CaptureStart
            ? $this->cameraFrameFinalization->findOperationForReplay(
                $actor,
                $workbenchId,
                $this->requiredString($payload, 'operation_id'),
                $this->requiredString($payload, 'idempotency_key'),
            )
            : null;
        if ($command !== OmrWorkbenchCommand::DiagnosticCancel && $this->cameraFrameFinalization->hasUnresolvedCaptureOperation(
            $actor,
            $workbenchId,
            $existingCaptureOperation?->operation_id,
        )) {
            return $this->failureProjection($run, $actor, 'blocked', 'omr_diagnostic_capture_outcome_unknown');
        }
        if (! $current->hasCommand($command)
            && $existingCaptureOperation === null
            && $command !== OmrWorkbenchCommand::DiagnosticCancel) {
            return $this->failureProjection(
                $run,
                $actor,
                'blocked',
                'diagnostic_command_not_projected',
            );
        }

        try {
            $this->assertServerContext($workbenchId, $run, $actorId, $payload);
            $result = match ($command) {
                OmrWorkbenchCommand::CaptureStart => $this->captureStart($workbenchId, $run, $payload, $actor, $current),
                OmrWorkbenchCommand::CaptureRetry => $this->captureRetry($run, $payload, $actor),
                OmrWorkbenchCommand::ReviewSave => $this->reviewSave($run, $payload, $actor),
                OmrWorkbenchCommand::DiagnosticCompare => $this->compareRun($run, $payload, $actor),
                OmrWorkbenchCommand::DiagnosticComplete => $this->completeRun($run, $payload, $actor),
                OmrWorkbenchCommand::DiagnosticCancel => $this->cancelRun($run, $payload, $actor),
                default => throw new RuntimeException('diagnostic_command_not_supported'),
            };

            if ($result instanceof OmrDiagnosticRun) {
                $result = ['run_id' => (int) $result->getKey()];
            }
            if (! is_array($result)) {
                throw new RuntimeException('diagnostic_action_result_invalid');
            }

            $targetRun = $this->runFromResult($result, $run);
            $typedOutcome = $this->typedOutcome($result);
            if ($typedOutcome !== null) {
                return $this->failureProjection(
                    $targetRun,
                    $actor,
                    $typedOutcome['kind'],
                    $typedOutcome['code'],
                );
            }

            if (isset($result['pending_capture_id'])) {
                $set = OmrPendingCaptureSet::query()
                    ->where('diagnostic_run_id', $run->id)
                    ->where('created_by_user_id', $actor->id)
                    ->where('public_id', $result['pending_capture_id'])
                    ->firstOrFail();

                return $this->pendingCaptureTarget->withStartedSet(
                    $this->projection(
                        $targetRun,
                        $actor,
                        null,
                        $this->nullableString($payload, 'operation_id'),
                        $this->nullableString($payload, 'idempotency_key'),
                    ),
                    $set,
                );
            }

            $projection = $this->projection(
                $targetRun,
                $actor,
                null,
                $this->nullableString($payload, 'operation_id'),
                $this->nullableString($payload, 'idempotency_key'),
            );
            if ($command === OmrWorkbenchCommand::DiagnosticCancel) {
                $safeProjection = $projection->toSafeArray();
                $recovery = is_array($safeProjection['recovery'] ?? null) ? $safeProjection['recovery'] : [];
                $recovery['outcome'] = ['kind' => 'success'];

                return OmrWorkbenchProjection::fromSafeArray([
                    ...$safeProjection,
                    'recovery' => $recovery,
                ]);
            }

            return $projection;
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            $fresh = $this->refreshRun($run);
            $outcome = $this->classify($exception);

            return $this->failureProjection($fresh, $actor, $outcome['kind'], $outcome['code']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function captureStart(
        string $workbenchId,
        OmrDiagnosticRun $run,
        array $payload,
        User $actor,
        OmrWorkbenchProjection $projection,
    ): array {
        if (($projection->context['camera']['available'] ?? false) !== true) {
            throw new RuntimeException('omr_camera_protocol_unavailable');
        }
        $operationId = $this->requiredString($payload, 'operation_id');
        $idempotencyKey = $this->requiredString($payload, 'idempotency_key');
        $priorOperation = $this->cameraFrameFinalization->findOperationForReplay($actor, $workbenchId, $operationId, $idempotencyKey);
        if ($priorOperation === null
            && $this->cameraFrameFinalization->hasUnresolvedCaptureOperation($actor, $workbenchId)) {
            throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
        }
        if ($priorOperation !== null) {
            $target = is_array($priorOperation->target_json) ? $priorOperation->target_json : [];
            if (($payload['target_key'] ?? null) !== ($target['targetKey'] ?? $target['target_key'] ?? null)
                || ($payload['client_capture_id'] ?? null) !== ($target['clientCaptureId'] ?? $target['client_capture_id'] ?? null)
                || (int) ($payload['page_no'] ?? 0) !== (int) ($target['pageNo'] ?? $target['page_no'] ?? 0)) {
                throw new RuntimeException('omr_camera_operation_fingerprint_conflict');
            }
        } else {
            $publicTarget = $projection->context['capture_target'] ?? null;
            if (! is_array($publicTarget) || ! is_string($publicTarget['target_key'] ?? null)) {
                throw new RuntimeException('omr_camera_capture_target_unavailable');
            }
            $target = $this->canonicalCaptureTarget($projection, $publicTarget);
            $assertion = $target;
            $assertion['targetKey'] = $this->requiredString($payload, 'target_key');
            $target = $this->cameraCaptureTarget->assertCurrentTarget($projection, $actor, $assertion);
            if ($this->requiredString($payload, 'client_capture_id') !== $target['clientCaptureId']
                || $this->requiredInt($payload, 'page_no') !== (int) $target['pageNo']) {
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
        $mockInstanceId = $run->hasMockContext()
            ? (string) ($target['instanceId'] ?? '')
            : null;
        if (array_key_exists('target_revision', $payload)
            && $this->requiredInt($payload, 'target_revision') !== (int) $target['captureRevision']) {
            throw new RuntimeException($run->hasMockContext() ? 'omr_mock_capture_target_stale' : 'omr_camera_capture_target_mismatch');
        }
        if ($run->hasMockContext()) {
            if (! is_string($payload['instance_id'] ?? null) || $payload['instance_id'] !== $mockInstanceId) {
                throw new RuntimeException('omr_mock_capture_target_stale');
            }
        } elseif (array_key_exists('instance_id', $payload)) {
            throw new RuntimeException('omr_mock_capture_target_not_allowed');
        }

        $cropPolicy = $payload['crop_policy'] ?? 'auto_document_quad';
        if (! is_string($cropPolicy) || ! in_array($cropPolicy, ['auto_document_quad', 'keep_original'], true)) {
            throw new RuntimeException('omr_diagnostic_crop_policy_invalid');
        }
        $isStaged = is_string($payload['staged_frame_id'] ?? null);
        if ($isStaged && ($payload['_camera_controller_authenticated'] ?? false) !== true) {
            throw new RuntimeException('omr_camera_controller_authentication_required');
        }
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
        $bytes = $isStaged ? '' : $this->requiredString($payload, 'bytes');
        $mime = $isStaged ? (string) $frame->mime_type : $this->requiredString($payload, 'mime');
        $quad = is_array($payload['client_quad'] ?? null)
            ? $payload['client_quad']
            : ($isStaged
                ? (is_array($frame->client_quad_json) ? $frame->client_quad_json : null)
                : $this->optionalArray($payload, 'client_quad'));
        $inputHash = $isStaged ? (string) $frame->input_sha256 : hash('sha256', $bytes);
        $sourceGeneration = $this->requiredInt($payload, 'source_generation');
        $configRevision = $this->requiredInt($payload, 'config_revision');
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
            'expected_revision' => $payload['expected_revision'] ?? null,
            'pending_capture_id' => $payload['pending_capture_id'] ?? null,
            'pending_capture_revision' => $payload['pending_capture_revision'] ?? null,
            'replacement_reason' => $payload['replacement_reason'] ?? null,
            'instance_id' => $payload['instance_id'] ?? null,
            'target_revision' => $payload['target_revision'] ?? null,
            'source_type' => $isStaged ? 'staged' : 'raw',
            'frame_byte_count' => $isStaged ? (int) $frame->byte_count : null,
            'frame_width' => $isStaged ? (int) $frame->width : null,
            'frame_height' => $isStaged ? (int) $frame->height : null,
            'frame_orientation' => $isStaged ? (int) $frame->orientation : null,
            'pairing_id' => $pairingId,
            'camera_command_id' => $commandId,
            'staged_frame_id' => $frameId,
        ]);
        $workbenchBinding = $priorOperation !== null && is_array($priorOperation->payload_json['workbench'] ?? null)
            ? $priorOperation->payload_json['workbench']
            : [
                'workbench_id' => $workbenchId,
                'diagnostic_run_id' => (int) $run->id,
                'revision' => $this->requiredInt($payload, 'expected_revision'),
            ];
        $reservation = $this->cameraFrameFinalization->reserveOperation(
            $actor,
            hash('sha256', $workbenchId),
            $workbenchBinding,
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
            return [
                'run_id' => (int) $run->id,
                'capture_id' => (int) ($reservation->replayReceipt['capture_id'] ?? 0),
            ];
        }

        try {
            if ($isStaged) {
                $bytes = $this->cameraFrameFinalization->resolveOriginal($reservation);
            }
            $templateVersion = OmrAnswerSheetTemplateVersion::query()->findOrFail((int) $run->template_version_id);
            if ($templateVersion->qr_mode === OmrQrMode::Disabled) {
                $pendingCaptureId = $target['pending_capture_id'] ?? null;
                if (! is_string($pendingCaptureId) || ($payload['pending_capture_id'] ?? null) !== $pendingCaptureId) {
                    throw new RuntimeException('omr_capture_set_page_target_mismatch');
                }
                $setQuery = OmrPendingCaptureSet::query()
                    ->where('diagnostic_run_id', $run->id)
                    ->where('created_by_user_id', $actor->id);
                if ($mockInstanceId !== null) {
                    $setQuery->where('mock_instance_key', $mockInstanceId);
                }
                $set = $setQuery->where('public_id', $pendingCaptureId)->firstOrFail();
                $pendingRevision = $this->requiredInt(
                    ['pending_capture_revision' => $target['pending_capture_revision'] ?? null],
                    'pending_capture_revision',
                );
                $pendingEvidenceReplay = OmrPendingCapturePageEvidence::query()
                    ->where('set_id', (int) $set->id)
                    ->where('client_capture_id', (string) $target['clientCaptureId'])
                    ->where('page_no', (int) $target['pageNo'])
                    ->where('input_hash', hash('sha256', $bytes))
                    ->exists();
                if ($this->optionalInt($payload, 'pending_capture_revision') !== $pendingRevision
                    || ((int) $set->revision !== $pendingRevision && ! $pendingEvidenceReplay)) {
                    throw new RuntimeException('omr_capture_set_revision_stale');
                }
                if (is_string($redoAuthorizationId)) {
                    $result = $this->captureTransitions->captureDiagnosticRedo(
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
                } else {
                    $result = $this->retakePage->handle(
                        $set,
                        $actor,
                        $this->requiredInt($payload, 'expected_revision'),
                        $pendingRevision,
                        (int) $target['pageNo'],
                        (string) $target['clientCaptureId'],
                        $bytes,
                        $mime,
                        null,
                        $quad,
                        $mockInstanceId,
                        $cropPolicy,
                        $reservation,
                    );
                }
                $pendingCaptureId = (string) $result['set']->public_id;

                return ['run_id' => (int) $result['run']->id, 'pending_capture_id' => $pendingCaptureId];
            }

            if (array_key_exists('pending_capture_id', $payload)
                || array_key_exists('pending_capture_revision', $payload)
                || array_key_exists('replacement_reason', $payload)) {
                throw new RuntimeException('omr_capture_set_target_not_allowed_for_signed_qr');
            }

            return $this->capture->handle(
                $run,
                $bytes,
                $mime,
                (string) $target['clientCaptureId'],
                $actor,
                $this->requiredInt($payload, 'expected_revision'),
                $idempotencyKey,
                (int) $target['pageNo'],
                $quad,
                $mockInstanceId,
                $run->hasMockContext() ? (int) $target['captureRevision'] : null,
                $cropPolicy,
                $operationId,
                $reservation,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof ConnectionException) {
                $this->cameraFrameFinalization->markUncertain($reservation);
            } elseif ($this->hasPersistedPendingPageForReplay($reservation)) {
                $this->cameraFrameFinalization->releaseForRetry($reservation);
            } else {
                $this->cameraFrameFinalization->release($reservation, 'domain_capture_failed');
            }
            throw $exception;
        }
    }

    private function hasPersistedPendingPageForReplay(OmrCameraFrameReservation $reservation): bool
    {
        $operation = OmrCameraCommand::query()->whereKey($reservation->operation->id)->first();
        if ($operation === null) {
            return false;
        }
        $workbench = (array) ($operation->payload_json['workbench'] ?? []);
        $target = (array) $operation->target_json;
        $runId = $workbench['diagnostic_run_id'] ?? null;
        $pendingCaptureId = $target['pending_capture_id'] ?? null;
        $clientCaptureId = $target['clientCaptureId'] ?? $target['client_capture_id'] ?? null;
        $pageNo = $target['pageNo'] ?? $target['page_no'] ?? null;
        if (! is_numeric($runId) || ! is_string($pendingCaptureId) || $pendingCaptureId === ''
            || ! is_string($clientCaptureId) || ! is_numeric($pageNo)) {
            return false;
        }

        $set = OmrPendingCaptureSet::query()
            ->where('diagnostic_run_id', (int) $runId)
            ->whereNull('production_batch_id')
            ->where('public_id', $pendingCaptureId)
            ->first();
        if ($set === null) {
            return false;
        }
        $evidence = OmrPendingCapturePageEvidence::query()
            ->where('set_id', (int) $set->id)
            ->where('client_capture_id', $clientCaptureId)
            ->where('page_no', (int) $pageNo)
            ->first();
        if ($evidence === null) {
            return false;
        }

        foreach ((array) ($operation->payload_json['source_assets'] ?? []) as $asset) {
            if (is_array($asset) && ($asset['path'] ?? null) === $evidence->asset_path
                && is_string($asset['hmac'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function captureRetry(OmrDiagnosticRun $run, array $payload, User $actor): array
    {
        if ($this->cameraFrameFinalization->hasUnresolvedCaptureOperation($actor, 'diagnostic:'.$run->public_id)) {
            throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
        }

        $capture = OmrDiagnosticCapture::query()->findOrFail(
            $this->requiredInt($payload, 'capture_id'),
        );

        if ((int) $capture->diagnostic_run_id !== (int) $run->getKey()) {
            throw new RuntimeException('diagnostic_capture_run_mismatch');
        }

        return $this->retry->handle(
            $run,
            $capture,
            $actor,
            $this->requiredInt($payload, 'expected_revision'),
            $this->nullableString($payload, 'idempotency_key'),
            $this->nullableString($payload, 'client_capture_id'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function reviewSave(OmrDiagnosticRun $run, array $payload, User $actor): array
    {
        $item = OmrDiagnosticItemEvidence::query()
            ->with('capture')
            ->findOrFail($this->requiredInt($payload, 'item_evidence_id'));
        $capture = $item->capture;
        if (! $capture instanceof OmrDiagnosticCapture
            || (int) $capture->diagnostic_run_id !== (int) $run->getKey()) {
            throw new RuntimeException('diagnostic_item_run_mismatch');
        }

        $decision = OmrReviewDecision::tryFrom($this->requiredString($payload, 'decision'));
        if ($decision === null) {
            throw new RuntimeException('diagnostic_review_decision_invalid');
        }

        return $this->review->handle(
            $item,
            $decision,
            $this->requiredInt($payload, 'expected_run_revision'),
            $this->nonNegativeInt($payload, 'expected_capture_version'),
            $this->nonNegativeInt($payload, 'expected_item_revision'),
            $this->requiredInt($payload, 'expected_evidence_version'),
            $this->requiredString($payload, 'reviewed_evidence_hash'),
            $this->requiredString($payload, 'idempotency_key'),
            $actor,
            $this->nullableString($payload, 'final_option'),
            $this->nullableString($payload, 'reason'),
            $this->nullableString($payload, 'note'),
        );
    }

    private function compareRun(OmrDiagnosticRun $run, array $payload, User $actor): array
    {

        return $this->compare->handle(
            $run,
            $actor,
            $this->requiredInt($payload, 'expected_revision'),
            $this->requiredString($payload, 'idempotency_key'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeRun(OmrDiagnosticRun $run, array $payload, User $actor): array
    {
        return $this->complete->handle(
            $run,
            $actor,
            $this->requiredInt($payload, 'expected_revision'),
            $this->requiredString($payload, 'idempotency_key'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cancelRun(OmrDiagnosticRun $run, array $payload, User $actor): array
    {
        return $this->cancel->handle(
            $run,
            $actor,
            $this->requiredInt($payload, 'expected_revision'),
            $this->requiredString($payload, 'reason'),
            $this->requiredString($payload, 'idempotency_key'),
        );
    }

    /**
     * Reject client-owned context claims before a diagnostic action is called.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertServerContext(
        string $workbenchId,
        OmrDiagnosticRun $run,
        int $actorId,
        array $payload,
    ): void {
        if (array_key_exists('workbench_id', $payload)
            && (string) $payload['workbench_id'] !== $workbenchId) {
            throw new RuntimeException('diagnostic_workbench_identity_mismatch');
        }
        if (array_key_exists('run_id', $payload)
            && $this->requiredInt($payload, 'run_id') !== (int) $run->getKey()) {
            throw new RuntimeException('diagnostic_run_identity_mismatch');
        }
        if (array_key_exists('actor_id', $payload)
            && $this->requiredInt($payload, 'actor_id') !== $actorId) {
            throw new RuntimeException('diagnostic_actor_identity_mismatch');
        }

        foreach (['execution_kind', 'kind', 'mode'] as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null) {
                continue;
            }
            if (strtolower(trim((string) $payload[$key])) !== OmrExecutionKind::Diagnostic->value) {
                throw new RuntimeException('diagnostic_execution_kind_mismatch');
            }
        }
    }

    private function projection(
        OmrDiagnosticRun $run,
        User $actor,
        ?int $captureSheetId = null,
        ?string $captureOperationId = null,
        ?string $captureIdempotencyKey = null,
        ?int $diagnosticPageNo = null,
    ): OmrWorkbenchProjection {
        $projection = $this->snapshot->forRun($run, $actor);
        $context = is_array($projection['context'] ?? null) ? $projection['context'] : [];
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
        ];
        if (! $cameraAvailable) {
            $context['camera']['availability_reason'] = 'omr_camera_protocol_unavailable';
        }
        $context['pending_capture'] = $this->pendingCaptureTarget->forDiagnostic($run, $actor);
        $projection['context'] = $context;
        $projected = OmrWorkbenchProjection::fromSafeArray($projection);
        $hasUnresolvedOperation = $cameraAvailable
            && $this->cameraFrameFinalization->hasUnresolvedCaptureOperation($actor, 'diagnostic:'.$run->public_id);
        $hasActiveCapture = OmrDiagnosticCapture::query()
            ->where('diagnostic_run_id', $run->id)
            ->whereIn('status', [
                OmrDiagnosticCapture::STATUS_RECEIVED,
                OmrDiagnosticCapture::STATUS_QUEUED,
                OmrDiagnosticCapture::STATUS_PROCESSING,
            ])
            ->exists();
        $target = $cameraAvailable && ! $hasUnresolvedOperation && ! $hasActiveCapture
            ? $this->cameraCaptureTarget->forProjection($projected, $actor, $captureSheetId, $diagnosticPageNo)
            : null;
        if ($target !== null) {
            $context['capture_target'] = $this->publicCaptureTarget($target);
        } else {
            $context['capture_target'] = null;
        }
        if ($captureOperationId !== null && $captureIdempotencyKey !== null) {
            $operation = $cameraAvailable
                ? $this->cameraFrameFinalization->findOperationReceipt(
                    $actor,
                    $projected->workbenchId,
                    $captureOperationId,
                    $captureIdempotencyKey,
                )
                : null;
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
        } elseif ($cameraAvailable && ! $hasUnresolvedOperation && ! $hasActiveCapture) {
            $retakeTarget = is_array($context['capture_target'] ?? null)
                && is_string($context['capture_target']['redo_operation_id'] ?? null)
                && is_numeric($context['capture_target']['replaces_capture_id'] ?? null)
                ? $context['capture_target']
                : null;
            if ($retakeTarget !== null) {
                $sourceCapture = OmrDiagnosticCapture::query()
                    ->whereKey((int) $retakeTarget['replaces_capture_id'])
                    ->where('diagnostic_run_id', $run->id)
                    ->first();
                if ($sourceCapture !== null && $sourceCapture->failure_code !== 'discarded_by_operator') {
                    $selected = $this->cameraFrameFinalization->findAcceptedOperationReceipt(
                        $actor,
                        $projected->workbenchId,
                        (string) $retakeTarget['redo_operation_id'],
                    );
                    if ($selected !== null && (int) ($selected['receipt']['diagnostic_capture_id'] ?? 0) === (int) $sourceCapture->id) {
                        $context['capture_receipt'] = [
                            'operation_id' => (string) ($selected['receipt']['operation_id'] ?? ''),
                            ...((array) ($selected['receipt'] ?? [])),
                            'status' => (string) $selected['status'],
                        ];
                    }
                }
            } elseif ($retakeTarget === null) {
                $latest = $this->cameraFrameFinalization->findLatestAcceptedOperationReceipt($actor, $projected->workbenchId);
                $latestCaptureId = $latest['receipt']['diagnostic_capture_id'] ?? $latest['receipt']['capture_id'] ?? null;
                $captureStillProjected = $latestCaptureId !== null && array_any(
                    $projection['sheets'],
                    static fn (array $sheet): bool => (int) ($sheet['id'] ?? 0) === (int) $latestCaptureId,
                );
                if ($latest !== null && $captureStillProjected) {
                    $context['capture_receipt'] = [
                        'operation_id' => (string) ($latest['receipt']['operation_id'] ?? ''),
                        ...((array) ($latest['receipt'] ?? [])),
                        'status' => 'accepted',
                    ];
                }
            }
        }
        $projection['context'] = $context;
        if ($cameraAvailable) {
            $projectionForActions = OmrWorkbenchProjection::fromSafeArray($projection);
            $url = route('setup.exam-materials.omr-test.runs.capture-actions', ['run' => $run->public_id]);
            $urls = array_fill_keys(['next_page', 'next_set', 'redo_last', 'capture_retake'], $url);
            $context['capture_actions'] = $this->captureTransitions->projectAvailableActions(
                $actor,
                $projectionForActions->workbenchId,
                $projectionForActions,
                $urls,
            );
            $retakeGrants = $this->captureTransitions->projectSelectedPageRetakeGrants(
                $actor,
                $projectionForActions,
                $url,
            );
            $projection['sheets'] = array_map(
                static function (array $sheet) use ($retakeGrants): array {
                    $grant = $retakeGrants[(int) ($sheet['id'] ?? 0)] ?? null;
                    if (is_array($grant)) {
                        $sheet['capture_retake'] = $grant;
                    }

                    return $sheet;
                },
                $projection['sheets'],
            );
        }
        $projection['context'] = $context;

        return OmrWorkbenchProjection::fromSafeArray($projection);
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
            'registration_mode' => $target['registration_mode'] ?? null,
            'qr_mode' => $target['qr_mode'] ?? null,
            'identity_mode' => $target['identity_mode'] ?? null,
            'supplier_stock_allocation_id' => $target['supplier_stock_allocation_id'] ?? null,
            'redo_authorization_id' => $target['redo_authorization_id'] ?? null,
            'redo_reason' => $target['redo_reason'] ?? null,
            'redo_operation_id' => $target['redo_operation_id'] ?? null,
            'redo_source_page_id' => $target['redo_source_page_id'] ?? null,
            'redo_source_page_revision' => $target['redo_source_page_revision'] ?? null,
            'redo_source_evidence_id' => $target['redo_source_evidence_id'] ?? null,
            'redo_accepted_target_key' => $target['redo_accepted_target_key'] ?? null,
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

    private function resolveRun(string $workbenchId): OmrDiagnosticRun
    {
        return $this->findRun($workbenchId) ?? throw new InvalidArgumentException('Diagnostic workbench run was not found.');
    }

    private function findRun(string $workbenchId): ?OmrDiagnosticRun
    {
        $publicId = $this->publicId($workbenchId);

        return OmrDiagnosticRun::query()
            ->where('public_id', $publicId)
            ->where('execution_kind', OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC)
            ->first();
    }

    private function refreshRun(OmrDiagnosticRun $run): OmrDiagnosticRun
    {
        return OmrDiagnosticRun::query()
            ->where('execution_kind', OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC)
            ->findOrFail($run->getKey());
    }

    private function runFromResult(array $result, ?OmrDiagnosticRun $fallback): OmrDiagnosticRun
    {
        $runId = $result['run_id'] ?? $fallback?->getKey();
        if (! is_int($runId) && ! (is_string($runId) && ctype_digit($runId))) {
            throw new RuntimeException('diagnostic_action_run_missing');
        }

        return OmrDiagnosticRun::query()
            ->where('execution_kind', OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC)
            ->findOrFail((int) $runId);
    }

    private function resolveActor(int $actorId): User
    {
        if ($actorId < 1) {
            throw new InvalidArgumentException('Diagnostic actor is invalid.');
        }

        return User::query()->findOrFail($actorId);
    }

    private function publicId(string $workbenchId): string
    {
        if (! str_contains($workbenchId, ':') || ! str_starts_with($workbenchId, 'diagnostic:')) {
            throw new InvalidArgumentException('Diagnostic workbench identity is invalid.');
        }

        $publicId = trim(substr($workbenchId, strlen('diagnostic:')));
        if ($publicId === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $publicId) !== 1) {
            throw new InvalidArgumentException('Diagnostic workbench identity is invalid.');
        }

        return $publicId;
    }

    /**
     * @return array{kind:string,code:string}|null
     */
    private function typedOutcome(array $result): ?array
    {
        $outcome = strtolower((string) ($result['outcome'] ?? ''));
        if (in_array($outcome, ['stale', 'blocked', 'invalid', 'denied'], true)) {
            return [
                'kind' => $outcome === 'denied' ? 'blocked' : $outcome,
                'code' => (string) ($result['code'] ?? $result['error_code'] ?? 'diagnostic_'.$outcome),
            ];
        }

        return null;
    }

    /**
     * @return array{kind:string,code:string}
     */
    private function classify(Throwable $exception): array
    {
        $message = strtolower(trim($exception->getMessage()));
        $code = $this->errorCode($message);
        if (str_contains($message, 'stale') || str_contains($message, 'version') || str_contains($message, 'concurrency')
            || str_contains($message, 'conflict') || str_contains($message, 'mismatch')) {
            return ['kind' => 'stale', 'code' => $code];
        }
        if (str_contains($message, 'denied') || str_contains($message, 'authoriz') || str_contains($message, 'forbidden')
            || str_contains($message, 'blocked') || str_contains($message, 'not_allowed') || str_contains($message, 'permission')) {
            return ['kind' => 'blocked', 'code' => $code];
        }
        if (str_contains($message, 'invalid') || str_contains($message, 'missing') || str_contains($message, 'required')
            || str_contains($message, 'unsupported') || str_contains($message, 'mismatch')) {
            return ['kind' => 'invalid', 'code' => $code];
        }

        return ['kind' => 'blocked', 'code' => $code];
    }

    private function errorCode(string $message): string
    {
        if (preg_match('/^[a-z][a-z0-9_:-]{2,127}/', $message, $matches) === 1) {
            return (string) $matches[0];
        }

        return 'diagnostic_action_failed';
    }

    private function failureProjection(
        OmrDiagnosticRun $run,
        User $actor,
        string $kind,
        string $code,
    ): OmrWorkbenchProjection {
        $projection = $this->projection($run, $actor)->toSafeArray();
        $recovery = is_array($projection['recovery'] ?? null) ? $projection['recovery'] : [];
        $recovery['outcome'] = [
            'kind' => $kind,
            'code' => $code,
            'blocked' => $kind === 'blocked',
            'stale' => $kind === 'stale',
            'invalid' => $kind === 'invalid',
        ];

        return OmrWorkbenchProjection::fromSafeArray([
            ...$projection,
            'recovery' => $recovery,
        ]);
    }

    private function requiredInt(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new InvalidArgumentException('diagnostic_'.$key.'_invalid');
    }

    private function nonNegativeInt(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException('diagnostic_'.$key.'_invalid');
    }

    private function optionalInt(array $payload, string $key): ?int
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
            return null;
        }

        return $this->requiredInt($payload, $key);
    }

    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('diagnostic_'.$key.'_invalid');
        }

        return $value;
    }

    private function nullableString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        return $this->requiredString($payload, $key);
    }

    /** @return array<string, mixed>|null */
    private function optionalArray(array $payload, string $key): ?array
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }
        if (! is_array($payload[$key])) {
            throw new InvalidArgumentException('diagnostic_'.$key.'_invalid');
        }

        return $payload[$key];
    }
}
