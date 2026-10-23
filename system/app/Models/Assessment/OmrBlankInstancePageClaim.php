<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrBlankInstancePageClaim extends Model
{
    public const KIND_ORIGINAL = 'original';

    public const KIND_REUSABLE_COPY = 'reusable_copy';

    public const KIND_AUTHORIZED_COPY = 'authorized_copy';

    protected $fillable = [
        'blank_instance_page_id',
        'capture_sheet_page_id',
        'reproduction_authorization_id',
        'claim_kind',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'blank_instance_page_id' => 'integer',
            'capture_sheet_page_id' => 'integer',
            'reproduction_authorization_id' => 'integer',
            'created_by_user_id' => 'integer',
        ];
    }

    public function blankInstancePage(): BelongsTo
    {
        return $this->belongsTo(OmrBlankInstancePage::class, 'blank_instance_page_id');
    }

    public function captureSheetPage(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheetPage::class, 'capture_sheet_page_id');
    }

    public function reproductionAuthorization(): BelongsTo
    {
        return $this->belongsTo(OmrPaperReproductionAuthorization::class, 'reproduction_authorization_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
