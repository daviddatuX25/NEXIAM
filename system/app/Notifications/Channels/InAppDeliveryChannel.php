<?php

namespace App\Notifications\Channels;

use App\Notifications\Contracts\DeliveryChannel;
use App\Notifications\NotificationDelivery;

/**
 * The in-app delivery row is the durable projection. Delivering confirms that
 * the row is available; surfacing and human read state are recorded elsewhere.
 */
final class InAppDeliveryChannel implements DeliveryChannel
{
    public function channelName(): string
    {
        return NotificationDelivery::CHANNEL_IN_APP;
    }

    public function deliver(NotificationDelivery $delivery): ?string
    {
        return null;
    }
}
