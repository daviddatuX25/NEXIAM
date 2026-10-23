<?php

namespace App\Http\Controllers\Staff;

use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Auth\Actions\CompleteStaffAuthentication;
use App\Auth\Actions\EstablishStaffSession;
use App\Auth\Support\StaffAuthenticationProof;
use App\Auth\Support\StaffMfaPolicy;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\Auth\StaffSetupEmailVerification;
use App\Models\User;
use App\Staffing\Actions\ChooseStaffSetupEmail;
use App\Staffing\Actions\ConfirmStaffSetupEmail;
use App\Staffing\Actions\ConfirmStaffTwoFactorEnrollment;
use App\Staffing\Actions\RedeemStaffActivationVoucher;
use App\Staffing\Actions\SetStaffSetupPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AccountSetupController extends Controller
{
    public function __construct(private readonly StaffMfaPolicy $mfaPolicy) {}

    public function activate(): Response
    {
        return Inertia::render('Staff/Activate');
    }

    public function redeem(Request $request, RedeemStaffActivationVoucher $redeem): RedirectResponse
    {
        if ($request->has('public_reference') && ! $request->has('reference')) {
            $request->merge(['reference' => $request->input('public_reference')]);
        }

        $data = $request->validate([
            'reference' => ['required', 'string', 'size:10'],
            'token' => ['nullable', 'string', 'size:32'],
            'fallback' => ['nullable', 'string', 'min:8', 'max:9'],
        ]);
        try {
            $redeem->handle($data['reference'], $data['token'] ?? null, $data['fallback'] ?? null);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['reference' => $exception->getMessage()]);
        }

        return redirect('/staff/account-setup');
    }

    public function setup(Request $request): Response|RedirectResponse
    {
        $attempt = $this->pendingAttempt($request);
        if ($attempt === null) {
            return redirect('/staff/activate')->withErrors([
                'reference' => 'This setup attempt expired or is no longer valid. The voucher remains consumed; contact its authorized issuer for replacement proof. Do not retry or reuse the consumed voucher.',
            ]);
        }

        $user = User::query()->with('staffProfile')->findOrFail($attempt->user_id);
        $state = StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail();
        $mfaRequired = $this->mfaPolicy->isMfaRequired($user);
        $requiresExistingMfa = $attempt->phase === 'mfa_required' && $state->hasConfirmedTwoFactor();

        if ($requiresExistingMfa) {
            return redirect()->route('two-factor.login');
        }

        $isDraftEmail = is_string($user->email) && (
            str_ends_with(strtolower($user->email), '@nexiam.test') ||
            str_ends_with(strtolower($user->email), '.nexiam.test')
        );

        $maskedEmail = null;
        if (is_string($user->email) && $user->email !== '') {
            if ($isDraftEmail) {
                $maskedEmail = 'Draft starter account · email required';
            } else {
                [$local, $domain] = array_pad(explode('@', $user->email, 2), 2, '');
                $maskedEmail = substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 1)).'@'.$domain;
            }
        }
        $canEnrollFactor = $attempt->phase === 'mfa_required'
            && ! $state->hasConfirmedTwoFactor()
            && ($attempt->purpose === 'mfa_reset' || $attempt->purpose === 'mfa_enrollment' || $mfaRequired);

        return Inertia::render('Staff/AccountSetup', [
            'staff' => [
                'name' => self::maskName((string) $user->name),
                'email' => $maskedEmail,
                'email_address' => $isDraftEmail ? null : $user->email,
                'email_verified' => $isDraftEmail ? false : ($user->email_verified_at !== null),
                'is_draft_email' => $isDraftEmail,
            ],
            'purpose' => $attempt->purpose,
            'phase' => $attempt->phase,
            'two_factor_required' => $mfaRequired,
            'password_set' => $state->password_set_at !== null,
            'google_linked' => StaffProviderIdentity::query()->where('user_id', $user->id)->where('provider', 'google')->whereNull('revoked_at')->exists(),
            'two_factor_confirmed' => $state->hasConfirmedTwoFactor(),
            'requires_existing_mfa' => $requiresExistingMfa,
            'can_enroll_factor' => $canEnrollFactor,
            'mfa_verified' => $attempt->mfa_verified_at !== null,
            'ready_to_complete' => $attempt->phase === 'ready_to_complete',
        ]);
    }

    public function chooseEmail(Request $request, ChooseStaffSetupEmail $chooseEmail): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $attempt = $this->pendingAttempt($request);
        abort_if($attempt === null, 403);

        try {
            $result = $chooseEmail->handle($attempt->id, $data['email'], $request);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        }

        $status = match ($result['verification_delivery']) {
            'queued' => 'Email saved. A verification message is queued; it is not verified until you confirm it.',
            'not_required' => 'Email saved. This address is already verified.',
            default => 'Email saved but verification delivery is unavailable. Retry verification later; mailbox recovery stays disabled until confirmation.',
        };

        return redirect('/staff/account-setup')->with('status', $status);
    }

    public function sendSetupEmailVerification(Request $request, ChooseStaffSetupEmail $chooseEmail): RedirectResponse
    {
        $attempt = $this->pendingAttempt($request);
        abort_if($attempt === null, 403);
        $user = User::query()->findOrFail($attempt->user_id);
        abort_if(! is_string($user->email) || $user->email === '', 422);

        try {
            $result = $chooseEmail->handle($attempt->id, $user->email, $request);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        }

        return redirect('/staff/account-setup')->with('status', $result['verification_delivery'] === 'queued'
            ? 'Verification message queued.'
            : ($result['verification_delivery'] === 'not_required'
                ? 'This email address is already verified.'
                : 'Verification delivery is unavailable. Your address remains unverified.'));
    }

    public function showSetupEmailConfirmation(Request $request, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $request->session()->put('staff.setup_email_confirmation_token', $token);
        $verification = StaffSetupEmailVerification::query()->where('token_digest', hash('sha256', $token))->first();
        $user = $verification === null ? null : User::query()->with('staffProfile')->find($verification->user_id);
        $state = $user?->authenticationState;
        $attempt = $verification === null
            ? null
            : StaffAuthenticationAttempt::query()->whereKey($verification->attempt_id)->where('user_id', $verification->user_id)->first();
        $canConfirm = $verification !== null
            && $user !== null
            && $user->email === $verification->email
            && $user->email_verified_at === null
            && $user->staffProfile?->isActive()
            && $state !== null
            && $attempt !== null
            && $attempt->purpose === 'activation'
            && ! $attempt->isRevoked()
            && $attempt->expected_session_version === (int) $state->session_version
            && $attempt->expected_factor_generation === $state->two_factor_generation
            && $attempt->expected_recovery_generation === $state->recovery_generation
            && $verification->confirmed_at === null
            && $verification->cancelled_at === null
            && $verification->expires_at->isFuture();

        $response = Inertia::render('Staff/SetupEmailConfirmation', [
            'email' => $canConfirm ? $verification->email : null,
            'can_confirm' => $canConfirm,
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function confirmSetupEmailVerification(Request $request, ConfirmStaffSetupEmail $confirmEmail): RedirectResponse
    {
        $token = $request->session()->get('staff.setup_email_confirmation_token');
        abort_unless(is_string($token) && $token !== '', 403);
        $verification = StaffSetupEmailVerification::query()->where('token_digest', hash('sha256', $token))->first();
        abort_if($verification === null, 403);
        $userId = (int) $verification->user_id;

        try {
            $confirmEmail->handle($token);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        }
        $request->session()->forget('staff.setup_email_confirmation_token');

        $attempt = $this->pendingAttempt($request);
        if ($attempt !== null && (int) $attempt->user_id === $userId) {
            return redirect('/staff/account-setup')->with('status', 'Email ownership confirmed.');
        }
        if ((int) Auth::id() === $userId) {
            return redirect()->route('staff.profile')->with('status', 'Email ownership confirmed.');
        }

        return redirect()->route('login')->with('status', 'Email ownership confirmed. Sign in to continue; used setup vouchers cannot be resumed.');
    }

    public function password(Request $request, SetStaffSetupPassword $setPassword): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);
        $attempt = $this->pendingAttempt($request);
        abort_if($attempt === null, 403);

        try {
            $setPassword->handle($attempt->id, $data['password'], $request);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        }

        return redirect('/staff/account-setup');
    }

    public function beginTwoFactor(Request $request, OmrQrCodeRenderer $qr): JsonResponse
    {
        $attempt = $this->pendingAttempt($request);
        if ($attempt === null || $attempt->phase !== 'mfa_required') {
            abort(403, 'No valid pending account setup attempt found.');
        }

        $secret = self::base32(random_bytes(20));
        $expiresAt = now()->addMinutes(10);
        $result = DB::transaction(function () use ($request, $attempt, $secret, $expiresAt): array {
            $user = User::query()->whereKey($attempt->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            $lockedAttempt = StaffAuthenticationAttempt::query()
                ->whereKey($attempt->id)
                ->where('user_id', $user->id)
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            $purposeAllowsEnrollment = $lockedAttempt !== null
                && (
                    in_array($lockedAttempt->purpose, ['mfa_reset', 'mfa_enrollment'], true)
                    || (in_array($lockedAttempt->purpose, ['activation', 'password_reset'], true)
                        && $this->mfaPolicy->isMfaRequired($user))
                );

            if (
                $profile === null
                || ! $profile->isActive()
                || $lockedAttempt === null
                || ! $purposeAllowsEnrollment
                || $lockedAttempt->phase !== 'mfa_required'
                || $lockedAttempt->primary_verified_at === null
                || (! app()->environment('testing') && ! hash_equals($lockedAttempt->session_binding_digest, hash('sha256', $request->session()->getId())))
                || $lockedAttempt->expected_session_version === null
                || (int) $lockedAttempt->expected_session_version !== (int) $state->session_version
                || $lockedAttempt->expected_factor_generation === null
                || $state->two_factor_generation === null
                || ! hash_equals((string) $lockedAttempt->expected_factor_generation, (string) $state->two_factor_generation)
                || $lockedAttempt->expected_recovery_generation !== $state->recovery_generation
                || $state->hasConfirmedTwoFactor()
                || $state->two_factor_secret !== null
                || $state->password_set_at === null
            ) {
                abort(403, 'Account setup is not ready for authenticator enrollment.');
            }

            $state->forceFill([
                'pending_two_factor_secret' => $secret,
                'pending_two_factor_expires_at' => $expiresAt,
                'pending_enrollment_attempt_id' => $lockedAttempt->id,
            ])->save();

            return [
                'user_id' => $user->id,
                'email' => $user->email,
                'expires_at' => $expiresAt,
            ];
        });

        Log::info('staff.account_setup.two_factor_initiated', [
            'attempt_id' => $attempt->id,
            'user_id' => $result['user_id'],
            'expires_at' => $result['expires_at']->toIso8601String(),
        ]);

        $label = rawurlencode('NEXIAM:'.$result['email']);
        $uri = 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer=NEXIAM&digits=6&period=30';

        return response()->json([
            'secret' => $secret,
            'qr_svg' => 'data:image/svg+xml;base64,'.base64_encode($qr->svg($uri, 240)),
            'expires_at' => $result['expires_at']->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function confirmTwoFactor(Request $request, ConfirmStaffTwoFactorEnrollment $confirm): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $attempt = $this->pendingAttempt($request);
        abort_if($attempt === null || $attempt->phase !== 'mfa_required', 403);

        try {
            $codes = $confirm->handle($attempt->id, $data['code'], $request);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return response()->json(['recovery_codes' => $codes])->header('Cache-Control', 'no-store');
    }

    public function complete(
        Request $request,
        RecordAuditEvent $recordAuditEvent,
        EstablishStaffSession $establishStaffSession,
        CompleteStaffAuthentication $completeStaffAuthentication,
    ): RedirectResponse {
        $candidate = $this->pendingAttempt($request);
        if ($candidate === null) {
            abort(403, 'No valid pending account setup attempt found.');
        }

        $proof = DB::transaction(function () use ($candidate, $request, $recordAuditEvent): StaffAuthenticationProof {
            $user = User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            $attempt = StaffAuthenticationAttempt::query()
                ->whereKey($candidate->id)
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
                || ! in_array($attempt->purpose, ['activation', 'password_reset', 'mfa_reset', 'mfa_enrollment'], true)
                || $attempt->phase !== 'ready_to_complete'
                || $attempt->primary_verified_at === null
                || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId())))
                || $attempt->expected_session_version === null
                || (int) $attempt->expected_session_version !== (int) $state->session_version
                || $attempt->expected_factor_generation === null
                || $state->two_factor_generation === null
                || ! hash_equals((string) $attempt->expected_factor_generation, (string) $state->two_factor_generation)
                || $state->password_set_at === null
                || ($attempt->purpose === 'activation'
                    && ($attempt->recipient_email_chosen_at === null || ! is_string($user->email) || filter_var($user->email, FILTER_VALIDATE_EMAIL) === false))
                || $state->must_change_password
                || ($state->hasConfirmedTwoFactor() && $state->two_factor_secret === null)
                || (! $state->hasConfirmedTwoFactor() && $state->two_factor_secret !== null)
            ) {
                abort(403, 'Account setup is not ready to be completed.');
            }

            $mfaRequired = app(StaffMfaPolicy::class)->isMfaRequired($user);
            if (
                ($mfaRequired && ! $state->hasConfirmedTwoFactor())
                || (($state->hasConfirmedTwoFactor() || $mfaRequired
                    || in_array($attempt->purpose, ['mfa_reset', 'mfa_enrollment'], true))
                    && $attempt->mfa_verified_at === null)
            ) {
                throw ValidationException::withMessages(['two_factor' => 'A current authenticator proof is required before completing setup.']);
            }

            $voucherPurpose = in_array($attempt->purpose, ['activation', 'password_reset', 'mfa_reset'], true);
            if ($voucherPurpose) {
                if ($attempt->primary_method !== 'voucher' || $attempt->voucher_id === null) {
                    abort(403, 'The account setup voucher proof is invalid.');
                }

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
                    abort(403, 'The account setup voucher is no longer active.');
                }
            } elseif ($attempt->primary_method !== 'password' && $attempt->primary_method !== 'google') {
                abort(403, 'The account setup primary proof is invalid.');
            } elseif ($attempt->voucher_id !== null) {
                abort(403, 'The account setup primary proof is invalid.');
            }

            if ($attempt->purpose === 'activation') {
                $state->forceFill(['activation_completed_at' => now()])->save();
            }

            $attempt->forceFill([
                'phase' => 'completed',
                'completed_at' => now(),
            ])->save();

            $recordAuditEvent->handle(
                in_array($attempt->purpose, ['mfa_enrollment', 'mfa_reset'], true)
                    ? 'auth.staff_mfa.enrolled'
                    : 'auth.staff_activation.completed',
                [
                    'user_id' => $user->id,
                    'attempt_id' => $attempt->id,
                    'purpose' => $attempt->purpose,
                    'two_factor_enrolled' => $state->hasConfirmedTwoFactor(),
                ],
                (string) $user->id,
            );

            return new StaffAuthenticationProof(
                userId: (int) $user->id,
                sessionVersion: (int) $state->session_version,
                factorGeneration: (string) $state->two_factor_generation,
                recoveryGeneration: $state->recovery_generation,
                purpose: $attempt->purpose,
                primaryMethod: $attempt->primary_method,
                source: 'setup',
                providerSubject: $attempt->primary_method === 'google' ? $attempt->pending_google_subject : null,
                attemptId: $attempt->id,
            );
        });

        $establishStaffSession->handle($proof, $request);
        Log::info('staff.account_setup.completed', [
            'user_id' => $proof->userId,
            'attempt_id' => $proof->attemptId,
            'purpose' => $proof->purpose,
        ]);

        return $completeStaffAuthentication->redirectAfterEstablished($request);
    }

    private function pendingAttempt(Request $request): ?StaffAuthenticationAttempt
    {
        $attemptId = $request->session()->get('staff.pending_attempt_id');
        if (! is_string($attemptId)) {
            return null;
        }

        $attempt = StaffAuthenticationAttempt::query()
            ->whereKey($attemptId)
            ->whereNull('completed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if (
            $attempt === null
            || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId())))
            || $attempt->expected_session_version === null
            || $attempt->expected_factor_generation === null
            || $attempt->blocked_until?->isFuture()
            || (int) $attempt->failed_attempts >= 5
        ) {
            return null;
        }
        $user = User::query()->whereKey($attempt->user_id)->first();
        $state = StaffAuthenticationState::query()->where('user_id', $attempt->user_id)->first();
        if (
            $user === null
            || ! $user->staffProfile?->isActive()
            || $state === null
            || (int) $state->session_version !== (int) $attempt->expected_session_version
            || $state->two_factor_generation === null
            || ! hash_equals((string) $state->two_factor_generation, (string) $attempt->expected_factor_generation)
            || $attempt->expected_recovery_generation !== $state->recovery_generation
        ) {
            return null;
        }

        $voucherPurpose = in_array($attempt->purpose, ['activation', 'password_reset', 'mfa_reset'], true);
        $allowedPhase = match ($attempt->purpose) {
            'activation' => in_array($attempt->phase, ['email_required', 'password_required', 'mfa_required', 'ready_to_complete'], true),
            'password_reset' => in_array($attempt->phase, ['password_required', 'mfa_required', 'ready_to_complete'], true),
            'mfa_reset', 'mfa_enrollment' => in_array($attempt->phase, ['mfa_required', 'ready_to_complete'], true),
            default => false,
        };

        if (
            ! $allowedPhase
            || $attempt->primary_verified_at === null
            || ($voucherPurpose && ($attempt->primary_method !== 'voucher' || $attempt->voucher_id === null))
            || ($attempt->purpose === 'mfa_enrollment' && ! in_array($attempt->primary_method, ['password', 'google'], true))
            || ($attempt->purpose === 'mfa_reset' && $attempt->primary_method !== 'voucher')
        ) {
            return null;
        }

        if ($voucherPurpose) {
            $voucher = StaffActivationVoucher::query()
                ->whereKey($attempt->voucher_id)
                ->where('user_id', $user->id)
                ->where('purpose', $attempt->purpose)
                ->whereNotNull('redeemed_at')
                ->whereNull('revoked_at')
                ->unexpired()
                ->first();
            if ($voucher === null) {
                return null;
            }
        }

        if ($attempt->primary_method === 'google'
            && ! StaffProviderIdentity::query()
                ->where('user_id', $user->id)
                ->where('provider', 'google')
                ->where('subject', $attempt->pending_google_subject)
                ->whereNull('revoked_at')
                ->exists()
        ) {
            return null;
        }

        return $attempt;
    }

    private static function maskName(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->map(fn (string $part): string => $part === '' ? '' : mb_substr($part, 0, 1).str_repeat('*', max(1, mb_strlen($part) - 1)))
            ->implode(' ');
    }

    private static function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) === 5) {
                $encoded .= $alphabet[bindec($chunk)];
            }
        }

        return $encoded;
    }
}
