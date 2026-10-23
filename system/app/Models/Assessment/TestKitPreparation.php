<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class TestKitPreparation extends Model
{
    public const STATE_PREPARING = 'preparing';

    public const STATE_ASSEMBLED = 'assembled';

    public const STATE_DISCARDED = 'discarded';

    protected $attributes = [
        'composition_revision' => 1,
    ];

    protected $fillable = [
        'test_kit_id',
        'state',
        'revision',
        'composition_revision',
        'test_instrument_version_id',
        'norm_package_version_id',
        'answer_sheet_draft_id',
        'template_version_id',
        'booklet_version_id',
        'booklet_set_id',
        'assembled_test_kit_version_id',
        'source_context_json',
        'source_context_sha256',
        'assembly_context_json',
        'assembly_context_sha256',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'test_kit_id' => 'integer',
            'revision' => 'integer',
            'composition_revision' => 'integer',
            'test_instrument_version_id' => 'integer',
            'norm_package_version_id' => 'integer',
            'answer_sheet_draft_id' => 'integer',
            'template_version_id' => 'integer',
            'booklet_version_id' => 'integer',
            'booklet_set_id' => 'integer',
            'assembled_test_kit_version_id' => 'integer',
            'source_context_json' => 'array',
            'assembly_context_json' => 'array',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $preparation): void {
            if ($preparation->isDirty('source_context_json') || $preparation->isDirty('source_context_sha256')) {
                throw new RuntimeException('test_kit_preparation_source_context_is_immutable');
            }
            if (in_array($preparation->getOriginal('state'), [self::STATE_ASSEMBLED, self::STATE_DISCARDED], true)) {
                throw new RuntimeException('assembled_test_kit_preparations_are_immutable_history');
            }
            if ($preparation->state === self::STATE_DISCARDED && $preparation->getOriginal('state') !== self::STATE_PREPARING) {
                throw new RuntimeException('only_open_test_kit_preparations_can_be_discarded');
            }

            if ($preparation->state === self::STATE_ASSEMBLED) {
                foreach ([
                    'test_instrument_version_id',
                    'norm_package_version_id',
                    'answer_sheet_draft_id',
                    'template_version_id',
                    'booklet_version_id',
                    'composition_revision',
                    'omr_enabled',
                    'booklet_set_id',
                ] as $field) {
                    if ($preparation->isDirty($field)) {
                        throw new RuntimeException('assembled_test_kit_preparation_selection_is_immutable');
                    }
                }
            }
        });

        static::deleting(static function (self $preparation): void {
            if (in_array($preparation->state, [self::STATE_ASSEMBLED, self::STATE_DISCARDED], true)) {
                throw new RuntimeException('assembled_test_kit_preparations_are_immutable_history');
            }
        });
    }

    /**
     * @return BelongsTo<TestKit, $this>
     */
    public function testKit(): BelongsTo
    {
        return $this->belongsTo(TestKit::class, 'test_kit_id');
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function testInstrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return BelongsTo<AssessmentNormPackageVersion, $this>
     */
    public function normPackageVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentNormPackageVersion::class, 'norm_package_version_id');
    }

    /**
     * @return BelongsTo<OmrTemplateAuthoringDraft, $this>
     */
    public function answerSheetDraft(): BelongsTo
    {
        return $this->belongsTo(OmrTemplateAuthoringDraft::class, 'answer_sheet_draft_id');
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
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function assembledTestKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'assembled_test_kit_version_id');
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
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** @return HasMany<TestKitPreparationCompositionRevision, $this> */
    public function compositionRevisions(): HasMany
    {
        return $this->hasMany(TestKitPreparationCompositionRevision::class, 'test_kit_preparation_id')
            ->orderBy('composition_revision');
    }

    /** @return HasMany<TestKitPreparationCompositionRow, $this> */
    public function compositionRows(): HasMany
    {
        return $this->hasMany(TestKitPreparationCompositionRow::class, 'test_kit_preparation_id')
            ->orderBy('composition_revision')
            ->orderBy('id');
    }
}
