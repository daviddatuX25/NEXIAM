<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * @property int $id
 * @property int $template_version_id
 * @property int $aptitude_area_id
 * @property int $display_order
 * @property int $max_items
 * @property float $region_x
 * @property float $region_y
 * @property float $region_w
 * @property float $region_h
 * @property string|null $projection_manifest_hash
 * @property string $projection_source
 * @property-read OmrAnswerSheetTemplateVersion|null $templateVersion
 * @property-read AptitudeArea|null $aptitudeArea
 * @property-read Collection<int, OmrTemplateQuestion> $questions
 */
class OmrTemplateArea extends Model
{
    protected $table = 'omr_template_areas';

    protected $attributes = ['projection_source' => 'activation_manifest'];

    protected static function booted(): void
    {
        static::saving(static function (self $area): void {
            $area->assertCompiledParentsMutable();
        });

        static::deleting(static function (self $area): void {
            $area->assertCompiledParentsMutable();
        });
    }

    private function assertCompiledParentsMutable(): void
    {
        $versionIds = array_unique(array_filter([
            $this->exists ? $this->getRawOriginal('template_version_id') : null,
            $this->template_version_id,
        ]));

        if (OmrAnswerSheetTemplateVersion::query()
            ->whereIn('id', $versionIds)
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
            ->exists()) {
            throw new RuntimeException('Compiled answer-sheet projections are immutable.');
        }
    }

    protected $fillable = [
        'template_version_id', 'aptitude_area_id', 'display_order', 'max_items',
        'region_x', 'region_y', 'region_w', 'region_h',
        'projection_manifest_hash', 'projection_source',
    ];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'max_items' => 'integer', 'region_x' => 'float', 'region_y' => 'float', 'region_w' => 'float', 'region_h' => 'float'];
    }

    /** @return BelongsTo<OmrAnswerSheetTemplateVersion, $this> */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    /** @return BelongsTo<AptitudeArea, $this> */
    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }

    /** @return HasMany<OmrTemplateQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(OmrTemplateQuestion::class, 'template_area_id');
    }
}
