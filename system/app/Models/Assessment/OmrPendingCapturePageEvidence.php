<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OmrPendingCapturePageEvidence extends Model
{
    protected $table = 'omr_pending_capture_page_evidence';

    protected $fillable = [
        'set_id', 'page_no', 'client_capture_id', 'input_hash', 'asset_path',
        'mime_type', 'byte_count', 'captured_by_user_id', 'replacement_reason', 'supersedes_id',
        'client_quad_json', 'crop_policy',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('omr_capture_set_evidence_immutable');
        });
        self::deleting(static function (): void {
            throw new LogicException('omr_capture_set_evidence_immutable');
        });
    }

    protected function casts(): array
    {
        return ['page_no' => 'integer', 'byte_count' => 'integer', 'client_quad_json' => 'array'];
    }

    public function captureSet(): BelongsTo
    {
        return $this->belongsTo(OmrPendingCaptureSet::class, 'set_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
