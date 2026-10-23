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

    public const SOURCE_BUILDER = 'builder';

    public const SOURCE_DOCX = 'docx';

    public const SOURCE_LEGACY_HTML = 'legacy_html';

    public const BUILDER_SCHEMA_VERSION = 1;

    public const BUILDER_COMPILER_VERSION = 'result-sheet-builder-1';

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
        'source_kind',
        'builder_schema_version',
        'builder_json',
        'compiled_html',
        'compiler_version',
        'lock_version',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'builder_json' => 'array',
            'builder_schema_version' => 'integer',
            'lock_version' => 'integer',
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

    public function printChecksum(): string
    {
        return hash('sha256', json_encode([
            'mode' => $this->mode,
            'source_kind' => $this->source_kind,
            'builder_schema_version' => $this->builder_schema_version,
            'compiler_version' => $this->compiler_version,
            'builder_json' => $this->builder_json,
            'compiled_html_checksum' => hash('sha256', (string) ($this->compiled_html ?? $this->content)),
            'paper_size' => $this->paper_size,
            'orientation' => $this->orientation,
            'logical_unit' => $this->logical_unit,
            'watermark_text' => $this->watermark_text,
            'document_checksum' => $this->document_checksum,
        ], JSON_THROW_ON_ERROR));
    }

    public function isBuilder(): bool
    {
        return $this->source_kind === self::SOURCE_BUILDER;
    }
}
