<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexiRunEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'nexi_run_events';

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
     * @return BelongsTo<NexiRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(NexiRun::class, 'run_id');
    }
}
