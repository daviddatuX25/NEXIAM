<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Canonical\OmrBlankPrintContract;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class StartOmrPendingCaptureSet
{
    private const EXPIRY_MINUTES = 30;

    public function __construct(
        private readonly OmrPendingCaptureAccess $access,
        private readonly OmrDiagnosticRunStatusSnapshot $diagnosticSnapshot,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function forProduction(GradingSession $gradingSession, OmrCaptureBatch $batch, OmrQrDisabledIssuance $issuance, User $actor, string $idempotencyKey): OmrPendingCaptureSet
    {
        $this->assertKey($idempotencyKey);

        return DB::transaction(function () use ($gradingSession, $batch, $issuance, $actor, $idempotencyKey): OmrPendingCaptureSet {
            $session = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);
            $locked = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ((int) $locked->grading_session_id !== (int) $session->id) {
                throw new RuntimeException('omr_capture_set_session_batch_mismatch');
            }
            $scope = $this->access->actorScope($actor);
            $examSession = $session->examSession()->with('academicCycle', 'currentMaterialSnapshot')->firstOrFail();
            if ((int) $examSession->academic_cycle_id !== $scope['academic_cycle_id']
                || (int) $examSession->academicCycle?->institution_id !== $scope['institution_id']) {
                throw new RuntimeException('omr_capture_set_scope_mismatch');
            }
            $snapshot = $examSession->currentMaterialSnapshot;
            if ($snapshot === null
                || (int) $snapshot->template_version_id !== (int) $locked->template_version_id
                || (int) $snapshot->booklet_version_id !== (int) $locked->booklet_version_id) {
                throw new RuntimeException('omr_capture_set_material_mismatch');
            }

            $printed = OmrQrDisabledIssuance::query()->findOrFail($issuance->id);
            $contract = $this->contract((int) $locked->template_version_id);
            if ($contract->sourceKind === 'uploaded_original') {
                throw new RuntimeException('omr_capture_set_supplier_stock_required');
            }
            if ($printed->status !== OmrQrDisabledIssuance::STATUS_PRINTED
                || (int) $printed->template_version_id !== $contract->templateVersionId
                || (int) $printed->institution_id !== $scope['institution_id']
                || (int) $printed->academic_cycle_id !== $scope['academic_cycle_id']
                || (int) $printed->page_count !== count($contract->compiledPages)
                || ! in_array($printed->copy_policy, [OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE, OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER], true)
                || ! is_string($printed->artifact_manifest_hash)
                || ! preg_match('/^[a-f0-9]{64}$/i', $printed->artifact_manifest_hash)) {
                throw new RuntimeException('omr_capture_set_issuance_mismatch');
            }

            $fields = $this->pinnedFields($contract, $scope['institution_id'], $scope['academic_cycle_id'], (int) $actor->id, $idempotencyKey);
            $fields['production_batch_id'] = (int) $locked->id;
            $fields['diagnostic_run_id'] = null;
            $fields['issuance_id'] = (int) $printed->id;
            $fields['copy_policy'] = $printed->copy_policy;
            $fields['payload_fingerprint'] = $this->fingerprint($fields);

            return $this->start($fields, $locked->status === OmrCaptureBatch::STATUS_OPEN
                && $session->status !== GradingSession::STATUS_FINALIZED, $actor);
        }, 3);
    }

    public function forSupplierStock(GradingSession $gradingSession, OmrCaptureBatch $batch, OmrSupplierStockAllocation $allocation, User $actor, string $idempotencyKey): OmrPendingCaptureSet
    {
        $this->assertKey($idempotencyKey);

        return DB::transaction(function () use ($gradingSession, $batch, $allocation, $actor, $idempotencyKey): OmrPendingCaptureSet {
            $session = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);
            $locked = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ((int) $locked->grading_session_id !== (int) $session->id) {
                throw new RuntimeException('omr_capture_set_session_batch_mismatch');
            }
            $scope = $this->access->actorScope($actor);
            $examSession = $session->examSession()->with('academicCycle', 'currentMaterialSnapshot')->firstOrFail();
            if ((int) $examSession->academic_cycle_id !== $scope['academic_cycle_id']
                || (int) $examSession->academicCycle?->institution_id !== $scope['institution_id']) {
                throw new RuntimeException('omr_capture_set_scope_mismatch');
            }
            $snapshot = $examSession->currentMaterialSnapshot;
            if ($snapshot === null
                || (int) $snapshot->template_version_id !== (int) $locked->template_version_id
                || (int) $snapshot->booklet_version_id !== (int) $locked->booklet_version_id) {
                throw new RuntimeException('omr_capture_set_material_mismatch');
            }

            $stockReference = OmrSupplierStockAllocation::query()->findOrFail($allocation->id);
            $lot = OmrSupplierStockLot::query()->lockForUpdate()->findOrFail($stockReference->lot_id);
            $stock = OmrSupplierStockAllocation::query()->lockForUpdate()->findOrFail($allocation->id);
            $contract = $this->contract((int) $locked->template_version_id);
            if ($contract->sourceKind !== 'uploaded_original'
                || (int) $stock->lot_id !== (int) $lot->id
                || $stock->status !== OmrSupplierStockAllocation::STATUS_ACTIVE
                || $lot->status !== OmrSupplierStockLot::STATUS_AVAILABLE
                || (int) $stock->quantity <= (int) $stock->used_quantity
                || (int) $stock->grading_session_id !== (int) $session->id
                || (int) $stock->batch_id !== (int) $locked->id
                || (int) $stock->template_version_id !== $contract->templateVersionId
                || (int) $lot->template_version_id !== $contract->templateVersionId
                || (int) $stock->institution_id !== $scope['institution_id']
                || (int) $stock->academic_cycle_id !== $scope['academic_cycle_id']
                || (int) $lot->institution_id !== $scope['institution_id']
                || (int) $lot->academic_cycle_id !== $scope['academic_cycle_id']
                || (int) $stock->page_count !== count($contract->compiledPages)
                || (int) $lot->page_count !== count($contract->compiledPages)
                || $stock->copy_policy !== 'supplier_stock') {
                throw new RuntimeException('omr_capture_set_supplier_stock_mismatch');
            }

            $fields = $this->pinnedFields($contract, $scope['institution_id'], $scope['academic_cycle_id'], (int) $actor->id, $idempotencyKey);
            $fields['production_batch_id'] = (int) $locked->id;
            $fields['diagnostic_run_id'] = null;
            $fields['issuance_id'] = null;
            $fields['supplier_stock_allocation_id'] = (int) $stock->id;
            $fields['copy_policy'] = 'supplier_stock';
            $fields['payload_fingerprint'] = $this->fingerprint($fields);

            return $this->start($fields, $locked->status === OmrCaptureBatch::STATUS_OPEN
                && $session->status !== GradingSession::STATUS_FINALIZED, $actor);
        }, 3);
    }

    public function forDiagnostic(
        OmrDiagnosticRun $run,
        User $actor,
        string $idempotencyKey,
        ?string $mockInstanceKey = null,
    ): OmrPendingCaptureSet {
        $this->assertKey($idempotencyKey);

        return DB::transaction(function () use ($run, $actor, $idempotencyKey, $mockInstanceKey): OmrPendingCaptureSet {
            $locked = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($run->id);
            $scope = $this->access->actorScope($actor);
            $this->diagnosticSnapshot->assertCanProject($locked, $actor);
            if ((int) $locked->institution_id !== $scope['institution_id']
                || (int) $locked->academic_cycle_id !== $scope['academic_cycle_id']) {
                throw new RuntimeException('omr_capture_set_scope_mismatch');
            }
            if (($locked->hasMockContext() && ($mockInstanceKey === null
                    || ! preg_match('/^instance-00[1-3]$/D', $mockInstanceKey)))
                || (! $locked->hasMockContext() && $mockInstanceKey !== null)) {
                throw new RuntimeException('omr_capture_set_mock_instance_invalid');
            }

            if ($mockInstanceKey !== null) {
                $existing = OmrPendingCaptureSet::query()
                    ->where('diagnostic_run_id', (int) $locked->id)
                    ->where('mock_instance_key', $mockInstanceKey)
                    ->where('created_by_user_id', (int) $actor->id)
                    ->whereIn('status', [OmrPendingCaptureSet::STATUS_PENDING, OmrPendingCaptureSet::STATUS_CAPTURED, OmrPendingCaptureSet::STATUS_READY])
                    ->where('expires_at', '>', now())
                    ->latest('id')
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $contract = $this->contract((int) $locked->template_version_id);
            $fields = $this->pinnedFields($contract, $scope['institution_id'], $scope['academic_cycle_id'], (int) $actor->id, $idempotencyKey);
            $fields['production_batch_id'] = null;
            $fields['diagnostic_run_id'] = (int) $locked->id;
            $fields['issuance_id'] = null;
            $fields['copy_policy'] = 'diagnostic_only';
            $fields['mock_instance_key'] = $mockInstanceKey;
            $fields['payload_fingerprint'] = $this->fingerprint($fields);

            $canStart = $locked->hasMockContext()
                ? in_array($locked->status, [OmrDiagnosticRun::STATUS_OPEN, OmrDiagnosticRun::STATUS_NEEDS_REVIEW], true)
                    && in_array($locked->phase, [OmrDiagnosticRun::PHASE_CAPTURE, OmrDiagnosticRun::PHASE_REVIEW, OmrDiagnosticRun::PHASE_RECOVERY], true)
                : $locked->status === OmrDiagnosticRun::STATUS_OPEN
                    && $locked->phase === OmrDiagnosticRun::PHASE_CAPTURE;

            return $this->start($fields, $canStart, $actor);
        }, 3);
    }

    private function contract(int $versionId): OmrBlankPrintContract
    {
        $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($versionId);
        $contract = OmrBlankPrintContract::fromVersion($version);
        if ($contract->qrMode !== OmrQrMode::Disabled) {
            throw new RuntimeException('omr_capture_set_qr_mode_mismatch');
        }

        return $contract;
    }

    /** @return array<string, mixed> */
    private function pinnedFields(OmrBlankPrintContract $contract, int $institutionId, int $cycleId, int $actorId, string $key): array
    {
        return [
            'institution_id' => $institutionId,
            'academic_cycle_id' => $cycleId,
            'template_version_id' => $contract->templateVersionId,
            'created_by_user_id' => $actorId,
            'registration_mode' => $contract->registrationMode->value,
            'expected_pages_json' => $contract->compiledPages,
            'page_count' => count($contract->compiledPages),
            'pages_hash' => hash('sha256', json_encode($contract->compiledPages, JSON_THROW_ON_ERROR)),
            'native_bundle_hash' => $contract->nativeBundleHash,
            'artifact_hash' => $contract->artifactHash,
            'idempotency_key' => $key,
        ];
    }

    /** @param array<string, mixed> $fields */
    private function fingerprint(array $fields): string
    {
        return hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $fields */
    private function start(array $fields, bool $canStart, User $actor): OmrPendingCaptureSet
    {
        $query = OmrPendingCaptureSet::query()
            ->where('created_by_user_id', $actor->id)
            ->where('idempotency_key', $fields['idempotency_key']);
        if ($fields['production_batch_id'] !== null) {
            $query->where('production_batch_id', $fields['production_batch_id']);
        } else {
            $query->where('diagnostic_run_id', $fields['diagnostic_run_id']);
        }
        $existing = $query->first();
        if ($existing !== null) {
            if (! hash_equals((string) $existing->payload_fingerprint, $fields['payload_fingerprint'])) {
                throw new RuntimeException('omr_capture_set_idempotency_conflict');
            }
            if ($existing->isExpired()) {
                throw new RuntimeException('omr_capture_set_expired');
            }

            return $existing;
        }
        if (! $canStart) {
            throw new RuntimeException('omr_capture_set_context_closed');
        }

        $set = OmrPendingCaptureSet::query()->create($fields + [
            'public_id' => (string) Str::uuid(),
            'active_page_no' => 1,
            'revision' => 1,
            'identity_mode' => $this->identityMode((int) $fields['template_version_id']),
            'status' => OmrPendingCaptureSet::STATUS_PENDING,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);
        $this->audit->handle($set->production_batch_id !== null
            ? 'omr.production.capture_set_started'
            : 'omr.diagnostic.capture_set_started', [
                'capture_set_id' => $set->id,
                'production_batch_id' => $set->production_batch_id,
                'diagnostic_run_id' => $set->diagnostic_run_id,
                'template_version_id' => $set->template_version_id,
                'issuance_id' => $set->issuance_id,
                'supplier_stock_allocation_id' => $set->supplier_stock_allocation_id,
                'page_count' => $set->page_count,
            ], (string) $actor->id);

        return $set;
    }

    private function assertKey(string $key): void
    {
        if (! Str::isUuid($key)) {
            throw new InvalidArgumentException('omr_capture_set_idempotency_key_invalid');
        }
    }

    private function identityMode(int $versionId): string
    {
        $version = OmrAnswerSheetTemplateVersion::query()->find($versionId);
        $manifest = is_array($version?->authoring_manifest) ? $version->authoring_manifest : [];
        $method = is_array($manifest['identity_method'] ?? null) ? ($manifest['identity_method']['method'] ?? null) : null;

        return $method === 'write_in_manual' ? 'write_in_manual' : 'shaded_grid';
    }
}
