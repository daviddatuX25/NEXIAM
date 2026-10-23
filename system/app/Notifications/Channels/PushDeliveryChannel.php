<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Notifications\PushSubscription;
use App\Notifications\Contracts\DeliveryChannel;
use App\Notifications\NotificationDelivery;
use App\Notifications\Services\WebPushService;

final class PushDeliveryChannel implements DeliveryChannel
{
    public function __construct(private readonly WebPushService $webPush) {}

    public function channelName(): string
    {
        return NotificationDelivery::CHANNEL_PUSH;
    }

    public function deliver(NotificationDelivery $delivery): ?string
    {
        if ($delivery->recipient_type === ApplicationOpeningSubscription::class) {
            throw new \RuntimeException('application_opening_alert_mail_only');
        }
        $subscriptions = PushSubscription::query()
            ->where('recipient_type', $delivery->recipient_type)
            ->where('recipient_id', $delivery->recipient_id)
            ->active()
            ->get();

        if ($subscriptions->isEmpty()) {
            return 'no_subscriptions';
        }

        $payload = $this->buildPayload($delivery);
        $successCount = 0;
        $totalCount = $subscriptions->count();

        foreach ($subscriptions as $subscription) {
            $result = $this->webPush->send($subscription, $payload);
            if (($result['reason'] ?? null) === 'vapid_configuration_unavailable'
                || ($result['reason'] ?? null) === 'vapid_configuration_invalid') {
                throw new \RuntimeException($result['reason']);
            }
            if ($result['success']) {
                $successCount++;
            }
        }

        return "sent:{$successCount}/{$totalCount}";
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    private function buildPayload(NotificationDelivery $delivery): array
    {
        $payload = $delivery->payload ?? [];
        $portal = $payload['portal'] ?? [];

        $title = (string) ($portal['title'] ?? $payload['subject'] ?? 'NEXIAM Notification');
        $body = (string) ($portal['body'] ?? $payload['body'] ?? 'You have a new update in the NEXIAM portal.');
        $url = (string) ($portal['action_url'] ?? $payload['action_url'] ?? '/notifications');
        $tag = (string) ($delivery->event_type ?? 'nexiam-alert');

        return [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag,
        ];
    }
}
