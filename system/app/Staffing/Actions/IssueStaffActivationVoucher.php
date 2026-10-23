<?php

namespace App\Staffing\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Staffing\Support\StaffVoucherLifetime;
use App\Staffing\Support\StaffVoucherTargetAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class IssueStaffActivationVoucher
{
    public function __construct(
        private readonly StaffVoucherTargetAuthority $targetAuthority,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly StaffVoucherLifetime $lifetime,
    ) {}

    /** @return array{public_reference: string, token: string, fallback: string, mode: string, expires_at: ?string, qr_url: string} */
    public function handle(
        User $actor,
        User $target,
        string $purpose = 'activation',
        string $mode = 'standard_24h',
        ?string $customExpiry = null,
        ?string $timezone = null,
        bool $replaceExisting = false,
    ): array {
        if (! $this->targetAuthority->permits($actor, $target)) {
            throw new RuntimeException('Actor cannot issue a voucher for this staff member.');
        }
        $institutionId = (int) $target->staffProfile?->institution_id;
        $isHr = $this->authorizer->decideFresh($actor, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed;
        if (! in_array($purpose, ['activation', 'password_reset', 'mfa_reset'], true)) {
            throw new RuntimeException('Unsupported staff voucher purpose.');
        }

        $expiresAt = $this->lifetime->expiry(
            $purpose,
            $mode,
            $customExpiry,
            $timezone,
            $isHr,
        );
        $reference = substr(self::base32(random_bytes(7)), 0, 10);
        $token = bin2hex(random_bytes(16));
        $fallback = self::base32(random_bytes(5));
        $fallbackDisplay = substr($fallback, 0, 4).'-'.substr($fallback, 4, 4);

        DB::transaction(function () use ($actor, $target, $purpose, $mode, $reference, $token, $fallback, $expiresAt, $replaceExisting): void {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $hasUnrevokedVoucher = StaffActivationVoucher::query()
                ->where('user_id', $lockedTarget->id)
                ->where('purpose', $purpose)
                ->whereNull('revoked_at')
                ->exists();
            if ($hasUnrevokedVoucher && ! $replaceExisting) {
                throw new RuntimeException('A prior voucher exists. Confirm explicit replacement to invalidate it and its pending attempt.');
            }
            $state = StaffAuthenticationState::query()->where('user_id', $lockedTarget->id)->lockForUpdate()->first();
            $profile = $lockedTarget->staffProfile()->lockForUpdate()->first();
            if ($profile === null || ! $profile->isActive()) {
                throw new RuntimeException('Target is not an active staff account.');
            }
            $isDraftAccount = $lockedTarget->email !== null && (
                str_ends_with(strtolower($lockedTarget->email), '@nexiam.test') ||
                str_ends_with(strtolower($lockedTarget->email), '.nexiam.test')
            );
            if ($mode === 'never_expires_single_use' && $state?->activation_completed_at !== null && ! $isDraftAccount) {
                throw new RuntimeException('Non-expiring vouchers cannot be issued for staff accounts that have already completed activation.');
            }
            if ($state === null) {
                StaffAuthenticationState::query()->create([
                    'user_id' => $lockedTarget->id,
                    'activation_completed_at' => null,
                    'password_set_at' => null,
                    'must_change_password' => false,
                    'session_version' => 1,
                    'mfa_required' => false,
                    'two_factor_generation' => (string) Str::uuid(),
                ]);
            } elseif ($purpose === 'activation' && $isDraftAccount) {
                $state->forceFill(['activation_completed_at' => null])->save();
            }

            StaffActivationVoucher::query()
                ->where('user_id', $lockedTarget->id)
                ->where('purpose', $purpose)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'voucher_reissued',
                ]);
            StaffAuthenticationAttempt::query()
                ->where('user_id', $lockedTarget->id)
                ->whereIn('voucher_id', StaffActivationVoucher::query()
                    ->select('id')
                    ->where('user_id', $lockedTarget->id))
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'voucher_reissued',
                ]);
            StaffActivationVoucher::query()->create([
                'id' => (string) Str::uuid(),
                'public_reference' => $reference,
                'user_id' => $lockedTarget->id,
                'purpose' => $purpose,
                'mode' => $mode,
                'token_digest' => hash('sha256', $token),
                'fallback_hash' => password_hash($fallback, PASSWORD_ARGON2ID),
                'issued_by_user_id' => $actor->id,
                'expires_at' => $expiresAt,
                'failed_attempts' => 0,
                'lifetime_fallback_attempts' => 0,
                'fallback_disabled' => false,
            ]);
            $this->recordAuditEvent->handle('hr.voucher.issued', [
                'user_id' => $lockedTarget->id,
                'purpose' => $purpose,
                'mode' => $mode,
                'public_reference' => $reference,
                'expires_at' => $expiresAt?->toIso8601String(),
            ], (string) $actor->id);
        });

        return [
            'public_reference' => $reference,
            'token' => $token,
            'fallback' => $fallbackDisplay,
            'mode' => $mode,
            'expires_at' => $expiresAt?->toIso8601String(),
            'qr_url' => url('/staff/activate').'#v='.$reference.'&t='.$token,
        ];
    }

    private static function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($bytes) as $byte) {
            $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) === 5) {
                $encoded .= $alphabet[bindec($chunk)];
            }
        }

        return $encoded;
    }
}
