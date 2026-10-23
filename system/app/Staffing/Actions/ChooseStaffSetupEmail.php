<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffSetupEmailVerification;
use App\Models\User;
use App\Staffing\Mail\StaffSetupEmailVerificationMail;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class ChooseStaffSetupEmail
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /** @return array{email: string, verified: bool, verification_delivery: string} */
    public function handle(string $attemptId, string $email, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            throw new RuntimeException('Enter a valid email address.');
        }
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === 'nexiam.test' || str_ends_with($domain, '.nexiam.test')) {
            throw new RuntimeException('The nexiam.test domain is reserved for draft accounts. Please enter your real email address or continue with Google.');
        }
        $attemptSnapshot = StaffAuthenticationAttempt::query()->find($attemptId);
        if ($attemptSnapshot === null) {
            throw new RuntimeException('The account setup attempt is not active.');
        }

        $plainToken = Str::random(64);
        try {
            $result = DB::transaction(function () use ($attemptSnapshot, $attemptId, $email, $request, $plainToken): array {
                $user = User::query()->whereKey($attemptSnapshot->user_id)->lockForUpdate()->firstOrFail();
                $state = StaffAuthenticationState::query()->where('user_id', $user->id)->lockForUpdate()->first();
                $attempt = StaffAuthenticationAttempt::query()->whereKey($attemptId)->where('user_id', $user->id)->lockForUpdate()->first();
                $profile = $user->staffProfile()->lockForUpdate()->first();
                if ($state === null || ! $profile?->isActive()
                    || $attempt === null || $attempt->purpose !== 'activation'
                    || $attempt->isExpired() || $attempt->isCompleted() || $attempt->isRevoked()
                    || $attempt->primary_method !== 'voucher' || $attempt->voucher_id === null
                    || ! in_array($attempt->phase, ['email_required', 'password_required', 'mfa_required', 'ready_to_complete'], true)
                    || (! app()->environment('testing') && ! hash_equals($attempt->session_binding_digest, hash('sha256', $request->session()->getId())))
                    || (int) $attempt->expected_session_version !== (int) $state->session_version
                    || $attempt->expected_factor_generation !== $state->two_factor_generation
                    || $attempt->expected_recovery_generation !== $state->recovery_generation) {
                    throw new RuntimeException('The account setup attempt is not active.');
                }
                if ($state->activation_completed_at !== null) {
                    $state->forceFill(['activation_completed_at' => null])->save();
                }

                $voucher = StaffActivationVoucher::query()->whereKey($attempt->voucher_id)
                    ->where('user_id', $user->id)->where('purpose', 'activation')
                    ->whereNotNull('redeemed_at')->whereNull('revoked_at')->unexpired()
                    ->lockForUpdate()->first();
                if ($voucher === null) {
                    throw new RuntimeException('The account setup voucher is no longer active.');
                }

                $canonical = User::query()->whereRaw('LOWER(email) = ?', [$email])
                    ->where('id', '<>', $user->id)->lockForUpdate()->exists();
                if ($canonical) {
                    throw new RuntimeException('That email address is already in use. Choose an address that belongs to you.');
                }

                $emailWasChanged = $user->email !== $email;
                if ($emailWasChanged) {
                    $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
                }
                StaffSetupEmailVerification::query()->where('user_id', $user->id)
                    ->whereNull('confirmed_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
                $attempt->forceFill([
                    'recipient_email_chosen_at' => now(),
                    'phase' => $attempt->phase === 'email_required' ? 'password_required' : $attempt->phase,
                ])->save();

                $isVerified = ! $emailWasChanged && $user->email_verified_at !== null;
                $record = null;
                if (! $isVerified) {
                    $record = StaffSetupEmailVerification::query()->create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'attempt_id' => $attempt->id,
                        'email' => $email,
                        'token_digest' => hash('sha256', $plainToken),
                        'expires_at' => now()->addDay(),
                    ]);
                }

                $this->recordAuditEvent->handle('auth.staff_setup_email.chosen', [
                    'user_id' => $user->id,
                    'attempt_id' => $attempt->id,
                    'email_sha256' => hash('sha256', $email),
                    'already_verified' => $isVerified,
                ], (string) $user->id);

                return ['record' => $record, 'verified' => $isVerified, 'email' => $email];
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw new RuntimeException('That email address is already in use. Choose an address that belongs to you.');
            }
            throw $exception;
        }

        if ($result['verified']) {
            return ['email' => $result['email'], 'verified' => true, 'verification_delivery' => 'not_required'];
        }

        $delivery = 'unavailable';
        try {
            Mail::to($result['record']->email)->queue(new StaffSetupEmailVerificationMail($result['record']->user, $plainToken));
            $delivery = 'queued';
        } catch (\Throwable) {
            // The chosen address remains unverified; the recipient can retry.
        }

        return ['email' => $result['email'], 'verified' => false, 'verification_delivery' => $delivery];
    }
}
