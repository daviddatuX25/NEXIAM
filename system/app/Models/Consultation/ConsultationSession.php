<?php

namespace App\Models\Consultation;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Database\Factories\Consultation\ConsultationSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academic_cycle_id
 * @property int|null $room_id
 * @property string $code
 * @property string $status
 * @property string $booking_mode
 * @property Carbon|null $scheduled_at
 * @property int $version
 * @property int|null $published_by_user_id
 * @property Carbon|null $published_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $reservation_opened_at
 * @property Carbon|null $reservation_closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConsultationSession extends Model
{
    /** @use HasFactory<ConsultationSessionFactory> */
    use HasFactory;

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

    protected $attributes = [
        'auto_assign_enabled' => false,
    ];

    protected $fillable = [
        'academic_cycle_id',
        'room_id',
        'code',
        'status',
        'booking_mode',
        'auto_assign_enabled',
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
            'auto_assign_enabled' => 'boolean',
            'version' => 'integer',
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
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDistributionEligible(Builder $query, int $academicCycleId): Builder
    {
        return $query
            ->where('academic_cycle_id', $academicCycleId)
            ->where('status', self::STATUS_DRAFT)
            ->where('booking_mode', self::BOOKING_STAFF_ONLY)
            ->whereHas('room', fn (Builder $room): Builder => $room->where('is_active', true));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAutoAssignEligible(Builder $query, int $academicCycleId): Builder
    {
        return $query
            ->distributionEligible($academicCycleId)
            ->where('auto_assign_enabled', true);
    }

    /**
     * @return BelongsTo<AcademicCycle, $this>
     */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * @return HasMany<ConsultationAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ConsultationAssignment::class);
    }
}
