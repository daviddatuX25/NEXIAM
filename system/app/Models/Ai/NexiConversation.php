<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NexiConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_conversations';

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'institution_id',
        'surface_key',
        'partition_key',
        'context_epoch',
        'status',
    ];

    /**
     * @return HasMany<NexiTurn, $this>
     */
    public function turns(): HasMany
    {
        return $this->hasMany(NexiTurn::class, 'conversation_id');
    }

    /**
     * @return HasMany<NexiRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(NexiRun::class, 'conversation_id');
    }
}
