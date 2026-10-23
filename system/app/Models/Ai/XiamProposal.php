<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XiamProposal extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'xiam_proposals';

    protected $fillable = [
        'id',
        'run_id',
        'conversation_id',
        'status',
        'capability_id',
        'expected_version',
        'execution_id',
        'canonical_digest',
        'safety_tier',
        'confirmation_phrase',
        'requires_reauth',
        'safety_policy_version',
        'confirmation_idempotency_key',
        'summary',
        'proposal_view',
        'receipt',
        'claimed_at',
        'expires_at',
        'failure_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_reauth' => 'boolean',
            'safety_policy_version' => 'integer',
            'summary' => 'array',
            'proposal_view' => 'array',
            'receipt' => 'array',
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<XiamConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(XiamConversation::class, 'conversation_id');
    }
}
