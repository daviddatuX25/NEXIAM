<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTwoFactorRecoveryCode extends Model
{
    protected $table = 'staff_two_factor_recovery_codes';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'generation',
        'code_digest',
        'consumed_at',
        'revoked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'code_digest',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isRevoked();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
