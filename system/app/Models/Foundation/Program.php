<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Program extends Model
{
    protected $attributes = [
        'is_admission_offered' => true,
    ];

    protected $fillable = [
        'institution_id',
        'department_id',
        'code',
        'name',
        'is_active',
        'is_admission_offered',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_admission_offered' => 'boolean',
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
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAdmissionOffered(Builder $query): Builder
    {
        return $query->where('is_admission_offered', true);
    }
}
