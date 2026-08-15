<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Contracts\DeliveryChannel;
use App\Notifications\Mail\NotificationMailable;
use App\Notifications\NotificationDelivery;
use Illuminate\Support\Facades\Mail;

/**
 * Mail channel (Mailpit in dev). Sends the versioned template snapshot stored
 * on the delivery; records the provider reference on success. Mail failure is
 * surfaced to the worker state machine — never to a toast before delivery.
 */
final class MailDeliveryChannel implements DeliveryChannel
{
    public function channelName(): string
    {
        return NotificationDelivery::CHANNEL_MAIL;
    }

    public function deliver(NotificationDelivery $delivery): ?string
    {
        $recipient = User::query()->find($delivery->recipient_id);

        if ($recipient === null) {
            throw new \RuntimeException('Mail recipient user no longer exists.');
        }

        $mailable = new NotificationMailable(
            templateKey: $delivery->template_key,
            templateVersion: $delivery->template_version,
            payload: $delivery->payload,
        );

        $sent = Mail::to($recipient->email)->send($mailable);

        return $sent?->getMessageId();
    }
}
