<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use RuntimeException;

class OmrTemplateQuestionOption extends Model
{
    protected $table = 'omr_template_question_options';

    protected $attributes = ['projection_source' => 'activation_manifest'];

    protected static function booted(): void
    {
        static::saving(static function (self $option): void {
            $option->assertProjectionParentMutable();
        });

        static::deleting(static function (self $option): void {
            $option->assertProjectionParentMutable();
        });
    }

    private function assertProjectionParentMutable(): void
    {
        $status = $this->question()->with('area.templateVersion')->first()?->area?->templateVersion?->status;

        if (in_array($status, [OmrAnswerSheetTemplateVersion::STATUS_ACTIVE, OmrAnswerSheetTemplateVersion::STATUS_RETIRED], true)) {
            throw new RuntimeException('OMR template projections are immutable after activation.');
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

    public function question(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateQuestion::class, 'template_question_id');
    }
}
