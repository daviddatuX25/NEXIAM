<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class NexiUsageReservation extends Model
{
    public const STATE_RESERVED = 'reserved';

    public const STATE_SETTLED = 'settled';

    public const STATE_RELEASED = 'released';

    public const STATE_UNKNOWN = 'unknown';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_usage_reservations';

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'institution_id',
        'run_id',
        'unit',
        'amount',
        'state',
    ];
}
