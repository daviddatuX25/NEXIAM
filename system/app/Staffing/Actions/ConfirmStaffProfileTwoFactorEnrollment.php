<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ConfirmStaffProfileTwoFactorEnrollment
{
    public function __construct(
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @return list<string> */
    public function handle(User $user, string $code, Request $request): array
    {
        $session = $request->hasSession() ? $request->session() : session();
        $attemptId = $session->get('staff.pending_profile_enrollment_attempt_id');
        if (! is_string($attemptId)) {
            throw ValidationException::withMessages(['code' => 'The authenticator enrollment has expired. Start again to continue.']);
        }

        $attemptSnapshot = StaffAuthenticationAttempt::query()->whereKey($attemptId)->first();
        if ($attemptSnapshot === null || (string) $attemptSnapshot->user_id !== (string) $user->id) {
            throw ValidationException::withMessages(['code' => 'The authenticator enrollment has expired. Start again to continue.']);
        }

        $result = DB::transaction(function () use ($user, $attemptId, $code, $session): array {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->firstOrFail();
            $profile = $lockedUser->staffProfile()->lockForUpdate()->first();
            $attempt = StaffAuthenticationAttempt::query()
                ->whereKey($attemptId)
                ->where('user_id', $lockedUser->id)
                ->where('purpose', 'mfa_profile_enrollment')
                ->where('primary_method', 'password')
                ->where('phase', 'mfa_required')
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (
                $profile === null
                || ! $profile->isActive()
                || ! $state->isOnboarded()
                || $state->must_change_password
                || $state->hasConfirmedTwoFactor()
                || $state->two_factor_generation === null
                || $attempt === null
                || $attempt->primary_verified_at === null
                || $attempt->expected_session_version === null
                || (int) $attempt->expected_session_version !== (int) $state->session_version
                || $attempt->expected_factor_generation === null
                || ! hash_equals((string) $attempt->expected_factor_generation, (string) $state->two_factor_generation)
                || $attempt->expected_recovery_generation !== $state->recovery_generation
                || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', (string) $session->getId())))
                || $state->pending_enrollment_attempt_id !== $attempt->id
                || $state->pending_two_factor_expires_at?->isFuture() !== true
                || ! is_string($state->pending_two_factor_secret)
                || (string) Auth::id() !== (string) $lockedUser->id
                || $session->get('auth.surface') !== 'staff'
                || $session->get('auth.session_version') === null
                || (int) $session->get('auth.session_version') !== (int) $state->session_version
            ) {
                throw ValidationException::withMessages(['code' => 'The authenticator enrollment has expired. Start again to continue.']);
            }

            $cleanCode = preg_replace('/\D/', '', $code);
            if (strlen($cleanCode) !== 6) {
                throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
            }

            $step = (new Google2FA)->verifyKeyNewer(
                $state->pending_two_factor_secret,
                $cleanCode,
                -1,
                2,
            );
            if ($step === false) {
                throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
            }

            $pendingSecret = $state->pending_two_factor_secret;
            $recoveryGeneration = (string) Str::uuid();
            StaffTwoFactorRecoveryCode::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $codes = [];
            for ($index = 0; $index < 8; $index++) {
                $clean = bin2hex(random_bytes(16));
                $codes[] = implode('-', str_split($clean, 4));
                StaffTwoFactorRecoveryCode::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $lockedUser->id,
                    'generation' => $recoveryGeneration,
                    'code_digest' => hash('sha256', $clean),
                ]);
            }

            $state->forceFill([
                'two_factor_secret' => $pendingSecret,
                'two_factor_confirmed_at' => now(),
                'two_factor_last_accepted_step' => $step,
                'recovery_generation' => $recoveryGeneration,
            ])->save();
            $state = $this->invalidateStaffAuthentication->handle(
                $lockedUser,
                $state,
                $attempt->id,
                factorChanged: true,
            );
            $attempt->forceFill([
                'phase' => 'completed',
                'mfa_verified_at' => now(),
                'completed_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle('auth.staff_mfa.profile_enrollment_confirmed', [
                'user_id' => $lockedUser->id,
                'attempt_id' => $attempt->id,
                'generation' => $state->two_factor_generation,
            ], (string) $lockedUser->id);

            return ['recovery_codes' => $codes, 'session_version' => (int) $state->session_version];
        });

        $session->put('auth.session_version', $result['session_version']);
        $session->forget('staff.pending_profile_enrollment_attempt_id');

        return $result['recovery_codes'];
    }
}
