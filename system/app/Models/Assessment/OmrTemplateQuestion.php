<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * @property int $id
 * @property int $template_area_id
 * @property int|null $template_version_id
 * @property int $page_no
 * @property int $question_no
 * @property string|null $physical_item_uid
 * @property string|null $printed_section_uid
 * @property string|null $native_label
 * @property int|null $global_position
 * @property float $region_x
 * @property float $region_y
 * @property float $region_w
 * @property float $region_h
 * @property string|null $projection_manifest_hash
 * @property string $projection_source
 * @property-read OmrTemplateArea|null $area
 * @property-read Collection<int, OmrTemplateQuestionOption> $options
 */
class OmrTemplateQuestion extends Model
{
    protected $table = 'omr_template_questions';

    protected $attributes = ['projection_source' => 'activation_manifest'];

    protected static function booted(): void
    {
        static::saving(static function (self $question): void {
            $question->assertCompiledParentsMutable();
        });

        static::deleting(static function (self $question): void {
            $question->assertCompiledParentsMutable();
        });
    }

    private function assertCompiledParentsMutable(): void
    {
        $areaIds = array_unique(array_filter([
            $this->exists ? $this->getRawOriginal('template_area_id') : null,
            $this->template_area_id,
        ]));
        $versionIds = OmrTemplateArea::query()->whereIn('id', $areaIds)->pluck('template_version_id');

        if (OmrAnswerSheetTemplateVersion::query()
            ->whereIn('id', $versionIds)
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
            ->exists()) {
            throw new RuntimeException('Compiled answer-sheet projections are immutable.');
        }
    }

    protected $fillable = [
        'template_area_id', 'template_version_id', 'page_no', 'question_no', 'physical_item_uid', 'printed_section_uid',
        'native_label', 'global_position', 'region_x', 'region_y', 'region_w', 'region_h', 'projection_manifest_hash',
        'projection_source',
    ];

    protected function casts(): array
    {
        return ['page_no' => 'integer', 'question_no' => 'integer', 'global_position' => 'integer', 'region_x' => 'float', 'region_y' => 'float', 'region_w' => 'float', 'region_h' => 'float'];
    }

    /** @return BelongsTo<OmrTemplateArea, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateArea::class, 'template_area_id');
    }

    /** @return HasMany<OmrTemplateQuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(OmrTemplateQuestionOption::class, 'template_question_id');
    }
}
