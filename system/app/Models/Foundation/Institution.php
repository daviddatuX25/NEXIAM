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
     * @return HasMany<AcademicCycle, $this>
     */
    public function academicCycles(): HasMany
    {
        return $this->hasMany(AcademicCycle::class);
    }
}
