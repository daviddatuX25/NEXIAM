<?php

namespace App\Models\Foundation;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramQuota extends Model
{
    protected $fillable = [
        'program_id',
        'academic_cycle_id',
        'slots',
        'last_notice',
        'last_mutated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slots' => 'integer',
        ];
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
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastMutatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_mutated_by_user_id');
    }

    /**
     * @return HasMany<ProgramQuotaChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(ProgramQuotaChange::class)->orderByDesc('id');
    }
}
