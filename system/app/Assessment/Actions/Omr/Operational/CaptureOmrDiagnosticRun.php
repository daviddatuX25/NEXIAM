<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticEvidence;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Assessment\Support\TestKitMockSession;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Database\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function array_values;
use function hash;
use function in_array;
use function is_array;
use function is_numeric;
use function json_encode;
use function max;
use function preg_replace;
use function strlen;
use function strtolower;
use function trim;

/**
 * Captures and analyses one diagnostic image through the shared analysis port.
 *
 * The action reserves the immutable input before calling the provider, then
 * commits the provider evidence and item rows in a second transaction.  This
 * keeps provider work outside a database transaction while making every
 * durable state transition replay-safe and auditable.
 */
final class CaptureOmrDiagnosticRun
{
    public const COMMAND_NAME = 'omr.diagnostic.capture';

    public const RETRY_COMMAND_NAME = 'omr.diagnostic.capture.retry';

    private const RECEIPT_LEASE_MINUTES = 5;

    private const AGGREGATE_TYPE = 'omr_diagnostic_run';

    public function __construct(
        private readonly OmrCaptureAnalysisPort $analysis,
        private readonly SensitiveAssetStore $assetStore,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrCameraFrameFinalization $cameraFrameFinalization,
    ) {}

    /**
     * @return array{run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: array<string, mixed>|null, identities: array<string, mixed>}
     */
    public function handle(
        OmrDiagnosticRun|int|string $run,
        string $bytes,
        string $mime,
        string $clientCaptureId,
        User $actor,
        int $expectedRevision,
        ?string $idempotencyKey = null,
        int $pageNo = 1,
        ?array $clientQuad = null,
        ?string $caseId = null,
        ?int $targetRevision = null,
        string $cropPolicy = 'auto_document_quad',
        ?string $operationId = null,
        ?OmrCameraFrameReservation $cameraReservation = null,
    ): array {
        $clientCaptureId = trim($clientCaptureId);
        $idempotencyKey = trim($idempotencyKey ?? $clientCaptureId);
        $mime = strtolower(trim($mime));
        if ($cameraReservation !== null
            && (! is_string($operationId)
                || ! hash_equals((string) $cameraReservation->operation->operation_id, $operationId)
                || (string) ($cameraReservation->operation->payload_json['idempotency_key'] ?? '') !== $idempotencyKey)) {
            throw new RuntimeException('omr_camera_operation_identity_mismatch');
        }

        if (! in_array($cropPolicy, ['auto_document_quad', 'keep_original'], true)) {
            throw new RuntimeException('omr_diagnostic_crop_policy_invalid');
        }
        if ($bytes === '' || $mime === '' || $clientCaptureId === '' || $idempotencyKey === '' || strlen($idempotencyKey) > 255 || $pageNo < 1) {
            throw new RuntimeException('omr_diagnostic_capture_input_invalid');
        }
        if (strlen($mime) > 127 || ! in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ], true)) {
            throw new RuntimeException('omr_diagnostic_capture_input_invalid');
        }
        if (! Str::isUuid($clientCaptureId)) {
            throw new RuntimeException('omr_diagnostic_capture_identity_invalid');
        }
        if ($expectedRevision < 1) {
            throw new RuntimeException('omr_diagnostic_run_revision_invalid');
        }

        $resolvedRun = $this->resolveRun($run);
        $this->assertActor($resolvedRun, $actor);
        if ($cropPolicy === 'keep_original'
            && $resolvedRun->templateVersion()->firstOrFail()->registration_mode !== OmrRegistrationMode::DocumentBound) {
            throw new RuntimeException('omr_diagnostic_crop_policy_not_allowed');
        }
        if ($resolvedRun->hasMockContext()
            && (! is_string($caseId)
                || preg_match('/^instance-00[1-3]$/D', $caseId) !== 1
                || $targetRevision !== $expectedRevision)) {
            throw new RuntimeException('omr_mock_capture_target_stale');
        }
        $validatedClientQuad = null;
        if ($clientQuad !== null) {
            try {
                if ($mime === 'application/pdf') {
                    throw new RuntimeException('omr_diagnostic_client_quad_requires_raster');
                }
                $validatedClientQuad = OmrClientQuad::fromArray($clientQuad);
                if (! $validatedClientQuad->isBoundTo(OmrSourceImage::fromBytes($bytes), $pageNo)) {
                    throw new RuntimeException('omr_diagnostic_client_quad_source_mismatch');
                }
            } catch (Throwable $exception) {
                throw new RuntimeException('omr_diagnostic_client_quad_invalid', previous: $exception);
            }
        }
        $inputHash = hash('sha256', $bytes);
        $payloadFingerprint = hash('sha256', json_encode([
            'run_id' => (int) $resolvedRun->id,
            'operation_id' => $operationId,
            'client_capture_id' => $clientCaptureId,
            'input_hash' => $inputHash,
            'mime' => $mime,
            'page_no' => $pageNo,
            'client_quad' => $validatedClientQuad?->toArray(),
            'crop_policy' => $cropPolicy,
            'expected_revision' => $expectedRevision,
            ...($resolvedRun->hasMockContext() ? [
                'mock_instance_id' => $caseId,
                'target_revision' => $targetRevision,
            ] : []),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $createdInputPath = null;
        $plannedInputPath = $cameraReservation === null
            ? null
            : 'omr/diagnostic/'.(string) $resolvedRun->public_id.'/camera-'.(int) $cameraReservation->operation->id.'_input.bin';
        if ($cameraReservation !== null && $plannedInputPath !== null) {
            $this->cameraFrameFinalization->recordAssetIntent(
                $cameraReservation,
                $plannedInputPath,
                $this->assetStore->hmac($bytes),
            );
        }

        try {
            $reservation = DB::transaction(function () use ($resolvedRun, $actor, $bytes, $mime, $clientCaptureId, $idempotencyKey, $expectedRevision, $pageNo, $inputHash, $payloadFingerprint, $validatedClientQuad, $cropPolicy, $plannedInputPath, &$createdInputPath): array {
                $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($resolvedRun->id);
                $this->assertDiagnosticRun($lockedRun, $actor);
                if ($lockedRun->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                    throw new RuntimeException('omr_diagnostic_run_terminal');
                }

                $receipt = $this->lockReceipt($actor, (int) $lockedRun->id, $idempotencyKey);
                if ($receipt !== null) {
                    $this->assertReceiptFingerprint($receipt, $payloadFingerprint);
                    if ((string) $receipt->status === 'succeeded') {
                        $receiptResult = $this->receiptResult($receipt);
                        $existingId = (int) ($receiptResult['capture_id'] ?? 0);
                        $existing = OmrDiagnosticCapture::query()
                            ->whereKey($existingId)
                            ->where('diagnostic_run_id', $lockedRun->id)
                            ->with(['inputAsset', 'alignedAsset', 'overlayAsset', 'evidenceItems'])
                            ->first();
                        if ($existing === null) {
                            throw new RuntimeException('omr_diagnostic_receipt_stale');
                        }
                        if (! in_array($existing->status, [
                            OmrDiagnosticCapture::STATUS_FAILED,
                            OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                            OmrDiagnosticCapture::STATUS_REVIEWED,
                            OmrDiagnosticCapture::STATUS_COMPLETED,
                        ], true)) {
                            throw new RuntimeException('omr_diagnostic_receipt_stale');
                        }

                        return [
                            'replay' => true,
                            'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                            'capture' => $existing,
                            'receipt_id' => null,
                        ];
                    }
                    if ((string) $receipt->status === 'started'
                        && $receipt->lease_expires_at !== null
                        && strtotime((string) $receipt->lease_expires_at) > time()) {
                        throw new OmrDiagnosticCaptureInProgress;
                    }

                    $receiptId = (int) $receipt->id;
                    $executionToken = (string) Str::uuid();
                    DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                        'status' => 'started',
                        'execution_token' => $executionToken,
                        'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                        'safe_result_json' => null,
                        'updated_at' => now(),
                    ]);
                } else {
                    $executionToken = (string) Str::uuid();
                    $receiptId = (int) DB::table('omr_command_receipts')->insertGetId([
                        'actor_id' => (int) $actor->id,
                        'command_name' => self::COMMAND_NAME,
                        'aggregate_type' => self::AGGREGATE_TYPE,
                        'aggregate_id' => (string) $lockedRun->id,
                        'idempotency_key' => $idempotencyKey,
                        'payload_fingerprint' => $payloadFingerprint,
                        'status' => 'started',
                        'execution_token' => $executionToken,
                        'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                        'safe_result_json' => null,
                        'correlation_id' => (string) Str::uuid(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $existing = OmrDiagnosticCapture::query()
                    ->where('diagnostic_run_id', $lockedRun->id)
                    ->where('client_capture_id', $clientCaptureId)
                    ->with(['inputAsset', 'alignedAsset', 'overlayAsset', 'evidenceItems'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals((string) $existing->input_asset_hash, $inputHash)) {
                        throw new RuntimeException('omr_diagnostic_idempotency_conflict');
                    }
                    $existingCropPolicy = is_array($existing->inputAsset?->metadata)
                        ? ($existing->inputAsset->metadata['crop_policy'] ?? 'auto_document_quad')
                        : 'auto_document_quad';
                    if ($existing->inputAsset === null
                        || (string) $existing->inputAsset->mime !== $mime
                        || (int) $existing->inputAsset->page_no !== $pageNo
                        || $existingCropPolicy !== $cropPolicy) {
                        throw new RuntimeException('omr_diagnostic_idempotency_conflict');
                    }
                    if ($existing->status === OmrDiagnosticCapture::STATUS_PROCESSING) {
                        // The initial reservation advances the run revision once.
                        $this->assertExpectedRevision($lockedRun, $expectedRevision + 1);

                        return [
                            'replay' => false,
                            'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                            'capture' => $existing->fresh(['inputAsset']),
                            'receipt_id' => $receiptId,
                            'execution_token' => $executionToken,
                        ];
                    }
                    if (! in_array($existing->status, [
                        OmrDiagnosticCapture::STATUS_FAILED,
                        OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                        OmrDiagnosticCapture::STATUS_REVIEWED,
                        OmrDiagnosticCapture::STATUS_COMPLETED,
                    ], true)) {
                        throw new RuntimeException('omr_diagnostic_capture_active');
                    }
                    DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                        'status' => 'succeeded',
                        'lease_expires_at' => null,
                        'safe_result_json' => json_encode([
                            'outcome' => 'succeeded',
                            'run_id' => (int) $lockedRun->id,
                            'capture_id' => (int) $existing->id,
                        ], JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);

                    return [
                        'replay' => true,
                        'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                        'capture' => $existing,
                        'receipt_id' => null,
                    ];
                }

                $this->assertExpectedRevision($lockedRun, $expectedRevision);
                if ($lockedRun->isTerminal()) {
                    throw new RuntimeException('omr_diagnostic_run_terminal');
                }
                if (OmrDiagnosticCapture::query()
                    ->where('diagnostic_run_id', $lockedRun->id)
                    ->whereIn('status', [OmrDiagnosticCapture::STATUS_QUEUED, OmrDiagnosticCapture::STATUS_PROCESSING])
                    ->exists()) {
                    throw new RuntimeException('omr_diagnostic_capture_active');
                }

                $inputPath = $plannedInputPath ?? $this->assetPath($lockedRun, $clientCaptureId, 'input');
                $storedPath = $this->assetStore->store($bytes, $inputPath);
                $createdInputPath = $storedPath;
                $inputAsset = $this->createAsset(
                    $lockedRun,
                    $storedPath,
                    $mime,
                    $bytes,
                    $pageNo,
                    'capture_input',
                    [
                        'idempotency_key_hash' => hash('sha256', $idempotencyKey),
                        'crop_policy' => $cropPolicy,
                    ],
                );

                $capture = OmrDiagnosticCapture::query()->create([
                    'diagnostic_run_id' => $lockedRun->id,
                    'client_capture_id' => $clientCaptureId,
                    'status' => OmrDiagnosticCapture::STATUS_PROCESSING,
                    'version' => 0,
                    'input_asset_id' => $inputAsset->id,
                    'input_asset_hash' => $inputHash,
                    'created_by_user_id' => $actor->id,
                    'captured_at' => now(),
                ]);

                $lockedRun->forceFill([
                    'status' => OmrDiagnosticRun::STATUS_PROCESSING,
                    'phase' => OmrDiagnosticRun::PHASE_PROCESSING,
                    'revision' => (int) $lockedRun->revision + 1,
                ])->save();

                $this->recordAuditEvent->handle('omr.diagnostic.capture_started', [
                    'run_id' => (int) $lockedRun->id,
                    'capture_id' => (int) $capture->id,
                    'client_capture_id' => $clientCaptureId,
                    'input_asset_id' => (int) $inputAsset->id,
                    'input_hash' => $inputHash,
                    'page_no' => $pageNo,
                    'client_quad_attached' => $validatedClientQuad !== null,
                ], (string) $actor->id);

                return [
                    'replay' => false,
                    'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                    'capture' => $capture->fresh(['inputAsset']),
                    'execution_token' => $executionToken,
                    'receipt_id' => $receiptId,
                ];
            });
        } catch (Throwable $exception) {
            if ($createdInputPath !== null && $cameraReservation === null) {
                $this->deleteAssetPath($createdInputPath);
            }

            throw $exception;
        }

        /** @var OmrDiagnosticRun $reservedRun */
        $reservedRun = $reservation['run'];
        /** @var OmrDiagnosticCapture $reservedCapture */
        $reservedCapture = $reservation['capture'];

        if ($reservation['replay']) {
            $result = $this->result($reservedRun, $reservedCapture);
            if ($cameraReservation !== null) {
                DB::transaction(function () use ($reservedRun, $reservedCapture, $actor, $cameraReservation, $pageNo): void {
                    $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($reservedRun->id);
                    $lockedCapture = OmrDiagnosticCapture::query()->lockForUpdate()->findOrFail($reservedCapture->id);
                    $this->assertDiagnosticRun($lockedRun, $actor);
                    $this->recordAuditEvent->handle('omr.diagnostic.capture_camera_replay_accepted', [
                        'run_id' => (int) $lockedRun->id,
                        'capture_id' => (int) $lockedCapture->id,
                        'input_hash' => (string) $lockedCapture->input_asset_hash,
                    ], (string) $actor->id);
                    $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                        'operation_id' => (string) $cameraReservation->operation->operation_id,
                        'target_key' => (string) ($cameraReservation->operation->target_json['targetKey'] ?? $cameraReservation->operation->target_json['target_key'] ?? ''),
                        'status' => 'accepted',
                        'capture_id' => (int) $lockedCapture->id,
                        'page_id' => (int) $lockedCapture->id,
                        'page_no' => $pageNo,
                        'client_capture_id' => (string) $lockedCapture->client_capture_id,
                        'revision' => (int) $lockedRun->revision,
                    ]);
                });
            }

            return $result;

        }
        $pageCount = 1;
        $target = null;
        $input = new OmrCaptureInput(
            bytes: $bytes,
            mime: $mime,
            pageNo: $pageNo,
            pageCount: $pageCount,
            expectedPageCount: null,
            assetId: (string) $reservedCapture->input_asset_id,
            method: 'diagnostic',
            clientQuad: $validatedClientQuad,
            originalHash: $inputHash,
            cropPolicy: $cropPolicy,
        );
        $prepared = null;
        $failureCode = null;
        $failureDetail = null;

        try {
            $template = $reservedRun->templateVersion()->with('template')->firstOrFail();
            $target = new CompiledVersionDiagnosticTarget($template);
            $pageCount = max(1, $target->pageCount());
            $input = new OmrCaptureInput(
                bytes: $bytes,
                mime: $mime,
                pageNo: $pageNo,
                pageCount: $pageCount,
                expectedPageCount: $pageCount,
                assetId: (string) $reservedCapture->input_asset_id,
                method: 'diagnostic',
                clientQuad: $validatedClientQuad,
                originalHash: $inputHash,
                cropPolicy: $cropPolicy,
            );

            $prepared = $this->analysis->analyze(new OmrCaptureAnalysisRequest(
                capture: $input,
                target: $target,
                plan: null,
                pageNo: $pageNo,
                expectedVectorHash: null,
                diagnosticOptions: [],
                challengeCode: null,
                onPhase: null,
            ));
        } catch (Throwable $exception) {
            report($exception);
            $failureCode = 'analysis_failed';
            $failureDetail = 'The diagnostic capture analysis could not be completed.';
        }

        if ($prepared !== null) {
            $failureCode = $prepared->code;
            $failureDetail = $prepared->detail;
        }
        if ($cameraReservation !== null) {
            try {
                $cameraReservation = $this->cameraFrameFinalization->renew($cameraReservation);
            } catch (Throwable $exception) {
                $this->cameraFrameFinalization->markUncertain($cameraReservation);
                throw $exception;
            }
        }

        try {
            $result = $this->persistAnalysis(
                $reservedRun,
                $reservedCapture,
                $actor,
                $input,
                $target,
                $prepared,
                $failureCode,
                $failureDetail,
                $caseId,
                $idempotencyKey,
                (int) $reservation['receipt_id'],
                (string) $reservation['execution_token'],
                $cameraReservation,
            );
        } catch (ConnectionException $exception) {
            throw $exception;
        } catch (Throwable) {
            $result = $this->persistTypedFailure(
                $reservedRun,
                $reservedCapture,
                $actor,
                $input,
                'omr_diagnostic_evidence_persistence_failed',
                'The diagnostic evidence could not be committed.',
                (int) $reservation['receipt_id'],
                (string) $reservation['execution_token'],
                $cameraReservation,
            );
        }

        return $result;
    }

    /**
     * Reprocesses a prior diagnostic capture using the same encrypted input
     * asset.  A new capture row is appended for every retry; the original
     * capture and its item evidence are never overwritten.
     *
     * @return array{run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: array<string, mixed>|null, identities: array<string, mixed>}
     */
    public function retry(
        OmrDiagnosticRun|int|string $run,
        OmrDiagnosticCapture|int $capture,
        User $actor,
        int $expectedRevision,
        ?string $idempotencyKey = null,
        ?string $clientCaptureId = null,
    ): array {
        $resolvedRun = $this->resolveRun($run);
        $this->assertActor($resolvedRun, $actor);
        $resolvedCapture = $capture instanceof OmrDiagnosticCapture
            ? $capture->exists
                ? $capture->fresh(['inputAsset']) ?? $capture
                : throw new RuntimeException('omr_diagnostic_capture_missing')
            : OmrDiagnosticCapture::query()->with('inputAsset')->find($capture);
        if ($resolvedCapture === null) {
            throw new RuntimeException('omr_diagnostic_capture_missing');
        }
        if ($expectedRevision < 1) {
            throw new RuntimeException('omr_diagnostic_run_revision_invalid');
        }

        $idempotencyKey = trim((string) ($idempotencyKey ?? ''));
        if ($idempotencyKey !== '' && strlen($idempotencyKey) > 255) {
            throw new RuntimeException('omr_diagnostic_capture_identity_invalid');
        }
        $clientCaptureId = trim((string) ($clientCaptureId ?? ''));
        if ($clientCaptureId === '') {
            $clientCaptureId = $idempotencyKey !== ''
                ? $this->retryClientCaptureId($resolvedRun, $resolvedCapture, $idempotencyKey)
                : Str::uuid()->toString();
        }
        if (! Str::isUuid($clientCaptureId)) {
            throw new RuntimeException('omr_diagnostic_capture_identity_invalid');
        }
        if ($idempotencyKey === '') {
            $idempotencyKey = 'retry:'.$clientCaptureId;
        }
        $payloadFingerprint = hash('sha256', json_encode([
            'run_id' => (int) $resolvedRun->id,
            'capture_id' => (int) $resolvedCapture->id,
            'client_capture_id' => $clientCaptureId,
            'expected_revision' => $expectedRevision,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $reservation = DB::transaction(function () use ($resolvedRun, $resolvedCapture, $actor, $expectedRevision, $idempotencyKey, $clientCaptureId, $payloadFingerprint): array {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($resolvedRun->id);
            $this->assertDiagnosticRun($lockedRun, $actor);
            if ($lockedRun->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                throw new RuntimeException('omr_diagnostic_run_terminal');
            }
            if ($this->cameraFrameFinalization->hasUnresolvedCaptureOperation($actor, 'diagnostic:'.$lockedRun->public_id)) {
                throw new RuntimeException('omr_diagnostic_capture_outcome_unknown');
            }
            $original = OmrDiagnosticCapture::query()
                ->whereKey($resolvedCapture->id)
                ->where('diagnostic_run_id', $lockedRun->id)
                ->with('inputAsset')
                ->lockForUpdate()
                ->first();
            if ($original === null) {
                throw new RuntimeException('omr_diagnostic_capture_binding_mismatch');
            }
            if ($original->failure_code === 'discarded_by_operator') {
                throw new RuntimeException('omr_diagnostic_capture_discarded');
            }
            $receipt = $this->lockReceipt(
                $actor,
                (int) $lockedRun->id,
                $idempotencyKey,
                self::RETRY_COMMAND_NAME,
            );
            if ($receipt !== null) {
                $this->assertReceiptFingerprint($receipt, $payloadFingerprint);
                if ((string) $receipt->status === 'succeeded') {
                    $receiptResult = $this->receiptResult($receipt);
                    $receiptCaptureId = (int) ($receiptResult['capture_id'] ?? 0);
                    $existing = OmrDiagnosticCapture::query()
                        ->whereKey($receiptCaptureId)
                        ->where('diagnostic_run_id', $lockedRun->id)
                        ->where('client_capture_id', $clientCaptureId)
                        ->with(['inputAsset', 'alignedAsset', 'overlayAsset', 'evidenceItems'])
                        ->first();
                    if ($existing === null) {
                        throw new RuntimeException('omr_diagnostic_retry_receipt_stale');
                    }
                    if ($existing->failure_code === 'discarded_by_operator') {
                        throw new RuntimeException('omr_diagnostic_capture_discarded');
                    }
                    if (! in_array($existing->status, [
                        OmrDiagnosticCapture::STATUS_FAILED,
                        OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                        OmrDiagnosticCapture::STATUS_REVIEWED,
                        OmrDiagnosticCapture::STATUS_COMPLETED,
                    ], true)) {
                        throw new RuntimeException('omr_diagnostic_retry_receipt_stale');
                    }

                    return [
                        'replay' => true,
                        'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                        'capture' => $existing,
                        'bytes' => null,
                        'receipt_id' => null,
                    ];
                }
                if ((string) $receipt->status === 'started'
                    && $receipt->lease_expires_at !== null
                    && strtotime((string) $receipt->lease_expires_at) > time()) {
                    throw new RuntimeException('omr_diagnostic_retry_idempotency_in_progress');
                }

                $receiptId = (int) $receipt->id;
                $correlationId = Str::isUuid((string) $receipt->correlation_id)
                    ? (string) $receipt->correlation_id
                    : (string) Str::uuid();
                $executionToken = (string) Str::uuid();
                DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                    'status' => 'started',
                    'execution_token' => $executionToken,
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'safe_result_json' => null,
                    'correlation_id' => $correlationId,
                    'updated_at' => now(),
                ]);
            } else {
                $executionToken = (string) Str::uuid();
                $receiptId = (int) DB::table('omr_command_receipts')->insertGetId([
                    'actor_id' => (int) $actor->id,
                    'command_name' => self::RETRY_COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $lockedRun->id,
                    'idempotency_key' => $idempotencyKey,
                    'payload_fingerprint' => $payloadFingerprint,
                    'status' => 'started',
                    'execution_token' => $executionToken,
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'safe_result_json' => null,
                    'correlation_id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $existing = OmrDiagnosticCapture::query()
                ->where('diagnostic_run_id', $lockedRun->id)
                ->where('client_capture_id', $clientCaptureId)
                ->with(['inputAsset', 'alignedAsset', 'overlayAsset', 'evidenceItems'])
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if ((int) $existing->input_asset_id !== (int) $original->input_asset_id
                    || ! hash_equals((string) $existing->input_asset_hash, (string) $original->input_asset_hash)) {
                    throw new RuntimeException('omr_diagnostic_idempotency_conflict');
                }
                if ($existing->status === OmrDiagnosticCapture::STATUS_PROCESSING) {
                    $this->assertExpectedRevision($lockedRun, $expectedRevision);
                    if ($original->inputAsset === null
                        || (string) $original->inputAsset->disk !== 'sensitive'
                        || trim((string) $original->inputAsset->path) === '') {
                        throw new RuntimeException('omr_diagnostic_input_asset_missing');
                    }

                    $metadata = is_array($original->inputAsset->metadata) ? $original->inputAsset->metadata : [];
                    $hmac = is_string($metadata['hmac'] ?? null) ? $metadata['hmac'] : null;
                    $bytes = $this->assetStore->read((string) $original->inputAsset->path, $hmac);
                    if (! hash_equals((string) $original->input_asset_hash, hash('sha256', $bytes))) {
                        throw new RuntimeException('omr_diagnostic_input_asset_integrity_failed');
                    }

                    return [
                        'replay' => false,
                        'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                        'capture' => $existing->fresh(['inputAsset']),
                        'bytes' => $bytes,
                        'receipt_id' => $receiptId,
                    ];
                }
                if (! in_array($existing->status, [
                    OmrDiagnosticCapture::STATUS_FAILED,
                    OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                    OmrDiagnosticCapture::STATUS_REVIEWED,
                    OmrDiagnosticCapture::STATUS_COMPLETED,
                ], true)) {
                    throw new RuntimeException('omr_diagnostic_retry_capture_active');
                }

                $this->completeReceipt(
                    $receiptId,
                    $executionToken,
                    $this->result($lockedRun, $existing),
                );

                return [
                    'replay' => true,
                    'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                    'capture' => $existing->fresh(['inputAsset', 'alignedAsset', 'overlayAsset', 'evidenceItems']),
                    'bytes' => null,
                    'receipt_id' => null,
                ];
            }

            $this->assertExpectedRevision($lockedRun, $expectedRevision);
            if ($lockedRun->isTerminal()) {
                throw new RuntimeException('omr_diagnostic_run_terminal');
            }
            if (! in_array($original->status, [
                OmrDiagnosticCapture::STATUS_FAILED,
                OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                OmrDiagnosticCapture::STATUS_REVIEWED,
            ], true)) {
                throw new RuntimeException('omr_diagnostic_capture_not_retryable');
            }
            if ($original->inputAsset === null
                || (string) $original->inputAsset->disk !== 'sensitive'
                || trim((string) $original->inputAsset->path) === '') {
                throw new RuntimeException('omr_diagnostic_input_asset_missing');
            }

            $metadata = is_array($original->inputAsset->metadata) ? $original->inputAsset->metadata : [];
            $hmac = is_string($metadata['hmac'] ?? null) ? $metadata['hmac'] : null;
            $bytes = $this->assetStore->read((string) $original->inputAsset->path, $hmac);
            if (! hash_equals((string) $original->input_asset_hash, hash('sha256', $bytes))) {
                throw new RuntimeException('omr_diagnostic_input_asset_integrity_failed');
            }

            $child = OmrDiagnosticCapture::query()->create([
                'diagnostic_run_id' => $lockedRun->id,
                'client_capture_id' => $clientCaptureId,
                'status' => OmrDiagnosticCapture::STATUS_PROCESSING,
                'version' => 0,
                'input_asset_id' => $original->input_asset_id,
                'input_asset_hash' => $original->input_asset_hash,
                'created_by_user_id' => $actor->id,
                'captured_at' => now(),
            ]);
            $lockedRun->forceFill([
                'status' => OmrDiagnosticRun::STATUS_PROCESSING,
                'phase' => OmrDiagnosticRun::PHASE_PROCESSING,
                'revision' => (int) $lockedRun->revision + 1,
            ])->save();
            $this->recordAuditEvent->handle('omr.diagnostic.capture_retried', [
                'run_id' => (int) $lockedRun->id,
                'capture_id' => (int) $child->id,
                'replaced_capture_id' => (int) $original->id,
                'input_asset_id' => (int) $original->input_asset_id,
                'input_hash' => (string) $original->input_asset_hash,
            ], (string) $actor->id);

            return [
                'replay' => false,
                'run' => $lockedRun->fresh(['templateVersion', 'bookletVersion']),
                'capture' => $child->fresh(['inputAsset']),
                'bytes' => $bytes,
                'receipt_id' => $receiptId,
                'execution_token' => $executionToken,
            ];
        });

        /** @var OmrDiagnosticRun $reservedRun */
        $reservedRun = $reservation['run'];
        /** @var OmrDiagnosticCapture $reservedCapture */
        $reservedCapture = $reservation['capture'];
        if ($reservation['replay']) {
            return $this->result($reservedRun, $reservedCapture);
        }

        $bytes = (string) $reservation['bytes'];
        $mime = strtolower(trim((string) ($resolvedCapture->inputAsset->mime ?? 'application/octet-stream')));
        $pageNo = max(1, (int) ($resolvedCapture->inputAsset->page_no ?? 1));
        $storedEvidence = is_array($resolvedCapture->evidence_json) ? $resolvedCapture->evidence_json : [];
        $cropPolicy = in_array($storedEvidence['crop_policy'] ?? null, ['auto_document_quad', 'keep_original'], true)
            ? $storedEvidence['crop_policy']
            : 'auto_document_quad';
        $input = new OmrCaptureInput(
            bytes: $bytes,
            mime: $mime,
            pageNo: $pageNo,
            pageCount: 1,
            expectedPageCount: null,
            assetId: (string) $reservedCapture->input_asset_id,
            method: 'diagnostic_retry',
            originalHash: (string) $reservedCapture->input_asset_hash,
            cropPolicy: $cropPolicy,
        );
        $target = null;
        $prepared = null;
        $failureCode = null;
        $failureDetail = null;
        try {
            $template = $reservedRun->templateVersion()->with('template')->firstOrFail();
            $target = new CompiledVersionDiagnosticTarget($template);
            $pageCount = max(1, $target->pageCount());
            $input = new OmrCaptureInput(
                bytes: $bytes,
                mime: $mime,
                pageNo: $pageNo,
                pageCount: $pageCount,
                expectedPageCount: $pageCount,
                assetId: (string) $reservedCapture->input_asset_id,
                method: 'diagnostic_retry',
                originalHash: (string) $reservedCapture->input_asset_hash,
                cropPolicy: $cropPolicy,
            );
            $prepared = $this->analysis->analyze(new OmrCaptureAnalysisRequest(
                capture: $input,
                target: $target,
                plan: null,
                pageNo: $pageNo,
                expectedVectorHash: null,
                diagnosticOptions: [],
                challengeCode: null,
                onPhase: null,
            ));
        } catch (Throwable) {
            $failureCode = 'analysis_failed';
            $failureDetail = 'The diagnostic capture analysis could not be completed.';
        }
        if ($prepared !== null) {
            $failureCode = $prepared->code;
            $failureDetail = $prepared->detail;
        }

        try {
            $result = $this->persistAnalysis(
                $reservedRun,
                $reservedCapture,
                $actor,
                $input,
                $target,
                $prepared,
                $failureCode,
                $failureDetail,
                null,
                $idempotencyKey,
                (int) $reservation['receipt_id'],
                (string) $reservation['execution_token'],
                null,
            );
        } catch (ConnectionException $exception) {
            throw $exception;
        } catch (Throwable) {
            $result = $this->persistTypedFailure(
                $reservedRun,
                $reservedCapture,
                $actor,
                $input,
                'omr_diagnostic_evidence_persistence_failed',
                'The diagnostic evidence could not be committed.',
                (int) $reservation['receipt_id'],
                (string) $reservation['execution_token'],
                null,
            );
        }

        return $result;
    }

    private function retryClientCaptureId(OmrDiagnosticRun $run, OmrDiagnosticCapture $capture, string $idempotencyKey): string
    {
        $hash = hash('sha256', (string) $run->public_id.'|'.(int) $capture->id.'|'.$idempotencyKey);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 16, 3).'-'.substr($hash, 19, 12);
    }

    private function resolveRun(OmrDiagnosticRun|int|string $run): OmrDiagnosticRun
    {
        if ($run instanceof OmrDiagnosticRun) {
            return $run->exists
                ? $run->fresh(['academicCycle', 'templateVersion', 'bookletVersion']) ?? $run
                : throw new RuntimeException('omr_diagnostic_run_missing');
        }

        $query = OmrDiagnosticRun::query()->with(['academicCycle', 'templateVersion', 'bookletVersion']);
        $resolved = is_int($run)
            ? $query->whereKey($run)->first()
            : $query->where('public_id', (string) $run)->first();

        return $resolved ?? throw new RuntimeException('omr_diagnostic_run_missing');
    }

    private function assertActor(OmrDiagnosticRun $run, User $actor): void
    {
        $actor->loadMissing('staffProfile');
        if ($actor->staffProfile === null || ! $actor->staffProfile->isActive()) {
            throw new RuntimeException('omr_diagnostic_owner_unavailable');
        }
        if ((int) $run->created_by_user_id !== (int) $actor->id) {
            throw new RuntimeException('omr_diagnostic_owner_mismatch');
        }

        $this->assertScope($run, $actor);
    }

    private function assertDiagnosticRun(OmrDiagnosticRun $run, User $actor): void
    {
        if ($run->execution_kind !== OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC) {
            throw new RuntimeException('omr_diagnostic_execution_kind_invalid');
        }
        $this->assertActor($run, $actor);
    }

    private function assertScope(OmrDiagnosticRun $run, User $actor): void
    {
        $run->loadMissing('academicCycle');
        $scope = $actor->actorScope();
        if ($scope->institutionId === null || $scope->institutionId !== (int) $run->institution_id) {
            throw new RuntimeException('omr_diagnostic_scope_mismatch');
        }
        if ($scope->activeCycleCode === null || (string) $scope->activeCycleCode !== (string) $run->academicCycle?->code) {
            throw new RuntimeException('omr_diagnostic_scope_mismatch');
        }

        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, [
            'office' => 'guidance',
            'institution_id' => (int) $run->institution_id,
            'cycle' => (string) $run->academicCycle?->code,
        ]);
        if (! $decision->allowed) {
            throw new RuntimeException('omr_diagnostic_capability_'.$decision->reason);
        }
    }

    private function assertExpectedRevision(OmrDiagnosticRun $run, int $expectedRevision): void
    {
        if ($expectedRevision < 1 || $expectedRevision !== (int) $run->revision) {
            throw new RuntimeException('omr_diagnostic_revision_stale');
        }
    }

    /**
     * @return array{run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: array<string, mixed>|null, identities: array<string, mixed>}
     */
    private function result(OmrDiagnosticRun $run, OmrDiagnosticCapture $capture): array
    {
        $capture = $capture->fresh(['inputAsset', 'alignedAsset', 'overlayAsset', 'evidenceItems']) ?? $capture;
        $run = $run->fresh(['templateVersion', 'bookletVersion']) ?? $run;
        $evidence = is_array($capture->evidence_json) ? $this->sanitize($capture->evidence_json) : null;
        $evidenceIds = $capture->evidenceItems->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();

        $identities = [
            'run_id' => (int) $run->id,
            'run_public_id' => (string) $run->public_id,
            'capture_id' => (int) $capture->id,
            'client_capture_id' => (string) $capture->client_capture_id,
            'evidence_id' => $evidenceIds[0] ?? null,
            'evidence_ids' => $evidenceIds,
            'revision' => (int) $run->revision,
        ];

        return [
            'run_id' => (int) $run->id,
            'capture_id' => (int) $capture->id,
            'evidence_id' => $evidenceIds[0] ?? null,
            'run' => $run,
            'capture' => $capture,
            'evidence' => $evidence,
            'identities' => $identities,
        ];
    }

    /**
     * @return array{run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: array<string, mixed>|null, identities: array<string, mixed>}
     */
    private function persistAnalysis(
        OmrDiagnosticRun $run,
        OmrDiagnosticCapture $capture,
        User $actor,
        OmrCaptureInput $input,
        ?object $target,
        ?object $prepared,
        ?string $failureCode,
        ?string $failureDetail,
        ?string $caseId,
        ?string $idempotencyKey,
        int $receiptId,
        string $executionToken,
        ?OmrCameraFrameReservation $cameraReservation,
    ): array {
        $derivedPaths = [];
        if ($cameraReservation !== null && $prepared !== null && method_exists($prepared, 'asDiagnosticEvidence')) {
            try {
                $preparedEvidence = $prepared->asDiagnosticEvidence();
            } catch (Throwable) {
                $preparedEvidence = null;
            }
            $preparedAssets = is_array($preparedEvidence?->assets) ? $preparedEvidence->assets : [];
            foreach (['aligned', 'overlay'] as $assetName) {
                $asset = $preparedAssets[$assetName] ?? null;
                if (is_array($asset) && is_string($asset['bytes'] ?? null) && $asset['bytes'] !== '') {
                    $this->cameraFrameFinalization->recordAssetIntent(
                        $cameraReservation,
                        $this->assetPath($run, (string) $capture->client_capture_id, $assetName),
                        $this->assetStore->hmac($asset['bytes']),
                    );
                }
            }
        }
        $committed = false;
        try {
            $result = DB::transaction(function () use ($run, $capture, $actor, $input, $target, $prepared, $failureCode, $failureDetail, $caseId, $idempotencyKey, $receiptId, $executionToken, $cameraReservation, &$derivedPaths): array {
                $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($run->id);
                $lockedCapture = OmrDiagnosticCapture::query()->lockForUpdate()->findOrFail($capture->id);
                $this->assertDiagnosticRun($lockedRun, $actor);
                if ($lockedRun->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                    throw new RuntimeException('omr_diagnostic_run_terminal');
                }

                if ((int) $lockedCapture->diagnostic_run_id !== (int) $lockedRun->id) {
                    throw new RuntimeException('omr_diagnostic_capture_binding_mismatch');
                }
                if ($lockedCapture->status !== OmrDiagnosticCapture::STATUS_PROCESSING) {
                    $result = $this->result($lockedRun, $lockedCapture);
                    $this->completeReceipt($receiptId, $executionToken, $result);

                    if ($cameraReservation !== null) {
                        $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                            'operation_id' => (string) $cameraReservation->operation->operation_id,
                            'target_key' => (string) ($cameraReservation->operation->target_json['target_key'] ?? ''),
                            'status' => 'accepted',
                            'capture_id' => (int) $lockedCapture->id,
                            'page_id' => (int) $lockedCapture->id,
                            'page_no' => $input->pageNo,
                            'client_capture_id' => (string) $lockedCapture->client_capture_id,
                            'revision' => (int) $lockedRun->revision,
                        ]);
                    }

                    return $result;
                }

                $analysisSucceeded = $prepared !== null
                    && method_exists($prepared, 'succeeded')
                    && $prepared->succeeded();
                $diagnostic = null;
                if ($prepared !== null && method_exists($prepared, 'asDiagnosticEvidence')) {
                    try {
                        $diagnostic = $prepared->asDiagnosticEvidence();
                    } catch (Throwable) {
                        $diagnostic = null;
                    }
                }
                if (! $analysisSucceeded || $diagnostic === null || ! $diagnostic->succeeded()) {
                    $analysisSucceeded = false;
                }

                $rawEvidence = $diagnostic?->normalize();
                if (! is_array($rawEvidence)) {
                    $rawEvidence = $this->fallbackEvidence(
                        $input,
                        $failureCode ?? 'analysis_failed',
                        $failureDetail ?? 'The diagnostic capture analysis could not be completed.',
                        $target,
                    );
                }
                $evidence = $this->sanitize($rawEvidence);
                $evidence['crop_policy'] = $input->cropPolicy ?? 'auto_document_quad';
                $evidence['evidence_hash'] = $this->hashValue($evidence);
                $code = $this->safeCode($failureCode ?? ($diagnostic !== null ? $diagnostic->code : 'analysis_failed'));
                $detail = $failureDetail ?? $diagnostic?->detail;

                $alignedAsset = null;
                $overlayAsset = null;
                $assets = is_array($diagnostic?->assets) ? $diagnostic->assets : [];
                foreach (['aligned', 'overlay'] as $assetName) {
                    $asset = $assets[$assetName] ?? null;
                    if (! is_array($asset) || ! is_string($asset['bytes'] ?? null) || $asset['bytes'] === '') {
                        continue;
                    }
                    $assetBytes = $asset['bytes'];
                    $assetMime = is_string($asset['mime'] ?? null) && $asset['mime'] !== ''
                        ? strtolower($asset['mime'])
                        : 'application/octet-stream';
                    $path = $this->assetPath($lockedRun, (string) $lockedCapture->client_capture_id, $assetName);
                    $storedPath = $this->assetStore->store($assetBytes, $path);
                    $derivedPaths[] = $storedPath;
                    $savedAsset = $this->createAsset(
                        $lockedRun,
                        $storedPath,
                        $assetMime,
                        $assetBytes,
                        (int) ($evidence['page_no'] ?? $input->pageNo),
                        'capture_'.$assetName,
                        [
                            'diagnostic_capture_id' => (int) $lockedCapture->id,
                        ],
                        isset($asset['width']) ? (int) $asset['width'] : null,
                        isset($asset['height']) ? (int) $asset['height'] : null,
                    );
                    if ($assetName === 'aligned') {
                        $alignedAsset = $savedAsset;
                    } else {
                        $overlayAsset = $savedAsset;
                    }
                }

                $items = $this->persistItems($lockedCapture, is_array($evidence['items'] ?? null) ? $evidence['items'] : []);
                $detectedAnswers = $this->detectedAnswers($items);
                $status = $analysisSucceeded
                    ? OmrDiagnosticCapture::STATUS_NEEDS_REVIEW
                    : OmrDiagnosticCapture::STATUS_FAILED;
                $runPhase = $analysisSucceeded
                    ? OmrDiagnosticRun::PHASE_REVIEW
                    : OmrDiagnosticRun::PHASE_RECOVERY;
                $runStatus = OmrDiagnosticRun::STATUS_NEEDS_REVIEW;

                $lockedCapture->forceFill([
                    'status' => $status,
                    'version' => (int) $lockedCapture->version + 1,
                    'aligned_asset_id' => $alignedAsset?->id,
                    'aligned_asset_hash' => $alignedAsset?->sha256,
                    'overlay_asset_id' => $overlayAsset?->id,
                    'overlay_asset_hash' => $overlayAsset?->sha256,
                    'evidence_json' => $evidence,
                    'evidence_hash' => $evidence['evidence_hash'],
                    'detected_answers_json' => $detectedAnswers,
                    'effective_answers_json' => null,
                    'projected_score_json' => null,
                    'failure_code' => $analysisSucceeded ? null : $code,
                    'failure_detail' => $analysisSucceeded ? null : $detail,
                    'completed_at' => $analysisSucceeded ? null : now(),
                ])->save();

                $lockedRun->forceFill([
                    'status' => $runStatus,
                    'phase' => $runPhase,
                    'revision' => (int) $lockedRun->revision + 1,
                ])->save();

                $this->recordAuditEvent->handle(
                    $analysisSucceeded ? 'omr.diagnostic.capture_analyzed' : 'omr.diagnostic.capture_failed',
                    [
                        'run_id' => (int) $lockedRun->id,
                        'capture_id' => (int) $lockedCapture->id,
                        'input_hash' => (string) $lockedCapture->input_asset_hash,
                        'evidence_hash' => (string) $lockedCapture->evidence_hash,
                        'status' => $status,
                        'failure_code' => $analysisSucceeded ? null : $code,
                        'item_count' => count($items),
                    ],
                    (string) $actor->id,
                );

                $this->recordKitAttemptAndSelection(
                    $lockedRun,
                    $lockedCapture,
                    $actor,
                    (int) ($evidence['page_no'] ?? $input->pageNo),
                    $caseId,
                    $idempotencyKey,
                );

                $result = $this->result($lockedRun, $lockedCapture);
                $this->completeReceipt($receiptId, $executionToken, $result);
                if ($cameraReservation !== null) {
                    $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                        'operation_id' => (string) $cameraReservation->operation->operation_id,
                        'target_key' => (string) ($cameraReservation->operation->target_json['target_key'] ?? ''),
                        'status' => 'accepted',
                        'capture_id' => (int) $lockedCapture->id,
                        'page_id' => (int) $lockedCapture->id,
                        'page_no' => $input->pageNo,
                        'client_capture_id' => (string) $lockedCapture->client_capture_id,
                        'revision' => (int) $lockedRun->revision,
                    ]);
                }

                return $result;
            });
            $committed = true;

            return $result;
        } finally {
            if (! $committed && $cameraReservation === null) {
                foreach ($derivedPaths as $path) {
                    $this->deleteAssetPath($path);
                }
            }
        }
    }

    /**
     * Persist a typed failure after an unexpected provider/persistence error.
     * The original input row remains intact and can be retried.
     *
     * @return array{run: OmrDiagnosticRun, capture: OmrDiagnosticCapture, evidence: array<string, mixed>|null, identities: array<string, mixed>}
     */
    private function persistTypedFailure(
        OmrDiagnosticRun $run,
        OmrDiagnosticCapture $capture,
        User $actor,
        OmrCaptureInput $input,
        string $code,
        string $detail,
        int $receiptId,
        string $executionToken,
        ?OmrCameraFrameReservation $cameraReservation,
    ): array {
        return DB::transaction(function () use ($run, $capture, $actor, $input, $code, $detail, $receiptId, $executionToken, $cameraReservation): array {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($run->id);
            $lockedCapture = OmrDiagnosticCapture::query()->lockForUpdate()->findOrFail($capture->id);
            $this->assertDiagnosticRun($lockedRun, $actor);
            if ($lockedRun->status === OmrDiagnosticRun::STATUS_CANCELLED) {
                throw new RuntimeException('omr_diagnostic_run_terminal');
            }
            if ($lockedCapture->status !== OmrDiagnosticCapture::STATUS_PROCESSING) {
                $result = $this->result($lockedRun, $lockedCapture);
                $this->completeReceipt($receiptId, $executionToken, $result);
                if ($cameraReservation !== null) {
                    $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                        'operation_id' => (string) $cameraReservation->operation->operation_id,
                        'target_key' => (string) ($cameraReservation->operation->target_json['target_key'] ?? ''),
                        'status' => 'accepted',
                        'capture_id' => (int) $lockedCapture->id,
                        'page_id' => (int) $lockedCapture->id,
                        'page_no' => $input->pageNo,
                        'client_capture_id' => (string) $lockedCapture->client_capture_id,
                        'revision' => (int) $lockedRun->revision,
                    ]);
                }

                return $result;
            }

            $evidence = $this->fallbackEvidence($input, $code, $detail, null);
            $evidence['crop_policy'] = $input->cropPolicy ?? 'auto_document_quad';
            $evidence['evidence_hash'] = $this->hashValue($evidence);
            $lockedCapture->forceFill([
                'status' => OmrDiagnosticCapture::STATUS_FAILED,
                'version' => (int) $lockedCapture->version + 1,
                'evidence_json' => $evidence,
                'evidence_hash' => $evidence['evidence_hash'],
                'detected_answers_json' => [],
                'effective_answers_json' => null,
                'projected_score_json' => null,
                'failure_code' => $this->safeCode($code),
                'failure_detail' => $detail,
                'completed_at' => now(),
            ])->save();
            $lockedRun->forceFill([
                'status' => OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
                'phase' => OmrDiagnosticRun::PHASE_RECOVERY,
                'revision' => (int) $lockedRun->revision + 1,
            ])->save();
            $this->recordAuditEvent->handle('omr.diagnostic.capture_failed', [
                'run_id' => (int) $lockedRun->id,
                'capture_id' => (int) $lockedCapture->id,
                'input_hash' => (string) $lockedCapture->input_asset_hash,
                'evidence_hash' => (string) $lockedCapture->evidence_hash,
                'status' => OmrDiagnosticCapture::STATUS_FAILED,
                'failure_code' => $this->safeCode($code),
                'item_count' => 0,
            ], (string) $actor->id);

            $result = $this->result($lockedRun, $lockedCapture);
            $this->completeReceipt($receiptId, $executionToken, $result);
            if ($cameraReservation !== null) {
                $this->cameraFrameFinalization->commitAccepted($cameraReservation, [
                    'operation_id' => (string) $cameraReservation->operation->operation_id,
                    'target_key' => (string) ($cameraReservation->operation->target_json['targetKey'] ?? $cameraReservation->operation->target_json['target_key'] ?? ''),
                    'status' => 'accepted',
                    'capture_id' => (int) $lockedCapture->id,
                    'page_id' => (int) $lockedCapture->id,
                    'page_no' => $input->pageNo,
                    'client_capture_id' => (string) $lockedCapture->client_capture_id,
                    'revision' => (int) $lockedRun->revision,
                ]);
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $items
     * @return list<OmrDiagnosticItemEvidence>
     */
    private function persistItems(OmrDiagnosticCapture $capture, array $items): array
    {
        $saved = [];
        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('omr_diagnostic_item_invalid');
            }
            $safeItem = $this->sanitize($item);
            $areaCode = trim((string) ($safeItem['area_code'] ?? ''));
            $questionNo = (int) ($safeItem['question_no'] ?? 0);
            $pageNo = (int) ($safeItem['page_no'] ?? 0);
            if ($areaCode === '' || $questionNo < 1 || $pageNo < 1) {
                throw new RuntimeException('omr_diagnostic_item_invalid');
            }
            $itemKey = trim((string) ($safeItem['native_label'] ?? ''));
            if ($itemKey === '') {
                $itemKey = strtoupper($areaCode).'_Q_'.$questionNo;
            }
            if (strlen($itemKey) > 128) {
                throw new RuntimeException('omr_diagnostic_item_invalid');
            }

            $detected = is_array($safeItem['detected'] ?? null) ? $safeItem['detected'] : [];
            $selectedOption = $detected['selected_option'] ?? ($safeItem['selected_option'] ?? null);
            $selectedOption = $selectedOption === null ? null : trim((string) $selectedOption);
            $confidence = $detected['confidence'] ?? ($safeItem['confidence'] ?? null);
            $confidence = is_numeric($confidence) ? (float) $confidence : null;
            if ($confidence !== null && ($confidence < 0.0 || $confidence > 1.0)) {
                throw new RuntimeException('omr_diagnostic_item_invalid');
            }
            $optionEvidence = is_array($safeItem['options'] ?? null) ? $safeItem['options'] : [];
            $itemHash = hash('sha256', json_encode($safeItem, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $saved[] = OmrDiagnosticItemEvidence::query()->create([
                'diagnostic_capture_id' => $capture->id,
                'item_key' => $itemKey,
                'page_no' => $pageNo,
                'area_code' => $areaCode,
                'question_no' => $questionNo,
                'option_code' => $selectedOption !== '' ? $selectedOption : null,
                'option_evidence_json' => $optionEvidence,
                'machine_proposal_json' => $safeItem,
                'machine_confidence' => $confidence,
                'evidence_hash' => $itemHash,
                'evidence_version' => 'v1',
            ]);
        }

        return $saved;
    }

    /**
     * @param  list<OmrDiagnosticItemEvidence>  $items
     * @return array<string, array{state: ?string, selected_option: ?string, confidence: ?float}>
     */
    private function detectedAnswers(array $items): array
    {
        $answers = [];
        foreach ($items as $item) {
            $proposal = is_array($item->machine_proposal_json) ? $item->machine_proposal_json : [];
            $detected = is_array($proposal['detected'] ?? null) ? $proposal['detected'] : [];
            $answers[(string) $item->item_key] = [
                'state' => isset($detected['state']) ? (string) $detected['state'] : null,
                'selected_option' => $item->option_code !== null ? (string) $item->option_code : null,
                'confidence' => $item->machine_confidence === null ? null : (float) $item->machine_confidence,
            ];
        }

        return $answers;
    }

    private function createAsset(
        OmrDiagnosticRun $run,
        string $path,
        string $mime,
        string $bytes,
        int $pageNo,
        string $role,
        array $metadata = [],
        ?int $width = null,
        ?int $height = null,
    ): OmrTemplateAsset {
        return OmrTemplateAsset::query()->create([
            'template_version_id' => $run->template_version_id,
            'kind' => OmrTemplateAsset::KIND_EVIDENCE,
            'page_no' => $pageNo,
            'disk' => 'sensitive',
            'path' => $path,
            'mime' => $mime,
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'metadata' => [
                'source' => 'omr_diagnostic',
                'diagnostic_run_id' => (int) $run->id,
                'role' => $role,
                'width' => $width,
                'height' => $height,
                'hmac' => $this->assetStore->hmac($bytes),
                ...$metadata,
            ],
        ]);
    }

    private function assetPath(OmrDiagnosticRun $run, string $captureId, string $role): string
    {
        return 'omr/diagnostic/'.(string) $run->public_id.'/'.hash('sha256', $captureId).'_'.$role.'.bin';
    }

    private function deleteAssetPath(string $path): void
    {
        try {
            $this->assetStore->delete($path);
        } catch (Throwable) {
            // Storage cleanup must not replace the typed command failure.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackEvidence(OmrCaptureInput $input, string $code, string $detail, ?object $target): array
    {
        $targetIdentity = ['kind' => 'compiled_version', 'id' => null];
        if ($target !== null && method_exists($target, 'kind') && method_exists($target, 'targetId')) {
            $targetIdentity = [
                'kind' => $target->kind(),
                'id' => $target->targetId(),
            ];
        }

        return [
            'outcome' => OmrDiagnosticEvidence::OUTCOME_FAILED,
            'code' => $this->safeCode($code),
            'detail' => $detail,
            'page_no' => $input->pageNo,
            'target' => $targetIdentity,
            'hashes' => ['input' => $input->sourceHash(), 'aligned' => null, 'overlay' => null],
            'assets' => [
                'captured' => [
                    'mime' => $input->mime,
                    'sha256' => $input->sourceHash(),
                    'bytes' => $input->byteCount,
                ],
                'aligned' => null,
                'overlay' => null,
            ],
            'gates' => null,
            'marker_matches' => null,
            'field_block_shifts' => null,
            'items' => [],
            'identity' => [],
            'summary' => ['total' => 0, 'ambiguous' => 0, 'missing' => 0],
            'timings' => [],
            'issues' => [['code' => $this->safeCode($code), 'severity' => 'terminal']],
            'calibration_status' => null,
            'provider_item_evidence' => null,
        ];
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->forbiddenKey($key)) {
            return null;
        }
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $childKey => $child) {
            if (is_string($childKey) && $this->forbiddenKey($childKey)) {
                continue;
            }
            $result[$childKey] = $this->sanitize($child, is_string($childKey) ? $childKey : null);
        }

        return $result;
    }

    private function forbiddenKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));
        $compact = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
        if ($compact === '') {
            return false;
        }

        foreach (['answerkey', 'correctanswer', 'correctoption', 'expected', 'qualification'] as $part) {
            if (str_contains($compact, $part)) {
                return true;
            }
        }
        if (str_contains($compact, 'score')) {
            return true;
        }

        return in_array($normalized, [
            'exact',
            'plan_hash',
            'plan_purpose',
            'vector_match',
            'bytes',
        ], true);
    }

    private function lockReceipt(
        User $actor,
        int $runId,
        string $idempotencyKey,
        ?string $commandName = null,
    ): ?object {
        return DB::table('omr_command_receipts')
            ->where('actor_id', (int) $actor->id)
            ->where('command_name', $commandName ?? self::COMMAND_NAME)
            ->where('aggregate_type', self::AGGREGATE_TYPE)
            ->where('aggregate_id', (string) $runId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function assertReceiptFingerprint(object $receipt, string $payloadFingerprint): void
    {
        if (! hash_equals((string) $receipt->payload_fingerprint, $payloadFingerprint)) {
            throw new RuntimeException('omr_diagnostic_capture_idempotency_conflict');
        }
    }

    /** @return array<string, mixed> */
    private function receiptResult(object $receipt): array
    {
        $result = json_decode((string) $receipt->safe_result_json, true);
        if (! is_array($result)) {
            throw new RuntimeException('omr_diagnostic_receipt_invalid');
        }

        return $result;
    }

    /** @param array<string, mixed> $result */
    private function completeReceipt(int $receiptId, string $executionToken, array $result): void
    {
        $identities = is_array($result['identities'] ?? null) ? $result['identities'] : [];
        $updated = DB::table('omr_command_receipts')
            ->where('id', $receiptId)
            ->where('execution_token', $executionToken)
            ->where('status', 'started')
            ->update([
                'status' => 'succeeded',
                'lease_expires_at' => null,
                'safe_result_json' => json_encode([
                    'outcome' => 'succeeded',
                    'run_id' => (int) ($result['run_id'] ?? $identities['run_id'] ?? 0),
                    'capture_id' => (int) ($result['capture_id'] ?? $identities['capture_id'] ?? 0),
                    'client_capture_id' => (string) ($result['client_capture_id'] ?? $identities['client_capture_id'] ?? ''),
                    'evidence_id' => ($result['evidence_id'] ?? $identities['evidence_id'] ?? null) === null
                        ? null
                        : (int) ($result['evidence_id'] ?? $identities['evidence_id']),
                    'revision' => (int) ($identities['revision'] ?? 0),
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new RuntimeException('omr_diagnostic_capture_receipt_fenced');
        }
    }

    private function hashValue(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function safeCode(?string $code): string
    {
        $code = trim((string) $code);
        $code = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $code) ?? 'analysis_failed';

        return substr($code !== '' ? $code : 'analysis_failed', 0, 100);
    }

    private function recordKitAttemptAndSelection(
        OmrDiagnosticRun $run,
        OmrDiagnosticCapture $capture,
        User $actor,
        int $pageNo,
        ?string $caseId = null,
        ?string $idempotencyKey = null,
    ): void {
        if ($idempotencyKey !== null && str_starts_with($idempotencyKey, 'pending-page:')) {
            return;
        }

        $plan = $run->acceptance_plan_json;
        if (! is_array($plan) && ($caseId === null || trim($caseId) === '')) {
            return;
        }

        $resolvedCaseId = $this->resolveCaseId($run, $pageNo, $caseId);
        if ($resolvedCaseId === null) {
            return;
        }

        $itemBindings = $this->resolveItemBindings($run, $pageNo, $resolvedCaseId, $capture);

        $existingSelection = DB::table('test_kit_diagnostic_page_selections')
            ->where('diagnostic_run_id', (int) $run->id)
            ->where('case_id', $resolvedCaseId)
            ->where('page_no', $pageNo)
            ->lockForUpdate()
            ->first();

        $predecessorAttemptId = $existingSelection !== null
            ? (int) $existingSelection->selected_attempt_id
            : null;

        $attemptId = DB::table('test_kit_diagnostic_page_attempts')->insertGetId([
            'diagnostic_run_id' => (int) $run->id,
            'case_id' => $resolvedCaseId,
            'page_no' => $pageNo,
            'diagnostic_capture_id' => (int) $capture->id,
            'pending_set_id' => null,
            'pending_page_evidence_id' => null,
            'input_sha256' => (string) $capture->input_asset_hash,
            'predecessor_attempt_id' => $predecessorAttemptId,
            'item_bindings_json' => json_encode($itemBindings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => (int) $actor->id,
            'created_at' => now(),
        ]);

        if ($existingSelection !== null) {
            DB::table('test_kit_diagnostic_page_selections')
                ->where('id', (int) $existingSelection->id)
                ->update([
                    'selected_attempt_id' => $attemptId,
                    'revision' => (int) $existingSelection->revision + 1,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('test_kit_diagnostic_page_selections')->insert([
                'diagnostic_run_id' => (int) $run->id,
                'case_id' => $resolvedCaseId,
                'page_no' => $pageNo,
                'selected_attempt_id' => $attemptId,
                'revision' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function resolveCaseId(
        OmrDiagnosticRun $run,
        int $pageNo,
        ?string $caseId,
    ): ?string {
        $plan = $run->acceptance_plan_json;
        if (! is_array($plan) || ! isset($plan['cases']) || ! is_array($plan['cases']) || $plan['cases'] === []) {
            return $caseId !== null && trim($caseId) !== '' ? trim($caseId) : null;
        }

        $validCases = [];
        foreach ($plan['cases'] as $case) {
            if (isset($case['case_id']) && is_string($case['case_id'])) {
                $validCases[$case['case_id']] = $case;
            }
        }

        if ($caseId !== null && trim($caseId) !== '') {
            $trimmed = trim($caseId);
            if (! isset($validCases[$trimmed])) {
                throw new RuntimeException('omr_diagnostic_acceptance_case_invalid');
            }

            return $trimmed;
        }

        if (count($validCases) === 1) {
            return (string) array_key_first($validCases);
        }

        throw new RuntimeException('omr_diagnostic_acceptance_case_required');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveItemBindings(
        OmrDiagnosticRun $run,
        int $pageNo,
        string $caseId,
        OmrDiagnosticCapture $capture,
    ): array {
        if ($run->hasMockContext()) {
            return app(TestKitMockSession::class)->itemBindingsForPage($run, $pageNo);
        }
        $plan = $run->acceptance_plan_json;
        if (! is_array($plan)) {
            throw new RuntimeException('omr_diagnostic_acceptance_plan_missing');
        }

        $bindings = $plan['item_bindings'] ?? null;
        if (! is_array($bindings) || $bindings === []) {
            throw new RuntimeException('omr_diagnostic_acceptance_bindings_missing');
        }

        $pageBindings = array_values(array_filter(
            $bindings,
            static fn (array $b): bool => (int) ($b['page_no'] ?? 0) === $pageNo
        ));
        if ($pageBindings === []) {
            throw new RuntimeException('omr_diagnostic_acceptance_bindings_missing');
        }

        $itemRows = OmrDiagnosticItemEvidence::query()
            ->where('diagnostic_capture_id', (int) $capture->id)
            ->where('page_no', $pageNo)
            ->get();

        $itemsByLabel = [];
        foreach ($itemRows as $itemRow) {
            $itemsByLabel[(string) $itemRow->item_key] = $itemRow;
        }

        $mappedBindings = [];
        foreach ($pageBindings as $binding) {
            $label = (string) ($binding['native_label'] ?? '');
            $matchedItem = $itemsByLabel[$label] ?? null;
            if ($matchedItem === null) {
                foreach ($itemRows as $itemRow) {
                    if ($itemRow->item_key === $caseId.'-'.$label || str_ends_with((string) $itemRow->item_key, '-'.$label)) {
                        $matchedItem = $itemRow;
                        break;
                    }
                }
            }
            $itemKey = $matchedItem !== null ? (string) $matchedItem->item_key : $label;
            $mappedBindings[] = array_replace($binding, ['item_key' => $itemKey]);
        }

        return $mappedBindings;
    }
}
