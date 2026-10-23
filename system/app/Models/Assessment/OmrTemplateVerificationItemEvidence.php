<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One immutable diagnostic row for one mapped item in one capture.
 *
 * Option means and crop metadata are evidence projections only. This model
 * intentionally has no applicant, answer-key, score, or image-byte fields.
 */
class OmrTemplateVerificationItemEvidence extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new RuntimeException('verification_item_evidence_immutable');
        });

        static::deleting(static function (): void {
            throw new RuntimeException('verification_item_evidence_immutable');
        });
    }

    public const GROUP_APTITUDE_AREA = 'aptitude_area';

    public const GROUP_IDENTITY = 'identity';

    public const GROUP_CALIBRATION = 'calibration';

    public const GROUPS = [
        self::GROUP_APTITUDE_AREA,
        self::GROUP_IDENTITY,
        self::GROUP_CALIBRATION,
    ];

    public const STATE_MATCH = 'match';

    public const STATE_MISMATCH = 'mismatch';

    public const STATE_AMBIGUOUS = 'ambiguous';

    public const STATE_MISSING = 'missing';

    protected $table = 'omr_template_verification_item_evidence';

    protected $attributes = [
        'group_type' => self::GROUP_APTITUDE_AREA,
        'evidence_version' => 'v1',
        'row_version' => 0,
    ];

    protected $fillable = [
        'capture_id', 'group_type', 'area_key', 'area_position', 'question_no',
        'item_id', 'options', 'expected', 'detected', 'state', 'means', 'delta',
        'status', 'crop_asset_id', 'crop_asset_hash', 'crop_rect',
        'evidence_version', 'evidence_hash', 'row_version',
    ];

    protected function casts(): array
    {
        return [
            'capture_id' => 'integer',
            'area_position' => 'integer',
            'question_no' => 'integer',
            'options' => 'array',
            'means' => 'array',
            'delta' => 'float',
            'crop_asset_id' => 'integer',
            'crop_rect' => 'array',
            'row_version' => 'integer',
        ];
    }

    public function capture(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateVerificationCapture::class, 'capture_id');
    }

    public function cropAsset(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAsset::class, 'crop_asset_id');
    }

    public function scopeForGroup(Builder $query, string $groupType): Builder
    {
        return $query->where('group_type', $groupType);
    }

    public function scopeMismatches(Builder $query): Builder
    {
        return $query->where(function (Builder $nested): void {
            $nested->whereIn('state', [self::STATE_MISMATCH, self::STATE_AMBIGUOUS, self::STATE_MISSING])
                ->orWhereIn('status', [self::STATE_MISMATCH, self::STATE_AMBIGUOUS, self::STATE_MISSING]);
        });
    }

    public function scopeStableOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN area_position IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(area_position, 2147483647)')
            ->orderByRaw('CASE WHEN question_no IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(question_no, 2147483647)')
            ->orderByRaw('CASE WHEN item_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw("COALESCE(item_id, '')")
            ->orderBy('id');
    }
}
