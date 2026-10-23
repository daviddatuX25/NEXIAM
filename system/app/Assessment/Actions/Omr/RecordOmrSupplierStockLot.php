<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrSupplierStockLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RecordOmrSupplierStockLot
{
    public function __construct(
        private readonly OmrPendingCaptureAccess $access,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, mixed> $custodyEvidence */
    public function handle(
        OmrAnswerSheetTemplateVersion $version,
        User $actor,
        string $vendorName,
        string $sourceReference,
        int $receivedQuantity,
        array $custodyEvidence = [],
    ): OmrSupplierStockLot {
        $vendorName = trim($vendorName);
        $sourceReference = trim($sourceReference);
        if ($vendorName === '' || $sourceReference === '' || $receivedQuantity < 1 || $receivedQuantity > 100000) {
            throw new InvalidArgumentException('supplier_stock_lot_input_invalid');
        }

        $scope = $this->access->actorScope($actor);

        return DB::transaction(function () use ($version, $actor, $vendorName, $sourceReference, $receivedQuantity, $custodyEvidence, $scope): OmrSupplierStockLot {
            $lockedVersion = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($version->id);
            if (OmrSourcePolicy::sourceKindForVersion($lockedVersion) !== OmrSourcePolicy::UPLOADED_ORIGINAL) {
                throw new RuntimeException('supplier_stock_requires_uploaded_version');
            }

            $pageCount = $lockedVersion->pageCount();
            if ($pageCount < 1 || $pageCount > 4) {
                throw new RuntimeException('supplier_stock_page_count_invalid');
            }

            $pageInventory = [];
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pageInventory[] = [
                    'page_no' => $pageNo,
                    'received_quantity' => $receivedQuantity,
                    'available_quantity' => $receivedQuantity,
                ];
            }

            $manifest = is_array($lockedVersion->authoring_manifest) ? $lockedVersion->authoring_manifest : [];
            $sourceSha256 = is_string($manifest['source_sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/i', $manifest['source_sha256']) === 1
                ? strtolower($manifest['source_sha256'])
                : strtolower((string) $lockedVersion->native_bundle_hash);

            $lot = OmrSupplierStockLot::query()->create([
                'institution_id' => $scope['institution_id'],
                'academic_cycle_id' => $scope['academic_cycle_id'],
                'template_version_id' => $lockedVersion->id,
                'page_count' => $pageCount,
                'source_sha256' => $sourceSha256,
                'source_kind' => OmrSourcePolicy::UPLOADED_ORIGINAL,
                'vendor_name' => $vendorName,
                'source_reference' => $sourceReference,
                'received_quantity' => $receivedQuantity,
                'available_quantity' => $receivedQuantity,
                'page_inventory' => $pageInventory,
                'custody_evidence' => $custodyEvidence,
                'inventory_revision' => 1,
                'status' => OmrSupplierStockLot::STATUS_AVAILABLE,
                'received_by_user_id' => $actor->id,
                'received_at' => now(),
            ]);

            $this->audit->handle('omr.supplier_stock.lot_recorded', [
                'lot_id' => $lot->id,
                'institution_id' => $lot->institution_id,
                'academic_cycle_id' => $lot->academic_cycle_id,
                'template_version_id' => $lot->template_version_id,
                'vendor_name' => $lot->vendor_name,
                'source_reference' => $lot->source_reference,
                'received_quantity' => $lot->received_quantity,
                'page_count' => $lot->page_count,
            ], (string) $actor->id);

            return $lot;
        }, 3);
    }
}
