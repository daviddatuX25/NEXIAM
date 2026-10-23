<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffActivationVoucher extends Model
{
    protected $table = 'staff_activation_vouchers';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'public_reference',
        'user_id',
        'purpose',
        'mode',
        'token_digest',
        'fallback_hash',
        'issued_by_user_id',
        'expires_at',
        'redeemed_at',
        'revoked_at',
        'revoke_reason',
        'failed_attempts',
        'lifetime_fallback_attempts',
        'fallback_disabled',
        'attempt_window_started_at',
        'blocked_until',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_digest',
        'fallback_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'attempt_window_started_at' => 'datetime',
            'blocked_until' => 'datetime',
            'failed_attempts' => 'integer',
            'lifetime_fallback_attempts' => 'integer',
            'fallback_disabled' => 'boolean',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isRedeemed(): bool
    {
        return $this->redeemed_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired()
            && ! $this->isRedeemed()
            && ! $this->isRevoked()
            && ! $this->isBlocked();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_until?->isFuture() ?? false;
    }

    /**
     * Scope a query to only include unexpired or non-expiring vouchers.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnexpired($query): void
    {
        $query->where(function ($q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /**
     * @return BelongsTo<StaffAuthenticationState, $this>
     */
    public function authenticationState(): BelongsTo
    {
        return $this->belongsTo(StaffAuthenticationState::class, 'user_id', 'user_id');
    }
}
