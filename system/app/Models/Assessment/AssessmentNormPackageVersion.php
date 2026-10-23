<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentNormPackageVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    /** @var list<string> */
    protected $fillable = [
        'norm_package_id',
        'version_number',
        'status',
        'display_name',
        'publisher_name',
        'form_code',
        'edition',
        'language',
        'applicable_population',
        'source_reference',
        'permitted_use_reference',
        'source_reviewed_by_user_id',
        'source_reviewed_at',
        'approved_by_user_id',
        'approved_at',
        'retired_by_user_id',
        'retired_at',
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
            'norm_package_id' => 'integer',
            'version_number' => 'integer',
            'lock_version' => 'integer',
            'source_reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<AssessmentNormPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(AssessmentNormPackage::class, 'norm_package_id');
    }

    /**
     * @return BelongsTo<AssessmentNormPackage, $this>
     */
    public function normPackage(): BelongsTo
    {
        return $this->belongsTo(AssessmentNormPackage::class, 'norm_package_id');
    }

    /**
     * @return HasMany<AssessmentNormTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(AssessmentNormTable::class, 'norm_package_version_id')->orderBy('metric_code');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sourceReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_reviewed_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function retiredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<AssessmentConversionRevision, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AssessmentConversionRevision::class, 'norm_package_version_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRetired(): bool
    {
        return $this->status === self::STATUS_RETIRED;
    }
}
