<?php

namespace App\Notifications\Contracts;

use App\Notifications\NotificationDelivery;

/**
 * A delivery channel adapter. Channels are invoked by the delivery worker and
 * must never write authoritative domain state — only delivery-side evidence.
 */
interface DeliveryChannel
{
    public function channelName(): string;

    /**
     * Deliver one notification. Throws on provider failure; the worker owns the
     * retry/terminal state machine.
     *
     * @return string|null provider reference (e.g. mail message id), if any
     */
    public function deliver(NotificationDelivery $delivery): ?string;
}
