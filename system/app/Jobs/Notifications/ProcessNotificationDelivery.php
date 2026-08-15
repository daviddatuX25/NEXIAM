<?php

namespace App\Jobs\Notifications;

use App\Foundation\Actions\RecordAuditEvent;
use App\Notifications\Channels\InAppDeliveryChannel;
use App\Notifications\Channels\MailDeliveryChannel;
use App\Notifications\Contracts\DeliveryChannel;
use App\Notifications\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * After-commit delivery worker. Claims one delivery, runs the channel adapter,
 * and advances the state machine:
 *
 *   pending → processing → delivered
 *                        → retrying (backoff, max 5 attempts) → pending
 *                        → failed_terminal
 *
 * `in_app` and `mail` run independently (a mail failure never blocks the
 * in-app projection). Transitions are audit-logged.
 */
class ProcessNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 5;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public string $deliveryId)
    {
    }

    public function handle(RecordAuditEvent $recordAuditEvent): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);

        if ($delivery === null || ! $this->claimable($delivery)) {
            return;
        }

        DB::transaction(function () use ($delivery, $recordAuditEvent): void {
            $delivery = NotificationDelivery::query()->lockForUpdate()->find($delivery->id);
            if ($delivery === null || ! $this->claimable($delivery)) {
                return;
            }

            $delivery->update([
                'status' => NotificationDelivery::STATUS_PROCESSING,
            ]);

            $channel = $this->channelFor($delivery);

            try {
                $providerReference = $channel->deliver($delivery);

                $delivery->update([
                    'status' => NotificationDelivery::STATUS_DELIVERED,
                    'attempts' => $delivery->attempts + 1,
                    'next_attempt_at' => null,
                    'last_error' => null,
                    'provider_reference' => $providerReference,
                ]);

                $recordAuditEvent->handle('notification.delivery_delivered', [
                    'delivery_id' => $delivery->id,
                    'event_type' => $delivery->event_type,
                    'channel' => $delivery->channel,
                    'attempts' => $delivery->attempts,
                ], 'system');
            } catch (Throwable $exception) {
                $this->handleFailure($delivery, $recordAuditEvent, $exception);
            }
        });
    }

    private function channelFor(NotificationDelivery $delivery): DeliveryChannel
    {
        return match ($delivery->channel) {
            NotificationDelivery::CHANNEL_IN_APP => app(InAppDeliveryChannel::class),
            NotificationDelivery::CHANNEL_MAIL => app(MailDeliveryChannel::class),
            default => throw new \RuntimeException("Unknown delivery channel: {$delivery->channel}"),
        };
    }

    private function handleFailure(
        NotificationDelivery $delivery,
        RecordAuditEvent $recordAuditEvent,
        Throwable $exception,
    ): void {
        $attempts = $delivery->attempts + 1;
        $redacted = $this->redact($exception->getMessage());

        if ($attempts >= self::MAX_ATTEMPTS) {
            $delivery->update([
                'status' => NotificationDelivery::STATUS_FAILED_TERMINAL,
                'attempts' => $attempts,
                'last_error' => $redacted,
            ]);

            $recordAuditEvent->handle('notification.delivery_failed', [
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type,
                'channel' => $delivery->channel,
                'attempts' => $attempts,
            ], 'system');

            return;
        }

        $backoffSeconds = (int) (30 * (2 ** ($attempts - 1)));
        $nextAttempt = now()->addSeconds($backoffSeconds);

        // retrying → pending (backoff). The row stays claimable only when the
        // scheduled retry is due, so the delayed job and a due-scan both agree.
        $delivery->update([
            'status' => NotificationDelivery::STATUS_PENDING,
            'attempts' => $attempts,
            'next_attempt_at' => $nextAttempt,
            'last_error' => $redacted,
        ]);

        $recordAuditEvent->handle('notification.delivery_retrying', [
            'delivery_id' => $delivery->id,
            'event_type' => $delivery->event_type,
            'channel' => $delivery->channel,
            'attempts' => $attempts,
            'next_attempt_at' => $nextAttempt->toIso8601String(),
        ], 'system');

        self::dispatch($delivery->id)->delay($nextAttempt);
    }

    private function claimable(NotificationDelivery $delivery): bool
    {
        if ($delivery->status !== NotificationDelivery::STATUS_PENDING) {
            return false;
        }

        return $delivery->next_attempt_at === null || $delivery->next_attempt_at->lte(now());
    }

    private function redact(string $message): string
    {
        $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', '[email]', $message);

        return (string) $message;
    }
}
