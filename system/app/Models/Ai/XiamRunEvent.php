<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class XiamRunEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'xiam_run_events';

    protected $fillable = [
        'id',
        'run_id',
        'conversation_id',
        'seq',
        'type',
        'payload',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<XiamRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(XiamRun::class, 'run_id');
    }

    /**
     * @return BelongsTo<XiamConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(XiamConversation::class, 'conversation_id');
    }
}
