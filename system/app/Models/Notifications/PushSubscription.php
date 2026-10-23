<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushSubscription extends Model
{
    use HasUuids;

    protected $table = 'push_subscriptions';

    protected $fillable = [
        'id',
        'recipient_type',
        'recipient_id',
        'institution_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth_token',
        'vapid_key_id',
        'device_label',
        'user_agent',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeForRecipient(Builder $query, User|ApplicantIdentity $recipient): Builder
    {
        return $query->where('recipient_type', $recipient instanceof User ? User::class : ApplicantIdentity::class)
            ->where('recipient_id', $recipient->getKey());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }
}
