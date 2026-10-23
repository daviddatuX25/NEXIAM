<?php

namespace App\Models\Examination;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSubmissionRecord extends Model
{
    public const LEGACY_SEAT_ALLOCATION_NOT_ATTEMPTED = 'not_attempted';

    public const LEGACY_SEAT_ALLOCATION_BACKFILLED = 'backfilled';

    public const LEGACY_SEAT_ALLOCATION_UNRESOLVED = 'unresolved';

    /**
     * @var list<string>
     */
    public const LEGACY_SEAT_ALLOCATION_STATUSES = [
        self::LEGACY_SEAT_ALLOCATION_NOT_ATTEMPTED,
        self::LEGACY_SEAT_ALLOCATION_BACKFILLED,
        self::LEGACY_SEAT_ALLOCATION_UNRESOLVED,
    ];

    protected $fillable = [
        'exam_assignment_id',
        'exam_session_id',
        'seat_allocation_id',
        'exam_assignment_logistics_seat_allocation_id',
        'recorded_by_user_id',
        'idempotency_key',
        'submitted_at',
        'legacy_seat_allocation_status',
        'legacy_seat_allocation_exception',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_session_id' => 'integer',
            'seat_allocation_id' => 'integer',
            'exam_assignment_logistics_seat_allocation_id' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamAssignment, $this>
     */
    public function examAssignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return BelongsTo<ExamAssignmentSeatAllocation, $this>
     */
    public function seatAllocation(): BelongsTo
    {
        return $this->belongsTo(ExamAssignmentSeatAllocation::class, 'seat_allocation_id');
    }

    /**
     * @return BelongsTo<ExamAssignmentLogisticsSeatAllocation, $this>
     */
    public function logisticsSeatAllocation(): BelongsTo
    {
        return $this->belongsTo(ExamAssignmentLogisticsSeatAllocation::class, 'exam_assignment_logistics_seat_allocation_id');
    }
}
