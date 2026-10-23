<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionAttemptRequirementSet extends Model
{
    protected $fillable = [
        'admission_attempt_id',
        'applicant_track',
        'catalog_version',
        'is_current',
        'initialized_by_user_id',
        'initialized_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'catalog_version' => 'integer',
            'is_current' => 'boolean',
            'initialized_at' => 'datetime',
        ];
    }

    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdmissionAttemptRequirementItem::class, 'requirement_set_id');
    }

    public function initializedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initialized_by_user_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
