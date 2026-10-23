<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffSetupEmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConfirmStaffSetupEmail
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(string $plainToken): void
    {
        $digest = hash('sha256', $plainToken);
        $candidate = StaffSetupEmailVerification::query()->where('token_digest', $digest)->first();
        if ($candidate === null) {
            throw new RuntimeException('This email confirmation link is invalid or expired.');
        }

        DB::transaction(function () use ($candidate, $digest): void {
            $user = User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $attempt = StaffAuthenticationAttempt::query()->whereKey($candidate->attempt_id)
                ->where('user_id', $user->id)->lockForUpdate()->first();
            $verification = StaffSetupEmailVerification::query()->whereKey($candidate->id)
                ->where('token_digest', $digest)->lockForUpdate()->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            if ($state === null || ! $profile?->isActive()
                || $attempt === null || $attempt->purpose !== 'activation' || $attempt->primary_method !== 'voucher'
                || $attempt->voucher_id === null || $attempt->isRevoked()
                || (int) $attempt->expected_session_version !== (int) $state->session_version
                || $attempt->expected_factor_generation !== $state->two_factor_generation
                || $attempt->expected_recovery_generation !== $state->recovery_generation
                || $verification === null || $verification->attempt_id !== $attempt->id
                || $verification->confirmed_at !== null || $verification->cancelled_at !== null
                || $verification->expires_at->isPast() || $user->email !== $verification->email
                || $user->email_verified_at !== null) {
                throw new RuntimeException('This email confirmation link is invalid or expired.');
            }

            $user->forceFill(['email_verified_at' => now()])->save();
            $verification->forceFill(['confirmed_at' => now()])->save();
            $this->recordAuditEvent->handle('auth.staff_setup_email.verified', [
                'user_id' => $user->id,
                'attempt_id' => $attempt->id,
                'verification_id' => $verification->id,
            ], (string) $user->id);
        });
    }
}
