<?php

namespace App\Models\Assessment;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\User;
use Database\Factories\OmrAnswerSheetTemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $template_id
 * @property int|null $test_instrument_version_id
 * @property int $version
 * @property string $status
 * @property OmrRegistrationMode $registration_mode
 * @property OmrQrMode $qr_mode
 * @property int $page_count
 * @property array<array-key, mixed>|null $identity_regions_json
 * @property array<array-key, mixed>|null $provider_config_json
 * @property array<array-key, mixed>|null $native_bundle_json
 * @property array<array-key, mixed>|null $review_manifest_json
 * @property array<array-key, mixed>|null $print_overlay_json
 * @property array<array-key, mixed>|null $layout_blueprint_json
 * @property array<array-key, mixed>|null $authoring_manifest
 * @property string|null $native_bundle_hash
 * @property string|null $template_json_hash
 * @property string|null $config_hash
 * @property string|null $review_manifest_hash
 * @property string|null $layout_blueprint_hash
 */
class OmrAnswerSheetTemplateVersion extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(static function (self $version): void {
            $originalStatus = (string) $version->getRawOriginal('status');
            $newStatus = (string) $version->status;
            $allowedTransitions = [
                self::STATUS_DRAFT => [self::STATUS_DRAFT, self::STATUS_COMPILED, self::STATUS_ACTIVE],
                self::STATUS_COMPILED => [self::STATUS_COMPILED, self::STATUS_ACTIVE],
                self::STATUS_ACTIVE => [self::STATUS_ACTIVE, self::STATUS_RETIRED],
                self::STATUS_RETIRED => [self::STATUS_RETIRED],
            ];
            if (! in_array($newStatus, $allowedTransitions[$originalStatus] ?? [], true)) {
                throw new \RuntimeException('Answer-sheet template version lifecycle transition is invalid.');
            }

            $dirtyFields = array_keys($version->getDirty());
            $immutableFields = [
                'template_id', 'test_instrument_version_id', 'version',
                'registration_mode', 'qr_mode', 'page_count', 'page_width', 'page_height',
                'identity_regions_json', 'provider_config_json', 'config_hash',
                'template_json_hash', 'native_bundle_version', 'native_bundle_json',
                'native_bundle_hash', 'engine_name', 'engine_commit', 'patch_identity',
                'review_manifest_json', 'review_manifest_hash', 'print_overlay_json',
                'layout_blueprint_json', 'layout_blueprint_hash', 'layout_solver_version',
            ];
            $lifecycleFields = [
                'status', 'activated_by_user_id', 'activated_at', 'authoring_manifest', 'updated_at',
            ];
            if ($originalStatus !== self::STATUS_DRAFT) {
                if (array_intersect($dirtyFields, $immutableFields) !== []
                    || array_diff($dirtyFields, $lifecycleFields) !== []) {
                    throw new \RuntimeException('Compiled answer-sheet versions are immutable.');
                }
                if ($originalStatus === self::STATUS_RETIRED
                    && array_diff($dirtyFields, ['updated_at']) !== []) {
                    throw new \RuntimeException('Retired answer-sheet versions are immutable.');
                }
            }
            if ($newStatus === self::STATUS_RETIRED
                && array_diff($dirtyFields, ['status', 'updated_at']) !== []) {
                throw new \RuntimeException('Retired answer-sheet versions cannot change lifecycle metadata.');
            }

            if ($newStatus === self::STATUS_COMPILED
                && $originalStatus === self::STATUS_DRAFT
                && ($version->native_bundle_hash === null
                    || preg_match('/\A[a-f0-9]{64}\z/D', $version->native_bundle_hash) !== 1
                    || $version->native_bundle_json === [])) {
                throw new \RuntimeException('Compiled answer-sheet versions require a valid draft bundle identity.');
            }
        });

        static::deleting(static function (self $version): void {
            if ($version->getRawOriginal('status') !== self::STATUS_DRAFT) {
                throw new \RuntimeException('Compiled answer-sheet versions are immutable.');
            }
        });
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPILED = 'compiled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    protected $table = 'omr_answer_sheet_template_versions';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
        'qr_mode' => OmrQrMode::Signed->value,
        'page_count' => 1,
    ];

    protected $fillable = [
        'template_id', 'test_instrument_version_id', 'version', 'status', 'registration_mode', 'qr_mode', 'page_count',
        'page_width', 'page_height', 'identity_regions_json', 'provider_config_json',
        'config_hash', 'template_json_hash', 'native_bundle_version', 'native_bundle_json',
        'native_bundle_hash', 'engine_name', 'engine_commit', 'patch_identity',
        'review_manifest_json', 'review_manifest_hash', 'print_overlay_json',
        'layout_blueprint_json', 'layout_blueprint_hash', 'layout_solver_version',
        'authoring_manifest', 'activated_by_user_id', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'registration_mode' => OmrRegistrationMode::class,
            'qr_mode' => OmrQrMode::class,
            'page_count' => 'integer',
            'page_width' => 'integer',
            'page_height' => 'integer',
            'identity_regions_json' => 'array',
            'provider_config_json' => 'array',
            'native_bundle_json' => 'array',
            'review_manifest_json' => 'array',
            'print_overlay_json' => 'array',
            'layout_blueprint_json' => 'array',
            'authoring_manifest' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OmrAnswerSheetTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplate::class, 'template_id');
    }

    /** @return BelongsTo<TestInstrumentVersion, $this> */
    public function testInstrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /** @return HasMany<OmrTemplateItemMapping, $this> */
    public function itemMappings(): HasMany
    {
        return $this->hasMany(OmrTemplateItemMapping::class, 'template_version_id');
    }

    /** @return HasMany<OmrTemplateAuthoringDraft, $this> */
    public function authoringDrafts(): HasMany
    {
        return $this->hasMany(OmrTemplateAuthoringDraft::class, 'compiled_version_id');
    }

    /** @return HasMany<OmrTemplateAsset, $this> */
    public function canonicalAssets(): HasMany
    {
        return $this->hasMany(OmrTemplateAsset::class, 'template_version_id');
    }

    /** @return HasMany<OmrBlankInstance, $this> */
    public function blankInstances(): HasMany
    {
        return $this->hasMany(OmrBlankInstance::class, 'template_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    /** @return HasMany<OmrTemplateArea, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(OmrTemplateArea::class, 'template_version_id');
    }

    /** @return HasMany<OmrAnswerSheetPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(OmrAnswerSheetPage::class, 'template_version_id');
    }

    /** @return HasMany<OmrQuestionBookletVersion, $this> */
    public function bookletVersions(): HasMany
    {
        return $this->hasMany(OmrQuestionBookletVersion::class, 'template_version_id');
    }

    public function pageCount(): int
    {
        return (int) $this->page_count;
    }

    /** @return HasMany<OmrTemplateBookletPair, $this> */
    public function pairs(): HasMany
    {
        return $this->hasMany(OmrTemplateBookletPair::class, 'template_version_id');
    }

    public function isCompiled(): bool
    {
        return in_array($this->status, [self::STATUS_COMPILED, self::STATUS_ACTIVE], true);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected static function newFactory(): Factory
    {
        return OmrAnswerSheetTemplateVersionFactory::new();
    }
}
