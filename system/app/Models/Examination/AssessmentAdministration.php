<?php

namespace App\Models\Examination;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $exam_assignment_id
 * @property int $admission_attempt_id
 * @property string $binding_kind
 * @property int $binding_revision
 * @property int $test_instrument_version_id
 * @property int|null $material_snapshot_id
 * @property int|null $exam_session_seat_id
 * @property int|null $material_set_id
 * @property int|null $booklet_set_id
 * @property array $required_metric_set
 * @property string $binding_state
 * @property int|null $bound_by_user_id
 * @property Carbon $bound_at
 * @property Carbon|null $superseded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssessmentAdministration extends Model
{
    public const KIND_SCHEDULED_MATERIAL = 'scheduled_material';

    public const KIND_DIRECT_MANUAL = 'direct_manual';

    public const STATE_AWAITING_ITEM_MAP = 'awaiting_item_map';

    public const STATE_READY = 'ready';

    public const STATE_RAW_INCOMPLETE = 'raw_incomplete';

    public const STATE_AWAITING_NORM_CONVERSION = 'awaiting_norm_conversion';

    public const STATE_READY_TO_FINALIZE = 'ready_to_finalize';

    public const STATE_FINALIZED = 'finalized';

    protected $table = 'assessment_administrations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'exam_assignment_id',
        'admission_attempt_id',
        'binding_kind',
        'binding_revision',
        'test_kit_version_id',
        'manifest_sha256',
        'acceptance_receipt_id',
        'procedure_snapshot_json',
        'test_instrument_version_id',
        'material_snapshot_id',
        'exam_session_seat_id',
        'material_set_id',
        'booklet_set_id',
        'required_metric_set',
        'binding_state',
        'bound_by_user_id',
        'bound_at',
        'superseded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'binding_revision' => 'integer',
            'required_metric_set' => 'array',
            'test_kit_version_id' => 'integer',
            'manifest_sha256' => 'string',
            'acceptance_receipt_id' => 'integer',
            'procedure_snapshot_json' => 'array',
            'bound_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    /**
     * @return BelongsTo<ExamAssignment, $this>
     */
    public function examAssignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function testInstrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }

    /**
     * @return BelongsTo<TestKitAcceptanceReceipt, $this>
     */
    public function acceptanceReceipt(): BelongsTo
    {
        return $this->belongsTo(TestKitAcceptanceReceipt::class, 'acceptance_receipt_id');
    }

    public function isPaperBacked(): bool
    {
        return ($this->procedure_snapshot_json['material_requirement'] ?? null) === TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED;
    }

    public function requiresTestKit(): bool
    {
        return $this->isPaperBacked();
    }

    public function institutionId(): int
    {
        return (int) (
            $this->testInstrumentVersion?->instrument?->institution_id
            ?? $this->admissionAttempt?->institution_id
            ?? 0
        );
    }

    /**
     * @return BelongsTo<ExamSessionMaterialSnapshot, $this>
     */
    public function materialSnapshot(): BelongsTo
    {
        return $this->belongsTo(ExamSessionMaterialSnapshot::class, 'material_snapshot_id');
    }

    /**
     * @return BelongsTo<ExamSessionSeat, $this>
     */
    public function examSessionSeat(): BelongsTo
    {
        return $this->belongsTo(ExamSessionSeat::class, 'exam_session_seat_id');
    }

    /**
     * @return BelongsTo<ExamSessionMaterialSet, $this>
     */
    public function materialSet(): BelongsTo
    {
        return $this->belongsTo(ExamSessionMaterialSet::class, 'material_set_id');
    }

    /**
     * @return BelongsTo<OmrBookletSet, $this>
     */
    public function bookletSet(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSet::class, 'booklet_set_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function boundBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bound_by_user_id');
    }

    /**
     * @return HasMany<AssessmentScoringRun, $this>
     */
    public function scoringRuns(): HasMany
    {
        return $this->hasMany(AssessmentScoringRun::class, 'administration_id');
    }

    /**
     * @return HasMany<AssessmentRawResultRevision, $this>
     */
    public function rawResultRevisions(): HasMany
    {
        return $this->hasMany(AssessmentRawResultRevision::class, 'administration_id');
    }

    /**
     * @return HasMany<AssessmentRawResultRevision, $this>
     */
    public function currentRawResultRevisions(): HasMany
    {
        return $this->hasMany(AssessmentRawResultRevision::class, 'administration_id')->where('is_current', true);
    }

    /**
     * @return HasMany<AssessmentRawResultRevision, $this>
     */
    public function rawRevisions(): HasMany
    {
        return $this->rawResultRevisions();
    }

    /**
     * @return HasMany<AssessmentConversionRevision, $this>
     */
    public function conversionRevisions(): HasMany
    {
        return $this->hasMany(AssessmentConversionRevision::class, 'administration_id');
    }

    /**
     * @return HasOne<AssessmentConversionRevision, $this>
     */
    public function currentConversionRevision(): HasOne
    {
        return $this->hasOne(AssessmentConversionRevision::class, 'administration_id')->where('is_current', true);
    }
}
