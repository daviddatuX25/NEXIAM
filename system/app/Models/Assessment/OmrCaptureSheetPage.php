<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class OmrCaptureSheetPage extends Model
{
    public const STATUS_PENDING_UPLOAD = 'pending_upload';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED_TERMINAL = 'failed_terminal';

    public const TERMINAL_STATUSES = [self::STATUS_SUCCEEDED, self::STATUS_FAILED_TERMINAL];

    protected $table = 'omr_capture_sheet_pages';

    protected $attributes = [
        'current_revision' => 1,
    ];

    protected $fillable = [
        'sheet_id', 'page_no', 'status',
        'asset_path', 'asset_hmac', 'asset_hmac_key_id', 'asset_key_id',
        'asset_format_version', 'asset_state',
        'original_mime', 'original_bytes', 'client_quad_json', 'crop_policy',
        'aligned_mime', 'aligned_bytes', 'aligned_width', 'aligned_height', 'aligned_hash',
        'aligned_asset_path', 'aligned_asset_hmac', 'aligned_asset_hmac_key_id', 'aligned_asset_key_id',
        'proposal_json', 'current_processing_run_id', 'current_revision', 'current_revision_id',
    ];

    protected function casts(): array
    {
        return [
            'page_no' => 'integer',
            'original_bytes' => 'integer',
            'aligned_bytes' => 'integer',
            'aligned_width' => 'integer',
            'aligned_height' => 'integer',
            'current_processing_run_id' => 'integer',
            'current_revision' => 'integer',
            'current_revision_id' => 'integer',
            'proposal_json' => 'array',
            'client_quad_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::deleting(static function (OmrCaptureSheetPage $page): void {
            if ($page->revisions()->exists()) {
                throw new LogicException('omr_capture_sheet_page_history_retained');
            }
        });
    }

    public function currentProcessingRun(): BelongsTo
    {
        return $this->belongsTo(OmrProcessingRun::class, 'current_processing_run_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheetPageRevision::class, 'current_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(OmrCaptureSheetPageRevision::class, 'page_id');
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'sheet_id');
    }

    public function blankInstanceClaim(): HasOne
    {
        return $this->hasOne(OmrBlankInstancePageClaim::class, 'capture_sheet_page_id');
    }

    public function qrDisabledClaim(): HasOne
    {
        return $this->hasOne(OmrQrDisabledPageClaim::class, 'capture_sheet_page_id');
    }

    public function processingRuns(): HasMany
    {
        return $this->hasMany(OmrProcessingRun::class, 'sheet_id', 'sheet_id')
            ->where('omr_processing_runs.page_no', $this->page_no);
    }
}
