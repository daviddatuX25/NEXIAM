<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrAnswerSheetTemplateVersion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRED = 'retired';

    protected $table = 'omr_answer_sheet_template_versions';

    protected $attributes = ['status' => self::STATUS_DRAFT];

    protected $fillable = [
        'template_id', 'version', 'status', 'page_width', 'page_height',
        'identity_regions_json', 'provider_config_json', 'config_hash', 'template_json_hash',
        'native_bundle_version', 'native_bundle_json', 'native_bundle_hash',
        'engine_name', 'engine_commit', 'patch_identity',
        'review_manifest_json', 'review_manifest_hash', 'print_overlay_json',
        'layout_blueprint_json', 'layout_blueprint_hash', 'layout_solver_version',
        'activated_by_user_id', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'page_width' => 'integer',
            'page_height' => 'integer',
            'identity_regions_json' => 'array',
            'provider_config_json' => 'array',
            'native_bundle_json' => 'array',
            'review_manifest_json' => 'array',
            'print_overlay_json' => 'array',
            'layout_blueprint_json' => 'array',
            'activated_at' => 'datetime',
        ];
    }


    public function template(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplate::class, 'template_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(OmrTemplateArea::class, 'template_version_id');
    }
    public function pages(): HasMany
    {
        return $this->hasMany(OmrAnswerSheetPage::class, 'template_version_id');
    }

    public function pageCount(): int
    {
        $template = is_array($this->native_bundle_json)
            ? ($this->native_bundle_json['template'] ?? [])
            : [];
        $pages = is_array($template) ? ($template['pages'] ?? null) : null;

        return is_array($pages) && $pages !== [] ? count($pages) : 1;
    }


    public function pairs(): HasMany
    {
        return $this->hasMany(OmrTemplateBookletPair::class, 'template_version_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
