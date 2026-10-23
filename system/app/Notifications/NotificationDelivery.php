<?php

namespace App\Notifications;

use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One authoritative delivery row per (domain event, recipient, channel).
 *
 * Written inside the domain transaction by {@see NotificationIntentDispatcher}
 * and advanced by the after-commit worker state machine
 * (pending → processing → delivered / retrying → failed_terminal or superseded).
 */
class NotificationDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_FAILED_TERMINAL = 'failed_terminal';

    public const STATUS_OUTCOME_UNKNOWN = 'outcome_unknown';

    public const STATUS_SUPERSEDED = 'superseded';

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_PUSH = 'push';

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
        'delivered_at',
        'claim_token',
        'lease_expires_at',
        'outcome_unknown_at',
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
            'delivered_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'outcome_unknown_at' => 'datetime',
        ];
    }

    /**
     * Current User's own staff rows and any applicant-identity rows linked to it.
     *
     * @return Builder<self>
     */
    public static function forUser(User $user): Builder
    {
        $applicantIdentityId = $user->applicantAccount()->value('applicant_identity_id');

        return static::query()->where(function (Builder $query) use ($user, $applicantIdentityId): void {
            $query->where(function (Builder $recipientQuery) use ($user): void {
                $recipientQuery
                    ->where('recipient_type', User::class)
                    ->where('recipient_id', $user->id);
            });

            if ($applicantIdentityId !== null) {
                $query->orWhere(function (Builder $recipientQuery) use ($applicantIdentityId): void {
                    $recipientQuery
                        ->where('recipient_type', ApplicantIdentity::class)
                        ->where('recipient_id', (int) $applicantIdentityId);
                });
            }
        });
    }

    public static function operationalStatus(string $channel, string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'queued',
            self::STATUS_PROCESSING => 'in_progress',
            self::STATUS_RETRYING => 'retry_scheduled',
            self::STATUS_DELIVERED => match ($channel) {
                self::CHANNEL_MAIL => 'accepted_mail',
                self::CHANNEL_PUSH => 'accepted_push',
                self::CHANNEL_IN_APP => 'available_in_app',
                default => 'unknown',
            },
            self::STATUS_FAILED_TERMINAL => 'failed',
            self::STATUS_SUPERSEDED => 'superseded',
            default => 'unknown',
        };
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
