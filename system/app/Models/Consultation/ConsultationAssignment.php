<?php

namespace App\Models\Consultation;

use App\Models\Admission\AdmissionAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationAssignment extends Model
{
    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_CONSULTED = 'consulted';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_STAFF_ASSIGNED = 'staff_assigned';

    public const SOURCE_APPLICANT_RESERVED = 'applicant_reserved';

    /**
     * Active seat holds that participate in uniqueness and capacity.
     *
     * @var list<string>
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_ASSIGNED,
    ];

    /**
     * @var list<string>
     */
    public const SOURCES = [
        self::SOURCE_STAFF_ASSIGNED,
        self::SOURCE_APPLICANT_RESERVED,
    ];

    protected $fillable = [
        'consultation_session_id',
        'admission_attempt_id',
        'status',
        'source',
        'assigned_by_user_id',
        'reserved_by_user_id',
        'consulted_at',
        'cancelled_at',
        'cancel_reason',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * @return BelongsTo<ConsultationSession, $this>
     */
    public function consultationSession(): BelongsTo
    {
        return $this->belongsTo(ConsultationSession::class);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }
}
