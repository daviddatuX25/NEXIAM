<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrarQuotaGovernanceGrant extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'recipient_user_id',
        'institution_id',
        'program_id',
        'academic_cycle_id',
        'version',
        'status',
        'actor_user_id',
        'reason',
        'predecessor_id',
        'operation_id',
        'audit_event_id',
        'receipt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'receipt' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<AcademicCycle, $this> */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /** @return BelongsTo<Operation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function scopeLatestVersion(Builder $query): Builder
    {
        return $query->orderByDesc('version');
    }
}
