<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class XiamConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'xiam_conversations';

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'institution_id',
        'surface_key',
        'partition_key',
        'context_epoch',
        'epoch_version',
        'browser_session_id',
        'route_name',
        'surface_profile_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'epoch_version' => 'integer',
        ];
    }

    /**
     * @return HasMany<XiamTurn, $this>
     */
    public function turns(): HasMany
    {
        return $this->hasMany(XiamTurn::class, 'conversation_id');
    }

    /**
     * @return HasMany<XiamRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(XiamRun::class, 'conversation_id');
    }

    /**
     * @return HasMany<XiamRunStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(XiamRunStep::class, 'conversation_id');
    }
}
