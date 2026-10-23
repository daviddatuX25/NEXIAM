<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class XiamRunStep extends Model
{
    public const KIND_PROVIDER = 'provider_step';

    public const KIND_TOOL_CALL = 'tool_call';

    public const KIND_RUN = 'run_step';

    public const KIND_SKILL_TRANSITION = 'skill_transition';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'xiam_run_steps';

    protected $fillable = [
        'id',
        'run_id',
        'conversation_id',
        'seq',
        'step_kind',
        'status',
        'provider',
        'provider_request_id',
        'tool_id',
        'skill_key',
        'input',
        'output',
        'failure_reason',
        'started_at',
        'finished_at',
        'provider_driver',
        'model_id',
        'tariff_id',
        'rate_snapshot_json',
        'cache_read_tokens',
        'cache_write_tokens',
        'reasoning_tokens',
        'step_cost_micro',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'tariff_id' => 'integer',
            'rate_snapshot_json' => 'array',
            'cache_read_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'step_cost_micro' => 'integer',
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
