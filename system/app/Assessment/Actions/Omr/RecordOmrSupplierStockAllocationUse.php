<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockAllocationUse;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\User;
use RuntimeException;

final class RecordOmrSupplierStockAllocationUse
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        OmrPendingCaptureSet $set,
        User $actor,
        int $captureSheetId,
        string $idempotencyKey,
    ): OmrSupplierStockAllocation {
        if ($set->supplier_stock_allocation_id === null || $set->issuance_id !== null || $set->copy_policy !== OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK) {
            throw new RuntimeException('supplier_stock_invalid');
        }

        $allocationRef = OmrSupplierStockAllocation::query()->find($set->supplier_stock_allocation_id);
        if ($allocationRef === null) {
            throw new RuntimeException('supplier_stock_invalid');
        }

        $lot = OmrSupplierStockLot::query()->lockForUpdate()->find($allocationRef->lot_id);
        $allocation = OmrSupplierStockAllocation::query()->lockForUpdate()->find($allocationRef->id);
        if ($lot === null || $allocation === null) {
            throw new RuntimeException('supplier_stock_invalid');
        }

        $existingUse = OmrSupplierStockAllocationUse::query()
            ->where('set_id', $set->id)
            ->first();
        if ($existingUse !== null) {
            if ((int) $existingUse->allocation_id !== (int) $allocation->id
                || (int) $existingUse->capture_sheet_id !== $captureSheetId) {
                throw new RuntimeException('supplier_stock_invalid');
            }

            return $allocation;
        }

        if ($lot->status !== OmrSupplierStockLot::STATUS_AVAILABLE
            || $allocation->status !== OmrSupplierStockAllocation::STATUS_ACTIVE
            || (int) $allocation->lot_id !== (int) $lot->id
            || (int) $allocation->batch_id !== (int) $set->production_batch_id
            || (int) $allocation->template_version_id !== (int) $set->template_version_id
            || (int) $lot->template_version_id !== (int) $set->template_version_id
            || (int) $allocation->institution_id !== (int) $set->institution_id
            || (int) $allocation->academic_cycle_id !== (int) $set->academic_cycle_id
            || (int) $lot->institution_id !== (int) $set->institution_id
            || (int) $lot->academic_cycle_id !== (int) $set->academic_cycle_id
            || (int) $allocation->page_count !== (int) $set->page_count
            || (int) $lot->page_count !== (int) $set->page_count
            || $allocation->copy_policy !== OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK) {
            throw new RuntimeException('supplier_stock_invalid');
        }

        if ($allocation->remainingQuantity() < 1) {
            throw new RuntimeException('supplier_stock_exhausted');
        }

        $allocation->used_quantity = (int) $allocation->used_quantity + 1;
        if ($allocation->used_quantity >= (int) $allocation->quantity) {
            $allocation->status = OmrSupplierStockAllocation::STATUS_EXHAUSTED;
        }
        $allocation->save();

        OmrSupplierStockAllocationUse::query()->create([
            'allocation_id' => $allocation->id,
            'lot_id' => $lot->id,
            'set_id' => $set->id,
            'capture_sheet_id' => $captureSheetId,
            'idempotency_key' => $idempotencyKey,
            'recorded_by_user_id' => $actor->id,
            'recorded_at' => now(),
        ]);

        $this->audit->handle('omr.supplier_stock.allocation_used', [
            'allocation_id' => $allocation->id,
            'lot_id' => $lot->id,
            'capture_set_id' => $set->id,
            'capture_sheet_id' => $captureSheetId,
            'used_quantity' => $allocation->used_quantity,
            'quantity' => $allocation->quantity,
            'status' => $allocation->status,
        ], (string) $actor->id);

        return $allocation;
    }
}
