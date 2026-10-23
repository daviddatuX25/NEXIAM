<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\Actions\ConsumeStaffTwoFactorChallenge;
use App\Http\Controllers\Controller;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $attemptId = $request->session()->get('staff.pending_attempt_id');
        $attempt = is_string($attemptId)
            ? StaffAuthenticationAttempt::query()
                ->whereKey($attemptId)
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first()
            : null;

        if (
            $attempt === null
            || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId())))
            || $attempt->expected_session_version === null
            || $attempt->expected_factor_generation === null
            || $attempt->blocked_until?->isFuture()
            || (int) $attempt->failed_attempts >= 5
        ) {
            return redirect()->route('login');
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
            return redirect()->route('login');
        }

        if ($attempt->purpose === 'login') {
            if (
                $attempt->phase !== 'mfa_required'
                || ! in_array($attempt->primary_method, ['password', 'google'], true)
                || ! $state->hasConfirmedTwoFactor()
                || $state->two_factor_secret === null
            ) {
                return redirect()->route('login');
            }

            if ($attempt->primary_method === 'google' && ! StaffProviderIdentity::query()
                ->where('user_id', $user->id)
                ->where('provider', 'google')
                ->where('subject', $attempt->pending_google_subject)
                ->whereNull('revoked_at')
                ->exists()
            ) {
                return redirect()->route('login');
            }
        } elseif (in_array($attempt->purpose, ['activation', 'password_reset'], true)
            && $attempt->primary_method === 'voucher'
            && $attempt->phase === 'mfa_required'
            && $attempt->voucher_id !== null
        ) {
            if (! $state->hasConfirmedTwoFactor() || $state->two_factor_secret === null) {
                return redirect('/staff/account-setup');
            }

            $voucher = $attempt->voucher;
            if (
                $voucher === null
                || $voucher->purpose !== $attempt->purpose
                || $voucher->user_id !== $user->id
                || $voucher->redeemed_at === null
                || $voucher->revoked_at !== null
                || ($voucher->expires_at !== null && ! $voucher->expires_at->isFuture())
            ) {
                return redirect('/staff/account-setup');
            }
        } else {
            return redirect('/staff/account-setup');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request, ConsumeStaffTwoFactorChallenge $consume): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'prohibits:recovery_code', 'digits:6'],
            'recovery_code' => ['nullable', 'required_without:code', 'prohibits:code', 'string', 'max:64'],
        ]);

        return $consume->handle(
            (string) $request->session()->get('staff.pending_attempt_id', ''),
            $validated['code'] ?? null,
            $validated['recovery_code'] ?? null,
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $attemptId = $request->session()->get('staff.pending_attempt_id');

        if (is_string($attemptId)) {
            StaffAuthenticationAttempt::query()
                ->whereKey($attemptId)
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => Carbon::now(), 'phase' => 'revoked']);
        }

        $request->session()->forget('staff.pending_attempt_id');

        return redirect()->route('login');
    }
}
