<?php

namespace App\Models\Examination;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamSession extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'academic_cycle_id',
        'room_id',
        'code',
        'status',
        'starts_at',
        'effective_end_at',
        'version',
        'published_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'effective_end_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<\App\Models\Foundation\AcademicCycle, $this>
     */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Foundation\AcademicCycle::class);
    }

    /**
     * @return BelongsTo<\App\Models\Foundation\Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Foundation\Room::class);
    }

    /**
     * @return HasMany<ExamAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    /**
     * @return HasMany<ProctorDuty, $this>
     */
    public function proctorDuties(): HasMany
    {
        return $this->hasMany(ProctorDuty::class);
    }

    /**
     * @return HasOne<\App\Models\Assessment\GradingSession, $this>
     */
    public function gradingSession(): HasOne
    {
        return $this->hasOne(\App\Models\Assessment\GradingSession::class);
    }
}
