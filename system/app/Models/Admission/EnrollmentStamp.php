<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only Registrar enrollment stamps (D-091). The stamp, not the Guidance
 * pool, owns final-program truth; quota variance is snapshotted per stamp.
 */
class EnrollmentStamp extends Model
{
    protected $table = 'admission_attempt_enrollment_stamps';

    public $timestamps = false;

    public const STAMP_ENROLLING = 'enrolling';

    public const STAMP_ENROLLED = 'enrolled';

    public const STAMP_REVERSE_ENROLLING = 'reverse_enrolling';

    public const STAMP_REVERSE_ENROLLED = 'reverse_enrolled';

    protected $fillable = [
        'admission_attempt_id',
        'stamp_type',
        'provisional_program_id',
        'final_program_id',
        'stamped_by_user_id',
        'selection_id',
        'paper_reference',
        'paper_hash',
        'verifier',
        'reason',
        'quota_variance',
        'version',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quota_variance' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->stamp_type, [self::STAMP_ENROLLING, self::STAMP_ENROLLED], true);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function provisionalProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'provisional_program_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function finalProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'final_program_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function stampedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stamped_by_user_id');
    }
}
