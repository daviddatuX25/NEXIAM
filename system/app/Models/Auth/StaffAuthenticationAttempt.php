<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAuthenticationAttempt extends Model
{
    protected $table = 'staff_authentication_attempts';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'purpose',
        'primary_method',
        'phase',
        'voucher_id',
        'expected_session_version',
        'expected_factor_generation',
        'expected_recovery_generation',
        'session_binding_digest',
        'primary_verified_at',
        'mfa_verified_at',
        'failed_attempts',
        'blocked_until',
        'remember_requested',
        'intended_path',
        'pending_google_subject',
        'pending_google_email',
        'pending_google_email_verified',
        'recipient_email_chosen_at',
        'completed_at',
        'revoked_at',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'session_binding_digest',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient_email_chosen_at' => 'datetime',
            'primary_verified_at' => 'datetime',
            'mfa_verified_at' => 'datetime',
            'completed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
            'blocked_until' => 'datetime',
            'remember_requested' => 'boolean',
            'pending_google_email_verified' => 'boolean',
            'expected_recovery_generation' => 'string',
            'expected_session_version' => 'integer',
            'failed_attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<StaffActivationVoucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(StaffActivationVoucher::class, 'voucher_id');
    }

    /**
     * @return BelongsTo<StaffAuthenticationState, $this>
     */
    public function authenticationState(): BelongsTo
    {
        return $this->belongsTo(StaffAuthenticationState::class, 'user_id', 'user_id');
    }
}
