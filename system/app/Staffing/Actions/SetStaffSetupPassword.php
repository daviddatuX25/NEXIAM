<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Auth\Support\StaffMfaPolicy;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SetStaffSetupPassword
{
    public function __construct(
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly StaffMfaPolicy $mfaPolicy,
    ) {}

    public function handle(string $attemptId, string $password, Request $request): StaffAuthenticationAttempt
    {
        $attemptSnapshot = StaffAuthenticationAttempt::query()->find($attemptId);
        if ($attemptSnapshot === null) {
            throw new RuntimeException('The account setup attempt is not active.');
        }

        return DB::transaction(function () use ($attemptSnapshot, $attemptId, $password, $request): StaffAuthenticationAttempt {
            $user = User::query()->whereKey($attemptSnapshot->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            $attempt = StaffAuthenticationAttempt::query()
                ->whereKey($attemptId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (
                $state === null
                || ! $profile?->isActive()
                || $attempt === null
                || ! in_array($attempt->purpose, ['activation', 'password_reset'], true)
                || $attempt->phase !== 'password_required'
                || $attempt->isExpired()
                || $attempt->isCompleted()
                || $attempt->isRevoked()
                || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId())))
                || $attempt->expected_session_version === null
                || (int) $attempt->expected_session_version !== (int) $state->session_version
                || $attempt->expected_factor_generation === null
                || $state->two_factor_generation === null
                || ! hash_equals((string) $attempt->expected_factor_generation, (string) $state->two_factor_generation)
                || $attempt->expected_recovery_generation !== $state->recovery_generation
                || $attempt->voucher_id === null
            ) {
                throw new RuntimeException('The account setup attempt is not active.');
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
                throw new RuntimeException('The account setup voucher is no longer active.');
            }

            $user->forceFill(['password' => Hash::make($password)])->save();
            $state->forceFill([
                'password_set_at' => now(),
                'must_change_password' => false,
            ])->save();

            $state = $this->invalidateStaffAuthentication->handle($user, $state, $attempt->id);
            $requiresMfa = $state->hasConfirmedTwoFactor() || $this->mfaPolicy->isMfaRequired($user);
            $attempt->forceFill([
                'phase' => $requiresMfa ? 'mfa_required' : 'ready_to_complete',
            ])->save();

            $this->recordAuditEvent->handle('auth.staff_setup_password.changed', [
                'user_id' => $user->id,
                'attempt_id' => $attempt->id,
                'purpose' => $attempt->purpose,
            ], (string) $user->id);

            return $attempt->fresh();
        });
    }
}
