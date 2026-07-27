<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamSession extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_SCHEDULED = 'scheduled';

    public const TYPE_DIRECT = 'direct';

    protected $fillable = [
        'academic_year_id',
        'room_id',
        'date',
        'start_time',
        'end_time',
        'extended_end_time',
        'status',
        'published_at',
        'started_at',
        'closed_at',
        'created_by',
        'type',
        'label',
        'system_notes',
    ];

    protected $appends = ['is_publishable', 'publish_block_reason'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'published_at' => 'datetime',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'type' => 'string',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function scopeForAcademicYear($query, $academicYear): void
    {
        if ($academicYear instanceof AcademicYear) {
            $query->where('academic_year_id', $academicYear->id);
        } elseif ($academicYear !== null) {
            $query->where('academic_year_id', $academicYear);
        }
    }

    /** Users assigned as proctors for this session (proctor role). */
    public function proctors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'exam_session_user')
            ->withTimestamps();
    }

    /** Applicants assigned to this exam session. Per 08-API-SPEC-PHASE1: one applicant per session globally. */
    public function applicants(): BelongsToMany
    {
        return $this->belongsToMany(Applicant::class, 'exam_session_applicant')
            ->withPivot([
                'id',
                'attendance_status',
                'attendance_marked_at',
                'attendance_marked_by',
                'submission_status',
                'submitted_at',
                'submitted_to',
            ])
            ->withTimestamps();
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gradingSession(): HasOne
    {
        return $this->hasOne(GradingSession::class);
    }

    /**
     * Check if another session exists in the same room on the same date with overlapping time.
     * Exclude given session ID when updating.
     */
    public static function hasRoomConflict(int $roomId, string $date, string $startTime, ?string $endTime, ?int $excludeSessionId = null): bool
    {
        try {
            $start = Carbon::parse($startTime)->format('H:i:s');
            $end = ($endTime && $endTime !== '00:00:00' && $endTime !== '00:00')
                ? Carbon::parse($endTime)->format('H:i:s')
                : '23:59:59';
        } catch (\Throwable $e) {
            return false;
        }

        $query = self::query()
            ->where('room_id', $roomId)
            ->whereDate('date', $date);

        if ($excludeSessionId !== null) {
            $query->where('id', '!=', $excludeSessionId);
        }

        $sessions = $query->get(['id', 'start_time', 'end_time']);

        foreach ($sessions as $session) {
            try {
                $otherStart = Carbon::parse($session->start_time)->format('H:i:s');
                $otherEnd = ($session->end_time && $session->end_time !== '00:00:00' && $session->end_time !== '00:00')
                    ? Carbon::parse($session->end_time)->format('H:i:s')
                    : '23:59:59';
            } catch (\Throwable $e) {
                continue;
            }
            if ($start < $otherEnd && $end > $otherStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the given time (or now) falls within the allowed window to start this session.
     * Window: (date + start_time - grace_before) through (date + end_time + grace_after).
     * Grace values are fixed for now; will be dynamic from admin settings in the future (task deferred).
     */
    public function isWithinStartWindow(?Carbon $now = null): bool
    {
        $tz = config('app.timezone', 'UTC');
        $now ??= Carbon::now($tz);

        // Fixed grace minutes; will be dynamic from admin settings in the future (task deferred).
        $graceMinutesBeforeStart = 15;
        $graceMinutesAfterEnd = 30;

        $sessionDate = Carbon::parse($this->date)->tz($tz);
        $windowStart = $sessionDate->copy()->setTimeFromTimeString($this->start_time)->subMinutes($graceMinutesBeforeStart);
        $windowEnd = $sessionDate->copy()->setTimeFromTimeString($this->end_time ?? '23:59')->addMinutes($graceMinutesAfterEnd);

        return $now->between($windowStart, $windowEnd);
    }

    /**
     * True when the current time is between the session's start_time and end_time.
     * If no end_time is set, any time after start is considered within window.
     */
    public function isWithinExamWindow(?Carbon $now = null): bool
    {
        $tz = config('app.timezone', 'UTC');
        $now ??= Carbon::now($tz);
        $sessionDate = Carbon::parse($this->date)->tz($tz);
        $start = $sessionDate->copy()->setTimeFromTimeString($this->start_time);

        if ($now->lt($start)) {
            return false;
        }

        if (! $this->end_time) {
            return true;
        }

        $end = $sessionDate->copy()->setTimeFromTimeString($this->end_time);

        return $now->lte($end);
    }

    /**
     * True when the current time is past the session's end_time.
     * Returns false when end_time is not set (open-ended sessions never expire).
     */
    public function isPastEndTime(?Carbon $now = null): bool
    {
        if (! $this->end_time) {
            return false;
        }

        $tz = config('app.timezone', 'UTC');
        $now ??= Carbon::now($tz);
        $sessionDate = Carbon::parse($this->date)->tz($tz);
        $end = $sessionDate->copy()->setTimeFromTimeString($this->end_time);

        return $now->gt($end);
    }

    /**
     * True when the current time is past the session's effective end time
     * (extended_end_time if set, otherwise end_time).
     */
    public function isEffectiveEndTimePast(?Carbon $now = null): bool
    {
        $end = $this->extended_end_time ?? $this->end_time;
        if (! $end) {
            return false;
        }

        $tz = config('app.timezone', 'UTC');
        $now ??= Carbon::now($tz);
        $sessionDate = Carbon::parse($this->date)->tz($tz);
        $end = $sessionDate->copy()->setTimeFromTimeString($end);

        return $now->gt($end);
    }

    public function isDirect(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }

    /** Whether the session date is strictly in the past (not today). */
    public function isPastDate(): bool
    {
        return $this->date?->isPast() && ! $this->date?->isToday();
    }

    /** Whether this session has no applicants assigned. */
    public function hasNoApplicants(): bool
    {
        return $this->applicants()->count() === 0;
    }

    /** Returns the first reason a session cannot be published, or null if it can. */
    public function publishBlockReason(): ?string
    {
        if (in_array($this->status, [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            return 'Cannot publish a session that is in progress, completed, or cancelled.';
        }

        if (! $this->date || ! $this->start_time || ! $this->room_id) {
            return 'Set the date, start time, and room before publishing.';
        }

        if ($this->isPastDate()) {
            return 'Cannot publish a session with a past date.';
        }

        if ($this->isPastEndTime()) {
            return 'Cannot publish a session whose scheduled end time has already passed.';
        }

        if ($this->hasNoApplicants()) {
            return 'Assign at least one applicant before publishing.';
        }

        return null;
    }

    public function getIsPublishableAttribute(): bool
    {
        return $this->publishBlockReason() === null;
    }

    public function getPublishBlockReasonAttribute(): ?string
    {
        return $this->publishBlockReason();
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['end_time'] = (empty($value) || $value === '00:00:00' || $value === '00:00') ? null : $value;
    }

    public function setExtendedEndTimeAttribute($value): void
    {
        $this->attributes['extended_end_time'] = (empty($value) || $value === '00:00:00' || $value === '00:00') ? null : $value;
    }
}
