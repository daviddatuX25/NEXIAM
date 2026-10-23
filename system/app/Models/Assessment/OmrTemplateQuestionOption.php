<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $template_question_id
 * @property string $code
 * @property int $display_order
 * @property float $region_x
 * @property float $region_y
 * @property float $region_w
 * @property float $region_h
 * @property string|null $projection_manifest_hash
 * @property string $projection_source
 * @property-read OmrTemplateQuestion|null $question
 */
class OmrTemplateQuestionOption extends Model
{
    protected $table = 'omr_template_question_options';

    protected $attributes = ['projection_source' => 'activation_manifest'];

    protected static function booted(): void
    {
        static::saving(static function (self $option): void {
            $option->assertCompiledParentsMutable();
        });

        static::deleting(static function (self $option): void {
            $option->assertCompiledParentsMutable();
        });
    }

    private function assertCompiledParentsMutable(): void
    {
        $questionIds = array_unique(array_filter([
            $this->exists ? $this->getRawOriginal('template_question_id') : null,
            $this->template_question_id,
        ]));
        $areaIds = OmrTemplateQuestion::query()->whereIn('id', $questionIds)->pluck('template_area_id');
        $versionIds = OmrTemplateArea::query()->whereIn('id', $areaIds)->pluck('template_version_id');

        if (OmrAnswerSheetTemplateVersion::query()
            ->whereIn('id', $versionIds)
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
            ->exists()) {
            throw new RuntimeException('Compiled answer-sheet projections are immutable.');
        }
    }

    protected $fillable = [
        'template_question_id', 'code', 'display_order', 'region_x', 'region_y', 'region_w', 'region_h',
        'projection_manifest_hash', 'projection_source',
    ];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'region_x' => 'float', 'region_y' => 'float', 'region_w' => 'float', 'region_h' => 'float'];
    }

    /** @return BelongsTo<OmrTemplateQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateQuestion::class, 'template_question_id');
    }
}
