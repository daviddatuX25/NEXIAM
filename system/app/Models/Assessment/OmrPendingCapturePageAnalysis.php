<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OmrPendingCapturePageAnalysis extends Model
{
    protected $table = 'omr_pending_capture_page_analyses';

    protected $fillable = [
        'evidence_id',
        'detected_applicant_number',
        'recognition_code',
        'evidence_json',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('omr_capture_page_analysis_immutable');
        });
        self::deleting(static function (): void {
            throw new LogicException('omr_capture_page_analysis_immutable');
        });
    }

    protected function casts(): array
    {
        return ['evidence_json' => 'array'];
    }

    public function pageEvidence(): BelongsTo
    {
        return $this->belongsTo(OmrPendingCapturePageEvidence::class, 'evidence_id');
    }
}
