<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffEmailChange;
use App\Models\User;
use App\Staffing\Mail\StaffEmailChangeVerificationMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class RequestStaffEmailChange
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @return array{new_email: string, expires_at: Carbon}
     */
    public function handle(User $user, string $newEmail, string $currentPassword, ?string $totpCode = null): array
    {
        $newEmail = mb_strtolower(trim($newEmail));
        validator(['new_email' => $newEmail], [
            'new_email' => ['required', 'string', 'email', 'max:255'],
        ])->validate();
        if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['new_email' => 'This email address is already in use.']);
        }

        $plainToken = bin2hex(random_bytes(16));

        $record = DB::transaction(function () use ($user, $newEmail, $currentPassword, $totpCode, $plainToken): StaffEmailChange {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $lockedUser->id)->lockForUpdate()->first();

            if (! Hash::check($currentPassword, $lockedUser->password)) {
                throw ValidationException::withMessages(['current_password' => trans('auth.failed')]);
            }

            if ($state === null) {
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

            if ($newEmail === mb_strtolower($lockedUser->email)) {
                throw ValidationException::withMessages(['new_email' => 'Choose an email address different from your current address.']);
            }
            if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->where('id', '!=', $lockedUser->id)->exists()) {
                throw ValidationException::withMessages(['new_email' => 'This email address is already in use.']);
            }

            StaffEmailChange::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('confirmed_at')
                ->whereNull('cancelled_at')
                ->update(['cancelled_at' => now()]);

            $record = StaffEmailChange::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $lockedUser->id,
                'initiator_user_id' => $lockedUser->id,
                'old_email' => $lockedUser->email,
                'new_email' => $newEmail,
                'expected_session_version' => $state->session_version,
                'token_digest' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(24),
            ]);

            $this->recordAuditEvent->handle('auth.staff_email.change_requested', [
                'user_id' => $lockedUser->id,
                'old_email' => $lockedUser->email,
                'new_email' => $newEmail,
                'email_change_id' => $record->id,
            ], (string) $lockedUser->id);

            return $record;
        });

        Mail::to($newEmail)->queue(new StaffEmailChangeVerificationMail($user, $plainToken));

        return ['new_email' => $newEmail, 'expires_at' => $record->expires_at];
    }
}
