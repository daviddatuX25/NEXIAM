<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicantOfficeInquiry extends Model
{
    public const OFFICE_REGISTRAR = 'registrar';

    public const OFFICE_GUIDANCE = 'guidance';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'applicant_office_inquiries';

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'latest_message_sequence' => 0,
        'guest_last_read_sequence' => 0,
    ];

    protected $fillable = [
        'institution_id',
        'academic_cycle_id',
        'admission_attempt_id',
        'applicant_identity_id',
        'office',
        'subject',
        'status',
        'latest_message_sequence',
        'public_reference_code',
        'guest_token_hash',
        'guest_last_read_sequence',
        'created_by_user_id',
        'closed_by_user_id',
        'closed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latest_message_sequence' => 'integer',
            'guest_last_read_sequence' => 'integer',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isPreApplication(): bool
    {
        return $this->admission_attempt_id === null || $this->created_by_user_id === null;
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * @return HasMany<ApplicantOfficeInquiryMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ApplicantOfficeInquiryMessage::class, 'applicant_office_inquiry_id')->orderBy('sequence');
    }

    /**
     * @return HasMany<ApplicantOfficeInquiryRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ApplicantOfficeInquiryRead::class, 'applicant_office_inquiry_id');
    }
}
