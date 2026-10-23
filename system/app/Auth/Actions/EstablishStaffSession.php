<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Support\StaffAuthenticationProof;
use App\Auth\Support\StaffMfaPolicy;
use App\Auth\Support\StaffRememberToken;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use Illuminate\Auth\Recaller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EstablishStaffSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly StaffMfaPolicy $mfaPolicy,
        private readonly StaffRememberToken $rememberToken,
        private readonly EstablishStaffSessionLease $establishLease,
        private readonly StartStaffPresenceSession $startPresenceSession,
    ) {}

    public function handle(StaffAuthenticationProof $proof, Request $request, bool $remember = false): void
    {
        $guard = Auth::guard('web');
        if ($proof->source === 'remembered') {
            $cookieValue = $request->cookie($guard->getRecallerName());
            $recaller = is_string($cookieValue) ? new Recaller($cookieValue) : null;
            if (! $guard->viaRemember() || $recaller === null || ! $recaller->valid()) {
                throw ValidationException::withMessages(['email' => trans('auth.failed')]);
            }
        }
        $interactiveAuthenticatedAt = $proof->interactiveAuthenticatedAt ?? now();
        if (now()->greaterThanOrEqualTo($interactiveAuthenticatedAt->copy()->addHours(8))) {
            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }
        $user = DB::transaction(function () use ($proof, $request, $remember, $guard, &$interactiveAuthenticatedAt): User {
            $user = User::query()->whereKey($proof->userId)->lockForUpdate()->firstOrFail();
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
                || (int) $state->session_version !== $proof->sessionVersion
                || $state->two_factor_generation === null
                || ! hash_equals((string) $state->two_factor_generation, $proof->factorGeneration)
                || $state->recovery_generation !== $proof->recoveryGeneration
                || ($state->hasConfirmedTwoFactor() && $state->two_factor_secret === null)
                || (! $state->hasConfirmedTwoFactor() && $state->two_factor_secret !== null)
                || ($this->mfaPolicy->isMfaRequired($user) && ! $state->hasConfirmedTwoFactor())
            ) {
                throw ValidationException::withMessages(['email' => trans('auth.failed')]);
            }

            if ($proof->providerSubject !== null) {
                $identity = StaffProviderIdentity::query()
                    ->where('user_id', $user->id)
                    ->where('provider', 'google')
                    ->where('subject', $proof->providerSubject)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();

                if ($identity === null) {
                    throw ValidationException::withMessages(['email' => trans('auth.failed')]);
                }
            }

            if (! $this->proofIsCurrent($proof, $user, $state, $request, $remember)) {
                throw ValidationException::withMessages(['email' => trans('auth.failed')]);
            }

            if ($proof->source === 'remembered') {
                $cookieValue = $request->cookie($guard->getRecallerName());
                $recaller = is_string($cookieValue) ? new Recaller($cookieValue) : null;
                $claims = $this->rememberToken->parse((string) ($recaller?->token() ?? ''));
                $expectedPasswordHash = $guard->hashPasswordForCookie($user->getAuthPassword());

                if (
                    ! $guard->viaRemember()
                    || $recaller === null
                    || ! $recaller->valid()
                    || (string) $recaller->id() !== (string) $user->getAuthIdentifier()
                    || ! hash_equals((string) $user->getRememberToken(), $recaller->token())
                    || ! hash_equals($recaller->hash(), $expectedPasswordHash)
                    || $claims === null
                    || $claims['session_version'] !== $proof->sessionVersion
                    || ! hash_equals($claims['factor_generation'], $proof->factorGeneration)
                    || $claims['interactive_authenticated_at']->timestamp !== $proof->interactiveAuthenticatedAt?->timestamp
                    || now()->greaterThanOrEqualTo($claims['interactive_authenticated_at']->copy()->addHours(8))
                ) {
                    throw ValidationException::withMessages(['email' => trans('auth.failed')]);
                }
            }

            if ($remember) {
                $currentClaims = $this->rememberToken->parse((string) $user->getRememberToken());
                $alreadyBound = $currentClaims !== null
                    && $currentClaims['session_version'] === $proof->sessionVersion
                    && hash_equals($currentClaims['factor_generation'], $proof->factorGeneration)
                    && now()->lt($currentClaims['interactive_authenticated_at']->copy()->addHours(8));

                if ($alreadyBound) {
                    $interactiveAuthenticatedAt = $currentClaims['interactive_authenticated_at'];
                } else {
                    $interactiveAuthenticatedAt = now();
                    $user->forceFill(['remember_token' => Str::random(60)])->save();
                    $user->forceFill([
                        'remember_token' => $this->rememberToken->issue(
                            $proof->sessionVersion,
                            $proof->factorGeneration,
                            $interactiveAuthenticatedAt,
                        ),
                    ])->save();
                }
            }

            $this->recordAuditEvent->handle(
                $proof->source === 'remembered' ? 'auth.staff_remembered_login' : 'auth.staff_login',
                [
                    'institution_id' => $user->actorScope()->institutionId,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'office' => $user->actorScope()->office,
                    'source' => $proof->source,
                ],
                (string) $user->id,
            );
            if ($proof->primaryMethod === 'google') {
                $this->recordAuditEvent->handle('auth.staff_google_login', [
                    'institution_id' => $user->actorScope()->institutionId,
                    'user_id' => $user->id,
                    'provider' => 'google',
                ], (string) $user->id);
            }

            return $user->fresh();
        });

        $guard->login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget([
            'staff.pending_attempt_id',
            'staff.pending_reenrollment_attempt_id',
        ]);
        $request->session()->put('auth.surface', 'staff');
        $request->session()->put('auth.session_version', $proof->sessionVersion);
        $lease = $this->establishLease->handle(
            $user,
            $request->session()->getId(),
            now(),
            interactiveAuthenticatedAt: $interactiveAuthenticatedAt,
        );
        $request->session()->put('auth.staff_inactivity_generation', $lease->inactivity_generation);
        $this->startPresenceSession->handle($user, $request, $proof->sessionVersion);
    }

    private function proofIsCurrent(
        StaffAuthenticationProof $proof,
        User $user,
        StaffAuthenticationState $state,
        Request $request,
        bool $remember,
    ): bool {
        if ($proof->userId !== (int) $user->id) {
            return false;
        }

        if ($proof->source === 'password') {
            return ! $state->hasConfirmedTwoFactor()
                && $proof->purpose === 'login'
                && $proof->primaryMethod === 'password'
                && $proof->providerSubject === null
                && $proof->attemptId === null;
        }

        if ($proof->source === 'google') {
            return ! $remember
                && ! $state->hasConfirmedTwoFactor()
                && $proof->purpose === 'login'
                && $proof->primaryMethod === 'google'
                && $proof->providerSubject !== null
                && $proof->attemptId === null;
        }

        if ($proof->source === 'remembered') {
            return $remember
                && $proof->purpose === 'login'
                && $proof->primaryMethod === 'remember'
                && $proof->providerSubject === null
                && $proof->attemptId === null;
        }

        $isLogin = $proof->source === 'attempt'
            && $proof->purpose === 'login'
            && in_array($proof->primaryMethod, ['password', 'google'], true);
        $isSetup = $proof->source === 'setup'
            && ! $remember
            && in_array($proof->purpose, ['activation', 'password_reset', 'mfa_reset', 'mfa_enrollment'], true)
            && (
                ($proof->purpose === 'mfa_enrollment'
                    && (($proof->primaryMethod === 'password' && $proof->providerSubject === null)
                        || ($proof->primaryMethod === 'google' && $proof->providerSubject !== null)))
                || ($proof->purpose !== 'mfa_enrollment' && $proof->providerSubject === null)
            );

        if ((! $isLogin && ! $isSetup) || $proof->attemptId === null) {
            return false;
        }

        if ($isLogin && $proof->primaryMethod === 'google' && $proof->providerSubject === null) {
            return false;
        }

        $attempt = StaffAuthenticationAttempt::query()
            ->whereKey($proof->attemptId)
            ->where('user_id', $user->id)
            ->where('purpose', $proof->purpose)
            ->where('primary_method', $proof->primaryMethod)
            ->where('phase', 'completed')
            ->whereNotNull('completed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        if (
            $attempt === null
            || $attempt->primary_verified_at === null
            || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId())))
            || $attempt->expected_session_version !== $proof->sessionVersion
            || $attempt->expected_factor_generation !== $proof->factorGeneration
            || $attempt->expected_recovery_generation !== $proof->recoveryGeneration
            || ($attempt->mfa_verified_at !== null && $attempt->mfa_verified_at->lt($attempt->primary_verified_at))
            || ($attempt->mfa_verified_at !== null && $attempt->completed_at->lt($attempt->mfa_verified_at))
            || ($isLogin && $attempt->remember_requested !== $remember)
            || ($isSetup && $attempt->remember_requested)
            || ($isLogin && $proof->primaryMethod === 'google' && $attempt->pending_google_subject !== $proof->providerSubject)
            || (($state->hasConfirmedTwoFactor() || $this->mfaPolicy->isMfaRequired($user)) && $attempt->mfa_verified_at === null)
            || (($proof->purpose === 'mfa_reset' || $proof->purpose === 'mfa_enrollment') && ! $state->hasConfirmedTwoFactor())
        ) {
            return false;
        }

        if ($isLogin) {
            return true;
        }

        if ($proof->purpose === 'mfa_enrollment') {
            return $attempt->voucher_id === null
                && (($proof->primaryMethod === 'password' && $attempt->pending_google_subject === null)
                    || ($proof->primaryMethod === 'google' && $attempt->pending_google_subject === $proof->providerSubject));
        }

        if ($attempt->voucher_id === null) {
            return false;
        }

        return StaffActivationVoucher::query()
            ->whereKey($attempt->voucher_id)
            ->where('user_id', $user->id)
            ->where('purpose', $proof->purpose)
            ->whereNotNull('redeemed_at')
            ->whereNull('revoked_at')
            ->unexpired()
            ->lockForUpdate()
            ->exists();
    }
}
