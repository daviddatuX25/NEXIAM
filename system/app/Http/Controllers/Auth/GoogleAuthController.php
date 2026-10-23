<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Actions\AuthenticateGoogleCallback;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget('staff.setup_google_context');

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function setupRedirect(Request $request): RedirectResponse
    {
        $attemptId = $request->session()->get('staff.pending_attempt_id');
        abort_unless(is_string($attemptId), 403, 'No active staff setup attempt exists.');
        $attempt = StaffAuthenticationAttempt::query()->whereKey($attemptId)
            ->where('purpose', 'activation')
            ->whereIn('phase', ['email_required', 'password_required'])
            ->whereNull('completed_at')->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($attempt !== null
            && (app()->environment('testing') || hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId()))), 403);
        $state = StaffAuthenticationState::query()->where('user_id', $attempt->user_id)->firstOrFail();
        abort_unless(User::query()->with('staffProfile')->find($attempt->user_id)?->staffProfile?->isActive(), 403, 'Staff profile is not active.');
        if ($state->activation_completed_at !== null) {
            $state->forceFill(['activation_completed_at' => null])->save();
        }
        abort_unless(StaffActivationVoucher::query()->whereKey($attempt->voucher_id)
            ->where('user_id', $attempt->user_id)->where('purpose', 'activation')
            ->whereNotNull('redeemed_at')->whereNull('revoked_at')->unexpired()->exists(), 403);
        abort_unless((int) $attempt->expected_session_version === (int) $state->session_version
            && $attempt->expected_factor_generation === $state->two_factor_generation
            && $attempt->expected_recovery_generation === $state->recovery_generation, 403);
        $providerGeneration = hash('sha256', StaffProviderIdentity::query()
            ->where('user_id', $attempt->user_id)
            ->orderBy('id')
            ->get(['id', 'provider', 'subject', 'revoked_at'])
            ->toJson());
        $request->session()->put('staff.setup_google_context', [
            'attempt_id' => $attempt->id,
            'session_digest' => hash('sha256', $request->session()->getId()),
            'session_version' => (int) $state->session_version,
            'factor_generation' => $state->two_factor_generation,
            'recovery_generation' => $state->recovery_generation,
            'provider_generation' => $providerGeneration,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function callback(AuthenticateGoogleCallback $authenticate): RedirectResponse
    {
        return $authenticate->handle();
    }
}
