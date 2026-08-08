<?php

namespace App\Models\Examination;

use App\Models\Admission\AdmissionAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAssignment extends Model
{
    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_STAFF_ASSIGNED = 'staff_assigned';

    public const SOURCE_APPLICANT_RESERVED = 'applicant_reserved';

    /**
     * Active seat holds that participate in uniqueness and capacity (D-088 §3).
     *
     * @var list<string>
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_ASSIGNED,
    ];

    /**
     * @var list<string>
     */
    public const SOURCES = [
        self::SOURCE_STAFF_ASSIGNED,
        self::SOURCE_APPLICANT_RESERVED,
    ];

    protected $fillable = [
        'exam_session_id',
        'admission_attempt_id',
        'seat_label',
        'status',
        'source',
        'ended_at',
        'end_reason',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ended_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

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
