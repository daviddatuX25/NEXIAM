<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationOpeningToken extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['token_hash', 'encrypted_token'];

    protected $fillable = [
        'id', 'subscription_id', 'generation', 'purpose', 'token_hash', 'encrypted_token',
        'expires_at', 'consumed_at', 'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ApplicationOpeningSubscription::class, 'subscription_id');
    }
}
