<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class XiamRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'xiam_runs';

    protected $fillable = [
        'id',
        'turn_id',
        'conversation_id',
        'status',
        'publication_version',
        'gateway_route',
        'provider_request_id',
        'pricing_version',
        'usage_state',
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
     * @return BelongsTo<XiamTurn, $this>
     */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(XiamTurn::class, 'turn_id');
    }

    /**
     * @return BelongsTo<XiamConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(XiamConversation::class, 'conversation_id');
    }

    /**
     * @return HasMany<XiamRunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(XiamRunEvent::class, 'run_id')->orderBy('seq');
    }

    /**
     * @return HasMany<XiamRunStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(XiamRunStep::class, 'run_id')->orderBy('seq');
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
