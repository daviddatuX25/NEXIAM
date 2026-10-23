<?php

namespace App\Models\Assessment;

use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssessmentNormPackage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'institution_id',
        'code',
        'is_synthetic',
        'current_approved_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'is_synthetic' => 'boolean',
            'current_approved_version_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasMany<AssessmentNormPackageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AssessmentNormPackageVersion::class, 'norm_package_id')->orderByDesc('version_number');
    }

    /**
     * @return HasOne<AssessmentNormPackageVersion, $this>
     */
    public function draftVersion(): HasOne
    {
        return $this->hasOne(AssessmentNormPackageVersion::class, 'norm_package_id')
            ->where('status', AssessmentNormPackageVersion::STATUS_DRAFT);
    }

    /**
     * @return BelongsTo<AssessmentNormPackageVersion, $this>
     */
    public function currentApprovedVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentNormPackageVersion::class, 'current_approved_version_id');
    }

    /**
     * @return HasOne<AssessmentNormPackageVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(AssessmentNormPackageVersion::class, 'norm_package_id')->latestOfMany('version_number');
    }
}
