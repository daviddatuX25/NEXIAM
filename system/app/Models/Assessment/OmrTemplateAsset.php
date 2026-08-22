<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrTemplateAsset extends Model
{
    public const KIND_SOURCE = 'source';
    public const KIND_INTERMEDIATE_PAGE = 'intermediate_page';
    public const KIND_RASTER_PAGE = 'raster_page';
    public const KIND_PRINT_PDF = 'print_pdf';
    public const KIND_EVIDENCE = 'evidence';
    public const KIND_DIGITAL_PROOF = 'digital_proof';
    public const KIND_MARKER_PROFILE = 'marker_profile';
    public const KINDS = [
        self::KIND_SOURCE,
        self::KIND_INTERMEDIATE_PAGE,
        self::KIND_RASTER_PAGE,
        self::KIND_PRINT_PDF,
        self::KIND_EVIDENCE,
        self::KIND_DIGITAL_PROOF,
        self::KIND_MARKER_PROFILE,
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \RuntimeException('asset_immutable');
        });
        static::deleting(static function (self $asset): void {
            if ($asset->template_version_id !== null) {
                throw new \RuntimeException('version_asset_immutable');
            }
        });
    }

    protected $table = 'omr_template_assets';

    protected $fillable = [
        'authoring_draft_id', 'template_version_id', 'kind', 'page_no',
        'disk', 'path', 'mime', 'bytes', 'sha256', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'authoring_draft_id' => 'integer',
            'template_version_id' => 'integer',
            'page_no' => 'integer',
            'bytes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAuthoringDraft::class, 'authoring_draft_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }
}
