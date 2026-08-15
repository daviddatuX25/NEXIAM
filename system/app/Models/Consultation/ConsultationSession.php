<?php

namespace App\Models\Consultation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $academic_cycle_id
 * @property int|null $room_id
 * @property string $code
 * @property string $status
 * @property string $booking_mode
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property int $version
 * @property int|null $published_by_user_id
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $reservation_opened_at
 * @property \Illuminate\Support\Carbon|null $reservation_closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ConsultationSession extends Model
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
        'scheduled_at',
        'published_by_user_id',
        'published_at',
        'completed_at',
        'cancelled_at',
        'reservation_opened_at',
        'reservation_closed_at',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reservation_opened_at' => 'datetime',
            'reservation_closed_at' => 'datetime',
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
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'published_by_user_id');
    }

    /**
     * @return HasMany<ConsultationAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ConsultationAssignment::class);
    }
}
