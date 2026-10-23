<?php

declare(strict_types=1);

namespace App\Auth\Pipeline;

use App\Auth\Actions\BeginStaffAuthentication;
use App\Auth\Actions\CompleteStaffAuthentication;
use App\Auth\Support\StaffAuthenticationProof;
use App\Auth\Support\StaffMfaPolicy;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfStaffTwoFactorAuthenticatable
{
    public function __construct(
        private readonly BeginStaffAuthentication $beginStaffAuthentication,
        private readonly CompleteStaffAuthentication $completeStaffAuthentication,
        private readonly StaffMfaPolicy $mfaPolicy,
    ) {}

    /**
     * @param  Closure(Request): (Request|Response)  $next
     */
    public function __invoke(Request $request, Closure $next): Request|Response
    {
        /** @var User $user */
        $user = $request->attributes->get('auth.candidate');
        /** @var StaffAuthenticationProof $proof */
        $proof = $request->attributes->get('auth.staff_primary_proof');

        $state = StaffAuthenticationState::query()->where('user_id', $user->id)->first();
        if ($state?->hasConfirmedTwoFactor()) {
            $this->beginStaffAuthentication->handle($proof, $request);

            return redirect()->route('two-factor.login');
        }

        if ($this->mfaPolicy->isMfaRequired($user)) {
            $attempt = DB::transaction(function () use ($proof, $request): StaffAuthenticationAttempt {
                $lockedUser = User::query()->whereKey($proof->userId)->lockForUpdate()->firstOrFail();
                $lockedState = StaffAuthenticationState::query()
                    ->where('user_id', $lockedUser->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $profile = $lockedUser->staffProfile()->lockForUpdate()->first();

                if (
                    $proof->source !== 'password'
                    || $proof->purpose !== 'login'
                    || ! $profile?->isActive()
                    || $lockedState->hasConfirmedTwoFactor()
                    || (int) $lockedState->session_version !== $proof->sessionVersion
                    || ! hash_equals((string) $lockedState->two_factor_generation, $proof->factorGeneration)
                    || $lockedState->recovery_generation !== $proof->recoveryGeneration
                ) {
                    throw ValidationException::withMessages(['email' => trans('auth.failed')]);
                }

                return StaffAuthenticationAttempt::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $lockedUser->id,
                    'purpose' => 'mfa_enrollment',
                    'primary_method' => 'password',
                    'phase' => 'mfa_required',
                    'session_binding_digest' => hash('sha256', $request->session()->getId()),
                    'expected_session_version' => $proof->sessionVersion,
                    'expected_factor_generation' => $proof->factorGeneration,
                    'expected_recovery_generation' => $proof->recoveryGeneration,
                    'primary_verified_at' => now(),
                    'expires_at' => now()->addMinutes(10),
                ]);
            });

            $request->session()->put('staff.pending_attempt_id', $attempt->id);

            return redirect('/staff/account-setup');
        }

        return $this->completeStaffAuthentication->handle(
            $proof,
            $request,
            $request->boolean('remember'),
        );
    }
}
