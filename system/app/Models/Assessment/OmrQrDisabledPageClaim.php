<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OmrQrDisabledPageClaim extends Model
{
    protected $table = 'omr_qr_disabled_page_claims';

    protected $fillable = [
        'issuance_id',
        'supplier_stock_allocation_id',
        'set_id',
        'evidence_id',
        'capture_sheet_page_id',
        'page_no',
        'input_hash',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('omr_qr_disabled_page_claim_immutable');
        });
        self::deleting(static function (): void {
            throw new LogicException('omr_qr_disabled_page_claim_immutable');
        });
    }

    protected function casts(): array
    {
        return [
            'issuance_id' => 'integer',
            'supplier_stock_allocation_id' => 'integer',
            'set_id' => 'integer',
            'evidence_id' => 'integer',
            'capture_sheet_page_id' => 'integer',
            'page_no' => 'integer',
            'created_by_user_id' => 'integer',
        ];
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(OmrQrDisabledIssuance::class, 'issuance_id');
    }

    public function supplierStockAllocation(): BelongsTo
    {
        return $this->belongsTo(OmrSupplierStockAllocation::class, 'supplier_stock_allocation_id');
    }

    public function captureSet(): BelongsTo
    {
        return $this->belongsTo(OmrPendingCaptureSet::class, 'set_id');
    }

    public function pageEvidence(): BelongsTo
    {
        return $this->belongsTo(OmrPendingCapturePageEvidence::class, 'evidence_id');
    }

    public function captureSheetPage(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheetPage::class, 'capture_sheet_page_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
