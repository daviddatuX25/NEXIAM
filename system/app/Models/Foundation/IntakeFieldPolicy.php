<?php

namespace App\Models\Foundation;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeFieldPolicy extends Model
{
    protected $fillable = [
        'institution_id',
        'academic_cycle_id',
        'version',
        'field_key',
        'field_group',
        'label',
        'requirement_level',
        'is_active',
        'sort_order',
        'last_mutated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_key' => IntakeFieldKey::class,
            'requirement_level' => IntakeRequirementLevel::class,
            'version' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCycle(Builder $query, int $institutionId, int $academicCycleId, ?int $version = null): Builder
    {
        $query->where('institution_id', $institutionId)
            ->where('academic_cycle_id', $academicCycleId);

        if ($version !== null) {
            $query->where('version', $version);
        } else {
            $latestVersion = static::query()
                ->where('institution_id', $institutionId)
                ->where('academic_cycle_id', $academicCycleId)
                ->max('version') ?? 1;
            $query->where('version', $latestVersion);
        }

        return $query->orderBy('sort_order');
    }
}
