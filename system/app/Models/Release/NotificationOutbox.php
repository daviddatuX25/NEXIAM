<?php

namespace App\Models\Release;

use Illuminate\Database\Eloquent\Model;

class NotificationOutbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'notification_outbox';

    protected $fillable = [
        'source_type',
        'source_id',
        'recipient_type',
        'recipient_id',
        'channel',
        'payload',
        'status',
        'idempotency_key',
        'sent_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
