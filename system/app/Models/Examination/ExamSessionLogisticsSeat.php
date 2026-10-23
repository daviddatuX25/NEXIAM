<?php

namespace App\Models\Examination;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamSessionLogisticsSeat extends Model
{
    protected $fillable = ['logistics_plan_id', 'ordinal', 'seat_label', 'row_number', 'column_number'];

    protected function casts(): array
    {
        return ['ordinal' => 'integer', 'row_number' => 'integer', 'column_number' => 'integer'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ExamSessionLogisticsPlan::class, 'logistics_plan_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ExamAssignmentLogisticsSeatAllocation::class, 'logistics_seat_id');
    }

    public function activeAllocation(): HasOne
    {
        return $this->hasOne(ExamAssignmentLogisticsSeatAllocation::class, 'logistics_seat_id')->whereNull('effective_to');
    }
}
