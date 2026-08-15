<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class NexiConsentRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_consent_records';

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'session_id',
        'surface_key',
        'disclosure_version',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
