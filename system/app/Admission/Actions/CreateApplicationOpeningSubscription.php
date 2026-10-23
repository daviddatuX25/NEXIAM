<?php

namespace App\Admission\Actions;

use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Foundation\Institution;
use App\Notifications\ApplicationOpeningAlertDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateApplicationOpeningSubscription
{
    public function __construct(private readonly ApplicationOpeningAlertDelivery $delivery) {}

    /** @return 'confirmation_sent'|'already_pending' */
    public function handle(Institution $institution, string $email): string
    {
        $email = trim($email);
        $normalized = mb_strtolower($email, 'UTF-8');
        $now = now();
        $subscription = null;
        $token = null;

        try {
            DB::transaction(function () use ($institution, $email, $normalized, $now, &$subscription, &$token): void {
                $subscription = ApplicationOpeningSubscription::query()
                    ->where('institution_id', $institution->id)
                    ->where('normalized_email', $normalized)
                    ->lockForUpdate()
                    ->first();

                if ($subscription !== null) {
                    if ($subscription->state === ApplicationOpeningSubscription::STATE_UNSUBSCRIBED
                        || $subscription->state === ApplicationOpeningSubscription::STATE_CONSUMED
                        || $subscription->state === ApplicationOpeningSubscription::STATE_EXPIRED) {
                        $subscription->forceFill([
                            'email' => $email,
                            'normalized_email' => $normalized,
                            'state' => ApplicationOpeningSubscription::STATE_PENDING,
                            'generation' => (int) $subscription->generation + 1,
                            'consented_at' => $now,
                            'consent_version' => 'opening-alerts-v1',
                            'verified_at' => null,
                            'unsubscribed_at' => null,
                            'consumed_at' => null,
                            'expires_at' => $now->copy()->addMonths((int) config('application-opening-alerts.verified_retention_months', 12)),
                            'address_erased_at' => null,
                            'last_verification_sent_at' => null,
                        ])->save();
                    } elseif ($subscription->state !== ApplicationOpeningSubscription::STATE_PENDING
                        || ($subscription->last_verification_sent_at?->gt($now->copy()->subSeconds((int) config('application-opening-alerts.resend_cooldown_seconds', 60))) ?? false)) {
                        return;
                    }
                } else {
                    $subscription = ApplicationOpeningSubscription::query()->create([
                        'institution_id' => $institution->id,
                        'email' => $email,
                        'normalized_email' => $normalized,
                        'state' => ApplicationOpeningSubscription::STATE_PENDING,
                        'generation' => 1,
                        'consented_at' => $now,
                        'consent_version' => 'opening-alerts-v1',
                        'expires_at' => $now->copy()->addMonths((int) config('application-opening-alerts.verified_retention_months', 12)),
                    ]);
                }

                $subscription->tokens()->whereNull('consumed_at')->whereNull('superseded_at')->update([
                    'superseded_at' => $now,
                    'encrypted_token' => null,
                    'updated_at' => $now,
                ]);
                $rawToken = Str::random(64);
                $token = ApplicationOpeningToken::query()->create([
                    'id' => (string) Str::uuid(),
                    'subscription_id' => $subscription->id,
                    'generation' => $subscription->generation,
                    'purpose' => 'verify',
                    'token_hash' => hash('sha256', $rawToken),
                    'encrypted_token' => $rawToken,
                    'expires_at' => $now->copy()->addHours((int) config('application-opening-alerts.verification_token_hours', 24)),
                ]);
                $subscription->forceFill(['last_verification_sent_at' => $now])->save();
                $this->delivery->verificationAfterCommit($subscription, $token, $institution);
            });
        } catch (QueryException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'opening_subs_institution_email_unique')) {
                throw $exception;
            }
        }

        return $token === null ? 'already_pending' : 'confirmation_sent';
    }
}
