<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OmrSupplierStockLot extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_VOID = 'void';

    protected $table = 'omr_supplier_stock_lots';

    protected $fillable = [
        'institution_id',
        'academic_cycle_id',
        'template_version_id',
        'page_count',
        'source_sha256',
        'source_kind',
        'vendor_name',
        'source_reference',
        'received_quantity',
        'available_quantity',
        'page_inventory',
        'custody_evidence',
        'inventory_revision',
        'status',
        'received_by_user_id',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'academic_cycle_id' => 'integer',
            'template_version_id' => 'integer',
            'page_count' => 'integer',
            'received_quantity' => 'integer',
            'available_quantity' => 'integer',
            'page_inventory' => 'array',
            'custody_evidence' => 'array',
            'inventory_revision' => 'integer',
            'received_by_user_id' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function availablePageSets(): int
    {
        $entries = (array) $this->page_inventory;
        if ($entries === []) {
            return 0;
        }
        $counts = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $counts[] = max(0, (int) ($entry['available_quantity'] ?? 0));
            }
        }

        return $counts === [] ? 0 : min($counts);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OmrSupplierStockAllocation::class, 'lot_id');
    }
}
