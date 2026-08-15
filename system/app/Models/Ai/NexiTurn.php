<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NexiTurn extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_turns';

    protected $fillable = [
        'id',
        'conversation_id',
        'seq',
        'mode',
        'page_pack_key',
        'page_pack_version',
        'input_text',
        'idempotency_key',
    ];

    /**
     * @return BelongsTo<NexiConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(NexiConversation::class, 'conversation_id');
    }

    /**
     * @return HasOne<NexiRun, $this>
     */
    public function run(): HasOne
    {
        return $this->hasOne(NexiRun::class, 'turn_id');
    }
}
