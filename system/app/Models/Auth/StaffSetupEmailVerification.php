<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSetupEmailVerification extends Model
{
    protected $table = 'staff_setup_email_verifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'attempt_id',
        'email',
        'token_digest',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
