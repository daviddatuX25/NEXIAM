<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Support\StaffAuthenticationProof;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BeginStaffAuthentication
{
    public function handle(StaffAuthenticationProof $proof, Request $request): StaffAuthenticationAttempt
    {
        $attempt = DB::transaction(function () use ($proof, $request): StaffAuthenticationAttempt {
            $user = User::query()->whereKey($proof->userId)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();

            if (
                $proof->source !== 'password'
                || $proof->purpose !== 'login'
                || $state === null
                || ! $profile?->isActive()
                || ! $state->hasConfirmedTwoFactor()
                || $state->two_factor_secret === null
                || $state->two_factor_generation === null
                || (int) $state->session_version !== $proof->sessionVersion
                || ! hash_equals((string) $state->two_factor_generation, $proof->factorGeneration)
                || $state->recovery_generation !== $proof->recoveryGeneration
            ) {
                throw ValidationException::withMessages(['email' => trans('auth.failed')]);
            }

            return StaffAuthenticationAttempt::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'purpose' => 'login',
                'primary_method' => 'password',
                'phase' => 'mfa_required',
                'session_binding_digest' => hash('sha256', $request->session()->getId()),
                'expected_session_version' => $proof->sessionVersion,
                'expected_factor_generation' => $proof->factorGeneration,
                'expected_recovery_generation' => $proof->recoveryGeneration,
                'primary_verified_at' => now(),
                'expires_at' => now()->addMinutes(5),
                'remember_requested' => $request->boolean('remember'),
                'intended_path' => $request->session()->get('_previous.url'),
            ]);
        });

        $request->session()->put('staff.pending_attempt_id', $attempt->id);

        return $attempt;
    }
}
