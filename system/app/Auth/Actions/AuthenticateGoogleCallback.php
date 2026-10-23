<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Support\StaffAuthenticationProof;
use App\Auth\Support\StaffMfaPolicy;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class AuthenticateGoogleCallback
{
    public function __construct(
        private readonly CompleteStaffAuthentication $completeStaffAuthentication,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly StaffMfaPolicy $mfaPolicy,
        private readonly Request $request,
    ) {}

    public function handle(): RedirectResponse
    {
        $setupOAuth = is_array($this->request->session()->get('staff.setup_google_context'));
        if ($this->request->has('error')) {
            $this->request->session()->forget('staff.setup_google_context');
            if ($setupOAuth) {
                return redirect('/staff/account-setup')->withErrors(['email' => 'Google linking was cancelled. You can enter your email address instead.']);
            }

            return Auth::check()
                ? redirect()->route('staff.profile')->withErrors(['google' => 'Google linking was cancelled.'])
                : redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled.']);
        }
        try {
            /** @var SocialiteUser $socialUser */
            $socialUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            if (! $setupOAuth) {
                throw $exception;
            }
            $this->request->session()->forget('staff.setup_google_context');

            return redirect('/staff/account-setup')->withErrors(['email' => 'Google linking is temporarily unavailable. Enter your email address instead; the setup attempt remains available while it is valid.']);
        }
        $subject = $socialUser->getId();
        $email = $socialUser->getEmail();
        $emailVerified = $socialUser->user['email_verified'] ?? false;

        if (! is_string($subject) || $subject === '' || ! is_string($email)
            || Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            if ($setupOAuth) {
                $this->request->session()->forget('staff.setup_google_context');
            }

            return redirect($setupOAuth ? '/staff/account-setup' : route('login'))
                ->withErrors(['email' => 'Google did not provide a valid account email.']);
        }

        if ($emailVerified !== true) {
            if ($setupOAuth) {
                $this->request->session()->forget('staff.setup_google_context');
            }

            return redirect($setupOAuth ? '/staff/account-setup' : route('login'))
                ->withErrors(['email' => 'Your Google account email is not verified.']);
        }
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        $allowedDomains = config('ciam.google.allowed_domains', ['gmail.com']);
        if (! in_array($domain, $allowedDomains, true)) {
            if ($setupOAuth) {
                $this->request->session()->forget('staff.setup_google_context');
            }

            return redirect($setupOAuth ? '/staff/account-setup' : route('login'))
                ->withErrors(['email' => "Google domain [{$domain}] is not authorized for staff auto-linking."]);
        }

        if (is_array($this->request->session()->get('staff.setup_google_context'))) {
            return $this->linkGoogleDuringSetup(strtolower($email), $subject);
        }
        $identity = StaffProviderIdentity::query()
            ->where('provider', 'google')
            ->where('subject', $subject)
            ->whereNull('revoked_at')
            ->first();

        if ($identity !== null) {
            if (Auth::check()) {
                if ((string) $identity->user_id !== (string) Auth::id()) {
                    return redirect()->route('staff.profile')->withErrors([
                        'google' => 'This Google account is already linked to another staff account.',
                    ]);
                }

                return redirect()->route('staff.profile')->with('status', 'This Google account is already linked to your profile.');
            }

            return $this->authenticateBoundIdentity($subject);
        }

        $actor = Auth::user();
        if ($actor !== null) {
            $sessionVersion = $this->linkGoogleIdentity($actor, $email, $subject);
            if ($sessionVersion !== null) {
                $this->request->session()->put('auth.session_version', $sessionVersion);
            }

            return redirect()->route('staff.profile')->with('status', 'Google account linked successfully.');
        }

        return redirect()->route('login')->withErrors([
            'email' => 'No staff account is linked to this Google identity. Please sign in with your password and link Google in your profile.',
        ]);
    }

    private function linkGoogleDuringSetup(string $email, string $subject): RedirectResponse
    {
        $context = $this->request->session()->pull('staff.setup_google_context');
        try {
            if (! is_array($context) || ! is_string($context['attempt_id'] ?? null)
                || ! is_string($context['expires_at'] ?? null)
                || now()->greaterThanOrEqualTo($context['expires_at'])
                || ! hash_equals((string) ($context['session_digest'] ?? ''), hash('sha256', $this->request->session()->getId()))) {
                throw new \RuntimeException('The restricted Google setup attempt expired. Return to email choice or ask the issuer for replacement proof.');
            }
            DB::transaction(function () use ($context, $email, $subject): void {
                $attemptSnapshot = StaffAuthenticationAttempt::query()->whereKey($context['attempt_id'])->first();
                if ($attemptSnapshot === null) {
                    throw new \RuntimeException('The restricted Google setup attempt expired. Return to email choice or ask the issuer for replacement proof.');
                }
                $user = User::query()->whereKey($attemptSnapshot->user_id)->lockForUpdate()->firstOrFail();
                $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                $profile = $user->staffProfile()->lockForUpdate()->first();
                $attempt = StaffAuthenticationAttempt::query()->whereKey($context['attempt_id'])
                    ->where('user_id', $user->id)->lockForUpdate()->first();
                if ($attempt === null || $attempt->purpose !== 'activation'
                    || ! in_array($attempt->phase, ['email_required', 'password_required'], true)
                    || $attempt->isExpired() || $attempt->isCompleted() || $attempt->isRevoked()
                    || $attempt->primary_method !== 'voucher' || $attempt->voucher_id === null
                    || ! hash_equals($attempt->session_binding_digest, hash('sha256', $this->request->session()->getId()))) {
                    throw new \RuntimeException('The restricted Google setup attempt expired. Return to email choice or ask the issuer for replacement proof.');
                }
                $voucher = StaffActivationVoucher::query()->whereKey($attempt->voucher_id)
                    ->where('user_id', $user->id)->where('purpose', 'activation')
                    ->whereNotNull('redeemed_at')->whereNull('revoked_at')->unexpired()->lockForUpdate()->first();
                if ($voucher === null) {
                    throw new \RuntimeException('The restricted Google setup attempt expired. Return to email choice or ask the issuer for replacement proof.');
                }
                $providerGeneration = hash('sha256', StaffProviderIdentity::query()
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->get(['id', 'provider', 'subject', 'revoked_at'])
                    ->toJson());
                if (! $profile?->isActive()
                    || (int) $state->session_version !== (int) ($context['session_version'] ?? 0)
                    || $state->two_factor_generation !== ($context['factor_generation'] ?? null)
                    || $state->recovery_generation !== ($context['recovery_generation'] ?? null)
                    || (int) $attempt->expected_session_version !== (int) $state->session_version
                    || $attempt->expected_factor_generation !== $state->two_factor_generation
                    || $attempt->expected_recovery_generation !== $state->recovery_generation
                    || $providerGeneration !== ($context['provider_generation'] ?? null)) {
                    throw new \RuntimeException('The restricted Google setup attempt expired. Return to email choice or ask the issuer for replacement proof.');
                }
                if ($state->activation_completed_at !== null) {
                    $state->forceFill(['activation_completed_at' => null])->save();
                }
                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->where('id', '<>', $user->id)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('That Google email belongs to another account. Enter a different email address.');
                }
                if (StaffProviderIdentity::query()->where('provider', 'google')->where('subject', $subject)->exists()) {
                    throw new \RuntimeException('This Google identity already has an owner and cannot be transferred.');
                }
                StaffProviderIdentity::query()->create([
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'subject' => $subject,
                    'email_at_link' => $email,
                    'email_verified_at_link' => true,
                    'linked_at' => now(),
                ]);
                $user->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
                $attempt->forceFill([
                    'recipient_email_chosen_at' => now(),
                    'phase' => $attempt->phase === 'email_required' ? 'password_required' : $attempt->phase,
                ])->save();
                $this->recordAuditEvent->handle('auth.staff_google_setup_linked', [
                    'user_id' => $user->id,
                    'attempt_id' => $attempt->id,
                ], (string) $user->id);
            });
        } catch (\RuntimeException $exception) {
            return redirect('/staff/account-setup')->withErrors(['email' => $exception->getMessage()]);
        } catch (\Throwable) {
            return redirect('/staff/account-setup')->withErrors(['email' => 'Google linking could not be completed. Enter your email address or ask the authorized issuer for replacement proof.']);
        }

        return redirect('/staff/account-setup')->with('status', 'Verified Google email linked. Choose a local password and complete any required authenticator setup before access.');
    }

    private function authenticateBoundIdentity(string $subject): RedirectResponse
    {
        $result = DB::transaction(function () use ($subject): array {
            $identitySnapshot = StaffProviderIdentity::query()
                ->where('provider', 'google')
                ->where('subject', $subject)
                ->whereNull('revoked_at')
                ->first();
            if ($identitySnapshot === null) {
                throw ValidationException::withMessages(['email' => trans('auth.failed')]);
            }

            $user = User::query()->whereKey($identitySnapshot->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            $identity = StaffProviderIdentity::query()
                ->whereKey($identitySnapshot->id)
                ->where('user_id', $user->id)
                ->where('provider', 'google')
                ->where('subject', $subject)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if (
                $state === null
                || ! $profile?->isActive()
                || ! $state->isOnboarded()
                || $state->password_set_at === null
                || $state->must_change_password
                || $identity === null
                || ($state->hasConfirmedTwoFactor() && $state->two_factor_secret === null)
            ) {
                throw ValidationException::withMessages(['email' => trans('auth.failed')]);
            }

            if ($state->two_factor_generation === null) {
                $state->forceFill(['two_factor_generation' => (string) Str::uuid()])->save();
            }

            $proof = new StaffAuthenticationProof(
                userId: (int) $user->id,
                sessionVersion: (int) $state->session_version,
                factorGeneration: (string) $state->two_factor_generation,
                recoveryGeneration: $state->recovery_generation,
                purpose: 'login',
                primaryMethod: 'google',
                source: 'google',
                providerSubject: $subject,
            );

            if ($state->hasConfirmedTwoFactor()) {
                $attempt = $this->createGoogleAttempt($user, $proof, 'login', 'mfa_required');

                return ['attempt' => $attempt->id, 'proof' => null, 'enrollment' => false];
            }

            if ($this->mfaPolicy->isMfaRequired($user)) {
                $attempt = $this->createGoogleAttempt($user, $proof, 'mfa_enrollment', 'mfa_required');

                return ['attempt' => $attempt->id, 'proof' => null, 'enrollment' => true];
            }

            return ['attempt' => null, 'proof' => $proof, 'enrollment' => false];
        });

        if ($result['attempt'] !== null) {
            $this->request->session()->put('staff.pending_attempt_id', $result['attempt']);

            return $result['enrollment']
                ? redirect('/staff/account-setup')
                : redirect()->route('two-factor.login');
        }

        return $this->completeStaffAuthentication->handle($result['proof'], $this->request);
    }

    private function createGoogleAttempt(
        User $user,
        StaffAuthenticationProof $proof,
        string $purpose,
        string $phase,
    ): StaffAuthenticationAttempt {
        return StaffAuthenticationAttempt::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'purpose' => $purpose,
            'primary_method' => 'google',
            'phase' => $phase,
            'session_binding_digest' => hash('sha256', $this->request->session()->getId()),
            'expected_session_version' => $proof->sessionVersion,
            'expected_factor_generation' => $proof->factorGeneration,
            'expected_recovery_generation' => $proof->recoveryGeneration,
            'primary_verified_at' => now(),
            'expires_at' => now()->addMinutes($purpose === 'login' ? 5 : 10),
            'pending_google_subject' => $proof->providerSubject,
        ]);
    }

    private function linkGoogleIdentity(User $actor, string $email, string $subject): int
    {
        return DB::transaction(function () use ($actor, $email, $subject): int {
            $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();

            if (
                $state === null
                || ! $profile?->isActive()
                || session('auth.surface') !== 'staff'
                || session('auth.session_version') === null
                || (int) session('auth.session_version') !== (int) $state->session_version
            ) {
                throw ValidationException::withMessages(['google' => trans('auth.failed')]);
            }

            $existing = StaffProviderIdentity::query()
                ->where('provider', 'google')
                ->where('subject', $subject)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'google' => 'This Google identity has prior ownership and cannot be linked to this account.',
                ]);
            }

            StaffProviderIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => 'google',
                'subject' => $subject,
                'email_at_link' => $email,
                'email_verified_at_link' => true,
                'linked_at' => now(),
            ]);

            $state = $this->invalidateStaffAuthentication->handle($user, $state);
            $this->recordAuditEvent->handle('auth.staff_google_explicit_linked', [
                'user_id' => $user->id,
                'email' => $email,
            ], (string) $user->id);

            return (int) $state->session_version;
        });
    }
}
