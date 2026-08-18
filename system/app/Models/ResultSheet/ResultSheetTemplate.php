<?php

namespace App\Models\ResultSheet;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versioned result-sheet template authority (legacy parity). Exactly one
 * template is active at a time; the active template drives every result-sheet
 * render. Ratings on the sheet come from frozen `score_records.converted_score`
 * — `rating_scale_id` is reserved for a future rating-scale module.
 */
class ResultSheetTemplate extends Model
{
    public const MODE_HTML = 'html';

    public const MODE_DOCX = 'docx';

    public const PAPER_A4 = 'a4';

    public const PAPER_LEGAL = 'legal';

    public const PAPER_LETTER = 'letter';

    public const ORIENTATION_PORTRAIT = 'portrait';

    public const ORIENTATION_LANDSCAPE = 'landscape';

    public const LOGICAL_FULL = 'full';

    public const LOGICAL_HALF_A4 = 'half_a4';

    public const LOGICAL_HALF_LEGAL = 'half_legal';

    public const LOGICAL_HALF_LETTER = 'half_letter';

    protected $fillable = [
        'name',
        'mode',
        'paper_size',
        'orientation',
        'logical_unit',
        'content',
        'document_path',
        'document_checksum',
        'document_extension',
        'watermark_text',
        'rating_scale_id',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }
}
