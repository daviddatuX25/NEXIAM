<?php

namespace App\Models\Admission;

use App\Models\Communications\PublicPolicy;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Testing\HasPracticeProvenance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdmissionAttempt extends Model
{
    use HasPracticeProvenance;

    protected $hidden = ['practice_fixture_set_id'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_NEEDS_CORRECTION = 'needs_correction';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'applicant_identity_id',
        'practice_fixture_set_id',
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
        'public_policy_id',
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
     * @return BelongsTo<AcademicCycle, $this>
     */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /**
     * @return BelongsTo<PrivacyPolicy, $this>
     */
    public function privacyPolicy(): BelongsTo
    {
        return $this->belongsTo(PrivacyPolicy::class);
    }

    /**
     * @return BelongsTo<PublicPolicy, $this>
     */
    public function publicPolicy(): BelongsTo
    {
        return $this->belongsTo(PublicPolicy::class);
    }

    /**
     * @return HasMany<AdmissionAttemptGradeValue, $this>
     */
    public function gradeValues(): HasMany
    {
        return $this->hasMany(AdmissionAttemptGradeValue::class);
    }

    /**
     * @return HasMany<ExamAssignment, $this>
     */
    public function examAssignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    /**
     * @return HasMany<ResultDocument, $this>
     */
    public function resultDocuments(): HasMany
    {
        return $this->hasMany(ResultDocument::class);
    }

    /**
     * @return HasMany<ConsultationAssignment, $this>
     */
    public function consultationAssignments(): HasMany
    {
        return $this->hasMany(ConsultationAssignment::class);
    }

    /**
     * @return HasMany<ConsultationDecision, $this>
     */
    public function consultationDecisions(): HasMany
    {
        return $this->hasMany(ConsultationDecision::class);
    }

    /**
     * @return HasMany<ProgramPool, $this>
     */
    public function programPools(): HasMany
    {
        return $this->hasMany(ProgramPool::class, 'admission_attempt_id');
    }

    /**
     * @return HasMany<ProgramSelection, $this>
     */
    public function programSelections(): HasMany
    {
        return $this->hasMany(ProgramSelection::class, 'admission_attempt_id');
    }

    /**
     * @return HasMany<ProgramFollowUp, $this>
     */
    public function programFollowUps(): HasMany
    {
        return $this->hasMany(ProgramFollowUp::class, 'admission_attempt_id');
    }

    /**
     * @return HasMany<EnrollmentStamp, $this>
     */
    public function enrollmentStamps(): HasMany
    {
        return $this->hasMany(EnrollmentStamp::class, 'admission_attempt_id');
    }

    /**
     * @return HasOne<EnrollmentStamp, $this>
     */
    public function enrollmentStamp(): HasOne
    {
        return $this->hasOne(EnrollmentStamp::class, 'admission_attempt_id')->latestOfMany();
    }

    /**
     * @return HasMany<AdmissionClearanceReceipt, $this>
     */
    public function clearanceReceipts(): HasMany
    {
        return $this->hasMany(AdmissionClearanceReceipt::class, 'admission_attempt_id');
    }

    /**
     * @return HasOne<AdmissionClearanceReceipt, $this>
     */
    public function latestClearanceReceipt(): HasOne
    {
        return $this->hasOne(AdmissionClearanceReceipt::class, 'admission_attempt_id')->latestOfMany();
    }

    /**
     * @return HasMany<EnrollmentSheetHandoffReceipt, $this>
     */
    public function sheetHandoffReceipts(): HasMany
    {
        return $this->hasMany(EnrollmentSheetHandoffReceipt::class, 'admission_attempt_id');
    }

    /**
     * @return HasOne<EnrollmentSheetHandoffReceipt, $this>
     */
    public function latestSheetHandoffReceipt(): HasOne
    {
        return $this->hasOne(EnrollmentSheetHandoffReceipt::class, 'admission_attempt_id')->latestOfMany();
    }

    /**
     * @return HasMany<ApplicantAccessChallenge, $this>
     */
    public function accessChallenges(): HasMany
    {
        return $this->hasMany(ApplicantAccessChallenge::class, 'admission_attempt_id');
    }

    /**
     * @return HasOne<ApplicantAccessChallenge, $this>
     */
    public function accessChallenge(): HasOne
    {
        return $this->hasOne(ApplicantAccessChallenge::class, 'admission_attempt_id')->latestOfMany();
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

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}
