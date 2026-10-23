<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

use function trim;

/**
 * @property int $id
 * @property int $booklet_id
 * @property int $template_version_id
 * @property int|null $test_instrument_version_id
 * @property int $version
 * @property string $status
 * @property string|null $answer_key_hash
 * @property string|null $content_hash
 * @property Carbon|null $finalized_at
 */
class OmrQuestionBookletVersion extends Model
{
    protected static function booted(): void
    {
        static::creating(static function (self $version): void {
            if ($version->status === self::STATUS_FINALIZED
                && (! is_numeric($version->finalized_by_user_id)
                    || (int) $version->finalized_by_user_id < 1
                    || $version->finalized_at === null
                    || ! is_string($version->content_hash)
                    || trim($version->content_hash) === ''
                    || ! is_string($version->answer_key_hash)
                    || trim($version->answer_key_hash) === '')) {
                throw new \RuntimeException('Finalized Booklet versions require an attributed finalizer, timestamp, and material hashes.');
            }
        });

        static::updating(static function (self $version): void {
            $immutableFields = [
                'booklet_id',
                'template_version_id',
                'test_instrument_version_id',
                'version',
                'status',
                'authoring_mode',
                'allow_multiple_sets',
                'answer_key_hash',
                'content_hash',
                'finalized_by_user_id',
                'finalized_at',
            ];
            if ($version->isDirty('status')
                && $version->status === self::STATUS_FINALIZED
                && ($version->getRawOriginal('status') !== self::STATUS_DRAFT
                    || $version->getRawOriginal('finalized_at') !== null
                    || ! is_numeric($version->finalized_by_user_id)
                    || (int) $version->finalized_by_user_id < 1
                    || $version->finalized_at === null
                    || ! is_string($version->content_hash)
                    || trim($version->content_hash) === ''
                    || ! is_string($version->answer_key_hash)
                    || trim($version->answer_key_hash) === '')) {
                throw new \RuntimeException('Finalized Booklet versions require a draft source, attributed finalizer, timestamp, and material hashes.');
            }

            if (($version->getRawOriginal('finalized_at') !== null
                || $version->getRawOriginal('status') === self::STATUS_FINALIZED)
                && $version->isDirty($immutableFields)) {
                throw new \RuntimeException('Finalized Booklet versions are immutable.');
            }
        });

        static::deleting(static function (self $version): void {
            if ($version->getRawOriginal('finalized_at') !== null
                || $version->getRawOriginal('status') === self::STATUS_FINALIZED) {
                throw new \RuntimeException('Finalized Booklet versions are immutable.');
            }
        });
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_RETIRED = 'retired';

    public const AUTHORING_MODE_STORED_QUESTIONS = 'stored_questions';

    public const AUTHORING_MODE_ANSWER_KEYS_ONLY = 'answer_keys_only';

    public const AUTHORING_MODES = [
        self::AUTHORING_MODE_STORED_QUESTIONS,
        self::AUTHORING_MODE_ANSWER_KEYS_ONLY,
    ];

    protected $table = 'omr_question_booklet_versions';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'lock_version' => 1,
        'allow_multiple_sets' => false,
    ];

    protected $fillable = [
        'booklet_id',
        'template_version_id',
        'test_instrument_version_id',
        'version',
        'status',
        'authoring_mode',
        'allow_multiple_sets',
        'lock_version',
        'answer_key_hash',
        'content_hash',
        'activated_by_user_id',
        'activated_at',
        'finalized_by_user_id',
        'finalized_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'lock_version' => 'integer',
            'allow_multiple_sets' => 'boolean',
            'activated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OmrQuestionBooklet, $this> */
    public function booklet(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBooklet::class, 'booklet_id');
    }

    /** @return BelongsTo<OmrAnswerSheetTemplateVersion, $this> */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    /** @return BelongsTo<TestInstrumentVersion, $this> */
    public function testInstrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /** @return HasMany<OmrQuestionBookletQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(OmrQuestionBookletQuestion::class, 'booklet_version_id');
    }

    /** @return HasMany<OmrBookletItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OmrBookletItem::class, 'booklet_version_id');
    }

    /** @return HasMany<OmrBookletSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(OmrBookletSection::class, 'booklet_version_id');
    }

    /** @return HasMany<OmrBookletSet, $this> */
    public function sets(): HasMany
    {
        return $this->hasMany(OmrBookletSet::class, 'booklet_version_id');
    }

    /** @return HasMany<OmrTemplateBookletPair, $this> */
    public function pairs(): HasMany
    {
        return $this->hasMany(OmrTemplateBookletPair::class, 'booklet_version_id');
    }

    /**
     * @return list<string>
     */
    public function allowedSetCodes(): array
    {
        return $this->allow_multiple_sets
            ? OmrBookletSet::SET_CODES
            : ['A'];
    }

    /**
     * @return list<string>
     */
    public function requiredSetCodes(): array
    {
        return $this->allowedSetCodes();
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED
            && $this->finalized_by_user_id !== null
            && $this->finalized_at !== null
            && $this->content_hash !== null
            && trim($this->content_hash) !== ''
            && $this->answer_key_hash !== null
            && trim($this->answer_key_hash) !== '';
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->finalized_at === null;
    }
}
