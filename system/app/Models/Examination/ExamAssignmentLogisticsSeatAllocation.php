<?php

namespace App\Models\Examination;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAssignmentLogisticsSeatAllocation extends Model
{
    protected $fillable = [
        'exam_assignment_id',
        'exam_session_id',
        'logistics_plan_id',
        'logistics_seat_id',
        'effective_from',
        'effective_to',
        'reason',
        'assigned_by_user_id',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime', 'effective_to' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ExamSessionLogisticsPlan::class, 'logistics_plan_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(ExamSessionLogisticsSeat::class, 'logistics_seat_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
