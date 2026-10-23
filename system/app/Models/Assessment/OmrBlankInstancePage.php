<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrBlankInstancePage extends Model
{
    public const STATUS_PRINTED = 'printed';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_VOID = 'void';

    protected $table = 'omr_blank_instance_pages';

    protected $attributes = [
        'status' => self::STATUS_PRINTED,
    ];

    protected $fillable = [
        'instance_id', 'page_no', 'qr_payload_hash', 'signing_key_id',
        'print_artifact_hash', 'status', 'captured_page_id',
    ];

    protected function casts(): array
    {
        return [
            'instance_id' => 'integer',
            'page_no' => 'integer',
            'captured_page_id' => 'integer',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(OmrBlankInstance::class, 'instance_id');
    }

    public function capturedPage(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheetPage::class, 'captured_page_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(OmrBlankInstancePageClaim::class, 'blank_instance_page_id');
    }

    public function reproductionAuthorizations(): HasMany
    {
        return $this->hasMany(OmrPaperReproductionAuthorization::class, 'blank_instance_page_id');
    }

    public function isCaptured(): bool
    {
        return $this->status === self::STATUS_CAPTURED;
    }
}
