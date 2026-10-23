<?php

namespace App\Models\Admission;

use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationOpeningOccurrence extends Model
{
    protected $fillable = [
        'institution_id', 'academic_cycle_id', 'application_window_id', 'availability_generation', 'opened_at', 'closes_at',
        'observed_at', 'availability_key', 'last_subscription_id', 'fanout_complete',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime',
            'fanout_complete' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ApplicationOpeningAlertReceipt::class, 'occurrence_id');
    }
}
