<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class OmrPendingCaptureSet extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_READY = 'ready';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'public_id', 'production_batch_id', 'diagnostic_run_id', 'institution_id',
        'academic_cycle_id', 'template_version_id', 'issuance_id', 'supplier_stock_allocation_id',
        'created_by_user_id', 'registration_mode', 'copy_policy', 'identity_mode',
        'expected_pages_json', 'page_count', 'active_page_no', 'pages_hash',
        'native_bundle_hash', 'artifact_hash', 'idempotency_key', 'payload_fingerprint',
        'revision', 'status', 'pairing_code', 'manual_identity_json', 'adopted_sheet_id',
        'mock_instance_key',
        'resolution_code', 'expires_at',
    ];

    protected static function booted(): void
    {
        self::creating(static function (self $set): void {
            $production = $set->production_batch_id !== null;
            $diagnostic = $set->diagnostic_run_id !== null;
            $validProductionIssuance = $set->issuance_id !== null
                && $set->supplier_stock_allocation_id === null
                && in_array($set->copy_policy, [OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE, OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER], true);
            $validProductionSupplier = $set->issuance_id === null
                && $set->supplier_stock_allocation_id !== null
                && $set->copy_policy === OmrSupplierStockAllocation::COPY_POLICY_SUPPLIER_STOCK;
            if ($production === $diagnostic
                || ($production && ! $validProductionIssuance && ! $validProductionSupplier)
                || ($diagnostic && ($set->issuance_id !== null || $set->supplier_stock_allocation_id !== null || $set->copy_policy !== 'diagnostic_only'))) {
                throw new LogicException('omr_capture_set_context_invalid');
            }
            $pages = $set->expected_pages_json;
            if (! is_array($pages) || count($pages) < 1 || count($pages) > 4
                || count($pages) !== (int) $set->page_count) {
                throw new LogicException('omr_capture_set_pages_invalid');
            }
            foreach ($pages as $index => $page) {
                if ($page !== ['page_no' => $index + 1]) {
                    throw new LogicException('omr_capture_set_pages_invalid');
                }
            }
            if ((int) $set->revision !== 1
                || (int) $set->active_page_no !== 1
                || $set->status !== self::STATUS_PENDING
                || $set->adopted_sheet_id !== null
                || $set->resolution_code !== null
                || $set->expires_at === null
                || ! $set->expires_at->isFuture()) {
                throw new LogicException('omr_capture_set_initial_state_invalid');
            }
        });

        self::updating(static function (self $set): void {
            foreach ([
                'public_id', 'production_batch_id', 'diagnostic_run_id', 'institution_id',
                'academic_cycle_id', 'template_version_id', 'issuance_id', 'supplier_stock_allocation_id',
                'created_by_user_id', 'registration_mode', 'copy_policy', 'identity_mode',
                'expected_pages_json', 'page_count', 'pages_hash', 'native_bundle_hash',
                'mock_instance_key',
                'artifact_hash', 'idempotency_key', 'payload_fingerprint', 'expires_at',
            ] as $field) {
                if ($set->isDirty($field)) {
                    throw new LogicException('omr_capture_set_context_immutable');
                }
            }
            if ($set->isDirty('adopted_sheet_id')
                && ($set->getOriginal('adopted_sheet_id') !== null
                    || $set->adopted_sheet_id === null
                    || $set->getOriginal('status') !== self::STATUS_READY
                    || $set->status !== self::STATUS_READY)) {
                throw new LogicException('omr_capture_set_adopted_sheet_immutable');
            }
            if ($set->isDirty('resolution_code')
                && in_array($set->getOriginal('status'), [self::STATUS_EXPIRED, self::STATUS_CANCELLED], true)) {
                throw new LogicException('omr_capture_set_resolution_code_immutable');
            }
            if ($set->resolution_code !== null
                && (! is_string($set->resolution_code)
                    || trim($set->resolution_code) === ''
                    || $set->status !== self::STATUS_READY)) {
                throw new LogicException('omr_capture_set_resolution_code_invalid');
            }
            if ($set->isDirty(['status', 'active_page_no', 'revision', 'pairing_code', 'manual_identity_json', 'adopted_sheet_id', 'resolution_code'])) {
                if ((int) $set->revision !== (int) $set->getOriginal('revision') + 1
                    || (int) $set->active_page_no < 1
                    || (int) $set->active_page_no > (int) $set->page_count
                    || ! in_array($set->status, [self::STATUS_PENDING, self::STATUS_CAPTURED, self::STATUS_READY, self::STATUS_EXPIRED, self::STATUS_CANCELLED], true)
                    || ! in_array($set->pairing_code, [null, 'pairing_missing', 'pairing_conflict', 'identity_review_required'], true)
                    || ($set->status === self::STATUS_READY && $set->pairing_code !== null)
                    || ($set->status === self::STATUS_PENDING && $set->pairing_code !== null)
                    || (in_array($set->getOriginal('status'), [self::STATUS_EXPIRED, self::STATUS_CANCELLED], true) && $set->status !== $set->getOriginal('status'))) {
                    throw new LogicException('omr_capture_set_transition_invalid');
                }
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'expected_pages_json' => 'array',
            'manual_identity_json' => 'array',
            'expires_at' => 'datetime',
            'page_count' => 'integer',
            'active_page_no' => 'integer',
            'supplier_stock_allocation_id' => 'integer',
            'adopted_sheet_id' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureBatch::class, 'production_batch_id');
    }

    public function diagnosticRun(): BelongsTo
    {
        return $this->belongsTo(OmrDiagnosticRun::class, 'diagnostic_run_id');
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

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(OmrQrDisabledIssuance::class, 'issuance_id');
    }

    public function supplierStockAllocation(): BelongsTo
    {
        return $this->belongsTo(OmrSupplierStockAllocation::class, 'supplier_stock_allocation_id');
    }

    public function adoptedSheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'adopted_sheet_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function pageClaims(): HasMany
    {
        return $this->hasMany(OmrQrDisabledPageClaim::class, 'set_id');
    }
}
