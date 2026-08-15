<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class NexiTaskGrant extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_task_grants';

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'session_id',
        'institution_id',
        'surface_key',
        'skill_key',
        'skill_version',
        'allowed_capability_ids',
        'scope_bounds',
        'origin_pack_key',
        'origin_pack_version',
        'context_epoch',
        'status',
        'expires_at',
        'revoked_at',
        'revoke_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_capability_ids' => 'array',
            'scope_bounds' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
