<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProviderIdentity extends Model
{
    protected $table = 'staff_provider_identities';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'subject',
        'email_at_link',
        'email_verified_at_link',
        'linked_at',
        'revoked_at',
        'linked_via_attempt_id',
        'linked_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at_link' => 'boolean',
            'linked_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
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
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }

    /**
     * @return BelongsTo<StaffAuthenticationAttempt, $this>
     */
    public function linkedViaAttempt(): BelongsTo
    {
        return $this->belongsTo(StaffAuthenticationAttempt::class, 'linked_via_attempt_id');
    }
}
