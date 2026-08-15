<?php

namespace App\Notifications\Channels;

use App\Notifications\Contracts\DeliveryChannel;
use App\Notifications\NotificationDelivery;

/**
 * In-app channel: the `notification_deliveries` row IS the durable projection.
 * Delivering means the row is confirmed readable by the bell (no external call);
 * the worker marks it `delivered` with `seen_at` on first surface.
 */
final class InAppDeliveryChannel implements DeliveryChannel
{
    public function channelName(): string
    {
        return NotificationDelivery::CHANNEL_IN_APP;
    }

    public function deliver(NotificationDelivery $delivery): ?string
    {
        $delivery->update([
            'seen_at' => $delivery->seen_at ?? now(),
        ]);

        return null;
    }
}
