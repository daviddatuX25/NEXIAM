<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrCaptureSheetIssue extends Model
{
    protected $table = 'omr_capture_sheet_issues';

    protected $fillable = ['sheet_id', 'page_no', 'area_code', 'question_no', 'issue_code', 'severity', 'state', 'evidence_json', 'resolution_code', 'resolution_reason', 'resolved_by_user_id', 'resolved_at', 'version'];

    protected function casts(): array
    {
        return ['page_no' => 'integer', 'question_no' => 'integer', 'evidence_json' => 'array', 'resolved_at' => 'datetime', 'version' => 'integer'];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'sheet_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
