<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class BeginStaffTwoFactorReEnrollment
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /** @return array{secret: string, qr_svg: string, expires_at: Carbon} */
    public function handle(User $user, string $currentPassword, OmrQrCodeRenderer $qr, Request $request): array
    {
        $secret = (new Google2FA)->generateSecretKey();
        $expiresAt = now()->addMinutes(10);

        $session = $request->hasSession() ? $request->session() : session();
        $result = DB::transaction(function () use ($user, $currentPassword, $secret, $expiresAt, $session): array {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $lockedUser->id)->lockForUpdate()->firstOrFail();
            $profile = $lockedUser->staffProfile()->lockForUpdate()->first();

            if (
                $profile === null
                || ! $profile->isActive()
                || ! $state->isOnboarded()
                || $state->must_change_password
                || ! $state->hasConfirmedTwoFactor()
                || $state->two_factor_secret === null
                || $session->get('auth.surface') !== 'staff'
                || $session->get('auth.session_version') === null
                || (int) $session->get('auth.session_version') !== (int) $state->session_version
                || ! Hash::check($currentPassword, $lockedUser->password)
            ) {
                throw ValidationException::withMessages(['current_password' => 'The current account proof is invalid.']);
            }

            if ($state->two_factor_generation === null) {
                $state->forceFill(['two_factor_generation' => (string) Str::uuid()])->save();
            }

            if ($state->pending_enrollment_attempt_id !== null) {
                StaffAuthenticationAttempt::query()
                    ->whereKey($state->pending_enrollment_attempt_id)
                    ->where('user_id', $lockedUser->id)
                    ->where('purpose', 'mfa_reenrollment')
                    ->whereNull('completed_at')
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            $attempt = StaffAuthenticationAttempt::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $lockedUser->id,
                'purpose' => 'mfa_reenrollment',
                'primary_method' => 'password',
                'phase' => 'mfa_required',
                'expected_session_version' => $state->session_version,
                'expected_factor_generation' => $state->two_factor_generation,
                'expected_recovery_generation' => $state->recovery_generation,
                'session_binding_digest' => hash('sha256', (string) $session->getId()),
                'primary_verified_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $state->forceFill([
                'pending_two_factor_secret' => $secret,
                'pending_two_factor_expires_at' => $expiresAt,
                'pending_enrollment_attempt_id' => $attempt->id,
            ])->save();

            $this->recordAuditEvent->handle('auth.staff_mfa.reenrollment_started', [
                'user_id' => $lockedUser->id,
                'attempt_id' => $attempt->id,
            ], (string) $lockedUser->id);

            return [
                'attempt_id' => $attempt->id,
                'email' => $lockedUser->email,
            ];
        });

        $request->session()->put('staff.pending_reenrollment_attempt_id', $result['attempt_id']);
        $label = rawurlencode('NEXIAM:'.$result['email']);
        $uri = 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer=NEXIAM&digits=6&period=30';

        return [
            'secret' => $secret,
            'qr_svg' => 'data:image/svg+xml;base64,'.base64_encode($qr->svg($uri, 240)),
            'expires_at' => $expiresAt,
        ];
    }
}
