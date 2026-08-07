<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionReferenceCounter extends Model
{
    protected $fillable = [
        'academic_cycle_id',
        'next_value',
    ];

    /**
     * @return BelongsTo<\App\Models\Foundation\AcademicCycle, $this>
     */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Foundation\AcademicCycle::class);
    }
}
