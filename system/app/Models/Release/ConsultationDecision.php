<?php

namespace App\Models\Release;

use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationDecision extends Model
{
    protected $fillable = [
        'admission_attempt_id',
        'counselor_user_id',
        'decision_type',
        'notes',
        'version',
        'idempotency_key',
    ];

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_user_id');
    }
}
