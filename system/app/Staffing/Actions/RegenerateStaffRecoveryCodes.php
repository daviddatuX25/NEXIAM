<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class RegenerateStaffRecoveryCodes
{
    public function __construct(
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @return list<string> */
    public function handle(User $user, string $currentPassword, ?string $totpCode, Request $request): array
    {
        $session = $request->hasSession() ? $request->session() : session();
        $result = DB::transaction(function () use ($user, $currentPassword, $totpCode, $session): array {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $lockedUser->id)->lockForUpdate()->firstOrFail();
            $profile = $lockedUser->staffProfile()->lockForUpdate()->first();

            if (
                $profile === null
                || ! $profile->isActive()
                || (string) Auth::id() !== (string) $lockedUser->id
                || $session->get('auth.surface') !== 'staff'
                || $session->get('auth.session_version') === null
                || (int) $session->get('auth.session_version') !== (int) $state->session_version
                || ! Hash::check($currentPassword, $lockedUser->password)
            ) {
                throw ValidationException::withMessages([
                    'current_password' => 'The current account proof is invalid.',
                ]);
            }

            if ($state->hasConfirmedTwoFactor()) {
                $currentStep = (int) floor(now()->timestamp / 30);
                $step = is_string($totpCode)
                    ? (new Google2FA)->verifyKeyNewer(
                        $state->two_factor_secret,
                        $totpCode,
                        $state->two_factor_last_accepted_step ?? -1,
                        4,
                        $currentStep,
                    )
                    : false;

                if ($step === false) {
                    throw ValidationException::withMessages(['totp_code' => 'The authenticator code is invalid.']);
                }

                $state->forceFill(['two_factor_last_accepted_step' => $step])->save();
            }

            if ($state->two_factor_generation === null) {
                $state->forceFill(['two_factor_generation' => (string) Str::uuid()])->save();
            }

            $generation = (string) Str::uuid();
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
                    'generation' => $generation,
                    'code_digest' => hash('sha256', $clean),
                ]);
            }

            $state->update(['recovery_generation' => $generation]);
            $state = $this->invalidateStaffAuthentication->handle($lockedUser, $state);
            $this->recordAuditEvent->handle('auth.staff_mfa.recovery_codes_regenerated', [
                'user_id' => $lockedUser->id,
                'generation' => $generation,
            ], (string) $lockedUser->id);

            return [
                'codes' => $codes,
                'session_version' => (int) $state->session_version,
            ];
        });

        $session->put('auth.session_version', $result['session_version']);

        return $result['codes'];
    }
}
