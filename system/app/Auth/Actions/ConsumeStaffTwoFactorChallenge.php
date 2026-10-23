<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Support\StaffAuthenticationProof;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ConsumeStaffTwoFactorChallenge
{
    public function __construct(
        private readonly Request $request,
        private readonly CompleteStaffAuthentication $completeStaffAuthentication,
    ) {}

    public function handle(string $attemptId, ?string $code = null, ?string $recoveryCode = null): RedirectResponse
    {
        if (($code === null) === ($recoveryCode === null)) {
            throw ValidationException::withMessages(['code' => trans('auth.failed')]);
        }

        $result = DB::transaction(function () use ($attemptId, $code, $recoveryCode): array {
            $attemptSnapshot = StaffAuthenticationAttempt::query()->find($attemptId);
            if ($attemptSnapshot === null) {
                throw ValidationException::withMessages(['code' => trans('auth.failed')]);
            }

            $user = User::query()->whereKey($attemptSnapshot->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            $providerIdentity = null;

            if ($attemptSnapshot->primary_method === 'google' && is_string($attemptSnapshot->pending_google_subject)) {
                $providerIdentity = StaffProviderIdentity::query()
                    ->where('user_id', $user->id)
                    ->where('provider', 'google')
                    ->where('subject', $attemptSnapshot->pending_google_subject)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();
            }

            $attempt = StaffAuthenticationAttempt::query()
                ->whereKey($attemptId)
                ->where('user_id', $user->id)
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();
            if (
                $state === null
                || ! $profile?->isActive()
                || $attempt === null
                || $attempt->blocked_until?->isFuture()
                || (int) $attempt->failed_attempts >= 5
                || $attempt->primary_verified_at === null
                || $attempt->phase !== 'mfa_required'
                || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $this->request->session()->getId())))
                || $attempt->expected_session_version === null
                || (int) $attempt->expected_session_version !== (int) $state->session_version
                || $attempt->expected_factor_generation === null
                || $state->two_factor_generation === null
                || ! hash_equals((string) $attempt->expected_factor_generation, (string) $state->two_factor_generation)
                || $state->two_factor_secret === null
                || ! $state->hasConfirmedTwoFactor()
                || ($attempt->primary_method === 'google' && $providerIdentity === null)
                || ! in_array($attempt->primary_method, ['password', 'google', 'voucher'], true)
            ) {
                throw ValidationException::withMessages([
                    $recoveryCode === null ? 'code' : 'recovery_code' => trans('auth.failed'),
                ]);
            }

            $isLoginAttempt = $attempt->purpose === 'login'
                && in_array($attempt->primary_method, ['password', 'google'], true);
            $isSetupAttempt = in_array($attempt->purpose, ['activation', 'password_reset'], true)
                && $attempt->primary_method === 'voucher'
                && $attempt->voucher_id !== null;

            if (! $isLoginAttempt && ! $isSetupAttempt) {
                throw ValidationException::withMessages([
                    $recoveryCode === null ? 'code' : 'recovery_code' => trans('auth.failed'),
                ]);
            }

            if (
                $attempt->expected_recovery_generation !== $state->recovery_generation
                || ($isSetupAttempt && ($state->password_set_at === null || $attempt->remember_requested))
                || ($isLoginAttempt && (! $state->isOnboarded() || $state->must_change_password))
            ) {
                throw ValidationException::withMessages([
                    $recoveryCode === null ? 'code' : 'recovery_code' => trans('auth.failed'),
                ]);
            }

            if ($isSetupAttempt) {
                $voucher = StaffActivationVoucher::query()
                    ->whereKey($attempt->voucher_id)
                    ->where('user_id', $user->id)
                    ->where('purpose', $attempt->purpose)
                    ->whereNotNull('redeemed_at')
                    ->whereNull('revoked_at')
                    ->unexpired()
                    ->lockForUpdate()
                    ->first();

                if ($voucher === null) {
                    throw ValidationException::withMessages([
                        $recoveryCode === null ? 'code' : 'recovery_code' => trans('auth.failed'),
                    ]);
                }
            }

            if ($code !== null) {
                $step = (new Google2FA)->verifyKeyNewer(
                    $state->two_factor_secret,
                    $code,
                    $state->two_factor_last_accepted_step ?? -1,
                );

                if ($step === false) {
                    throw ValidationException::withMessages(['code' => trans('auth.failed')]);
                }

                $state->update(['two_factor_last_accepted_step' => $step]);
            } else {
                $normalized = str_replace('-', '', strtolower((string) $recoveryCode));
                if ($attempt->expected_recovery_generation === null || $state->recovery_generation === null) {
                    throw ValidationException::withMessages(['recovery_code' => trans('auth.failed')]);
                }

                $recoveryCodeRecord = StaffTwoFactorRecoveryCode::query()
                    ->where('user_id', $attempt->user_id)
                    ->where('generation', $attempt->expected_recovery_generation)
                    ->where('generation', $state->recovery_generation)
                    ->where('code_digest', hash('sha256', $normalized))
                    ->whereNull('consumed_at')
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();

                if ($recoveryCodeRecord === null) {
                    throw ValidationException::withMessages(['recovery_code' => trans('auth.failed')]);
                }

                $consumed = StaffTwoFactorRecoveryCode::query()
                    ->whereKey($recoveryCodeRecord->id)
                    ->whereNull('consumed_at')
                    ->update(['consumed_at' => now()]);

                if ($consumed !== 1) {
                    throw ValidationException::withMessages(['recovery_code' => trans('auth.failed')]);
                }
            }

            $attempt->forceFill([
                'mfa_verified_at' => now(),
                'phase' => $isLoginAttempt ? 'completed' : 'ready_to_complete',
                'completed_at' => $isLoginAttempt ? now() : null,
            ])->save();

            if ($isSetupAttempt) {
                return ['setup' => true];
            }

            return [
                'setup' => false,
                'proof' => new StaffAuthenticationProof(
                    userId: (int) $user->id,
                    sessionVersion: (int) $state->session_version,
                    factorGeneration: (string) $state->two_factor_generation,
                    recoveryGeneration: $state->recovery_generation,
                    purpose: 'login',
                    primaryMethod: $attempt->primary_method,
                    source: 'attempt',
                    providerSubject: $attempt->primary_method === 'google' ? $attempt->pending_google_subject : null,
                    attemptId: $attempt->id,
                ),
                'remember' => (bool) $attempt->remember_requested,
            ];
        });

        if ($result['setup']) {
            return redirect('/staff/account-setup');
        }

        return $this->completeStaffAuthentication->handle(
            $result['proof'],
            $this->request,
            $result['remember'],
        );
    }
}
