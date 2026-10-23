<?php

namespace App\Models\Assessment;

use App\Models\Examination\AssessmentAdministration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $administration_id
 * @property int $scoring_run_id
 * @property int $metric_id
 * @property string $metric_code
 * @property int $revision_no
 * @property string $raw_value
 * @property string $source
 * @property string $status
 * @property array $provenance_json
 * @property bool $is_current
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 */
class AssessmentRawResultRevision extends Model
{
    public const UPDATED_AT = null;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_OMR = 'omr';

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_DERIVED_COMPOSITE = 'derived_composite';

    public const STATUS_VALID = 'valid';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_INVALID = 'invalid';

    protected $table = 'assessment_raw_result_revisions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'administration_id',
        'scoring_run_id',
        'metric_id',
        'metric_code',
        'revision_no',
        'raw_value',
        'source',
        'status',
        'provenance_json',
        'is_current',
        'superseded_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'raw_value' => 'decimal:6',
            'provenance_json' => 'array',
            'is_current' => 'boolean',
            'superseded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * @return BelongsTo<AssessmentAdministration, $this>
     */
    public function administration(): BelongsTo
    {
        return $this->belongsTo(AssessmentAdministration::class, 'administration_id');
    }

    /**
     * @return BelongsTo<AssessmentScoringRun, $this>
     */
    public function scoringRun(): BelongsTo
    {
        return $this->belongsTo(AssessmentScoringRun::class, 'scoring_run_id');
    }

    /**
     * @return BelongsTo<TestInstrumentMetric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentMetric::class, 'metric_id');
    }
}
