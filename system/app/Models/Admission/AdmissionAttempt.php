<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionAttempt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_NEEDS_CORRECTION = 'needs_correction';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'applicant_identity_id',
        'institution_id',
        'academic_cycle_id',
        'application_window_id',
        'reference_code',
        'status',
        'preferred_program_code',
        'program_preference_1_code',
        'program_preference_2_code',
        'program_preference_3_code',
        'applicant_type',
        'last_school_enrolled',
        'strand',
        'gwa',
        'privacy_policy_id',
        'contact_email',
        'contact_phone',
        'address_line',
        'city',
        'province',
        'zip_code',
        'payload',
        'version',
        'correction_notes',
        'dismissal_reason',
        'accepted_at',
        'dismissed_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'gwa' => 'decimal:2',
            'accepted_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApplicantIdentity, $this>
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(ApplicantIdentity::class, 'applicant_identity_id');
    }

    /**
     * @return BelongsTo<\App\Models\Foundation\AcademicCycle, $this>
     */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Foundation\AcademicCycle::class);
    }

    /**
     * @return BelongsTo<\App\Models\Foundation\PrivacyPolicy, $this>
     */
    public function privacyPolicy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Foundation\PrivacyPolicy::class);
    }

    /**
     * @return HasMany<AdmissionAttemptGradeValue, $this>
     */
    public function gradeValues(): HasMany
    {
        return $this->hasMany(AdmissionAttemptGradeValue::class);
    }

    /**
     * @return HasMany<\App\Models\Examination\ExamAssignment, $this>
     */
    public function examAssignments(): HasMany
    {
        return $this->hasMany(\App\Models\Examination\ExamAssignment::class);
    }

    /**
     * Single scheduling/exam eligibility gate (GF-06).
     * Authorized demographic amendment while accepted keeps this true — no retake.
     * Reconsider → pending flips this false so new assignments cannot be created.
     */
    public function isExamEligible(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}
