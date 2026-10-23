<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class XiamUsageReservation extends Model
{
    public const STATE_RESERVED = 'reserved';

    public const STATE_SETTLED = 'settled';

    public const STATE_RELEASED = 'released';

    public const STATE_UNKNOWN = 'unknown';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'xiam_usage_reservations';

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'institution_id',
        'run_id',
        'unit',
        'amount',
        'reserved_tokens',
        'reserved_cost_micro',
        'state',
        'provider_request_id',
        'office',
        'granted_by_user_id',
        'extension_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reserved_tokens' => 'integer',
            'reserved_cost_micro' => 'integer',
        ];
    }
}
