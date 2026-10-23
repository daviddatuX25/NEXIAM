<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Targeted PH edge-case follow-up (D-080/D-089). request_appearance is
 * non-terminal; assigned PH must clear or decline a routed edge follow-up.
 */
class ProgramFollowUp extends Model
{
    protected $table = 'admission_attempt_program_follow_ups';

    public const STATUS_OPEN = 'open';

    public const STATUS_APPEARANCE_REQUESTED = 'appearance_requested';

    public const STATUS_CLEARED_BY_PH = 'cleared_by_ph';

    public const STATUS_DECLINED_BY_PH = 'declined_by_ph';

    /** @var list<string> */
    public const OPEN_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_APPEARANCE_REQUESTED,
    ];

    /** @var list<string> */
    public const TERMINAL_STATUSES = [
        self::STATUS_CLEARED_BY_PH,
        self::STATUS_DECLINED_BY_PH,
    ];

    protected $fillable = [
        'admission_attempt_id',
        'program_id',
        'program_pool_id',
        'opened_by_user_id',
        'status',
        'reason',
        'resolved_by_user_id',
        'resolution_reason',
        'idempotency_key',
        'version',
        'opened_at',
        'resolved_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->attempt();
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<ProgramPool, $this>
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(ProgramPool::class, 'program_pool_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }
}
