<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $metadata
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $sha256
 */
class OmrTemplateAsset extends Model
{
    public const KIND_SOURCE = 'source';

    public const KIND_INTERMEDIATE_PAGE = 'intermediate_page';

    public const KIND_RASTER_PAGE = 'raster_page';

    public const KIND_PRINT_PDF = 'print_pdf';

    public const KIND_EVIDENCE = 'evidence';

    public const KIND_DIGITAL_PROOF = 'digital_proof';

    public const KIND_MARKER_PROFILE = 'marker_profile';

    public const KIND_NATIVE_TEMPLATE = 'native_template';

    public const KIND_NATIVE_CONFIG = 'native_config';

    public const KIND_NATIVE_MANIFEST = 'native_manifest';

    public const KIND_REFERENCE_PAGE = 'reference_page';

    public const KINDS = [
        self::KIND_SOURCE,
        self::KIND_INTERMEDIATE_PAGE,
        self::KIND_RASTER_PAGE,
        self::KIND_PRINT_PDF,
        self::KIND_EVIDENCE,
        self::KIND_DIGITAL_PROOF,
        self::KIND_MARKER_PROFILE,
        self::KIND_NATIVE_TEMPLATE,
        self::KIND_NATIVE_CONFIG,
        self::KIND_NATIVE_MANIFEST,
        self::KIND_REFERENCE_PAGE,
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $asset): void {
            if (self::isSealedTemplateVersion($asset->template_version_id)
                && ! self::isPermittedVerificationArtifact($asset)) {
                throw new \RuntimeException('Compiled answer-sheet material assets are immutable.');
            }
        });

        static::updating(static function (): never {
            throw new \RuntimeException('Answer-sheet assets are immutable.');
        });

        static::deleting(static function (self $asset): void {
            if ($asset->getRawOriginal('template_version_id') !== null
                || $asset->template_version_id !== null) {
                throw new \RuntimeException('Version assets are immutable.');
            }
        });
    }

    private static function isSealedTemplateVersion(?int $versionId): bool
    {
        if ($versionId === null) {
            return false;
        }

        return OmrAnswerSheetTemplateVersion::query()
            ->whereKey($versionId)
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
            ->exists();
    }

    public static function isPermittedVerificationArtifact(self $asset): bool
    {
        if (in_array($asset->kind, [self::KIND_EVIDENCE, self::KIND_DIGITAL_PROOF], true)) {
            return true;
        }

        $meta = (array) ($asset->metadata ?? []);
        $role = $meta['role'] ?? null;
        $hasRunId = isset($meta['run_id']) && (int) $meta['run_id'] > 0;

        if ($hasRunId) {
            if ($asset->kind === self::KIND_PRINT_PDF && in_array($role, ['challenge_sheet', 'challenge_guide_pdf'], true)) {
                return true;
            }
            if ($asset->kind === self::KIND_RASTER_PAGE && $role === 'challenge_sheet_page') {
                return true;
            }
        }

        return false;
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
