<?php

namespace App\Models\Examination;

use App\Models\Admission\AdmissionAttempt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAssignment extends Model
{
    protected $fillable = [
        'exam_session_id',
        'admission_attempt_id',
        'seat_label',
        'status',
        'version',
    ];

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return HasOne<ExamAttendanceRecord, $this>
     */
    public function attendanceRecord(): HasOne
    {
        return $this->hasOne(ExamAttendanceRecord::class);
    }

    /**
     * @return HasOne<ExamSubmissionRecord, $this>
     */
    public function submissionRecord(): HasOne
    {
        return $this->hasOne(ExamSubmissionRecord::class);
    }
}
