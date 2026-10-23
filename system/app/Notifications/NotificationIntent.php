<?php

namespace App\Notifications;

/**
 * Immutable typed intent describing one logical notification a domain command
 * wants delivered. The dispatcher freezes template version + payload at
 * dispatch time (append-only content; existing deliveries are never rewritten).
 *
 * `eventType` is the canonical contract key, e.g. `result.released.v1` —
 * consumers branch on the event contract shape. `templateKey` references the
 * immutable content template (`result.released`), whose `templateVersion` is
 * captured per delivery.
 */
final class NotificationIntent
{
    /**
     * @param  array<string, mixed>  $payload  frozen template variables
     * @param  list<string>  $channels  subset of {@see NotificationDelivery::CHANNEL_*}
     */
    public function __construct(
        public readonly string $eventType,
        public readonly string $templateKey,
        public readonly int $eventVersion,
        public readonly int $templateVersion,
        public readonly array $payload,
        public readonly array $channels = [NotificationDelivery::CHANNEL_IN_APP],
    ) {}

    public static function v1(string $eventType, string $templateKey, array $payload, array $channels = [NotificationDelivery::CHANNEL_IN_APP]): self
    {
        return new self(
            eventType: $eventType,
            templateKey: $templateKey,
            eventVersion: 1,
            templateVersion: 1,
            payload: $payload,
            channels: $channels,
        );
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }
}
