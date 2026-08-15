<?php

namespace App\Models\Assessment;

use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use RuntimeException;

class OmrTemplateArea extends Model
{
    protected $table = 'omr_template_areas';

    protected $attributes = ['projection_source' => 'activation_manifest'];

    protected static function booted(): void
    {
        static::saving(static function (self $area): void {
            $area->assertProjectionParentMutable();
        });

        static::deleting(static function (self $area): void {
            $area->assertProjectionParentMutable();
        });
    }

    private function assertProjectionParentMutable(): void
    {
        $status = $this->templateVersion()->value('status');

        if (in_array($status, [OmrAnswerSheetTemplateVersion::STATUS_ACTIVE, OmrAnswerSheetTemplateVersion::STATUS_RETIRED], true)) {
            throw new RuntimeException('OMR template projections are immutable after activation.');
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

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(OmrTemplateQuestion::class, 'template_area_id');
    }
}
