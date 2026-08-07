<?php

namespace App\Models\Assessment;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreRecord extends Model
{
    protected $fillable = [
        'grading_session_id',
        'admission_attempt_id',
        'aptitude_area_id',
        'score',
        'recorded_by_user_id',
        'idempotency_key',
    ];

    /**
     * @return BelongsTo<GradingSession, $this>
     */
    public function gradingSession(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return BelongsTo<AptitudeArea, $this>
     */
    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
