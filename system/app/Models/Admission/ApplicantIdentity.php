<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplicantIdentity extends Model
{
    protected $fillable = [
        'institution_id',
        'normalized_key',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'sex',
        'email',
        'phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<\App\Models\Foundation\Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Foundation\Institution::class);
    }

    /**
     * @return HasMany<AdmissionAttempt, $this>
     */
    public function admissionAttempts(): HasMany
    {
        return $this->hasMany(AdmissionAttempt::class);
    }

    /**
     * @return HasOne<ApplicantAccount, $this>
     */
    public function applicantAccount(): HasOne
    {
        return $this->hasOne(ApplicantAccount::class);
    }
}
