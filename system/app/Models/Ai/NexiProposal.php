<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class NexiProposal extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_proposals';

    protected $fillable = [
        'id',
        'run_id',
        'conversation_id',
        'status',
        'capability_id',
        'expected_version',
        'summary',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
