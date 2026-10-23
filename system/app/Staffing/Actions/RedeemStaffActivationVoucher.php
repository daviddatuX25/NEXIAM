<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RedeemStaffActivationVoucher
{
    public function __construct(
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly Request $request,
    ) {}

    public function handle(string $reference, ?string $token = null, ?string $fallback = null): StaffAuthenticationAttempt
    {
        $voucherSnapshot = StaffActivationVoucher::query()
            ->where('public_reference', strtoupper(trim($reference)))
            ->first();
        if ($voucherSnapshot === null) {
            throw new RuntimeException('This voucher is expired, revoked, or already redeemed.');
        }

        $result = DB::transaction(function () use ($reference, $token, $fallback, $voucherSnapshot): StaffAuthenticationAttempt|false {
            $user = User::query()->whereKey($voucherSnapshot->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            $voucher = StaffActivationVoucher::query()
                ->whereKey($voucherSnapshot->id)
                ->where('public_reference', strtoupper(trim($reference)))
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (
                $profile === null
                || ! $profile->isActive()
                || $voucher === null
                || $voucher->isExpired()
                || $voucher->isRevoked()
                || $voucher->isRedeemed()
            ) {
                throw new RuntimeException('This voucher is expired, revoked, or already redeemed.');
            }
            if ($voucher->failed_attempts >= 5 && $voucher->blocked_until?->isFuture()) {
                throw new RuntimeException('This voucher is temporarily locked.');
            }
            if ($fallback !== null && $voucher->fallback_disabled) {
                throw new RuntimeException('Fallback PIN redemption has been permanently locked for this voucher. Please scan the QR code or request an administrator reissue.');
            }

            $tokenMatches = is_string($token) && preg_match('/^[a-f0-9]{32}$/D', $token) === 1
                && hash_equals($voucher->token_digest, hash('sha256', $token));
            $cleanFallback = strtoupper(str_replace('-', '', trim((string) $fallback)));
            $fallbackMatches = $fallback !== null
                && ! $voucher->fallback_disabled
                && preg_match('/^[A-Z2-7]{8}$/D', $cleanFallback) === 1
                && password_verify($cleanFallback, $voucher->fallback_hash);

            if (! $tokenMatches && ! $fallbackMatches) {
                $failedAttempts = (int) $voucher->failed_attempts + 1;
                $lifetimeFallbackAttempts = $fallback !== null
                    ? ((int) $voucher->lifetime_fallback_attempts + 1)
                    : (int) $voucher->lifetime_fallback_attempts;
                $voucher->forceFill([
                    'failed_attempts' => $failedAttempts,
                    'blocked_until' => $failedAttempts >= 5 ? now()->addMinutes(15) : null,
                    'lifetime_fallback_attempts' => $lifetimeFallbackAttempts,
                    'fallback_disabled' => $lifetimeFallbackAttempts >= 10,
                ])->save();

                return false;
            }
            if ($this->request->hasSession()) {
                Auth::guard('web')->logoutCurrentDevice();
                $this->request->session()->invalidate();
                $this->request->session()->regenerateToken();
                $sessionId = $this->request->session()->getId();
            } else {
                $sessionId = 'mock-testing-session';
            }
            $voucher->forceFill(['redeemed_at' => now()])->save();

            if ($state->two_factor_generation === null) {
                $state->forceFill(['two_factor_generation' => (string) Str::uuid()])->save();
            }
            $isDraftAccount = $user->email !== null && (
                str_ends_with(strtolower($user->email), '@nexiam.test') ||
                str_ends_with(strtolower($user->email), '.nexiam.test')
            );
            if ($voucher->purpose === 'activation' && ($state->activation_completed_at === null || $isDraftAccount)) {
                $state->forceFill(['activation_completed_at' => null])->save();
            }

            if ($voucher->purpose === 'mfa_reset') {
                $state->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'pending_two_factor_secret' => null,
                    'recovery_generation' => (string) Str::uuid(),
                ])->save();
                StaffTwoFactorRecoveryCode::query()
                    ->where('user_id', $user->id)
                    ->whereNull('consumed_at')
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
                $state = $this->invalidateStaffAuthentication->handle($user, $state, factorChanged: true);
            }

            $expiresAt = ($voucher->expires_at !== null && $voucher->expires_at->isBefore(now()->addMinutes(10)))
                ? $voucher->expires_at
                : now()->addMinutes(10);

            $attempt = StaffAuthenticationAttempt::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'purpose' => $voucher->purpose,
                'primary_method' => 'voucher',
                'phase' => match ($voucher->purpose) {
                    'mfa_reset' => 'mfa_required',
                    'activation' => 'email_required',
                    default => 'password_required',
                },
                'voucher_id' => $voucher->id,
                'expected_session_version' => $state->session_version,
                'expected_factor_generation' => $state->two_factor_generation,
                'expected_recovery_generation' => $state->recovery_generation,
                'session_binding_digest' => hash('sha256', $sessionId),
                'primary_verified_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $this->recordAuditEvent->handle('auth.staff_voucher.redeemed', [
                'user_id' => $user->id,
                'voucher_id' => $voucher->id,
                'purpose' => $voucher->purpose,
                'attempt_id' => $attempt->id,
            ], (string) $user->id);

            return $attempt;
        });

        if ($result === false) {
            throw new RuntimeException('The voucher credentials do not match.');
        }

        if ($this->request->hasSession()) {
            $this->request->session()->put('staff.pending_attempt_id', $result->id);
        }

        return $result;
    }
}
