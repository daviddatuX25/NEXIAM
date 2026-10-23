<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentNormTable extends Model
{
    public const SCALE_PERCENTILE_RANK = 'percentile_rank';

    public const SCALE_STANINE = 'stanine';

    /** @var list<string> */
    protected $fillable = [
        'norm_package_version_id',
        'metric_code',
        'output_scale',
        'intervals_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'norm_package_version_id' => 'integer',
            'intervals_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AssessmentNormPackageVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AssessmentNormPackageVersion::class, 'norm_package_version_id');
    }

    /**
     * @return BelongsTo<AssessmentNormPackageVersion, $this>
     */
    public function normPackageVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentNormPackageVersion::class, 'norm_package_version_id');
    }
}
