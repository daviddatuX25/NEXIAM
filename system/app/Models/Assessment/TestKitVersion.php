<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class TestKitVersion extends Model
{
    public const STATUS_CANDIDATE = 'candidate';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    /** @var list<string> */
    private const IMMUTABLE_MATERIAL_FIELDS = [
        'test_kit_id',
        'version_number',
        'predecessor_version_id',
        'institution_id',
        'is_synthetic',
        'template_id',
        'booklet_id',
        'test_instrument_id',
        'template_version_id',
        'booklet_version_id',
        'booklet_set_id',
        'test_instrument_version_id',
        'source_preparation_id',
        'source_preparation_revision',
        'composition_revision',
        'composition_sha256',
        'booklet_set_code',
        'booklet_set_order_hash',
        'booklet_set_answer_key_hash',
        'manifest_schema_version',
        'canonicalizer_version',
        'manifest_json',
        'manifest_sha256',
        'created_by_user_id',
        'created_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_CANDIDATE,
        'revision' => 1,
    ];

    protected $fillable = [
        'test_kit_id',
        'institution_id',
        'is_synthetic',
        'template_id',
        'booklet_id',
        'test_instrument_id',
        'version_number',
        'predecessor_version_id',
        'template_version_id',
        'booklet_version_id',
        'booklet_set_id',
        'test_instrument_version_id',
        'source_preparation_id',
        'source_preparation_revision',
        'composition_revision',
        'composition_sha256',
        'composition_rows_sealed_at',
        'booklet_set_code',
        'booklet_set_order_hash',
        'booklet_set_answer_key_hash',
        'manifest_schema_version',
        'canonicalizer_version',
        'manifest_json',
        'manifest_sha256',
        'status',
        'revision',
        'selected_acceptance_receipt_id',
        'created_by_user_id',
        'activated_at',
        'activated_by_user_id',
        'retired_at',
        'retired_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
        'revocation_reason',
    ];

    protected static function booted(): void
    {
        static::updating(static function (self $version): void {
            foreach (self::IMMUTABLE_MATERIAL_FIELDS as $field) {
                if ($version->isDirty($field)) {
                    throw new RuntimeException('test_kit_version_material_is_immutable');
                }
            }

            $originalCompositionSeal = $version->getRawOriginal('composition_rows_sealed_at');
            if ($originalCompositionSeal !== null && $version->isDirty('composition_rows_sealed_at')) {
                throw new RuntimeException('test_kit_version_composition_seal_is_immutable');
            }
            if ($originalCompositionSeal === null && $version->composition_rows_sealed_at !== null
                && $version->composition_sha256 === null) {
                throw new RuntimeException('test_kit_version_composition_identity_is_missing');
            }
            if ((int) $version->revision < (int) $version->getRawOriginal('revision')) {
                throw new RuntimeException('test_kit_version_revision_must_be_monotonic');
            }
        });

        static::deleting(static function (): void {
            throw new RuntimeException('test_kit_versions_are_immutable_history');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test_kit_id' => 'integer',
            'institution_id' => 'integer',
            'is_synthetic' => 'boolean',
            'template_id' => 'integer',
            'booklet_id' => 'integer',
            'test_instrument_id' => 'integer',
            'version_number' => 'integer',
            'predecessor_version_id' => 'integer',
            'template_version_id' => 'integer',
            'booklet_version_id' => 'integer',
            'booklet_set_id' => 'integer',
            'test_instrument_version_id' => 'integer',
            'source_preparation_id' => 'integer',
            'source_preparation_revision' => 'integer',
            'composition_revision' => 'integer',
            'composition_sha256' => 'string',
            'composition_rows_sealed_at' => 'immutable_datetime',
            'booklet_set_order_hash' => 'string',
            'booklet_set_answer_key_hash' => 'string',
            'manifest_schema_version' => 'integer',
            'manifest_json' => 'array',
            'revision' => 'integer',
            'selected_acceptance_receipt_id' => 'integer',
            'created_by_user_id' => 'integer',
            'activated_at' => 'immutable_datetime',
            'activated_by_user_id' => 'integer',
            'retired_at' => 'immutable_datetime',
            'retired_by_user_id' => 'integer',
            'revoked_at' => 'immutable_datetime',
            'revoked_by_user_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @return BelongsTo<TestKit, $this>
     */
    public function testKit(): BelongsTo
    {
        return $this->belongsTo(TestKit::class, 'test_kit_id');
    }

    /**
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_version_id');
    }

    /**
     * @return HasMany<TestKitVersion, $this>
     */
    public function successors(): HasMany
    {
        return $this->hasMany(self::class, 'predecessor_version_id')->orderBy('version_number');
    }

    /**
     * @return BelongsTo<OmrAnswerSheetTemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(OmrAnswerSheetTemplateVersion::class, 'template_version_id');
    }

    /**
     * @return BelongsTo<OmrQuestionBookletVersion, $this>
     */
    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    /**
     * @return BelongsTo<OmrBookletSet, $this>
     */
    public function bookletSet(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSet::class, 'booklet_set_id');
    }

    /**
     * @return BelongsTo<TestInstrument, $this>
     */
    public function testInstrument(): BelongsTo
    {
        return $this->belongsTo(TestInstrument::class, 'test_instrument_id');
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function testInstrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return BelongsTo<TestKitAcceptanceReceipt, $this>
     */
    public function selectedAcceptanceReceipt(): BelongsTo
    {
        return $this->belongsTo(TestKitAcceptanceReceipt::class, 'selected_acceptance_receipt_id');
    }

    /**
     * @return HasMany<TestKitAcceptanceReceipt, $this>
     */
    public function acceptanceReceipts(): HasMany
    {
        return $this->hasMany(TestKitAcceptanceReceipt::class, 'test_kit_version_id')->orderBy('id');
    }

    /** @return BelongsTo<TestKitPreparation, $this> */
    public function sourcePreparation(): BelongsTo
    {
        return $this->belongsTo(TestKitPreparation::class, 'source_preparation_id');
    }

    /** @return HasMany<TestKitVersionCompositionRow, $this> */
    public function compositionRows(): HasMany
    {
        return $this->hasMany(TestKitVersionCompositionRow::class, 'test_kit_version_id')
            ->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
