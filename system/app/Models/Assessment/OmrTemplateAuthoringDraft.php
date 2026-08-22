<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrTemplateAuthoringDraft extends Model
{
    public const SOURCE_HTML = 'html';
    public const SOURCE_DOCX = 'docx';
    public const SOURCE_PDF = 'pdf';
    public const SOURCE_IMAGE = 'image';
    public const SOURCE_PRESET = 'preset';

    public const STAGE_DRAFT = 'draft';
    public const STAGE_SOURCE_CONFIRMED = 'source_confirmed';
    public const STAGE_PAGE_PREPARED = 'page_prepared';
    public const STAGE_MAPPING_COMPLETE = 'mapping_complete';
    public const STAGE_DIGITAL_VERIFIED = 'digital_verified';
    public const STAGE_COMPILED = 'compiled';

    public const SOURCE_KINDS = [
        self::SOURCE_HTML,
        self::SOURCE_DOCX,
        self::SOURCE_PDF,
        self::SOURCE_IMAGE,
        self::SOURCE_PRESET,
    ];

    public const STAGES = [
        self::STAGE_DRAFT,
        self::STAGE_SOURCE_CONFIRMED,
        self::STAGE_PAGE_PREPARED,
        self::STAGE_MAPPING_COMPLETE,
        self::STAGE_DIGITAL_VERIFIED,
        self::STAGE_COMPILED,
    ];

    protected $table = 'omr_template_authoring_drafts';

    protected $attributes = [
        'stage' => self::STAGE_DRAFT,
        'authoring_json' => '{}',
        'lock_version' => 0,
    ];

    protected $fillable = [
        'template_id', 'code', 'name', 'source_kind', 'stage',
        'marker_profile_id', 'authoring_json', 'lock_version',
        'owner_user_id', 'compiled_version_id',
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'marker_profile_id' => 'integer',
            'authoring_json' => 'array',
            'lock_version' => 'integer',
            'owner_user_id' => 'integer',
            'compiled_version_id' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplate::class, 'template_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function markerProfile(): BelongsTo
    {
        return $this->belongsTo(OmrMarkerProfile::class, 'marker_profile_id');
    }

    public function compiledVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'compiled_version_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(OmrTemplateAsset::class, 'authoring_draft_id');
    }

    public function isCompiled(): bool
    {
        return $this->stage === self::STAGE_COMPILED && $this->compiled_version_id !== null;
    }
}
