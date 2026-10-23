<?php

declare(strict_types=1);

namespace App\Models\Consultation;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Institution;
use App\Models\Release\ResultDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $admitted_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $access_starts_at
 * @property Carbon|null $access_ends_at
 * @property Carbon|null $media_host_joined_at
 */
class ConsultationEncounter extends Model
{
    public const MEDIUM_F2F = 'f2f';

    public const MEDIUM_ONLINE = 'online';

    public const BOOKING_PATH_SCHEDULED = 'scheduled';

    public const BOOKING_PATH_DIRECT = 'direct';

    public const STATUS_INVITED = 'invited';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_OUTCOME_RECORDED = 'outcome_recorded';

    public const STATUS_CLOSED_WITHOUT_OUTCOME = 'closed_without_outcome';

    public const STATUS_CLOSED_WITH_OUTCOME = 'closed_with_outcome';

    public const MEDIA_CLOSE_STATUS_NONE = 'none';

    public const MEDIA_CLOSE_STATUS_PENDING = 'pending';

    public const MEDIA_CLOSE_STATUS_ACKNOWLEDGED = 'acknowledged';

    public const MEDIA_CLOSE_STATUS_FAILED = 'failed';

    protected $attributes = [
        'status' => self::STATUS_INVITED,
        'schedule_generation' => 1,
        'media_close_status' => self::MEDIA_CLOSE_STATUS_NONE,
    ];

    protected $fillable = [
        'institution_id',
        'admission_attempt_id',
        'result_document_id',
        'result_document_version',
        'consultation_policy_version',
        'command_fingerprint',
        'consultation_session_id',
        'consultation_assignment_id',
        'counselor_user_id',
        'medium',
        'booking_path',
        'status',
        'room_name',
        'schedule_generation',
        'access_starts_at',
        'access_ends_at',
        'closed_at',
        'admitted_at',
        'media_host_joined_at',
        'close_reason',
        'media_close_status',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_document_version' => 'integer',
            'consultation_policy_version' => 'integer',
            'schedule_generation' => 'integer',
            'access_starts_at' => 'datetime',
            'access_ends_at' => 'datetime',
            'admitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'media_host_joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ResultDocument, $this>
     */
    public function resultDocument(): BelongsTo
    {
        return $this->belongsTo(ResultDocument::class);
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /**
     * @return BelongsTo<ConsultationSession, $this>
     */
    public function consultationSession(): BelongsTo
    {
        return $this->belongsTo(ConsultationSession::class);
    }

    /**
     * @return BelongsTo<ConsultationAssignment, $this>
     */
    public function consultationAssignment(): BelongsTo
    {
        return $this->belongsTo(ConsultationAssignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_user_id');
    }
}
