<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OmrSupplierStockAllocation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXHAUSTED = 'exhausted';

    public const STATUS_VOID = 'void';

    public const COPY_POLICY_SUPPLIER_STOCK = 'supplier_stock';

    protected $table = 'omr_supplier_stock_allocations';

    protected $fillable = [
        'lot_id',
        'institution_id',
        'academic_cycle_id',
        'grading_session_id',
        'batch_id',
        'template_version_id',
        'page_count',
        'copy_policy',
        'quantity',
        'used_quantity',
        'status',
        'idempotency_key',
        'allocated_by_user_id',
        'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'lot_id' => 'integer',
            'institution_id' => 'integer',
            'academic_cycle_id' => 'integer',
            'grading_session_id' => 'integer',
            'batch_id' => 'integer',
            'template_version_id' => 'integer',
            'page_count' => 'integer',
            'quantity' => 'integer',
            'used_quantity' => 'integer',
            'allocated_by_user_id' => 'integer',
            'allocated_at' => 'datetime',
        ];
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->used_quantity);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(OmrSupplierStockLot::class, 'lot_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    public function gradingSession(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class, 'grading_session_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureBatch::class, 'batch_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_user_id');
    }

    public function uses(): HasMany
    {
        return $this->hasMany(OmrSupplierStockAllocationUse::class, 'allocation_id');
    }
}
