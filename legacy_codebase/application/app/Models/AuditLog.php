<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'actor_type',
        'actor_id',
        'ip_address',
        'user_agent',
        'category',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Actor who performed the action (User or Applicant).
     */
    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'actor_type', 'actor_id');
    }

    /**
     * Subject of the action (polymorphic).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo('auditable', 'auditable_type', 'auditable_id');
    }
}
