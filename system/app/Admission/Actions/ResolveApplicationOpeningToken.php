<?php

namespace App\Admission\Actions;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Foundation\Institution;
use Illuminate\Support\Facades\DB;

final class ResolveApplicationOpeningToken
{
    public function __construct(private readonly CurrentIntakeQuery $currentIntake) {}

    public function resolve(string $plainToken, string $purpose, Institution $institution): string
    {
        $token = ApplicationOpeningToken::query()->with('subscription')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->whereNull('superseded_at')
            ->where('expires_at', '>', now())
            ->first();
        if ($token === null || (int) $token->subscription?->institution_id !== (int) $institution->id
            || (int) $token->generation !== (int) $token->subscription?->generation) {
            return 'invalid';
        }

        if ($purpose === 'verify') {
            if ($token->subscription->state === ApplicationOpeningSubscription::STATE_VERIFIED) {
                return $this->currentIntake->forInstitution($institution) === null ? 'confirmed' : 'open';
            }

            return $token->subscription->state === ApplicationOpeningSubscription::STATE_PENDING ? 'verify' : 'invalid';
        }
        if ($purpose === 'unsubscribe') {
            return in_array($token->subscription->state, [
                ApplicationOpeningSubscription::STATE_VERIFIED,
                ApplicationOpeningSubscription::STATE_CONSUMED,
            ], true) ? 'unsubscribe' : 'invalid';
        }

        return 'invalid';
    }

    public function confirm(string $plainToken, string $purpose, Institution $institution): string
    {
        return DB::transaction(function () use ($plainToken, $purpose, $institution): string {
            $candidate = ApplicationOpeningToken::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->whereNull('superseded_at')
                ->where('expires_at', '>', now())
                ->first();
            if ($candidate === null) {
                return 'invalid';
            }
            $subscription = ApplicationOpeningSubscription::query()->whereKey($candidate->subscription_id)->lockForUpdate()->first();
            if ($subscription === null || (int) $subscription->institution_id !== (int) $institution->id
                || (int) $candidate->generation !== (int) $subscription->generation) {
                return 'invalid';
            }
            $token = ApplicationOpeningToken::query()->whereKey($candidate->id)
                ->where('token_hash', hash('sha256', $plainToken))
                ->where('purpose', $purpose)
                ->where('generation', $subscription->generation)
                ->whereNull('consumed_at')->whereNull('superseded_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()->first();
            if ($token === null) {
                return 'invalid';
            }

            $token->forceFill(['consumed_at' => now(), 'encrypted_token' => null])->save();
            if ($purpose === 'verify' && $subscription->state === ApplicationOpeningSubscription::STATE_PENDING) {
                $isOpen = $this->currentIntake->forInstitution($institution) !== null;
                $subscription->forceFill([
                    'state' => $isOpen ? ApplicationOpeningSubscription::STATE_CONSUMED : ApplicationOpeningSubscription::STATE_VERIFIED,
                    'verified_at' => now(),
                    'consumed_at' => $isOpen ? now() : null,
                    'expires_at' => now()->addMonths((int) config('application-opening-alerts.verified_retention_months', 12)),
                ])->save();

                return $isOpen ? 'open' : 'confirmed';
            }
            if ($purpose === 'unsubscribe' && in_array($subscription->state, [
                ApplicationOpeningSubscription::STATE_VERIFIED,
                ApplicationOpeningSubscription::STATE_CONSUMED,
            ], true)) {
                $subscription->forceFill([
                    'state' => ApplicationOpeningSubscription::STATE_UNSUBSCRIBED,
                    'unsubscribed_at' => now(),
                ])->save();
                $subscription->receipts()->whereNull('consumed_at')->whereHas('delivery', fn ($query) => $query->whereIn('status', ['pending', 'retrying']))
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);

                return 'unsubscribed';
            }

            return 'invalid';
        });
    }
}
