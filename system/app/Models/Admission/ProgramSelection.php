<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable pre-enrollment choice referencing its exact pool/option version.
 * Quota is held only by pending_acknowledgement + active rows (D-090).
 */
class ProgramSelection extends Model
{
    protected $table = 'admission_attempt_program_selections';

    public const STATUS_PENDING_ACKNOWLEDGEMENT = 'pending_acknowledgement';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_PROGRAM_DECLINED = 'program_declined';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAPER_FINALIZED = 'paper_finalized';

    public const SOURCE_STUDENT_SELF = 'student_self';

    public const SOURCE_GUIDANCE_DEFAULT = 'guidance_default';

    /** @var list<string> */
    public const NON_TERMINAL_STATUSES = [
        self::STATUS_PENDING_ACKNOWLEDGEMENT,
        self::STATUS_ACTIVE,
    ];

    protected $fillable = [
        'admission_attempt_id',
        'program_pool_id',
        'program_pool_option_id',
        'program_id',
        'academic_cycle_id',
        'status',
        'source',
        'actor_user_id',
        'applicant_acknowledged_at',
        'commitment_identity',
        'idempotency_key',
        'version',
        'terminal_reason',
        'enrollment_stamp_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'applicant_acknowledged_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isNonTerminal(): bool
    {
        return in_array($this->status, self::NON_TERMINAL_STATUSES, true);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<ProgramPool, $this>
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(ProgramPool::class, 'program_pool_id');
    }

    /**
     * @return BelongsTo<ProgramPoolOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProgramPoolOption::class, 'program_pool_option_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<AcademicCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class, 'academic_cycle_id');
    }

    /**
     * @return BelongsTo<EnrollmentStamp, $this>
     */
    public function enrollmentStamp(): BelongsTo
    {
        return $this->belongsTo(EnrollmentStamp::class);
    }
}
