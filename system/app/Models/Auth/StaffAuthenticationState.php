<?php

namespace App\Models\Auth;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffAuthenticationState extends Model
{
    protected $table = 'staff_authentication_states';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'activation_completed_at',
        'password_set_at',
        'must_change_password',
        'session_version',
        'mfa_required',
        'mfa_exempt',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_generation',
        'two_factor_last_accepted_step',
        'recovery_generation',
        'pending_two_factor_secret',
        'pending_two_factor_expires_at',
        'pending_enrollment_attempt_id',
        'protected_from_reset',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'two_factor_secret',
        'pending_two_factor_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'two_factor_secret' => 'encrypted',
            'pending_two_factor_secret' => 'encrypted',
            'activation_completed_at' => 'datetime',
            'password_set_at' => 'datetime',
            'must_change_password' => 'boolean',
            'mfa_required' => 'boolean',
            'mfa_exempt' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'pending_two_factor_expires_at' => 'datetime',
            'session_version' => 'integer',
            'two_factor_last_accepted_step' => 'integer',
            'protected_from_reset' => 'boolean',
        ];
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isOnboarded(): bool
    {
        return $this->activation_completed_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'user_id', 'user_id');
    }

    /**
     * @return HasMany<StaffActivationVoucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(StaffActivationVoucher::class, 'user_id');
    }

    /**
     * @return HasMany<StaffAuthenticationAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(StaffAuthenticationAttempt::class, 'user_id');
    }

    /**
     * @return HasMany<StaffProviderIdentity, $this>
     */
    public function providerIdentities(): HasMany
    {
        return $this->hasMany(StaffProviderIdentity::class, 'user_id');
    }

    /**
     * @return HasMany<StaffTwoFactorRecoveryCode, $this>
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(StaffTwoFactorRecoveryCode::class, 'user_id');
    }

    /**
     * @return BelongsTo<StaffAuthenticationAttempt, $this>
     */
    public function pendingEnrollmentAttempt(): BelongsTo
    {
        return $this->belongsTo(StaffAuthenticationAttempt::class, 'pending_enrollment_attempt_id');
    }
}
