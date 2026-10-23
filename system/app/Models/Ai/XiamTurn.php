<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class XiamTurn extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'xiam_turns';

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
     * @return BelongsTo<XiamConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(XiamConversation::class, 'conversation_id');
    }

    /**
     * @return HasOne<XiamRun, $this>
     */
    public function run(): HasOne
    {
        return $this->hasOne(XiamRun::class, 'turn_id');
    }
}
