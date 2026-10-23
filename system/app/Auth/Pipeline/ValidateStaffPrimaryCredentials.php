<?php

declare(strict_types=1);

namespace App\Auth\Pipeline;

use App\Auth\Support\StaffAuthenticationProof;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidateStaffPrimaryCredentials
{
    /**
     * @param  Closure(Request): (Request|Response)  $next
     */
    public function __invoke(Request $request, Closure $next): Request|Response
    {
        $verified = DB::transaction(function () use ($request): ?array {
            $user = User::query()
                ->where('email', $request->input('email'))
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return null;
            }

            $state = StaffAuthenticationState::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();

            if (
                $state === null
                || ! $profile?->isActive()
                || ! $state->isOnboarded()
                || $state->password_set_at === null
                || $state->must_change_password
                || ! Hash::check((string) $request->input('password'), $user->password)
            ) {
                return null;
            }

            if (
                ($state->hasConfirmedTwoFactor() && $state->two_factor_secret === null)
                || (! $state->hasConfirmedTwoFactor() && $state->two_factor_secret !== null)
            ) {
                return null;
            }

            if ($state->two_factor_generation === null) {
                $state->forceFill(['two_factor_generation' => (string) Str::uuid()])->save();
            }

            return [
                'user' => $user,
                'proof' => new StaffAuthenticationProof(
                    userId: (int) $user->id,
                    sessionVersion: (int) $state->session_version,
                    factorGeneration: (string) $state->two_factor_generation,
                    recoveryGeneration: $state->recovery_generation,
                    purpose: 'login',
                    primaryMethod: 'password',
                    source: 'password',
                ),
            ];
        });

        if ($verified === null) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->attributes->set('auth.candidate', $verified['user']);
        $request->attributes->set('auth.staff_primary_proof', $verified['proof']);

        return $next($request);
    }
}
