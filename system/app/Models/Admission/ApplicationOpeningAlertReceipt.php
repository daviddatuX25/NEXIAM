<?php

namespace App\Models\Admission;

use App\Notifications\NotificationDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationOpeningAlertReceipt extends Model
{
    protected $fillable = [
        'subscription_id', 'generation', 'occurrence_id', 'delivery_id', 'status', 'claimed_at',
        'consumed_at', 'address_erased_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'address_erased_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ApplicationOpeningSubscription::class, 'subscription_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ApplicationOpeningOccurrence::class, 'occurrence_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'delivery_id');
    }
}
