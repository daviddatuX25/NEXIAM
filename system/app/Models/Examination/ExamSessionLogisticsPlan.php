<?php

namespace App\Models\Examination;

use App\Models\Foundation\SeatingArrangement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSessionLogisticsPlan extends Model
{
    public const MODE_SIMPLE_CAPACITY = 'simple_capacity';

    public const MODE_ASSIGNED_SEATING = 'assigned_seating';

    protected $fillable = [
        'exam_session_id',
        'revision',
        'capacity_limit',
        'seating_mode',
        'seating_arrangement_id',
        'arrangement_lock_version',
        'arrangement_rows',
        'arrangement_columns',
        'arrangement_booklet_pattern',
        'seat_manifest_hash',
        'is_current',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'capacity_limit' => 'integer',
            'seating_arrangement_id' => 'integer',
            'arrangement_lock_version' => 'integer',
            'arrangement_rows' => 'integer',
            'arrangement_columns' => 'integer',
            'is_current' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function arrangement(): BelongsTo
    {
        return $this->belongsTo(SeatingArrangement::class, 'seating_arrangement_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(ExamSessionLogisticsSeat::class, 'logistics_plan_id')->orderBy('ordinal');
    }

    public function activeAllocations(): HasMany
    {
        return $this->hasMany(ExamAssignmentLogisticsSeatAllocation::class, 'logistics_plan_id')->whereNull('effective_to');
    }

    public function isPhysicalSeating(): bool
    {
        return $this->seating_mode === self::MODE_ASSIGNED_SEATING;
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
