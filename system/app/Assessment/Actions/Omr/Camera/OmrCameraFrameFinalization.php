<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Camera;

use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class OmrCameraFrameFinalization
{
    public function __construct(private readonly SensitiveAssetStore $assets) {}

    /** Returns private persisted intent data; callers must still compare the complete fingerprint before replay. */
    public function findOperationForReplay(User $actor, string $workbenchId, string $operationId, string $idempotencyKey): ?OmrCameraCommand
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $receiptKey = hash('sha256', $actor->id.'|'.$workbenchKey.'|'.$operationId.'|'.$idempotencyKey);

        return OmrCameraCommand::query()->where('receipt_key', $receiptKey)
            ->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)
            ->where('operation_id', $operationId)->where('kind', 'CAPTURE_RECEIPT')
            ->whereNull('domain_receipt_json->action')->first();
    }

    /** Internal transition replay lookup; this model must never be serialized into an HTTP projection. */
    public function findTransitionForReplay(User $actor, string $workbenchId, string $operationId, string $idempotencyKey): ?OmrCameraCommand
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $receiptKey = hash('sha256', $actor->id.'|'.$workbenchKey.'|'.$operationId.'|'.$idempotencyKey);

        return OmrCameraCommand::query()->where('receipt_key', $receiptKey)
            ->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)
            ->where('operation_id', $operationId)->where('kind', 'CAPTURE_RECEIPT')
            ->whereNotNull('domain_receipt_json->action')->first();
    }

    /** Record the exact private asset before file I/O, outside the actual domain-persistence transaction. */
    public function recordAssetIntent(OmrCameraFrameReservation $reservation, string $path, string $hmac): void
    {
        if (! str_starts_with($path, 'omr/') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || array_intersect(['', '.', '..'], explode('/', $path)) !== []
            || preg_match('/^[a-f0-9]{64}$/D', $hmac) !== 1) {
            throw new RuntimeException('omr_camera_asset_intent_invalid');
        }
        $scope = (array) ($reservation->operation->payload_json['workbench'] ?? []);
        DB::transaction(function () use ($reservation, $path, $hmac, $scope): void {
            $this->lockWorkbench($scope);
            $operation = OmrCameraCommand::query()->whereKey($reservation->operation->id)->lockForUpdate()->firstOrFail();
            abort_unless($operation->domain_outcome === 'reserved'
                && hash_equals((string) $operation->payload_fingerprint, $reservation->payloadFingerprint)
                && hash_equals((string) $operation->finalization_lease_token, $reservation->leaseToken)
                && (int) $operation->finalization_lease_generation === $reservation->leaseGeneration
                && $operation->finalization_lease_expires_at?->isFuture()
                && $operation->finalization_absolute_expires_at?->isFuture(), 409);
            $payload = (array) $operation->payload_json;
            $assets = (array) ($payload['source_assets'] ?? []);
            foreach ($assets as $asset) {
                abort_unless(is_array($asset) && is_string($asset['path'] ?? null) && is_string($asset['hmac'] ?? null), 409);
                if ($asset['path'] === $path) {
                    abort_unless(hash_equals($asset['hmac'], $hmac), 409);

                    return;
                }
            }
            abort_unless(count($assets) < 16, 409);
            $payload['source_assets'] = [...$assets, ['path' => $path, 'hmac' => $hmac, 'created_at' => now()->toISOString()]];
            $operation->forceFill(['payload_json' => $payload])->saveOrFail();
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function fingerprintPayload(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return array{status:string,receipt:array<string,mixed>}|null */
    public function findTransitionReceipt(User $actor, string $workbenchId, string $operationId, string $idempotencyKey): ?array
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $receiptKey = hash('sha256', $actor->id.'|'.$workbenchKey.'|'.$operationId.'|'.$idempotencyKey);
        $operation = OmrCameraCommand::query()->where('receipt_key', $receiptKey)
            ->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)
            ->where('operation_id', $operationId)->where('kind', 'CAPTURE_RECEIPT')
            ->whereNotNull('domain_receipt_json->action')->first();
        if ($operation === null) {
            return null;
        }
        $receipt = array_intersect_key((array) $operation->domain_receipt_json, array_flip([
            'action', 'operation_id', 'accepted_operation_id', 'accepted_target_key',
            'target_key', 'page_no', 'replacement_reason', 'revision', 'status',
        ]));

        return [
            'status' => match ($operation->domain_outcome) {
                'accepted' => 'accepted',
                'rejected' => 'rejected',
                default => 'pending',
            },
            'receipt' => $receipt,
        ];
    }

    /** @return array{status:string,receipt:array<string,mixed>|null}|null */
    public function findOperationReceipt(User $actor, string $workbenchId, string $operationId, string $idempotencyKey): ?array
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $receiptKey = hash('sha256', $actor->id.'|'.$workbenchKey.'|'.$operationId.'|'.$idempotencyKey);
        $operation = OmrCameraCommand::query()->where('receipt_key', $receiptKey)
            ->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)
            ->where('kind', 'CAPTURE_RECEIPT')->whereNull('domain_receipt_json->action')->first();
        if ($operation === null) {
            return null;
        }
        $status = match ($operation->domain_outcome) {
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            default => 'pending',
        };

        return $this->captureReceiptView($operation, $status);
    }

    /** @return array{status:string,receipt:array<string,mixed>}|null */
    public function findAcceptedOperationReceipt(User $actor, string $workbenchId, string $operationId): ?array
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $operation = OmrCameraCommand::query()->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)
            ->where('operation_id', $operationId)->where('kind', 'CAPTURE_RECEIPT')->whereNull('domain_receipt_json->action')
            ->where('domain_outcome', 'accepted')->first();
        if ($operation === null) {
            return null;
        }

        return $this->captureReceiptView($operation, 'accepted');
    }

    /** @return array{status:string,receipt:array<string,mixed>}|null */
    public function findLatestAcceptedOperationReceipt(User $actor, string $workbenchId): ?array
    {
        $workbenchKey = hash('sha256', $workbenchId);
        $operation = OmrCameraCommand::query()->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)
            ->where('kind', 'CAPTURE_RECEIPT')->whereNull('domain_receipt_json->action')->where('domain_outcome', 'accepted')
            ->orderByDesc('completed_at')->orderByDesc('id')->first();

        return $operation === null ? null : $this->captureReceiptView($operation, 'accepted');
    }

    /** @return array{status:string,receipt:array<string,mixed>}|null */
    public function findAcceptedOperationReceiptForCapture(User $actor, string $workbenchId, int $captureId): ?array
    {
        $query = OmrCameraCommand::query()
            ->where('actor_id', $actor->id)
            ->where('workbench_key', hash('sha256', $workbenchId))
            ->where('kind', 'CAPTURE_RECEIPT')
            ->whereNull('domain_receipt_json->action')
            ->where('domain_outcome', 'accepted');
        if (str_starts_with($workbenchId, 'diagnostic:')) {
            $query->where('domain_receipt_json->diagnostic_capture_id', $captureId);
        } else {
            $query->where('domain_receipt_json->capture_id', $captureId);
        }
        $operation = $query
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        return $operation === null ? null : $this->captureReceiptView($operation, 'accepted');
    }

    public function hasUnresolvedCaptureOperation(
        User $actor,
        string $workbenchId,
        ?string $exceptOperationId = null,
    ): bool {
        $query = OmrCameraCommand::query()
            ->where('actor_id', $actor->id)
            ->where('workbench_key', hash('sha256', $workbenchId))
            ->where('kind', 'CAPTURE_RECEIPT')
            ->whereNull('domain_receipt_json->action')
            ->whereIn('domain_outcome', ['reserved', 'uncertain']);
        if ($exceptOperationId !== null) {
            $query->where('operation_id', '!=', $exceptOperationId);
        }

        return $query->exists();
    }

    /** @param array<string, mixed> $workbench @param array<string, mixed> $target @param array<string, mixed>|null $quad */
    public function reserveOperation(User $actor, string $workbenchKey, array $workbench, array $target, string $operationId,
        string $idempotencyKey, string $payloadFingerprint, string $cropPolicy, ?array $quad,
        ?string $pairingId = null, ?string $commandId = null, ?string $frameId = null): OmrCameraFrameReservation
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $payloadFingerprint) || $idempotencyKey === '') {
            throw new RuntimeException('omr_camera_operation_identity_invalid');
        }
        $fingerprint = $this->fingerprintPayload([
            'workbench' => $workbench, 'target' => $target, 'operation_id' => $operationId,
            'idempotency_key' => $idempotencyKey, 'payload_fingerprint' => $payloadFingerprint,
            'crop_policy' => $cropPolicy, 'quad' => $quad,
        ]);
        $receiptKey = hash('sha256', $actor->id.'|'.$workbenchKey.'|'.$operationId.'|'.$idempotencyKey);

        return DB::transaction(function () use ($actor, $workbenchKey, $workbench, $target, $operationId, $idempotencyKey,
            $cropPolicy, $quad, $pairingId, $commandId, $frameId, $fingerprint, $payloadFingerprint, $receiptKey): OmrCameraFrameReservation {
            $this->lockWorkbench($workbench);
            $operation = OmrCameraCommand::query()->where('receipt_key', $receiptKey)->lockForUpdate()->first();
            if ($operation !== null) {
                abort_unless(hash_equals((string) $operation->payload_fingerprint, $fingerprint), 409);
                if ($operation->domain_outcome === 'accepted') {
                    return new OmrCameraFrameReservation($operation, null, '', $fingerprint, (array) $operation->domain_receipt_json);
                }
                abort_unless($operation->domain_outcome !== 'rejected'
                    && ($operation->finalization_lease_expires_at === null || $operation->finalization_lease_expires_at->isPast()), 409);
            } else {
                abort_unless(! OmrCameraCommand::query()->where('actor_id', $actor->id)->where('workbench_key', $workbenchKey)
                    ->where('operation_id', $operationId)->where('kind', 'CAPTURE_RECEIPT')->exists(), 409);
                $operation = OmrCameraCommand::query()->create([
                    'pairing_id' => null, 'operation_id' => $operationId, 'command_id' => (string) Str::uuid(),
                    'issuer_role' => 'controller', 'sequence' => null, 'kind' => 'CAPTURE_RECEIPT',
                    'workbench_key' => $workbenchKey, 'actor_id' => $actor->id, 'receipt_key' => $receiptKey,
                    'payload_json' => ['workbench' => $workbench, 'idempotency_key' => $idempotencyKey,
                        'input_fingerprint' => $payloadFingerprint, 'crop_policy' => $cropPolicy, 'quad' => $quad],
                    'payload_fingerprint' => $fingerprint, 'target_json' => $target, 'issued_at' => now(),
                    'domain_outcome' => 'reserved', 'finalization_lease_generation' => 0,
                ]);
            }

            $frame = null;
            $leaseToken = (string) Str::uuid();
            $leaseGeneration = (int) $operation->finalization_lease_generation + 1;
            $absoluteExpiry = $operation->finalization_absolute_expires_at ?? now()->addMinutes(10);
            abort_unless($absoluteExpiry->isFuture(), 410);

            if ($frameId !== null) {
                abort_unless($pairingId !== null && $commandId !== null, 422);
                $pairing = OmrCameraPairing::query()->where('public_id', $pairingId)->lockForUpdate()->firstOrFail();
                abort_unless($pairing->isLive() && (int) $pairing->operator_id === (int) $actor->id
                    && $pairing->workbench_key === $workbenchKey, 404);
                $deviceCommand = $pairing->commands()->where('command_id', $commandId)->lockForUpdate()->firstOrFail();
                abort_unless($deviceCommand->kind === 'CAPTURE' && $deviceCommand->device_outcome === 'acquired'
                    && $deviceCommand->operation_id === $operationId, 409);
                $frame = OmrCameraFrame::query()->where('public_id', $frameId)->where('pairing_id', $pairing->id)->lockForUpdate()->firstOrFail();
                $isStaged = $frame->state === OmrCameraFrame::STATE_STAGED && $frame->upload_expires_at?->isFuture();
                $canResume = $frame->state === OmrCameraFrame::STATE_FINALIZING
                    && hash_equals((string) $frame->finalization_fingerprint, $fingerprint)
                    && $frame->finalization_lease_expires_at?->isPast()
                    && $frame->finalization_absolute_expires_at?->isFuture();
                abort_unless(($isStaged || $canResume)
                    && (int) $frame->node_generation === (int) $pairing->node_generation
                    && hash_equals((string) $frame->target_key, (string) ($target['targetKey'] ?? $target['target_key'] ?? '')), 409);
                $frame->forceFill(['state' => OmrCameraFrame::STATE_FINALIZING, 'finalization_fingerprint' => $fingerprint,
                    'finalization_lease_token' => $leaseToken, 'finalization_lease_expires_at' => now()->addSeconds(120),
                    'finalization_absolute_expires_at' => $absoluteExpiry])->save();
                $operation->forceFill(['pairing_id' => $pairing->id, 'target_json' => $target,
                    'controller_generation' => $pairing->controller_generation, 'source_generation' => $frame->source_generation])->save();
            }

            $leaseExpiry = min($absoluteExpiry->timestamp, now()->addSeconds(120)->timestamp);
            $operation->forceFill(['domain_outcome' => 'reserved', 'finalization_lease_token' => $leaseToken,
                'finalization_lease_expires_at' => now()->setTimestamp($leaseExpiry),
                'finalization_absolute_expires_at' => $absoluteExpiry,
                'finalization_lease_generation' => $leaseGeneration])->save();

            return new OmrCameraFrameReservation($operation->fresh(), $frame?->fresh(), $leaseToken, $fingerprint, null, $leaseGeneration);
        }, 3);
    }

    public function reserveForFinalization(string $pairingId, string $commandId, string $frameId, User $actor,
        array $workbench, array $target, string $operationId, string $idempotencyKey, string $payloadFingerprint,
        string $cropPolicy, ?array $quad): OmrCameraFrameReservation
    {
        return $this->reserveOperation($actor, hash('sha256', (string) ($workbench['workbench_id'] ?? '')),
            $workbench, $target, $operationId, $idempotencyKey, $payloadFingerprint, $cropPolicy, $quad,
            $pairingId, $commandId, $frameId);
    }

    public function assertControllerCredential(string $pairingId, User $actor, string $token, int $generation): OmrCameraPairing
    {
        $pairing = OmrCameraPairing::query()->where('public_id', $pairingId)->firstOrFail();
        abort_unless($pairing->isLive() && (int) $pairing->operator_id === (int) $actor->id
            && is_string($pairing->controller_token_hash)
            && hash_equals($pairing->controller_token_hash, hash('sha256', $token))
            && (int) $pairing->controller_generation === $generation, 403);

        return $pairing;
    }

    public function resolveOriginal(OmrCameraFrameReservation $reservation): string
    {
        if ($reservation->frame === null) {
            throw new RuntimeException('omr_camera_staged_original_missing');
        }

        return $this->resolveFrameOriginal($reservation->frame);
    }

    public function resolveFrameOriginal(OmrCameraFrame $frame): string
    {
        if ($frame->asset_disk !== 'sensitive' || $frame->asset_path === null || $frame->asset_hmac === null) {
            throw new RuntimeException('omr_camera_staged_original_missing');
        }
        $bytes = $this->assets->read($frame->asset_path, $frame->asset_hmac);
        if (! is_string($bytes) || strlen($bytes) !== (int) $frame->byte_count
            || ! hash_equals((string) $frame->input_sha256, hash('sha256', $bytes))) {
            throw new RuntimeException('omr_camera_staged_original_integrity_failed');
        }

        return $bytes;
    }

    /** @param array<string, mixed> $domainReceipt */
    public function commitAccepted(OmrCameraFrameReservation $reservation, array $domainReceipt): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('omr_camera_receipt_must_commit_inside_domain_transaction');
        }
        $this->lockWorkbench((array) ($reservation->operation->payload_json['workbench'] ?? []));
        $operation = OmrCameraCommand::query()->whereKey($reservation->operation->id)->lockForUpdate()->firstOrFail();
        abort_unless(in_array($operation->domain_outcome, ['reserved', 'uncertain'], true)
            && hash_equals((string) $operation->payload_fingerprint, $reservation->payloadFingerprint)
            && hash_equals((string) $operation->finalization_lease_token, $reservation->leaseToken)
            && (int) $operation->finalization_lease_generation === $reservation->leaseGeneration
            && $operation->finalization_lease_expires_at?->isFuture()
            && $operation->finalization_absolute_expires_at?->isFuture(), 409);
        if ($operation->pairing_id !== null) {
            $pairing = OmrCameraPairing::query()->whereKey($operation->pairing_id)->lockForUpdate()->firstOrFail();
            abort_unless($pairing->isLive() && (int) $pairing->controller_generation === (int) $operation->controller_generation
                && (int) $pairing->source_generation === (int) $operation->source_generation, 409);
        }
        $operation->forceFill(['domain_outcome' => 'accepted', 'domain_receipt_json' => $domainReceipt, 'completed_at' => now()])->save();
        if ($reservation->frame !== null) {
            $frame = OmrCameraFrame::query()->whereKey($reservation->frame->id)->lockForUpdate()->firstOrFail();
            abort_unless($frame->state === OmrCameraFrame::STATE_FINALIZING
                && hash_equals((string) $frame->finalization_lease_token, $reservation->leaseToken)
                && hash_equals((string) $frame->finalization_fingerprint, $reservation->payloadFingerprint)
                && $frame->finalization_lease_expires_at?->isFuture()
                && $frame->finalization_absolute_expires_at?->isFuture(), 409);
            $frame->forceFill(['state' => OmrCameraFrame::STATE_CONSUMED, 'domain_receipt_json' => $domainReceipt, 'consumed_at' => now()])->save();
        }
    }

    public function renew(OmrCameraFrameReservation $reservation): OmrCameraFrameReservation
    {
        return DB::transaction(function () use ($reservation): OmrCameraFrameReservation {
            $this->lockWorkbench((array) ($reservation->operation->payload_json['workbench'] ?? []));
            $operation = OmrCameraCommand::query()->whereKey($reservation->operation->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($operation->domain_outcome, ['reserved', 'uncertain'], true)
                && hash_equals((string) $operation->finalization_lease_token, $reservation->leaseToken)
                && (int) $operation->finalization_lease_generation === $reservation->leaseGeneration
                && $operation->finalization_lease_expires_at?->isFuture()
                && $operation->finalization_absolute_expires_at?->isFuture(), 409);
            if ($operation->pairing_id !== null) {
                $pairing = OmrCameraPairing::query()->whereKey($operation->pairing_id)->lockForUpdate()->firstOrFail();
                abort_unless($pairing->isLive() && (int) $pairing->controller_generation === (int) $operation->controller_generation, 409);
            }
            $operation->finalization_lease_expires_at = min(now()->addSeconds(120)->timestamp,
                $operation->finalization_absolute_expires_at->timestamp);
            $operation->save();
            if ($reservation->frame !== null) {
                OmrCameraFrame::query()->whereKey($reservation->frame->id)->where('finalization_lease_token', $reservation->leaseToken)
                    ->update(['finalization_lease_expires_at' => $operation->finalization_lease_expires_at]);
            }

            return new OmrCameraFrameReservation($operation->fresh(), $reservation->frame?->fresh(), $reservation->leaseToken,
                $reservation->payloadFingerprint, null, $reservation->leaseGeneration);
        }, 3);
    }

    public function release(OmrCameraFrameReservation $reservation, string $reason): void
    {
        DB::transaction(function () use ($reservation, $reason): void {
            $this->lockWorkbench((array) ($reservation->operation->payload_json['workbench'] ?? []));
            $operation = OmrCameraCommand::query()->whereKey($reservation->operation->id)->lockForUpdate()->first();
            if ($operation === null || $operation->domain_outcome !== 'reserved'
                || ! hash_equals((string) $operation->finalization_lease_token, $reservation->leaseToken)) {
                return;
            }
            $operation->forceFill(['domain_outcome' => 'rejected', 'device_outcome_json' => ['failure_reason' => $reason], 'completed_at' => now()])->save();
            if ($reservation->frame !== null) {
                OmrCameraFrame::query()->whereKey($reservation->frame->id)->where('state', OmrCameraFrame::STATE_FINALIZING)
                    ->where('finalization_lease_token', $reservation->leaseToken)
                    ->update(['state' => OmrCameraFrame::STATE_STAGED, 'finalization_lease_token' => null,
                        'finalization_lease_expires_at' => null, 'finalization_fingerprint' => null]);
            }
        }, 3);
    }

    public function releaseForRetry(OmrCameraFrameReservation $reservation): void
    {
        DB::transaction(function () use ($reservation): void {
            $scope = (array) ($reservation->operation->payload_json['workbench'] ?? []);
            $this->lockWorkbench($scope);
            $operation = OmrCameraCommand::query()->whereKey($reservation->operation->id)->lockForUpdate()->first();
            if ($operation === null
                || ! in_array($operation->domain_outcome, ['reserved', 'uncertain'], true)
                || ! hash_equals((string) $operation->payload_fingerprint, $reservation->payloadFingerprint)
                || ! hash_equals((string) $operation->finalization_lease_token, $reservation->leaseToken)
                || (int) $operation->finalization_lease_generation !== $reservation->leaseGeneration) {
                return;
            }
            if ($operation->pairing_id !== null) {
                OmrCameraPairing::query()->whereKey($operation->pairing_id)->lockForUpdate()->firstOrFail();
            }
            if ($reservation->frame !== null) {
                $frame = OmrCameraFrame::query()->whereKey($reservation->frame->id)->lockForUpdate()->first();
                if ($frame === null
                    || $frame->state !== OmrCameraFrame::STATE_FINALIZING
                    || ! hash_equals((string) $frame->finalization_lease_token, $reservation->leaseToken)
                    || ! hash_equals((string) $frame->finalization_fingerprint, $reservation->payloadFingerprint)) {
                    return;
                }
                $frame->forceFill([
                    'finalization_lease_token' => null,
                    'finalization_lease_expires_at' => now()->subSecond(),
                ])->saveOrFail();
            }
            $operation->forceFill([
                'domain_outcome' => 'reserved',
                'finalization_lease_token' => null,
                'finalization_lease_expires_at' => now()->subSecond(),
                'finalization_lease_generation' => (int) $operation->finalization_lease_generation + 1,
            ])->saveOrFail();
        }, 3);
    }

    public function markUncertain(OmrCameraFrameReservation $reservation): void
    {
        $scope = (array) ($reservation->operation->payload_json['workbench'] ?? []);
        DB::transaction(function () use ($reservation, $scope): void {
            $this->lockWorkbench($scope);
            OmrCameraCommand::query()->whereKey($reservation->operation->id)->where('domain_outcome', 'reserved')
                ->where('finalization_lease_token', $reservation->leaseToken)
                ->update(['domain_outcome' => 'uncertain', 'updated_at' => now()]);
        });
    }

    /** @return array{status:string,receipt:array<string,mixed>} */
    private function captureReceiptView(OmrCameraCommand $operation, string $status): array
    {
        $receipt = array_intersect_key((array) $operation->domain_receipt_json, array_flip([
            'status', 'capture_id', 'sheet_id', 'page_id', 'page_no', 'pending_capture_id',
            'pending_capture_revision', 'diagnostic_capture_id', 'operation_id', 'accepted', 'revision', 'phase',
        ]));
        $target = array_intersect_key((array) $operation->target_json, array_flip([
            'workbenchId', 'clientCaptureId', 'pageNo', 'captureRevision', 'instanceId', 'sheetId', 'targetKey',
            'workbench_id', 'client_capture_id', 'page_no', 'capture_revision', 'instance_id', 'sheet_id', 'target_key',
            'batch_id', 'run_id', 'page_count', 'expected_page_count', 'material_identity',
            'execution_kind', 'session_id', 'institution_id', 'academic_cycle_id', 'qr_mode',
            'registration_mode', 'diagnostic_purpose', 'pending_capture_id', 'pending_capture_revision',
            'template_version_id', 'booklet_version_id', 'batch_version', 'instance_no', 'instance_count',
            'identity_mode', 'supplier_stock_allocation_id',
        ]));
        $binding = [
            'workbenchId' => (string) ($target['workbenchId'] ?? $target['workbench_id'] ?? ''),
            'clientCaptureId' => $target['clientCaptureId'] ?? $target['client_capture_id'] ?? null,
            'pageNo' => (int) ($target['pageNo'] ?? $target['page_no'] ?? 0),
            'captureRevision' => $target['captureRevision'] ?? $target['capture_revision'] ?? null,
            'instanceId' => $target['instanceId'] ?? $target['instance_id'] ?? null,
            'sheetId' => $target['sheetId'] ?? $target['sheet_id'] ?? null,
            'targetKey' => (string) ($target['targetKey'] ?? $target['target_key'] ?? ''),
        ];

        return ['status' => $status, 'receipt' => [
            ...$target, ...$receipt,
            'status' => $status,
            'operation_id' => (string) $operation->operation_id,
            'target_key' => $binding['targetKey'],
            'page_no' => $receipt['page_no'] ?? $binding['pageNo'],
            'target' => $binding,
        ]];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map($this->canonicalize(...), $value);
    }

    /** @param array<string, mixed> $workbench */
    private function lockWorkbench(array $workbench): void
    {
        if (isset($workbench['production_batch_id'])) {
            DB::table('omr_capture_batches')->where('id', $workbench['production_batch_id'])->lockForUpdate()->first();
        } elseif (isset($workbench['diagnostic_run_id'])) {
            $run = DB::table('omr_diagnostic_runs')
                ->where('id', $workbench['diagnostic_run_id'])
                ->lockForUpdate()
                ->first();
            if (($run?->status ?? null) === OmrDiagnosticRun::STATUS_CANCELLED) {
                throw new RuntimeException('omr_diagnostic_run_terminal');
            }
        }
    }
}
