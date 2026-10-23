<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TestInstrumentVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    public const MATERIAL_REQUIREMENT_UNRESOLVED = 'unresolved';

    public const MATERIAL_REQUIREMENT_PAPER_BACKED = 'paper_backed';

    public const MATERIAL_REQUIREMENT_NO_PAPER = 'no_paper';

    /** @var list<string> */
    public const MATERIAL_REQUIREMENTS = [
        self::MATERIAL_REQUIREMENT_UNRESOLVED,
        self::MATERIAL_REQUIREMENT_PAPER_BACKED,
        self::MATERIAL_REQUIREMENT_NO_PAPER,
    ];

    /** @var array<string, string> */
    protected $attributes = [
        'material_requirement' => self::MATERIAL_REQUIREMENT_UNRESOLVED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'test_instrument_id',
        'version_number',
        'status',
        'display_name',
        'publisher_name',
        'form_code',
        'edition',
        'language',
        'source_reference',
        'permitted_use_reference',
        'material_requirement',
        'administration_procedure_reference',
        'source_reviewed_by_user_id',
        'source_reviewed_at',
        'approved_by_user_id',
        'approved_at',
        'retired_by_user_id',
        'retired_at',
        'predecessor_version_id',
        'lock_version',
        'content_sha256',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $version): void {
            $requirement = $version->material_requirement;
            if (! is_string($requirement) || ! in_array($requirement, self::MATERIAL_REQUIREMENTS, true)) {
                throw ValidationException::withMessages([
                    'profile.material_requirement' => 'Choose unresolved, paper-backed, or no-paper applicability.',
                ]);
            }

            $procedureReference = $version->administration_procedure_reference;
            if ($procedureReference !== null && mb_strlen((string) $procedureReference) > 500) {
                throw ValidationException::withMessages([
                    'profile.administration_procedure_reference' => 'The administration procedure reference may not exceed 500 characters.',
                ]);
            }

            $previousStatus = $version->exists ? $version->getOriginal('status') : null;
            if ($version->status !== self::STATUS_APPROVED || $previousStatus === self::STATUS_APPROVED) {
                return;
            }

            if ($requirement === self::MATERIAL_REQUIREMENT_UNRESOLVED) {
                throw ValidationException::withMessages([
                    'profile.material_requirement' => 'Declare whether the approved profile is paper-backed or no-paper.',
                ]);
            }

            if (! is_string($procedureReference) || trim($procedureReference) === '') {
                throw ValidationException::withMessages([
                    'profile.administration_procedure_reference' => 'An administration procedure reference is required before approval.',
                ]);
            }
        });

        static::updating(static function (self $version): void {
            if (! in_array($version->getOriginal('status'), [self::STATUS_APPROVED, self::STATUS_RETIRED], true)) {
                return;
            }

            if ($version->isDirty(['material_requirement', 'administration_procedure_reference'])) {
                throw new RuntimeException('Approved test instrument material applicability is immutable.');
            }
        });
    }

    public function isFinalOnly(): bool
    {
        $this->loadMissing('metrics');

        return $this->metrics->isNotEmpty()
            && $this->metrics->every(
                fn (TestInstrumentMetric $metric): bool => $metric->rule_kind === TestInstrumentMetric::RULE_EXTERNAL_FINAL,
            );
    }

    /**
     * @return BelongsTo<TestInstrument, $this>
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(TestInstrument::class, 'test_instrument_id');
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_version_id');
    }

    /**
     * @return HasMany<TestInstrumentSubtest, $this>
     */
    public function subtests(): HasMany
    {
        return $this->hasMany(TestInstrumentSubtest::class, 'test_instrument_version_id')->orderBy('display_order');
    }

    /**
     * @return HasMany<TestInstrumentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TestInstrumentItem::class, 'test_instrument_version_id');
    }

    /**
     * @return HasMany<TestInstrumentMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(TestInstrumentMetric::class, 'test_instrument_version_id')->orderBy('code');
    }
}
