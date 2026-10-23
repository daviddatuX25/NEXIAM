<?php

namespace App\Models\Assessment;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreRecord extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_OMR = 'omr';

    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'grading_session_id',
        'admission_attempt_id',
        'aptitude_area_id',
        'score',
        'converted_score',
        'source',
        'capture_sheet_id',
        'confidence',
        'recorded_by_user_id',
        'idempotency_key',
    ];

    /**
     * Score + converted output are the most sensitive fields in the grading
     * path and are encrypted at rest (SCV-5). Presence-only queries (finalize
     * completeness) are unaffected by the encrypted cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'encrypted',
            'converted_score' => 'encrypted',
            'confidence' => 'float',
        ];
    }

    /**
     * @return BelongsTo<OmrCaptureSheet, $this>
     */
    public function captureSheet(): BelongsTo
    {
        return $this->belongsTo(OmrCaptureSheet::class, 'capture_sheet_id');
    }

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
