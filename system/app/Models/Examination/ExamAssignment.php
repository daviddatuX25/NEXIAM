<?php

namespace App\Models\Examination;

use App\Models\Admission\AdmissionAttempt;
use Database\Factories\Examination\ExamAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAssignment extends Model
{
    /** @use HasFactory<ExamAssignmentFactory> */
    use HasFactory;

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_STAFF_ASSIGNED = 'staff_assigned';

    public const SOURCE_APPLICANT_RESERVED = 'applicant_reserved';

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_BATCH = 'batch';

    public const ORIGIN_CONTINUOUS = 'continuous';

    public const ORIGIN_RESERVATION = 'reservation';

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

    /**
     * @var list<string>
     */
    public const ORIGINS = [
        self::ORIGIN_MANUAL,
        self::ORIGIN_BATCH,
        self::ORIGIN_CONTINUOUS,
        self::ORIGIN_RESERVATION,
    ];

    protected $attributes = [
        'origin' => self::ORIGIN_MANUAL,
    ];

    protected $fillable = [
        'exam_session_id',
        'admission_attempt_id',
        'status',
        'source',
        'origin',
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
            'version' => 'integer',
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
     * Assignments that are currently active or recorded conduct (attendance/submission)
     * in the session before being released at session close or accommodated.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForConductRoster(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('status', self::ACTIVE_STATUSES)
                ->orWhereHas('attendanceRecord')
                ->orWhereHas('submissionRecord');
        });
    }

    /**
     * Active seat holds that block scheduling/waitlist eligibility.
     * Excludes any lingering active assignment on a completed/cancelled session
     * where the examinee was marked absent or void without a submission.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBlockingScheduling(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES)
            ->where(function (Builder $sub): void {
                $sub->whereDoesntHave('examSession', fn (Builder $s) => $s->whereIn('status', [
                    ExamSession::STATUS_COMPLETED,
                    ExamSession::STATUS_CANCELLED,
                ]))
                    ->orWhereHas('submissionRecord')
                    ->orWhereHas('attendanceRecord', fn (Builder $a) => $a->where('outcome', ExamAttendanceRecord::OUTCOME_PRESENT))
                    ->orWhereDoesntHave('attendanceRecord', fn (Builder $a) => $a->whereIn('outcome', [
                        ExamAttendanceRecord::OUTCOME_ABSENT,
                        ExamAttendanceRecord::OUTCOME_VOID,
                    ]));
            });
    }

    /**
     * Releases any lingering active assignment on a completed/cancelled session
     * where the examinee was marked absent or void without a submission.
     */
    public static function releaseTerminalMissedAssignmentsForAttempt(int $attemptId, string $reason): void
    {
        $staleAssignments = self::query()
            ->where('admission_attempt_id', $attemptId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereDoesntHave('submissionRecord')
            ->whereHas('examSession', fn (Builder $s) => $s->whereIn('status', [
                ExamSession::STATUS_COMPLETED,
                ExamSession::STATUS_CANCELLED,
            ]))
            ->whereHas('attendanceRecord', fn (Builder $a) => $a->whereIn('outcome', [
                ExamAttendanceRecord::OUTCOME_ABSENT,
                ExamAttendanceRecord::OUTCOME_VOID,
            ]))
            ->lockForUpdate()
            ->get();

        foreach ($staleAssignments as $stale) {
            $stale->update([
                'status' => self::STATUS_REMOVED,
                'ended_at' => now(),
                'end_reason' => $reason,
                'version' => (int) $stale->version + 1,
            ]);
        }
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

    /**
     * @return HasMany<ExamAssignmentSeatAllocation, $this>
     */
    public function seatAllocations(): HasMany
    {
        return $this->hasMany(ExamAssignmentSeatAllocation::class, 'exam_assignment_id');
    }

    /**
     * @return HasOne<ExamAssignmentSeatAllocation, $this>
     */
    public function activeSeatAllocation(): HasOne
    {
        return $this->hasOne(ExamAssignmentSeatAllocation::class, 'exam_assignment_id')
            ->whereNull('effective_to');
    }

    /**
     * @return HasMany<ExamAssignmentLogisticsSeatAllocation, $this>
     */
    public function logisticsSeatAllocations(): HasMany
    {
        return $this->hasMany(ExamAssignmentLogisticsSeatAllocation::class, 'exam_assignment_id');
    }

    /**
     * @return HasOne<ExamAssignmentLogisticsSeatAllocation, $this>
     */
    public function activeLogisticsSeatAllocation(): HasOne
    {
        return $this->hasOne(ExamAssignmentLogisticsSeatAllocation::class, 'exam_assignment_id')
            ->whereNull('effective_to');
    }
}
