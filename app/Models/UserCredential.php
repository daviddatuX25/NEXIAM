<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCredential extends Model
{
    const PROVIDER_GOOGLE = 'google';

    protected $fillable = ['user_id', 'provider', 'identifier', 'secret'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
