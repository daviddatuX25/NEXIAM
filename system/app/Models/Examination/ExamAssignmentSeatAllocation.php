<?php

namespace App\Models\Examination;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAssignmentSeatAllocation extends Model
{
    protected $fillable = [
        'exam_assignment_id',
        'exam_session_seat_id',
        'effective_from',
        'effective_to',
        'reason',
        'assigned_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_assignment_id' => 'integer',
            'exam_session_seat_id' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'assigned_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }

    /**
     * @return BelongsTo<ExamSessionSeat, $this>
     */
    public function sessionSeat(): BelongsTo
    {
        return $this->belongsTo(ExamSessionSeat::class, 'exam_session_seat_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * @return HasOne<ExamSubmissionRecord, $this>
     */
    public function submission(): HasOne
    {
        return $this->hasOne(ExamSubmissionRecord::class, 'seat_allocation_id');
    }
}
