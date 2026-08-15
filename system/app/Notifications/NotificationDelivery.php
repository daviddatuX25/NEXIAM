<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One authoritative delivery row per (domain event, recipient, channel).
 *
 * Written inside the domain transaction by {@see NotificationIntentDispatcher}
 * and advanced by the after-commit worker state machine
 * (pending → processing → delivered / retrying → failed_terminal).
 */
class NotificationDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_FAILED_TERMINAL = 'failed_terminal';

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_MAIL = 'mail';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'notification_deliveries';

    protected $fillable = [
        'id',
        'event_id',
        'event_type',
        'event_version',
        'recipient_type',
        'recipient_id',
        'channel',
        'template_key',
        'template_version',
        'payload',
        'redaction_class',
        'status',
        'attempts',
        'next_attempt_at',
        'last_error',
        'provider_reference',
        'read_at',
        'seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'template_version' => 'integer',
            'payload' => 'array',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'read_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<self>
     */
    public static function forUser(User $user): Builder
    {
        return static::query()
            ->where('recipient_type', User::class)
            ->where('recipient_id', $user->id);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }
}
