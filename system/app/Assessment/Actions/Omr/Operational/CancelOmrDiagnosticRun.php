<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Assessment\Actions\Omr\Camera\OmrCameraProtocol;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;
use function hash_equals;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function strtotime;
use function trim;

final class CancelOmrDiagnosticRun
{
    public const COMMAND_NAME = 'omr.diagnostic.cancel';

    public const AUDIT_EVENT = 'omr.diagnostic.cancelled';

    private const RECEIPT_LEASE_MINUTES = 5;

    private const AGGREGATE_TYPE = 'omr_diagnostic_run';

    public function __construct(
        private readonly OmrDiagnosticRunStatusSnapshot $snapshot,
        private readonly RecordAuditEvent $audit,
        private readonly OmrCameraProtocol $cameraProtocol,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        OmrDiagnosticRun $run,
        User $actor,
        int $expectedRevision,
        string $reason,
        string $idempotencyKey,
    ): array {
        $reason = trim($reason);
        $idempotencyKey = trim($idempotencyKey);
        if ($expectedRevision < 1) {
            throw new RuntimeException('omr_diagnostic_run_revision_invalid');
        }
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new RuntimeException('omr_diagnostic_cancel_reason_invalid');
        }
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 255) {
            throw new RuntimeException('omr_diagnostic_cancel_idempotency_key_invalid');
        }

        $this->snapshot->assertCanProject($run, $actor);
        if (! $run->hasMockContext()) {
            throw new RuntimeException('omr_diagnostic_cancel_purpose_invalid');
        }
        $payloadFingerprint = hash('sha256', json_encode([
            'run_id' => (int) $run->getKey(),
            'expected_revision' => $expectedRevision,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($run, $actor, $expectedRevision, $reason, $idempotencyKey, $payloadFingerprint): array {
            $lockedRun = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail((int) $run->getKey());
            $this->snapshot->assertCanProject($lockedRun, $actor);
            if (! $lockedRun->hasMockContext()) {
                throw new RuntimeException('omr_diagnostic_cancel_purpose_invalid');
            }

            $receipt = $this->lockReceipt($actor, (int) $lockedRun->getKey(), $idempotencyKey);
            if ($receipt !== null) {
                $this->assertReceiptFingerprint($receipt, $payloadFingerprint);
                if ((string) $receipt->status === 'succeeded') {
                    return $this->resultFromReceipt($receipt, $lockedRun);
                }
                if ((string) $receipt->status === 'started'
                    && $receipt->lease_expires_at !== null
                    && strtotime((string) $receipt->lease_expires_at) > time()) {
                    throw new RuntimeException('omr_diagnostic_cancel_idempotency_in_progress');
                }

                $receiptId = (int) $receipt->id;
                $correlationId = Str::isUuid((string) $receipt->correlation_id)
                    ? (string) $receipt->correlation_id
                    : (string) Str::uuid();
                DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                    'status' => 'started',
                    'execution_token' => (string) Str::uuid(),
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'correlation_id' => $correlationId,
                    'updated_at' => now(),
                ]);
            } else {
                $correlationId = (string) Str::uuid();
                $receiptId = (int) DB::table('omr_command_receipts')->insertGetId([
                    'actor_id' => (int) $actor->getKey(),
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $lockedRun->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'payload_fingerprint' => $payloadFingerprint,
                    'status' => 'started',
                    'execution_token' => (string) Str::uuid(),
                    'lease_expires_at' => now()->addMinutes(self::RECEIPT_LEASE_MINUTES),
                    'safe_result_json' => null,
                    'correlation_id' => $correlationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($lockedRun->isTerminal() || $lockedRun->sealed_at !== null) {
                throw new RuntimeException('omr_diagnostic_run_terminal');
            }
            if ((int) $lockedRun->revision !== $expectedRevision) {
                throw new RuntimeException('omr_diagnostic_run_revision_stale');
            }

            $activeCapture = OmrDiagnosticCapture::query()
                ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                ->whereIn('status', [
                    OmrDiagnosticCapture::STATUS_RECEIVED,
                    OmrDiagnosticCapture::STATUS_QUEUED,
                    OmrDiagnosticCapture::STATUS_PROCESSING,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->first(['id']);
            if ($activeCapture !== null) {
                throw new RuntimeException('omr_diagnostic_capture_active');
            }
            $pairings = OmrCameraPairing::query()
                ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $pairingIds = $pairings->modelKeys();
            $unacknowledgedCaptureCommand = OmrCameraCommand::query()
                ->whereIn('pairing_id', $pairingIds)
                ->where('kind', 'CAPTURE')
                ->whereNull('device_outcome')
                ->where('execution_expires_at', '>', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->first(['id']);
            $unresolvedFrame = OmrCameraFrame::query()
                ->whereIn('pairing_id', $pairingIds)
                ->where(function ($query): void {
                    $query->where('state', OmrCameraFrame::STATE_FINALIZING)
                        ->orWhere(function ($activeFrame): void {
                            $activeFrame
                                ->whereIn('state', [
                                    OmrCameraFrame::STATE_ACQUIRED,
                                    OmrCameraFrame::STATE_UPLOADING,
                                    OmrCameraFrame::STATE_STAGED,
                                ])
                                ->where('upload_expires_at', '>', now());
                        });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first(['id']);
            if ($unacknowledgedCaptureCommand !== null || $unresolvedFrame !== null) {
                throw new RuntimeException('omr_diagnostic_cancel_outcome_unknown');
            }


            $workbenchKey = hash('sha256', 'diagnostic:'.$lockedRun->public_id);
            $unresolvedOperation = DB::table('omr_camera_commands')
                ->where('workbench_key', $workbenchKey)
                ->where('kind', 'CAPTURE_RECEIPT')
                ->whereNull('domain_receipt_json->action')
                ->whereIn('domain_outcome', ['reserved', 'uncertain'])
                ->lockForUpdate()
                ->first(['id']);
            if ($unresolvedOperation !== null) {
                throw new RuntimeException('omr_diagnostic_cancel_outcome_unknown');
            }

            $pendingSets = OmrPendingCaptureSet::query()
                ->where('diagnostic_run_id', (int) $lockedRun->getKey())
                ->whereNull('production_batch_id')
                ->where('status', OmrPendingCaptureSet::STATUS_PENDING)
                ->where('expires_at', '>', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($pairings as $pairing) {
                if ($pairing->revoked_at === null) {
                    $this->cameraProtocol->revoke($pairing, 'diagnostic_run_cancelled');
                }
            }
            foreach ($pendingSets as $pendingSet) {
                $pendingSet->forceFill([
                    'status' => OmrPendingCaptureSet::STATUS_CANCELLED,
                    'revision' => (int) $pendingSet->revision + 1,
                ])->saveOrFail();
            }

            $nextRevision = (int) $lockedRun->revision + 1;
            $lockedRun->forceFill([
                'status' => OmrDiagnosticRun::STATUS_CANCELLED,
                'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
                'revision' => $nextRevision,
                'completed_at' => now(),
            ])->saveOrFail();

            $result = [
                'outcome' => 'succeeded',
                'run_id' => (int) $lockedRun->getKey(),
                'run_revision' => $nextRevision,
                'status' => OmrDiagnosticRun::STATUS_CANCELLED,
                'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
                'diagnostic_purpose' => OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL,
                'official' => false,
                'receipt' => [
                    'command_name' => self::COMMAND_NAME,
                    'aggregate_type' => self::AGGREGATE_TYPE,
                    'aggregate_id' => (string) $lockedRun->getKey(),
                    'run_revision' => $nextRevision,
                    'correlation_id' => $correlationId,
                ],
            ];

            $this->audit->handle(self::AUDIT_EVENT, [
                'run_id' => (int) $lockedRun->getKey(),
                'diagnostic_purpose' => OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL,
                'reason' => $reason,
                'prior_run_revision' => $expectedRevision,
                'run_revision' => $nextRevision,
                'cancelled_pending_capture_set_ids' => $pendingSets->modelKeys(),
                'correlation_id' => $correlationId,
                'official' => false,
            ], (string) $actor->getKey());

            DB::table('omr_command_receipts')->where('id', $receiptId)->update([
                'status' => 'succeeded',
                'lease_expires_at' => null,
                'safe_result_json' => json_encode($result, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $result;
        }, 3);
    }

    private function lockReceipt(User $actor, int $runId, string $idempotencyKey): ?object
    {
        return DB::table('omr_command_receipts')
            ->where('actor_id', (int) $actor->getKey())
            ->where('command_name', self::COMMAND_NAME)
            ->where('aggregate_type', self::AGGREGATE_TYPE)
            ->where('aggregate_id', (string) $runId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function assertReceiptFingerprint(object $receipt, string $payloadFingerprint): void
    {
        if (! hash_equals((string) $receipt->payload_fingerprint, $payloadFingerprint)) {
            throw new RuntimeException('omr_diagnostic_cancel_idempotency_conflict');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromReceipt(object $receipt, OmrDiagnosticRun $run): array
    {
        $result = json_decode((string) $receipt->safe_result_json, true);
        if (! is_array($result)
            || (int) ($result['run_id'] ?? 0) !== (int) $run->getKey()
            || ($result['status'] ?? null) !== OmrDiagnosticRun::STATUS_CANCELLED
            || ($result['phase'] ?? null) !== OmrDiagnosticRun::PHASE_COMPLETE
            || ($result['receipt']['command_name'] ?? null) !== self::COMMAND_NAME) {
            throw new RuntimeException('omr_diagnostic_cancel_receipt_invalid');
        }

        return $result;
    }
}
