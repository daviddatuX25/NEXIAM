<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ConfirmStaffTwoFactorEnrollment
{
    public function __construct(
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @return list<string> */
    public function handle(string $attemptId, string $code, Request $request): array
    {
        $attemptSnapshot = StaffAuthenticationAttempt::query()->find($attemptId);
        if ($attemptSnapshot === null) {
            throw new RuntimeException('The account setup attempt is not active.');
        }

        return DB::transaction(function () use ($attemptSnapshot, $attemptId, $code, $request): array {
            $user = User::query()->whereKey($attemptSnapshot->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();
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
                $profile === null
                || ! $profile->isActive()
                || $attempt === null
                || ! in_array($attempt->purpose, ['mfa_enrollment', 'activation', 'password_reset', 'mfa_reset'], true)
                || ($attempt->primary_method === 'google'
                    && ($providerIdentity === null
                        || $attempt->pending_google_subject === null
                        || $attempt->pending_google_subject !== $attemptSnapshot->pending_google_subject))
                || ($attempt->primary_method !== 'google' && $attempt->pending_google_subject !== null)
                || $attempt->phase !== 'mfa_required'
                || $attempt->primary_verified_at === null
                || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId())))
                || $attempt->expected_session_version === null
                || (int) $attempt->expected_session_version !== (int) $state->session_version
                || $attempt->expected_factor_generation === null
                || $state->two_factor_generation === null
                || ! hash_equals((string) $attempt->expected_factor_generation, (string) $state->two_factor_generation)
                || $attempt->expected_recovery_generation !== $state->recovery_generation
                || $state->hasConfirmedTwoFactor()
                || $state->password_set_at === null
                || $state->must_change_password
                || ($attempt->purpose !== 'activation' && ! $state->isOnboarded())
                || $state->pending_enrollment_attempt_id !== $attempt->id
                || $state->pending_two_factor_expires_at?->isFuture() !== true
                || ! is_string($state->pending_two_factor_secret)
            ) {
                throw new RuntimeException('The authenticator enrollment attempt is not active.');
            }

            $voucherPurpose = in_array($attempt->purpose, ['activation', 'password_reset', 'mfa_reset'], true);
            if (
                ($voucherPurpose && ($attempt->primary_method !== 'voucher' || $attempt->voucher_id === null))
                || ($attempt->purpose === 'mfa_enrollment'
                    && (! in_array($attempt->primary_method, ['password', 'google'], true)
                        || $attempt->voucher_id !== null
                        || ($attempt->primary_method === 'google' && ($attempt->pending_google_subject === null || $providerIdentity === null))
                        || ($attempt->primary_method === 'password' && $attempt->pending_google_subject !== null)))
            ) {
                throw new RuntimeException('The authenticator enrollment attempt is not active.');
            }

            if ($voucherPurpose) {
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
                    throw new RuntimeException('The account setup voucher is no longer active.');
                }
            }
            $pendingSecret = $state->pending_two_factor_secret;

            $step = self::validTotp(
                $pendingSecret,
                $code,
                now()->timestamp,
                -1,
            );
            if ($step === false) {
                throw new RuntimeException('The authenticator code is invalid.');
            }

            $recoveryGeneration = (string) Str::uuid();
            StaffTwoFactorRecoveryCode::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $codes = [];
            for ($index = 0; $index < 8; $index++) {
                $rawCode = bin2hex(random_bytes(16));
                $normalizedCode = strtolower(str_replace('-', '', $rawCode));
                $codes[] = implode('-', str_split($normalizedCode, 4));

                StaffTwoFactorRecoveryCode::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'generation' => $recoveryGeneration,
                    'code_digest' => hash('sha256', $normalizedCode),
                ]);
            }

            $state->forceFill([
                'two_factor_secret' => $pendingSecret,
                'two_factor_confirmed_at' => now(),
                'two_factor_last_accepted_step' => $step,
                'recovery_generation' => $recoveryGeneration,
            ])->save();
            $state = $this->invalidateStaffAuthentication->handle($user, $state, $attempt->id, factorChanged: true);

            $attempt->forceFill([
                'phase' => 'ready_to_complete',
                'mfa_verified_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle('auth.staff_mfa.enrolled', [
                'user_id' => $user->id,
                'attempt_id' => $attempt->id,
                'purpose' => $attempt->purpose,
                'generation' => $state->two_factor_generation,
            ], (string) $user->id);

            return $codes;
        });
    }

    private static function validTotp(string $secret, string $code, int $timestamp, int $oldStep): int|false
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $secret) ?? '');
        $code = preg_replace('/\D/', '', $code);
        if ($normalized === '' || preg_match('/^[A-Z2-7]+$/D', $normalized) !== 1 || strlen($code) !== 6) {
            return false;
        }
        $key = self::decodeBase32($normalized);
        if ($key === '') {
            return false;
        }

        $counter = intdiv($timestamp, 30);
        for ($offset = 2; $offset >= -2; $offset--) {
            $value = $counter + $offset;
            if ($value < 0 || $value <= $oldStep) {
                continue;
            }
            $counterBytes = pack('N2', 0, $value);
            $hash = hash_hmac('sha1', $counterBytes, $key, true);
            $position = ord($hash[19]) & 0x0F;
            $binary = ((ord($hash[$position]) & 0x7F) << 24)
                | ((ord($hash[$position + 1]) & 0xFF) << 16)
                | ((ord($hash[$position + 2]) & 0xFF) << 8)
                | (ord($hash[$position + 3]) & 0xFF);
            if (hash_equals(str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT), $code)) {
                return $value;
            }
        }

        return false;
    }

    private static function decodeBase32(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($encoded) as $character) {
            $index = strpos($alphabet, $character);
            if ($index === false) {
                return '';
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
