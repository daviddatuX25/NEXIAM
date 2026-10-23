<?php

namespace App\Models\Assessment;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Factories\OmrTemplateAuthoringDraftFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property int|null $institution_id
 * @property bool|null $is_synthetic
 * @property int|null $template_id
 * @property int|null $test_instrument_version_id
 * @property string $code
 * @property string $name
 * @property string $source_kind
 * @property OmrRegistrationMode $registration_mode
 * @property OmrQrMode $qr_mode
 * @property int $requested_page_count
 * @property string $stage
 * @property int|null $marker_profile_id
 * @property array<string, mixed> $authoring_json
 * @property int $lock_version
 * @property int $owner_user_id
 * @property int|null $compiled_version_id
 */
class OmrTemplateAuthoringDraft extends Model
{
    use HasFactory;

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
        'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
        'qr_mode' => OmrQrMode::Signed->value,
        'requested_page_count' => 1,
        'stage' => self::STAGE_DRAFT,
        'authoring_json' => '{}',
        'lock_version' => 0,
    ];

    protected $fillable = [
        'institution_id', 'is_synthetic', 'template_id', 'test_instrument_version_id', 'code', 'name', 'source_kind', 'registration_mode', 'qr_mode',
        'requested_page_count', 'stage', 'marker_profile_id', 'authoring_json', 'lock_version',
        'owner_user_id', 'compiled_version_id',
    ];

    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'is_synthetic' => 'boolean',
            'template_id' => 'integer',
            'registration_mode' => OmrRegistrationMode::class,
            'qr_mode' => OmrQrMode::class,
            'requested_page_count' => 'integer',
            'marker_profile_id' => 'integer',
            'authoring_json' => 'array',
            'lock_version' => 'integer',
            'owner_user_id' => 'integer',
            'compiled_version_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $draft): void {
            if ($draft->exists) {
                $isInitialClassification = $draft->getOriginal('institution_id') === null
                    && $draft->getOriginal('is_synthetic') === null
                    && $draft->compiled_version_id === null
                    && $draft->template_id === null
                    && $draft->institution_id !== null
                    && $draft->is_synthetic !== null
                    && $draft->owner_user_id !== null;

                if (! $isInitialClassification) {
                    if ($draft->getOriginal('institution_id') === null
                        || $draft->getOriginal('is_synthetic') === null
                        || $draft->getOriginal('owner_user_id') === null) {
                        throw new LogicException('Unclassified legacy authoring drafts cannot be used or updated.');
                    }

                    if ($draft->isDirty(['institution_id', 'is_synthetic', 'owner_user_id'])) {
                        throw new LogicException('Authoring draft ownership and provenance are immutable.');
                    }
                }
            } elseif ($draft->institution_id === null
                || $draft->is_synthetic === null
                || $draft->owner_user_id === null) {
                throw new LogicException('New authoring drafts require an institution owner and explicit provenance.');
            }

            if ($draft->template_id === null) {
                return;
            }

            if ($draft->institution_id === null || $draft->is_synthetic === null) {
                throw new LogicException('An authoring draft with a linked Answer Sheet requires classified ownership and provenance.');
            }

            $template = OmrAnswerSheetTemplate::query()
                ->whereKey($draft->template_id)
                ->first(['institution_id', 'is_synthetic']);

            if ($template === null
                || $template->institution_id === null
                || (int) $template->institution_id !== (int) $draft->institution_id
                || (bool) $template->is_synthetic !== (bool) $draft->is_synthetic) {
                throw new LogicException('An authoring draft must link to an Answer Sheet with matching institution ownership and provenance.');
            }
        });
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<OmrMarkerProfile, $this> */
    public function markerProfile(): BelongsTo
    {
        return $this->belongsTo(OmrMarkerProfile::class, 'marker_profile_id');
    }

    /** @return BelongsTo<OmrAnswerSheetTemplateVersion, $this> */
    public function compiledVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'compiled_version_id');
    }

    /** @return HasMany<OmrTemplateAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(OmrTemplateAsset::class, 'authoring_draft_id');
    }

    /** @return HasMany<OmrTemplateVerificationRun, $this> */
    public function verificationRuns(): HasMany
    {
        return $this->hasMany(OmrTemplateVerificationRun::class, 'draft_id');
    }

    public function isCompiled(): bool
    {
        return $this->stage === self::STAGE_COMPILED && $this->compiled_version_id !== null;
    }

    protected static function newFactory(): Factory
    {
        return OmrTemplateAuthoringDraftFactory::new();
    }
}
