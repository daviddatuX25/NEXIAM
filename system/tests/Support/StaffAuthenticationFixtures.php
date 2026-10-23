<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffSessionLease;
use App\Models\User;
use Illuminate\Support\Str;
use LogicException;

trait StaffAuthenticationFixtures
{
    /** Stamp a genuine confirmed factor and one initial simulated lease for an explicit staff test fixture. */
    protected function establishStaffAuthenticationFixture(User $user, string $sessionId, string $totpSecret = 'JBSWY3DPEHPK3PXP'): StaffSessionLease
    {
        $state = StaffAuthenticationState::query()->firstOrNew(['user_id' => $user->getKey()]);
        if ($state->exists && $state->hasConfirmedTwoFactor()) {
            throw new LogicException('Staff fixture MFA is already confirmed; refusing to restamp authentication state.');
        }
        if (StaffSessionLease::query()->where('user_id', $user->getKey())->where('session_id_hash', hash_hmac('sha256', $sessionId, (string) config('app.key')))->exists()) {
            throw new LogicException('Staff fixture lease already exists; refusing to restamp authentication state.');
        }

        $state->forceFill([
            'user_id' => $user->getKey(),
            'activation_completed_at' => $state->activation_completed_at ?? now(),
            'password_set_at' => $state->password_set_at ?? now(),
            'must_change_password' => false,
            'session_version' => $state->session_version ?? 1,
            'two_factor_secret' => $totpSecret,
            'two_factor_confirmed_at' => now(),
            'two_factor_generation' => $state->two_factor_generation ?? (string) Str::uuid(),
        ])->save();

        $now = now();
        $generation = (string) Str::uuid();

        return StaffSessionLease::query()->create([
            'user_id' => $user->getKey(),
            'session_id_hash' => hash_hmac('sha256', $sessionId, (string) config('app.key')),
            'inactivity_generation' => $generation,
            'authenticated_at' => $now,
            'idle_expires_at' => $now->copy()->addMinutes(15),
            'absolute_expires_at' => $now->copy()->addHours(8),
            'effective_idle_minutes' => 15,
        ]);
    }
}
