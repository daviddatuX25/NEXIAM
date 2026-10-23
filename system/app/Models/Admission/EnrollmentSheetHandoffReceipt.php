<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentSheetHandoffReceipt extends Model
{
    protected $fillable = [
        'admission_attempt_id',
        'admission_clearance_receipt_id',
        'program_id',
        'receipt_number',
        'issued_by_user_id',
        'issued_at',
        'paper_reference',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<AdmissionClearanceReceipt, $this>
     */
    public function clearanceReceipt(): BelongsTo
    {
        return $this->belongsTo(AdmissionClearanceReceipt::class, 'admission_clearance_receipt_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
