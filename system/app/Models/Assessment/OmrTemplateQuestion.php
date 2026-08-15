<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use RuntimeException;

class OmrTemplateQuestion extends Model
{
    protected $table = 'omr_template_questions';

    protected $attributes = ['projection_source' => 'activation_manifest'];

    protected static function booted(): void
    {
        static::saving(static function (self $question): void {
            $question->assertProjectionParentMutable();
        });

        static::deleting(static function (self $question): void {
            $question->assertProjectionParentMutable();
        });
    }

    private function assertProjectionParentMutable(): void
    {
        $status = $this->area()->with('templateVersion')->first()?->templateVersion?->status;

        if (in_array($status, [OmrAnswerSheetTemplateVersion::STATUS_ACTIVE, OmrAnswerSheetTemplateVersion::STATUS_RETIRED], true)) {
            throw new RuntimeException('OMR template projections are immutable after activation.');
        }
    }

    protected $fillable = [
        'template_area_id', 'page_no', 'question_no', 'region_x', 'region_y', 'region_w', 'region_h',
        'projection_manifest_hash', 'projection_source',
    ];

    protected function casts(): array
    {
        return ['page_no' => 'integer', 'question_no' => 'integer', 'region_x' => 'float', 'region_y' => 'float', 'region_w' => 'float', 'region_h' => 'float'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateArea::class, 'template_area_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(OmrTemplateQuestionOption::class, 'template_question_id');
    }
}
