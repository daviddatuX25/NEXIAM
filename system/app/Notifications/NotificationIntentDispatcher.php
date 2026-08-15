<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes `notification_deliveries` rows atomically with the caller's domain
 * transaction (must be invoked inside the command's DB::transaction).
 *
 * Dedupe: each (event_id, recipient, channel) is a unique row, so replaying an
 * idempotent command with the same deterministic `eventId` never duplicates a
 * delivery. The caller controls `eventId` (e.g. sha256 of the domain occurrence
 * + version) so the same logical event maps to the same dedupe key.
 *
 * Returns only freshly created deliveries; the caller enqueues their ids
 * AFTER the transaction commits (queue `after_commit=true`).
 */
final class NotificationIntentDispatcher
{
    /**
     * @param  iterable<User>  $recipients
     * @param  list<string>  $channels
     * @return Collection<int, NotificationDelivery>
     */
    public function dispatch(
        NotificationIntent $intent,
        string $eventId,
        iterable $recipients,
        array $channels,
    ): Collection {
        $created = [];

        foreach ($recipients as $user) {
            if (! $user instanceof User || $user->getKey() === null) {
                continue;
            }

            foreach ($channels as $channel) {
                if (! $intent->hasChannel($channel)) {
                    continue;
                }

                $existing = NotificationDelivery::query()
                    ->where('event_id', $eventId)
                    ->where('recipient_type', User::class)
                    ->where('recipient_id', $user->getKey())
                    ->where('channel', $channel)
                    ->first();

                if ($existing !== null) {
                    continue;
                }

                $created[] = NotificationDelivery::query()->create([
                    'id' => (string) Str::uuid(),
                    'event_id' => $eventId,
                    'event_type' => $intent->eventType,
                    'event_version' => $intent->eventVersion,
                    'recipient_type' => User::class,
                    'recipient_id' => $user->getKey(),
                    'channel' => $channel,
                    'template_key' => $intent->templateKey,
                    'template_version' => $intent->templateVersion,
                    'payload' => $intent->payload,
                    'status' => NotificationDelivery::STATUS_PENDING,
                    'attempts' => 0,
                ]);
            }
        }

        return collect($created);
    }

    /**
     * Enqueue after-commit processing for freshly created deliveries.
     * Safe to call immediately after the domain transaction commits.
     *
     * @param  Collection<int, NotificationDelivery>|list<NotificationDelivery>  $deliveries
     */
    public static function enqueueAfterCommit(Collection|array $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            DB::afterCommit(fn () => \App\Jobs\Notifications\ProcessNotificationDelivery::dispatch($delivery->id));
        }
    }
}
