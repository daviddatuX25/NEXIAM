<?php

namespace App\Jobs\Notifications;

use App\Events\Notifications\UserAttentionUpdated;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\User;
use App\Notifications\Channels\InAppDeliveryChannel;
use App\Notifications\Channels\MailDeliveryChannel;
use App\Notifications\Channels\PushDeliveryChannel;
use App\Notifications\Contracts\DeliveryChannel;
use App\Notifications\Mail\NotificationMailOutcomeUnknown;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Claims one durable delivery, performs channel I/O outside the database
 * transaction, then persists the result using a fenced claim token.
 */
class ProcessNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 5;

    private const LEASE_SECONDS = 120;

    public int $tries = 1;

    public int $timeout = 60;

    private ?string $activeClaimToken = null;

    public function __construct(public string $deliveryId) {}

    public function handle(RecordAuditEvent $recordAuditEvent, NotificationBusinessValidity $businessValidity): void
    {
        $claimToken = $this->claim($recordAuditEvent, $businessValidity);

        if ($claimToken === null) {
            return;
        }

        $this->activeClaimToken = $claimToken;
        $delivery = NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('claim_token', $claimToken)
            ->first();

        if ($delivery === null) {
            $this->activeClaimToken = null;

            return;
        }

        if ($this->supersedeClaimIfStale($claimToken, $recordAuditEvent, $businessValidity)) {
            $this->activeClaimToken = null;

            return;
        }

        try {
            $providerReference = $this->channelFor($delivery)->deliver($delivery);
        } catch (Throwable $exception) {
            if ($exception instanceof NotificationMailOutcomeUnknown) {
                $this->handleFailure($claimToken, $recordAuditEvent, $exception);
                $this->activeClaimToken = null;

                return;
            }
            if ($this->supersedeClaimIfStale($claimToken, $recordAuditEvent, $businessValidity)) {
                $this->activeClaimToken = null;

                return;
            }
            $this->handleFailure($claimToken, $recordAuditEvent, $exception);
            $this->activeClaimToken = null;

            return;
        }

        $this->markDelivered($claimToken, $providerReference, $recordAuditEvent);
        $this->activeClaimToken = null;
    }

    /**
     * Uncaught worker failures leave a fenced lease for the recovery command.
     * Mail is not retried automatically after an unknown provider outcome.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->activeClaimToken === null) {
            return;
        }

        $claimToken = $this->activeClaimToken;
        $redacted = $this->redact($exception->getMessage());
        $recordAuditEvent = app(RecordAuditEvent::class);

        DB::transaction(function () use ($claimToken, $redacted, $recordAuditEvent): void {
            $delivery = NotificationDelivery::query()
                ->whereKey($this->deliveryId)
                ->where('claim_token', $claimToken)
                ->lockForUpdate()
                ->first();

            if ($delivery === null || $delivery->status !== NotificationDelivery::STATUS_PROCESSING) {
                return;
            }

            if ($delivery->channel === NotificationDelivery::CHANNEL_MAIL) {
                $delivery->update([
                    'status' => NotificationDelivery::STATUS_OUTCOME_UNKNOWN,
                    'outcome_unknown_at' => now(),
                    'claim_token' => null,
                    'lease_expires_at' => null,
                    'last_error' => 'provider_outcome_unknown',
                ]);

                $recordAuditEvent->handle('notification.delivery_outcome_unknown', [
                    'delivery_id' => $delivery->id,
                    'event_type' => $delivery->event_type,
                    'channel' => $delivery->channel,
                    'attempts' => $delivery->attempts,
                ], 'system');

                return;
            }

            $nextAttempt = now();
            $delivery->update([
                'status' => NotificationDelivery::STATUS_RETRYING,
                'next_attempt_at' => $nextAttempt,
                'claim_token' => null,
                'lease_expires_at' => null,
                'last_error' => $redacted,
            ]);

            $recordAuditEvent->handle('notification.delivery_retrying', [
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type,
                'channel' => $delivery->channel,
                'attempts' => $delivery->attempts,
                'next_attempt_at' => $nextAttempt->toIso8601String(),
            ], 'system');

            DB::afterCommit(fn () => self::dispatch($delivery->id));
        });
    }

    private function claim(
        RecordAuditEvent $recordAuditEvent,
        NotificationBusinessValidity $businessValidity,
    ): ?string {
        return DB::transaction(function () use ($recordAuditEvent, $businessValidity): ?string {
            $delivery = NotificationDelivery::query()
                ->lockForUpdate()
                ->find($this->deliveryId);

            if ($delivery === null) {
                return null;
            }

            if (! in_array($delivery->status, [
                NotificationDelivery::STATUS_PENDING,
                NotificationDelivery::STATUS_RETRYING,
                NotificationDelivery::STATUS_PROCESSING,
            ], true)) {
                return null;
            }

            if ($delivery->status === NotificationDelivery::STATUS_PROCESSING) {
                if ($delivery->lease_expires_at !== null && $delivery->lease_expires_at->isFuture()) {
                    return null;
                }

                if ($delivery->channel === NotificationDelivery::CHANNEL_MAIL || $delivery->channel === NotificationDelivery::CHANNEL_PUSH) {
                    $delivery->update([
                        'status' => NotificationDelivery::STATUS_OUTCOME_UNKNOWN,
                        'outcome_unknown_at' => now(),
                        'claim_token' => null,
                        'lease_expires_at' => null,
                        'last_error' => 'provider_outcome_unknown',
                    ]);

                    $recordAuditEvent->handle('notification.delivery_outcome_unknown', [
                        'delivery_id' => $delivery->id,
                        'event_type' => $delivery->event_type,
                        'channel' => $delivery->channel,
                        'attempts' => $delivery->attempts,
                    ], 'system');

                    return null;
                }

                if (! $businessValidity->isCurrent($delivery)) {
                    $this->markSuperseded($delivery, $recordAuditEvent);

                    return null;
                }

                // The in-app row itself is the durable channel result; it is
                // safe to finish after a worker disappears.
                $delivery->update([
                    'status' => NotificationDelivery::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'claim_token' => null,
                    'lease_expires_at' => null,
                    'next_attempt_at' => null,
                    'last_error' => null,
                ]);
                $this->broadcastAttentionAfterCommit($delivery);

                $recordAuditEvent->handle('notification.delivery_delivered', [
                    'delivery_id' => $delivery->id,
                    'event_type' => $delivery->event_type,
                    'channel' => $delivery->channel,
                    'attempts' => $delivery->attempts,
                ], 'system');

                return null;
            }

            if (! $businessValidity->isCurrent($delivery)) {
                $this->markSuperseded($delivery, $recordAuditEvent);

                return null;
            }

            if (! in_array($delivery->status, [
                NotificationDelivery::STATUS_PENDING,
                NotificationDelivery::STATUS_RETRYING,
            ], true)) {
                return null;
            }

            if ($delivery->next_attempt_at !== null && $delivery->next_attempt_at->isFuture()) {
                return null;
            }

            $claimToken = (string) Str::uuid();
            $attempts = $delivery->attempts + 1;

            $delivery->update([
                'status' => NotificationDelivery::STATUS_PROCESSING,
                'attempts' => $attempts,
                'next_attempt_at' => null,
                'claim_token' => $claimToken,
                'lease_expires_at' => now()->addSeconds(self::LEASE_SECONDS),
            ]);

            $recordAuditEvent->handle('notification.delivery_processing', [
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type,
                'channel' => $delivery->channel,
                'attempts' => $attempts,
            ], 'system');

            return $claimToken;
        });
    }

    private function markDelivered(
        string $claimToken,
        ?string $providerReference,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        DB::transaction(function () use ($claimToken, $providerReference, $recordAuditEvent): void {
            $delivery = NotificationDelivery::query()
                ->whereKey($this->deliveryId)
                ->where('claim_token', $claimToken)
                ->lockForUpdate()
                ->first();

            if ($delivery === null || $delivery->status !== NotificationDelivery::STATUS_PROCESSING) {
                return;
            }

            $delivery->update([
                'status' => NotificationDelivery::STATUS_DELIVERED,
                'delivered_at' => now(),
                'next_attempt_at' => null,
                'claim_token' => null,
                'lease_expires_at' => null,
                'last_error' => null,
                'provider_reference' => $providerReference,
            ]);
            $this->broadcastAttentionAfterCommit($delivery);

            $recordAuditEvent->handle('notification.delivery_delivered', [
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type,
                'channel' => $delivery->channel,
                'attempts' => $delivery->attempts,
            ], 'system');
        });
    }

    private function markSuperseded(NotificationDelivery $delivery, RecordAuditEvent $recordAuditEvent): void
    {
        $delivery->update([
            'status' => NotificationDelivery::STATUS_SUPERSEDED,
            'claim_token' => null,
            'lease_expires_at' => null,
            'next_attempt_at' => null,
            'last_error' => null,
        ]);

        $recordAuditEvent->handle('notification.delivery_superseded', [
            'delivery_id' => $delivery->id,
            'event_type' => $delivery->event_type,
            'channel' => $delivery->channel,
            'attempts' => $delivery->attempts,
        ], 'system');
    }

    private function supersedeClaimIfStale(
        string $claimToken,
        RecordAuditEvent $recordAuditEvent,
        NotificationBusinessValidity $businessValidity,
    ): bool {
        return DB::transaction(function () use ($claimToken, $recordAuditEvent, $businessValidity): bool {
            $delivery = NotificationDelivery::query()
                ->whereKey($this->deliveryId)
                ->where('claim_token', $claimToken)
                ->where('status', NotificationDelivery::STATUS_PROCESSING)
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                return true;
            }

            if ($businessValidity->isCurrent($delivery)) {
                return false;
            }

            $this->markSuperseded($delivery, $recordAuditEvent);

            return true;
        });
    }

    private function channelFor(NotificationDelivery $delivery): DeliveryChannel
    {
        if ($delivery->recipient_type === ApplicationOpeningSubscription::class
            && $delivery->channel !== NotificationDelivery::CHANNEL_MAIL) {
            throw new \RuntimeException('application_opening_alert_mail_only');
        }

        return match ($delivery->channel) {
            NotificationDelivery::CHANNEL_IN_APP => app(InAppDeliveryChannel::class),
            NotificationDelivery::CHANNEL_MAIL => app(MailDeliveryChannel::class),
            NotificationDelivery::CHANNEL_PUSH => app(PushDeliveryChannel::class),
            default => throw new \RuntimeException("Unknown delivery channel: {$delivery->channel}"),
        };
    }

    private function handleFailure(
        string $claimToken,
        RecordAuditEvent $recordAuditEvent,
        Throwable $exception,
    ): void {
        $redacted = $this->redact($exception->getMessage());
        $outcomeUnknown = $exception instanceof NotificationMailOutcomeUnknown;

        DB::transaction(function () use ($claimToken, $recordAuditEvent, $redacted, $outcomeUnknown): void {
            $delivery = NotificationDelivery::query()
                ->whereKey($this->deliveryId)
                ->where('claim_token', $claimToken)
                ->lockForUpdate()
                ->first();

            if ($delivery === null || $delivery->status !== NotificationDelivery::STATUS_PROCESSING) {
                return;
            }
            if ($delivery->channel === NotificationDelivery::CHANNEL_MAIL && $outcomeUnknown) {
                $delivery->update([
                    'status' => NotificationDelivery::STATUS_OUTCOME_UNKNOWN,
                    'outcome_unknown_at' => now(),
                    'claim_token' => null,
                    'lease_expires_at' => null,
                    'next_attempt_at' => null,
                    'last_error' => $redacted,
                ]);
                $recordAuditEvent->handle('notification.delivery_outcome_unknown', [
                    'delivery_id' => $delivery->id,
                    'event_type' => $delivery->event_type,
                    'channel' => $delivery->channel,
                    'attempts' => $delivery->attempts,
                ], 'system');

                return;
            }

            if ($delivery->attempts >= self::MAX_ATTEMPTS) {
                $delivery->update([
                    'status' => NotificationDelivery::STATUS_FAILED_TERMINAL,
                    'claim_token' => null,
                    'lease_expires_at' => null,
                    'next_attempt_at' => null,
                    'last_error' => $redacted,
                ]);

                $recordAuditEvent->handle('notification.delivery_failed', [
                    'delivery_id' => $delivery->id,
                    'event_type' => $delivery->event_type,
                    'channel' => $delivery->channel,
                    'attempts' => $delivery->attempts,
                ], 'system');

                return;
            }

            $nextAttempt = now()->addSeconds((int) (30 * (2 ** ($delivery->attempts - 1))));

            $delivery->update([
                'status' => NotificationDelivery::STATUS_RETRYING,
                'next_attempt_at' => $nextAttempt,
                'claim_token' => null,
                'lease_expires_at' => null,
                'last_error' => $redacted,
            ]);

            $recordAuditEvent->handle('notification.delivery_retrying', [
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type,
                'channel' => $delivery->channel,
                'attempts' => $delivery->attempts,
                'next_attempt_at' => $nextAttempt->toIso8601String(),
            ], 'system');

            DB::afterCommit(fn () => self::dispatch($delivery->id)->delay($nextAttempt));
        });
    }

    private function broadcastAttentionAfterCommit(NotificationDelivery $delivery): void
    {
        if ($delivery->channel !== NotificationDelivery::CHANNEL_IN_APP) {
            return;
        }

        $userId = match ($delivery->recipient_type) {
            User::class => (int) $delivery->recipient_id,
            ApplicantIdentity::class => (int) (ApplicantAccount::query()
                ->where('applicant_identity_id', $delivery->recipient_id)
                ->value('user_id') ?? 0),
            default => 0,
        };
        if ($userId < 1) {
            return;
        }

        $domains = ['notifications'];
        if (in_array($delivery->event_type, [
            'admission.office_inquiry_message.v1',
            'admission.case_message_sent.v1',
        ], true)) {
            $domains[] = 'messaging';
        }

        $workspaceId = (string) ($delivery->payload['practice_fixture_set_id'] ?? 'operational');
        $revision = hash('sha256', $delivery->id.':'.$delivery->updated_at?->toISOString());

        DB::afterCommit(static function () use ($userId, $domains, $workspaceId, $revision): void {
            try {
                event(new UserAttentionUpdated($userId, $domains, $workspaceId, $revision));
            } catch (Throwable) {
            }
        });
    }

    private function redact(string $message): string
    {
        $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', '[email]', $message);
        $message = preg_replace('/\b[A-Z0-9]{4}-[A-Z0-9]{4}\b/i', '[secret]', (string) $message);

        return (string) $message;
    }
}
