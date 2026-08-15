<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * The greenfield deployment is single-institution; catalog rows are scoped
     * to this institution for uniqueness. Shared by Setup controllers and
     * scoped-unique validation rules.
     */
    public static function current(): self
    {
        return static::query()->where('code', 'SYN-INST-001')->firstOrFail();
    }

    /**
     * @return HasMany<AcademicCycle, $this>
     */
    public function academicCycles(): HasMany
    {
        return $this->hasMany(AcademicCycle::class);
    }
}
