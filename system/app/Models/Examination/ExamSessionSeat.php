<?php

namespace App\Models\Examination;

use App\Models\Assessment\OmrBookletSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamSessionSeat extends Model
{
    protected $fillable = [
        'material_snapshot_id',
        'ordinal',
        'row_number',
        'column_number',
        'seat_label',
        'booklet_set_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'material_snapshot_id' => 'integer',
            'ordinal' => 'integer',
            'row_number' => 'integer',
            'column_number' => 'integer',
            'booklet_set_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamSessionMaterialSnapshot, $this>
     */
    public function materialSnapshot(): BelongsTo
    {
        return $this->belongsTo(ExamSessionMaterialSnapshot::class, 'material_snapshot_id');
    }

    /**
     * @return BelongsTo<OmrBookletSet, $this>
     */
    public function bookletSet(): BelongsTo
    {
        return $this->belongsTo(OmrBookletSet::class, 'booklet_set_id');
    }

    /**
     * @return HasMany<ExamAssignmentSeatAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ExamAssignmentSeatAllocation::class, 'exam_session_seat_id');
    }

    /**
     * @return HasOne<ExamAssignmentSeatAllocation, $this>
     */
    public function activeAllocation(): HasOne
    {
        return $this->hasOne(ExamAssignmentSeatAllocation::class, 'exam_session_seat_id')
            ->whereNull('effective_to');
    }
}
