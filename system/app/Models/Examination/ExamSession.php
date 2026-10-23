<?php

namespace App\Models\Examination;

use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Testing\HasPracticeProvenance;
use Database\Factories\Examination\ExamSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $room_id
 * @property int $academic_cycle_id
 * @property int|null $test_instrument_version_id
 * @property int|null $test_kit_version_id
 * @property string $code
 * @property string|null $label
 * @property string $status
 * @property string $booking_mode
 * @property Carbon|null $starts_at
 * @property Carbon|null $effective_end_at
 * @property int $version
 * @property int|null $booklet_version_id
 * @property int|null $seating_arrangement_id
 * @property string|null $seating_mode
 * @property Carbon|null $published_at
 * @property Carbon|null $started_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExamSession extends Model
{
    /** @use HasFactory<ExamSessionFactory> */
    use HasFactory;

    use HasPracticeProvenance;

    protected $hidden = ['practice_fixture_set_id'];

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

    public const SEATING_MODE_SIMPLE_CAPACITY = 'simple_capacity';

    public const SEATING_MODE_ASSIGNED_SEATING = 'assigned_seating';

    /**
     * @var list<string>
     */
    public const SEATING_MODES = [
        self::SEATING_MODE_SIMPLE_CAPACITY,
        self::SEATING_MODE_ASSIGNED_SEATING,
    ];

    protected $attributes = [
        'auto_assign_enabled' => false,
        'seating_mode' => self::SEATING_MODE_SIMPLE_CAPACITY,
    ];

    protected $fillable = [
        'academic_cycle_id',
        'test_instrument_version_id',
        'test_kit_version_id',
        'practice_fixture_set_id',
        'room_id',
        'booklet_version_id',
        'seating_arrangement_id',
        'seating_mode',
        'code',
        'label',
        'status',
        'booking_mode',
        'auto_assign_enabled',
        'starts_at',
        'effective_end_at',
        'version',
        'published_at',
        'started_at',
        'reservation_opened_at',
        'reservation_closed_at',
        'closed_at',
        'notification_policy',
        'notification_policy_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test_instrument_version_id' => 'integer',
            'test_kit_version_id' => 'integer',
            'auto_assign_enabled' => 'boolean',
            'version' => 'integer',
            'starts_at' => 'datetime',
            'effective_end_at' => 'datetime',
            'published_at' => 'datetime',
            'started_at' => 'datetime',
            'reservation_opened_at' => 'datetime',
            'reservation_closed_at' => 'datetime',
            'closed_at' => 'datetime',
            'notification_policy' => 'array',
            'notification_policy_snapshot' => 'array',
        ];
    }

    public function attendanceIsOpen(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS && $this->closed_at === null;
    }

    public function scheduledDurationMinutes(): int
    {
        if ($this->starts_at === null || $this->effective_end_at === null) {
            return 90;
        }

        $minutes = (int) $this->starts_at->diffInMinutes($this->effective_end_at, false);

        return $minutes >= 15 ? $minutes : 90;
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
     * @return BelongsTo<OmrQuestionBookletVersion, $this>
     */
    public function bookletVersion(): BelongsTo
    {
        return $this->belongsTo(OmrQuestionBookletVersion::class, 'booklet_version_id');
    }

    /**
     * @return BelongsTo<SeatingArrangement, $this>
     */
    public function seatingArrangement(): BelongsTo
    {
        return $this->belongsTo(SeatingArrangement::class, 'seating_arrangement_id');
    }

    /**
     * @return HasMany<ExamSessionMaterialSnapshot, $this>
     */
    public function materialSnapshots(): HasMany
    {
        return $this->hasMany(ExamSessionMaterialSnapshot::class);
    }

    /**
     * @return HasOne<ExamSessionMaterialSnapshot, $this>
     */
    public function currentMaterialSnapshot(): HasOne
    {
        return $this->hasOne(ExamSessionMaterialSnapshot::class)
            ->where('is_current', true);
    }

    /**
     * @return HasMany<ExamSessionLogisticsPlan, $this>
     */
    public function logisticsPlans(): HasMany
    {
        return $this->hasMany(ExamSessionLogisticsPlan::class, 'exam_session_id');
    }

    /**
     * @return HasOne<ExamSessionLogisticsPlan, $this>
     */
    public function currentLogisticsPlan(): HasOne
    {
        return $this->hasOne(ExamSessionLogisticsPlan::class, 'exam_session_id')->where('is_current', true);
    }

    public function hasLogisticsGeneration(): bool
    {
        if (array_key_exists('has_logistics_generation', $this->getAttributes())) {
            return (bool) $this->getAttribute('has_logistics_generation');
        }

        return $this->relationLoaded('logisticsPlans')
            ? $this->logisticsPlans->isNotEmpty()
            : $this->logisticsPlans()->exists();
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
     * @return HasOne<GradingSession, $this>
     */
    public function gradingSession(): HasOne
    {
        return $this->hasOne(GradingSession::class);
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function testInstrumentVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return BelongsTo<TestKitVersion, $this>
     */
    public function testKitVersion(): BelongsTo
    {
        return $this->belongsTo(TestKitVersion::class, 'test_kit_version_id');
    }
}
