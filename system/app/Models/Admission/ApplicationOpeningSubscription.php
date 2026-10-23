<?php

namespace App\Models\Admission;

use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationOpeningSubscription extends Model
{
    public const STATE_PENDING = 'pending';

    public const STATE_VERIFIED = 'verified';

    public const STATE_CONSUMED = 'consumed';

    public const STATE_UNSUBSCRIBED = 'unsubscribed';

    public const STATE_EXPIRED = 'expired';

    protected $fillable = [
        'institution_id', 'email', 'normalized_email', 'state', 'generation', 'consented_at',
        'consent_version', 'verified_at', 'unsubscribed_at', 'consumed_at', 'expires_at',
        'address_erased_at', 'retention_hold', 'last_verification_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'unsubscribed_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'address_erased_at' => 'immutable_datetime',
            'retention_hold' => 'boolean',
            'last_verification_sent_at' => 'immutable_datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ApplicationOpeningToken::class, 'subscription_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ApplicationOpeningAlertReceipt::class, 'subscription_id');
    }

    public function scopeVerifiedAndEligible(Builder $query): Builder
    {
        return $query->where('state', self::STATE_VERIFIED)
            ->whereNotNull('verified_at')
            ->whereNull('unsubscribed_at')
            ->whereNull('consumed_at')
            ->whereNull('address_erased_at')
            ->where(function (Builder $eligible): void {
                $eligible->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
