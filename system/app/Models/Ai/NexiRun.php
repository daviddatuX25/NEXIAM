<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NexiRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_runs';

    protected $fillable = [
        'id',
        'turn_id',
        'conversation_id',
        'status',
        'publication_version',
        'gateway_route',
        'cancel_requested_at',
        'started_at',
        'finished_at',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancel_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NexiTurn, $this>
     */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(NexiTurn::class, 'turn_id');
    }

    /**
     * @return BelongsTo<NexiConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(NexiConversation::class, 'conversation_id');
    }

    /**
     * @return HasMany<NexiRunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(NexiRunEvent::class, 'run_id')->orderBy('seq');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCEEDED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
