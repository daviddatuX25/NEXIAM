<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class AllocateOmrSupplierStockToBatch
{
    public function __construct(
        private readonly OmrPendingCaptureAccess $access,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        GradingSession $gradingSession,
        OmrCaptureBatch $batch,
        OmrSupplierStockLot $lot,
        User $actor,
        int $quantity,
        string $idempotencyKey,
    ): OmrSupplierStockAllocation {
        if ($quantity < 1 || $quantity > 10000) {
            throw new InvalidArgumentException('supplier_stock_quantity_invalid');
        }
        if (! Str::isUuid($idempotencyKey)) {
            throw new InvalidArgumentException('supplier_stock_idempotency_key_invalid');
        }

        $scope = $this->access->actorScope($actor);

        return DB::transaction(function () use ($gradingSession, $batch, $lot, $actor, $quantity, $idempotencyKey, $scope): OmrSupplierStockAllocation {
            $session = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);
            $lockedBatch = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $lockedLot = OmrSupplierStockLot::query()->lockForUpdate()->findOrFail($lot->id);

            $existing = OmrSupplierStockAllocation::query()
                ->where('batch_id', $lockedBatch->id)
                ->where('allocated_by_user_id', $actor->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                if ((int) $existing->lot_id !== (int) $lockedLot->id || (int) $existing->quantity !== $quantity) {
                    throw new RuntimeException('supplier_stock_allocation_idempotency_conflict');
                }

                return $existing;
            }

            if ((int) $lockedBatch->grading_session_id !== (int) $session->id) {
                throw new RuntimeException('supplier_stock_session_batch_mismatch');
            }
            if ($lockedBatch->status !== OmrCaptureBatch::STATUS_OPEN || $session->status === GradingSession::STATUS_FINALIZED) {
                throw new RuntimeException('supplier_stock_batch_closed');
            }

            $examSession = $session->examSession()->with('academicCycle', 'currentMaterialSnapshot')->firstOrFail();
            if ((int) $examSession->academic_cycle_id !== $scope['academic_cycle_id']
                || (int) $examSession->academicCycle?->institution_id !== $scope['institution_id']
                || (int) $lockedLot->institution_id !== $scope['institution_id']
                || (int) $lockedLot->academic_cycle_id !== $scope['academic_cycle_id']) {
                throw new RuntimeException('supplier_stock_scope_mismatch');
            }

            $snapshot = $examSession->currentMaterialSnapshot;
            $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($lockedBatch->template_version_id);
            if ($snapshot === null
                || (int) $snapshot->template_version_id !== (int) $lockedBatch->template_version_id
                || (int) $snapshot->booklet_version_id !== (int) $lockedBatch->booklet_version_id
                || (int) $lockedLot->template_version_id !== (int) $version->id
                || OmrSourcePolicy::sourceKindForVersion($version) !== OmrSourcePolicy::UPLOADED_ORIGINAL
                || (int) $lockedLot->page_count !== $version->pageCount()) {
                throw new RuntimeException('supplier_stock_material_mismatch');
            }

            if ($lockedLot->status !== OmrSupplierStockLot::STATUS_AVAILABLE || $lockedLot->availablePageSets() < $quantity) {
                throw new RuntimeException('supplier_stock_insufficient');
            }

            $updatedInventory = [];
            foreach ((array) $lockedLot->page_inventory as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $available = (int) ($entry['available_quantity'] ?? 0);
                if ($available < $quantity) {
                    throw new RuntimeException('supplier_stock_insufficient');
                }
                $entry['available_quantity'] = $available - $quantity;
                $updatedInventory[] = $entry;
            }

            $lockedLot->page_inventory = $updatedInventory;
            $lockedLot->available_quantity = $lockedLot->availablePageSets();
            $lockedLot->inventory_revision = (int) $lockedLot->inventory_revision + 1;
            $lockedLot->save();

            $allocation = OmrSupplierStockAllocation::query()->create([
                'lot_id' => $lockedLot->id,
                'institution_id' => $scope['institution_id'],
                'academic_cycle_id' => $scope['academic_cycle_id'],
                'grading_session_id' => $session->id,
                'batch_id' => $lockedBatch->id,
                'template_version_id' => $version->id,
                'page_count' => $version->pageCount(),
                'copy_policy' => OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK,
                'quantity' => $quantity,
                'used_quantity' => 0,
                'status' => OmrSupplierStockAllocation::STATUS_ACTIVE,
                'idempotency_key' => $idempotencyKey,
                'allocated_by_user_id' => $actor->id,
                'allocated_at' => now(),
            ]);

            $this->audit->handle('omr.supplier_stock.batch_allocated', [
                'allocation_id' => $allocation->id,
                'lot_id' => $lockedLot->id,
                'batch_id' => $lockedBatch->id,
                'grading_session_id' => $session->id,
                'template_version_id' => $version->id,
                'quantity' => $quantity,
                'remaining_lot_sets' => $lockedLot->available_quantity,
            ], (string) $actor->id);

            return $allocation;
        }, 3);
    }
}
