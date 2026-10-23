<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Support\Str;

class InvalidateStaffAuthentication
{
    public function handle(
        User $user,
        StaffAuthenticationState $state,
        ?string $continuationAttemptId = null,
        bool $factorChanged = false,
    ): StaffAuthenticationState {
        $nextSessionVersion = (int) $state->session_version + 1;
        $nextFactorGeneration = $factorChanged ? (string) Str::uuid() : $state->two_factor_generation;

        $state->forceFill([
            'session_version' => $nextSessionVersion,
            'two_factor_generation' => $nextFactorGeneration,
            'pending_two_factor_secret' => null,
            'pending_two_factor_expires_at' => null,
            'pending_enrollment_attempt_id' => null,
        ])->save();

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        StaffAuthenticationAttempt::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->whereNull('revoked_at')
            ->when($continuationAttemptId !== null, fn ($query) => $query->where('id', '!=', $continuationAttemptId))
            ->update(['revoked_at' => now()]);
        StaffActivationVoucher::query()
            ->where('user_id', $user->id)
            ->whereNull('redeemed_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => 'authentication_state_changed',
            ]);

        if ($continuationAttemptId !== null) {
            $attempt = StaffAuthenticationAttempt::query()
                ->whereKey($continuationAttemptId)
                ->where('user_id', $user->id)
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->firstOrFail();

            $attempt->forceFill([
                'expected_session_version' => $nextSessionVersion,
                'expected_factor_generation' => $nextFactorGeneration,
                'expected_recovery_generation' => $state->recovery_generation,
            ])->save();
        }

        return $state;
    }
}
