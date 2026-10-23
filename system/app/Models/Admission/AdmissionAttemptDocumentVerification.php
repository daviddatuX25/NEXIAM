<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionAttemptDocumentVerification extends Model
{
    public const STATUS_VERIFIED = 'verified';

    public const STATUS_DEFICIENT = 'deficient';

    public const STATUS_MISSING = 'missing';

    public const STATUS_NOT_CHECKED = 'not_checked';

    protected $fillable = [
        'admission_attempt_id',
        'requirement_item_id',
        'status',
        'verified_by_user_id',
        'verified_at',
        'applicant_instruction',
        'internal_remarks',
        'predecessor_id',
        'idempotency_key',
    ];

    protected $hidden = ['remarks', 'internal_remarks', 'verified_by_user_id', 'predecessor_id', 'idempotency_key'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    public function requirementItem(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttemptRequirementItem::class, 'requirement_item_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_id');
    }
}
