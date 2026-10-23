<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OmrSupplierStockAllocationUse extends Model
{
    protected $table = 'omr_supplier_stock_allocation_uses';

    protected $fillable = [
        'allocation_id',
        'lot_id',
        'set_id',
        'capture_sheet_id',
        'idempotency_key',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('omr_supplier_stock_allocation_use_immutable');
        });
        self::deleting(static function (): void {
            throw new LogicException('omr_supplier_stock_allocation_use_immutable');
        });
    }

    protected function casts(): array
    {
        return [
            'allocation_id' => 'integer',
            'lot_id' => 'integer',
            'set_id' => 'integer',
            'capture_sheet_id' => 'integer',
            'recorded_by_user_id' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(OmrSupplierStockAllocation::class, 'allocation_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(OmrSupplierStockLot::class, 'lot_id');
    }

    public function captureSet(): BelongsTo
    {
        return $this->belongsTo(OmrPendingCaptureSet::class, 'set_id');
    }

    public function captureSheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'capture_sheet_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
