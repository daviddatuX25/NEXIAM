<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable assigned-PH decline outcome (D-089). Both direct selection decline
 * and follow-up decline write this same authority; effective-option queries
 * never infer exclusion from UI state.
 */
class ProgramDecline extends Model
{
    protected $table = 'admission_attempt_program_declines';

    public $timestamps = false;

    public const SOURCE_DIRECT_SELECTION = 'direct_selection';

    public const SOURCE_FOLLOW_UP = 'follow_up';

    protected $fillable = [
        'admission_attempt_id',
        'program_id',
        'program_pool_id',
        'selection_id',
        'follow_up_id',
        'declined_by_user_id',
        'source',
        'reason',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
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
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<ProgramSelection, $this>
     */
    public function selection(): BelongsTo
    {
        return $this->belongsTo(ProgramSelection::class, 'selection_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function declinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by_user_id');
    }
}
