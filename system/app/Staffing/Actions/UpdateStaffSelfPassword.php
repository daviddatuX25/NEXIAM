<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class UpdateStaffSelfPassword
{
    public function __construct(
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $user, string $currentPassword, string $newPassword, ?string $totpCode = null): void
    {
        $sessionVersionToRetain = DB::transaction(function () use ($user, $currentPassword, $newPassword, $totpCode): ?int {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $lockedUser->id)->lockForUpdate()->first();
            $profile = $lockedUser->staffProfile()->lockForUpdate()->first();

            if ($state === null || ! $profile?->isActive()) {
                throw ValidationException::withMessages(['current_password' => trans('auth.failed')]);
            }

            $sessionVersion = session('auth.session_version');
            $isCurrentStaffSession = (string) Auth::id() === (string) $lockedUser->id
                && session('auth.surface') === 'staff'
                && $sessionVersion !== null
                && (int) $sessionVersion === (int) $state->session_version;

            if (
                (string) Auth::id() === (string) $lockedUser->id
                && ! $isCurrentStaffSession
            ) {
                throw ValidationException::withMessages(['current_password' => trans('auth.failed')]);
            }

            if (! Hash::check($currentPassword, $lockedUser->password)) {
                throw ValidationException::withMessages(['current_password' => trans('auth.failed')]);
            }

            if ($state->hasConfirmedTwoFactor()) {
                $step = $totpCode === null || $state->two_factor_secret === null
                    ? false
                    : (new Google2FA)->verifyKeyNewer(
                        $state->two_factor_secret,
                        $totpCode,
                        $state->two_factor_last_accepted_step ?? -1,
                    );

                if ($step === false) {
                    throw ValidationException::withMessages(['totp_code' => trans('auth.failed')]);
                }

                $state->forceFill(['two_factor_last_accepted_step' => $step])->save();
            }

            if ($state->two_factor_generation === null) {
                $state->forceFill(['two_factor_generation' => (string) Str::uuid()])->save();
            }

            $lockedUser->forceFill(['password' => Hash::make($newPassword)])->save();
            $state->forceFill([
                'password_set_at' => now(),
                'must_change_password' => false,
            ])->save();

            $state = $this->invalidateStaffAuthentication->handle($lockedUser, $state);

            $this->recordAuditEvent->handle('auth.staff_password.changed', [
                'user_id' => $lockedUser->id,
            ], (string) $lockedUser->id);

            return $isCurrentStaffSession ? (int) $state->session_version : null;
        });

        if ($sessionVersionToRetain !== null) {
            session(['auth.session_version' => $sessionVersionToRetain]);
        }
    }
}
