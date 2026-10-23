<?php

namespace App\Models\Admission;

use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationOpeningAvailabilityState extends Model
{
    protected $primaryKey = 'institution_id';

    public $incrementing = false;

    protected $fillable = [
        'institution_id', 'is_available', 'generation', 'academic_cycle_id', 'application_window_id',
        'opens_at', 'available_since', 'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'opens_at' => 'immutable_datetime',
            'available_since' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
