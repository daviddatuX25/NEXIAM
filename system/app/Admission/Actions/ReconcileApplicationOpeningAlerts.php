<?php

namespace App\Admission\Actions;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Models\Admission\ApplicationOpeningAlertReceipt;
use App\Models\Admission\ApplicationOpeningAvailabilityState;
use App\Models\Admission\ApplicationOpeningOccurrence;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Foundation\Institution;
use App\Notifications\ApplicationOpeningAlertDelivery;
use App\Notifications\NotificationDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReconcileApplicationOpeningAlerts
{
    public function __construct(
        private readonly CurrentIntakeQuery $currentIntake,
        private readonly ApplicationOpeningAlertDelivery $delivery,
        private readonly ObserveApplicationOpeningAvailability $availability,
    ) {}

    public function handle(): int
    {
        if (! config('application-opening-alerts.enabled')) {
            return 0;
        }
        $processed = 0;
        Institution::query()->orderBy('id')->chunkById(100, function ($institutions) use (&$processed): void {
            foreach ($institutions as $institution) {
                $availability = $this->availability->observeInstitution((int) $institution->id);
                $period = $this->currentIntake->forInstitution($institution);
                if ($period === null || $availability === null || ! $availability->is_available) {
                    continue;
                }
                $this->restoreEligibleAfterUnannouncedOpening((int) $institution->id, (int) $availability->generation);
                $cycle = $period['cycle'];
                $window = $period['window'];
                $availabilityKey = hash('sha256', implode(':', [
                    $institution->id, $cycle->id, $window->id, $window->opens_at->toIso8601String(), $availability->generation,
                ]));
                try {
                    $occurrence = ApplicationOpeningOccurrence::query()->firstOrCreate(
                        ['availability_key' => $availabilityKey],
                        [
                            'institution_id' => $institution->id,
                            'academic_cycle_id' => $cycle->id,
                            'application_window_id' => $window->id,
                            'availability_generation' => $availability->generation,
                            'opened_at' => $availability->available_since ?? $window->opens_at,
                            'closes_at' => $window->closes_at,
                            'observed_at' => now(),
                        ],
                    );
                } catch (QueryException $exception) {
                    if (! str_contains(strtolower($exception->getMessage()), 'opening_occurrences_availability_key_unique')) {
                        throw $exception;
                    }
                    $occurrence = ApplicationOpeningOccurrence::query()->where('availability_key', $availabilityKey)->firstOrFail();
                }
                if (! $occurrence->fanout_complete) {
                    $processed += $this->fanout($occurrence->id);
                }
                $this->reissueStaleDeadlineDeliveries($occurrence, $cycle, $window, $institution);
            }
        });

        return $processed;
    }

    private function fanout(int $occurrenceId): int
    {
        $chunkSize = max(1, (int) config('application-opening-alerts.fanout_chunk_size', 100));
        $processed = 0;

        do {
            $count = DB::transaction(function () use ($occurrenceId, $chunkSize): int {
                $occurrence = ApplicationOpeningOccurrence::query()->lockForUpdate()->findOrFail($occurrenceId);
                if ($occurrence->fanout_complete) {
                    return 0;
                }
                $institution = Institution::query()->findOrFail($occurrence->institution_id);
                $availability = ApplicationOpeningAvailabilityState::query()->whereKey($institution->id)->first();
                $period = $this->currentIntake->forInstitution($institution);
                if ($period === null
                    || ! $availability?->is_available
                    || (int) $availability->generation !== (int) $occurrence->availability_generation
                    || (int) $period['cycle']->id !== (int) $occurrence->academic_cycle_id
                    || (int) $period['window']->id !== (int) $occurrence->application_window_id) {
                    $occurrence->forceFill(['fanout_complete' => true])->save();

                    return 0;
                }

                $subscriptions = ApplicationOpeningSubscription::query()
                    ->where('institution_id', $occurrence->institution_id)
                    ->where('state', ApplicationOpeningSubscription::STATE_VERIFIED)
                    ->whereNotNull('verified_at')->where('verified_at', '<=', $occurrence->opened_at)
                    ->whereNull('unsubscribed_at')->whereNull('consumed_at')->whereNull('address_erased_at')
                    ->where('retention_hold', false)->where('expires_at', '>', now())
                    ->where('id', '>', $occurrence->last_subscription_id)
                    ->orderBy('id')->limit($chunkSize)->lockForUpdate()->get();

                if ($subscriptions->isEmpty()) {
                    $occurrence->forceFill(['fanout_complete' => true])->save();

                    return 0;
                }

                foreach ($subscriptions as $subscription) {
                    $receipt = ApplicationOpeningAlertReceipt::query()->firstOrCreate([
                        'subscription_id' => $subscription->id,
                        'generation' => $subscription->generation,
                        'occurrence_id' => $occurrence->id,
                    ], [
                        'status' => 'claimed',
                        'claimed_at' => now(),
                    ]);
                    if ($receipt->wasRecentlyCreated) {
                        $unsubscribeToken = $this->issueToken($subscription, 'unsubscribe', now()->addMonths((int) config('application-opening-alerts.verified_retention_months', 12)));
                        $prefillToken = $this->issueToken($subscription, 'prefill', now()->addMinutes((int) config('application-opening-alerts.token_handoff_minutes', 15)));
                        $subscription->forceFill([
                            'state' => ApplicationOpeningSubscription::STATE_CONSUMED,
                            'consumed_at' => now(),
                        ])->save();
                        $delivery = $this->delivery->openingAfterCommit(
                            $subscription,
                            $occurrence,
                            $period['cycle'],
                            $period['window'],
                            $institution,
                            $unsubscribeToken,
                            $prefillToken,
                        );
                        if ($delivery !== null) {
                            $receipt->forceFill(['delivery_id' => $delivery->id])->save();
                        } else {
                            $receipt->forceFill(['status' => 'suppressed'])->save();
                        }
                    }
                    $occurrence->forceFill(['last_subscription_id' => $subscription->id])->save();
                }

                return $subscriptions->count();
            });
            $processed += $count;
        } while ($count === $chunkSize);

        return $processed;
    }

    private function restoreEligibleAfterUnannouncedOpening(int $institutionId, int $generation): void
    {
        $receipts = ApplicationOpeningAlertReceipt::query()
            ->where('status', 'claimed')
            ->whereHas('occurrence', fn ($query) => $query->where('institution_id', $institutionId)->where('availability_generation', '<', $generation))
            ->whereHas('delivery', fn ($query) => $query->where('status', NotificationDelivery::STATUS_SUPERSEDED)
                ->whereNull('delivered_at')->whereNull('outcome_unknown_at'))
            ->with(['subscription', 'delivery'])->orderBy('id')->lazyById(100);

        foreach ($receipts as $receipt) {
            DB::transaction(function () use ($receipt): void {
                $lockedReceipt = ApplicationOpeningAlertReceipt::query()->lockForUpdate()->find($receipt->id);
                $subscription = $lockedReceipt === null
                    ? null
                    : ApplicationOpeningSubscription::query()->lockForUpdate()->find($lockedReceipt->subscription_id);
                $delivery = $lockedReceipt?->delivery_id === null ? null : NotificationDelivery::query()->find($lockedReceipt->delivery_id);
                if ($lockedReceipt === null || $subscription === null || $delivery === null
                    || $lockedReceipt->status !== 'claimed'
                    || $delivery->status !== NotificationDelivery::STATUS_SUPERSEDED
                    || $delivery->delivered_at !== null || $delivery->outcome_unknown_at !== null
                    || (int) $lockedReceipt->generation !== (int) $subscription->generation
                    || $subscription->state !== ApplicationOpeningSubscription::STATE_CONSUMED
                    || $subscription->unsubscribed_at !== null || $subscription->address_erased_at !== null
                    || $subscription->retention_hold || $subscription->email === null
                    || $subscription->expires_at?->isPast()) {
                    return;
                }
                $subscription->forceFill([
                    'state' => ApplicationOpeningSubscription::STATE_VERIFIED,
                    'consumed_at' => null,
                ])->save();
                $lockedReceipt->forceFill(['status' => 'superseded'])->save();
            });
        }
    }

    private function reissueStaleDeadlineDeliveries(
        ApplicationOpeningOccurrence $occurrence,
        $cycle,
        $window,
        Institution $institution,
    ): void {
        $availability = ApplicationOpeningAvailabilityState::query()->whereKey($institution->id)->first();
        if ($availability === null || ! $availability->is_available
            || (int) $availability->generation !== (int) $occurrence->availability_generation) {
            return;
        }
        $receipts = $occurrence->receipts()->where('status', 'claimed')
            ->whereHas('delivery', fn ($query) => $query->where('status', NotificationDelivery::STATUS_SUPERSEDED)
                ->whereNull('delivered_at')->whereNull('outcome_unknown_at'))
            ->with(['subscription', 'delivery'])->orderBy('id')->lazyById(100);
        foreach ($receipts as $receipt) {
            DB::transaction(function () use ($receipt, $occurrence, $cycle, $window, $institution): void {
                $lockedReceipt = ApplicationOpeningAlertReceipt::query()->lockForUpdate()->find($receipt->id);
                $subscription = $lockedReceipt === null
                    ? null
                    : ApplicationOpeningSubscription::query()->lockForUpdate()->find($lockedReceipt->subscription_id);
                $previous = $lockedReceipt?->delivery_id === null ? null : NotificationDelivery::query()->find($lockedReceipt->delivery_id);
                $oldClose = $previous?->payload['event_resource']['closes_at'] ?? null;
                if ($lockedReceipt === null || $subscription === null || $previous === null
                    || $lockedReceipt->status !== 'claimed'
                    || $previous->status !== NotificationDelivery::STATUS_SUPERSEDED
                    || $previous->delivered_at !== null || $previous->outcome_unknown_at !== null
                    || (int) $lockedReceipt->generation !== (int) $subscription->generation
                    || ! is_string($oldClose) || $oldClose === $window->closes_at->toIso8601String()
                    || $subscription->state !== ApplicationOpeningSubscription::STATE_CONSUMED
                    || $subscription->unsubscribed_at !== null || $subscription->address_erased_at !== null
                    || $subscription->retention_hold || $subscription->email === null
                    || $subscription->expires_at?->isPast()) {
                    return;
                }
                $handoff = $previous->payload['handoff'] ?? [];
                ApplicationOpeningToken::query()->whereIn('id', array_values(array_filter([
                    $handoff['unsubscribe_token_id'] ?? null,
                    $handoff['prefill_token_id'] ?? null,
                ])))->whereNull('consumed_at')->update([
                    'superseded_at' => now(),
                    'encrypted_token' => null,
                    'updated_at' => now(),
                ]);
                $unsubscribeToken = $this->issueToken($subscription, 'unsubscribe', now()->addMonths((int) config('application-opening-alerts.verified_retention_months', 12)));
                $prefillToken = $this->issueToken($subscription, 'prefill', now()->addMinutes((int) config('application-opening-alerts.token_handoff_minutes', 15)));
                $newDelivery = $this->delivery->openingAfterCommit(
                    $subscription,
                    $occurrence,
                    $cycle,
                    $window,
                    $institution,
                    $unsubscribeToken,
                    $prefillToken,
                );
                if ($newDelivery !== null) {
                    $lockedReceipt->forceFill(['delivery_id' => $newDelivery->id])->save();
                }
            });
        }
    }

    private function issueToken(ApplicationOpeningSubscription $subscription, string $purpose, $expiresAt): ApplicationOpeningToken
    {
        $plain = Str::random(64);

        return ApplicationOpeningToken::query()->create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'generation' => $subscription->generation,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $plain),
            'encrypted_token' => $plain,
            'expires_at' => $expiresAt,
        ]);
    }
}
