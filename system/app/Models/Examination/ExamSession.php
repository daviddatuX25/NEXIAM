<?php

namespace App\Models\Examination;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $room_id
 * @property int $academic_cycle_id
 * @property string $code
 * @property string $status
 * @property string $booking_mode
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $effective_end_at
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $reservation_opened_at
 * @property \Illuminate\Support\Carbon|null $reservation_closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExamSession extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const BOOKING_STAFF_ONLY = 'staff_only';

    public const BOOKING_OPEN_FOR_RESERVATION = 'open_for_reservation';

    /**
     * @var list<string>
     */
    public const BOOKING_MODES = [
        self::BOOKING_STAFF_ONLY,
        self::BOOKING_OPEN_FOR_RESERVATION,
    ];

    protected $fillable = [
        'academic_cycle_id',
        'room_id',
        'code',
        'status',
        'booking_mode',
        'starts_at',
        'effective_end_at',
        'version',
        'published_at',
        'reservation_opened_at',
        'reservation_closed_at',
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
            'reservation_opened_at' => 'datetime',
            'reservation_closed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpenForReservation(): bool
    {
        return $this->booking_mode === self::BOOKING_OPEN_FOR_RESERVATION;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpenForReservation(Builder $query): Builder
    {
        return $query->where('booking_mode', self::BOOKING_OPEN_FOR_RESERVATION);
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
