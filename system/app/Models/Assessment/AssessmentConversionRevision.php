<?php

namespace App\Models\Assessment;

use App\Models\Examination\AssessmentAdministration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $norm_package_version_id
 * @property string|null $raw_dependency_sha256
 * @property string $source
 * @property array $typed_outputs_json
 * @property string $status
 * @property int|null $converted_by_user_id
 * @property Carbon $converted_at
 * @property bool $is_current
 * @property Carbon|null $superseded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssessmentConversionRevision extends Model
{
    public const STATUS_VALID = 'valid';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUPERSEDED = 'superseded';

    public const SOURCE_NORM_CONVERTED = 'norm_converted';

    public const SOURCE_MANUAL_CONVERTED = 'manual_converted';

    protected $table = 'assessment_conversion_revisions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'administration_id',
        'norm_package_version_id',
        'revision_no',
        'raw_dependency_sha256',
        'source',
        'provenance_json',
        'typed_outputs_json',
        'status',
        'converted_by_user_id',
        'converted_at',
        'is_current',
        'superseded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'raw_dependency_sha256' => 'string',
            'typed_outputs_json' => 'array',
            'provenance_json' => 'array',
            'is_current' => 'boolean',
            'converted_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<AssessmentRawResultRevision, $this>
     */
    public function rawDependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            AssessmentRawResultRevision::class,
            'assessment_conversion_raw_dependencies',
            'conversion_revision_id',
            'raw_result_revision_id',
        )->withPivot(['administration_id', 'metric_id']);
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
     * @return BelongsTo<AssessmentNormPackageVersion, $this>
     */
    public function normPackageVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentNormPackageVersion::class, 'norm_package_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by_user_id');
    }
}
