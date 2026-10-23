<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffEmailChange;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ConfirmStaffEmailChange
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
    ) {}

    public function findPending(string $token): ?StaffEmailChange
    {
        return StaffEmailChange::query()
            ->where('token_digest', hash('sha256', $token))
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function handle(string $token, User $actor, string $currentPassword, ?string $totpCode = null): User
    {
        $result = DB::transaction(function () use ($token, $actor, $currentPassword, $totpCode): array {
            $pending = StaffEmailChange::query()
                ->where('token_digest', hash('sha256', $token))
                ->whereNull('confirmed_at')
                ->whereNull('cancelled_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($pending === null || (string) $pending->user_id !== (string) $actor->id) {
                throw ValidationException::withMessages(['token' => 'This email confirmation link is invalid or expired.']);
            }

            $user = User::query()->whereKey($pending->user_id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();
            $record = StaffEmailChange::query()
                ->whereKey($pending->id)
                ->where('token_digest', hash('sha256', $token))
                ->whereNull('confirmed_at')
                ->whereNull('cancelled_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (
                $record === null
                || $state === null
                || ! $profile?->isActive()
                || (int) $state->session_version !== (int) $record->expected_session_version
                || session('auth.surface') !== 'staff'
                || session('auth.session_version') === null
                || (int) session('auth.session_version') !== (int) $state->session_version
                || ! Hash::check($currentPassword, $user->password)
            ) {
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

            if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($record->new_email)])
                ->where('id', '!=', $user->id)
                ->exists()
            ) {
                throw ValidationException::withMessages(['new_email' => 'This email address is already in use.']);
            }

            $user->forceFill([
                'email' => $record->new_email,
                'email_verified_at' => now(),
            ])->save();

            StaffProviderIdentity::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $state = $this->invalidateStaffAuthentication->handle($user, $state);
            $record->update(['confirmed_at' => now()]);

            $this->recordAuditEvent->handle('auth.staff_email.change_confirmed', [
                'user_id' => $user->id,
                'email_change_id' => $record->id,
                'old_email' => $record->old_email,
                'new_email' => $record->new_email,
            ], (string) $actor->id);

            return [
                'user' => $user->fresh(),
                'session_version' => (string) Auth::id() === (string) $user->id
                    ? (int) $state->session_version
                    : null,
            ];
        });

        if ($result['session_version'] !== null) {
            session(['auth.session_version' => $result['session_version']]);
        }

        return $result['user'];
    }
}
