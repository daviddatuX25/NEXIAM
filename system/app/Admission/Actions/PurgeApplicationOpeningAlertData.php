<?php

namespace App\Admission\Actions;

use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Notifications\NotificationDelivery;
use Illuminate\Support\Facades\DB;

final class PurgeApplicationOpeningAlertData
{
    public function handle(): int
    {
        if (! config('application-opening-alerts.enabled')) {
            return 0;
        }
        $changed = 0;
        ApplicationOpeningSubscription::query()
            ->where('state', ApplicationOpeningSubscription::STATE_PENDING)
            ->where('consented_at', '<=', now()->subDays((int) config('application-opening-alerts.unverified_retention_days', 7)))
            ->where('retention_hold', false)
            ->orderBy('id')->chunkById(100, function ($subscriptions) use (&$changed): void {
                foreach ($subscriptions as $subscription) {
                    $changed += $this->eraseAddress($subscription, true);
                }
            });

        ApplicationOpeningSubscription::query()
            ->where('state', ApplicationOpeningSubscription::STATE_VERIFIED)
            ->where('expires_at', '<=', now())
            ->where('retention_hold', false)
            ->orderBy('id')->chunkById(100, function ($subscriptions) use (&$changed): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription): void {
                        $locked = ApplicationOpeningSubscription::query()->lockForUpdate()->find($subscription->id);
                        if ($locked === null || $locked->state !== ApplicationOpeningSubscription::STATE_VERIFIED || $locked->retention_hold) {
                            return;
                        }
                        $locked->forceFill(['state' => ApplicationOpeningSubscription::STATE_EXPIRED])->save();
                        $this->cancelPendingDeliveries($locked);
                    });
                    $changed++;
                }
            });

        ApplicationOpeningSubscription::query()
            ->whereIn('state', [ApplicationOpeningSubscription::STATE_CONSUMED, ApplicationOpeningSubscription::STATE_UNSUBSCRIBED, ApplicationOpeningSubscription::STATE_EXPIRED])
            ->whereNull('address_erased_at')
            ->where('retention_hold', false)
            ->where('updated_at', '<=', now()->subDays((int) config('application-opening-alerts.terminal_address_erasure_days', 90)))
            ->orderBy('id')->chunkById(100, function ($subscriptions) use (&$changed): void {
                foreach ($subscriptions as $subscription) {
                    $changed += $this->eraseAddress($subscription, false);
                }
            });

        ApplicationOpeningToken::query()->whereNotNull('encrypted_token')
            ->where(function ($query): void {
                $query->whereNotNull('consumed_at')->orWhereNotNull('superseded_at')->orWhere('expires_at', '<=', now());
            })->update(['encrypted_token' => null, 'updated_at' => now()]);

        return $changed;
    }

    private function eraseAddress(ApplicationOpeningSubscription $subscription, bool $expirePending): int
    {
        return DB::transaction(function () use ($subscription, $expirePending): int {
            $locked = ApplicationOpeningSubscription::query()->lockForUpdate()->find($subscription->id);
            if ($locked === null || $locked->retention_hold || $locked->address_erased_at !== null) {
                return 0;
            }
            if ($expirePending) {
                $expiresAt = now()->subDays((int) config('application-opening-alerts.unverified_retention_days', 7));
                if ($locked->state !== ApplicationOpeningSubscription::STATE_PENDING || $locked->consented_at?->gt($expiresAt)) {
                    return 0;
                }
            } else {
                $terminalStates = [
                    ApplicationOpeningSubscription::STATE_CONSUMED,
                    ApplicationOpeningSubscription::STATE_UNSUBSCRIBED,
                    ApplicationOpeningSubscription::STATE_EXPIRED,
                ];
                $eraseAfter = now()->subDays((int) config('application-opening-alerts.terminal_address_erasure_days', 90));
                if (! in_array($locked->state, $terminalStates, true) || $locked->updated_at?->gt($eraseAfter)) {
                    return 0;
                }
            }
            if ($expirePending) {
                $locked->forceFill(['state' => ApplicationOpeningSubscription::STATE_EXPIRED])->save();
                $this->cancelPendingDeliveries($locked);
            }
            $locked->forceFill([
                'email' => null,
                'normalized_email' => null,
                'address_erased_at' => now(),
            ])->save();
            $locked->tokens()->whereNotNull('encrypted_token')->update(['encrypted_token' => null, 'updated_at' => now()]);
            $locked->receipts()->whereNull('address_erased_at')->update([
                'address_erased_at' => now(),
                'updated_at' => now(),
            ]);
            $this->redactDeliveries($locked);

            return 1;
        });
    }

    private function cancelPendingDeliveries(ApplicationOpeningSubscription $subscription): void
    {
        $deliveryIds = $subscription->receipts()->whereNotNull('delivery_id')->pluck('delivery_id');
        NotificationDelivery::query()->where(function ($query) use ($subscription, $deliveryIds): void {
            $query->whereIn('id', $deliveryIds)
                ->orWhere(function ($verification) use ($subscription): void {
                    $verification->where('recipient_type', ApplicationOpeningSubscription::class)
                        ->where('recipient_id', $subscription->id)
                        ->where('event_type', 'application.opening_alert.verify.v1');
                });
        })->whereIn('status', [NotificationDelivery::STATUS_PENDING, NotificationDelivery::STATUS_RETRYING])
            ->update(['status' => NotificationDelivery::STATUS_SUPERSEDED, 'last_error' => 'subscription_no_longer_eligible', 'updated_at' => now()]);
    }

    private function redactDeliveries(ApplicationOpeningSubscription $subscription): void
    {
        $ids = $subscription->receipts()->whereNotNull('delivery_id')->pluck('delivery_id');
        NotificationDelivery::query()->whereIn('id', $ids)->orderBy('id')->chunkById(100, function ($deliveries): void {
            foreach ($deliveries as $delivery) {
                $payload = $delivery->payload;
                unset($payload['recipient']['email'], $payload['recipient']['address']);
                $payload['address_erased'] = true;
                $delivery->forceFill([
                    'payload' => $payload,
                    'last_error' => null,
                    'redaction_class' => 'application_opening_address_erased',
                ])->save();
            }
        });
    }
}
