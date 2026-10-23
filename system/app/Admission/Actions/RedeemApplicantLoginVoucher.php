<?php

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantCapabilityGrants;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Redeem a single-use 24-hour QR login voucher for applicant portal sign-in.
 * Consumes only the `login_voucher` challenge while leaving the durable `access_key` challenge intact.
 */
class RedeemApplicantLoginVoucher
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(string $voucherToken): ?User
    {
        $normalized = strtolower(trim($voucherToken));
        if (preg_match('/^[0-9a-f]{32}$/', $normalized) !== 1) {
            return null;
        }

        $tokenHash = hash('sha256', $normalized);

        return DB::transaction(function () use ($tokenHash): ?User {
            $challenge = ApplicantAccessChallenge::query()
                ->where('purpose', 'login_voucher')
                ->where('token_hash', $tokenHash)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($challenge === null) {
                return null;
            }

            $identity = $challenge->identity()->lockForUpdate()->first();
            if ($identity === null) {
                return null;
            }

            $challenge->update([
                'consumed_at' => now(),
            ]);

            $account = ApplicantAccount::query()
                ->where('applicant_identity_id', $identity->id)
                ->first();

            if ($account === null) {
                $email = $identity->email;
                if ($email === null || $email === '') {
                    $email = sprintf('applicant.%d@internal.nexiam.local', (int) $identity->id);
                }

                $existingUser = User::query()->where('email', $email)->first();
                if ($existingUser !== null && ! $existingUser->staffProfile()->exists() && ! $existingUser->applicantAccount()->exists()) {
                    $user = $existingUser;
                } else {
                    if ($existingUser !== null && $existingUser->staffProfile()->exists()) {
                        return null;
                    }
                    if ($existingUser !== null) {
                        $email = sprintf('applicant.%d.%s@internal.nexiam.local', (int) $identity->id, Str::lower(Str::random(8)));
                    }

                    $user = User::query()->create([
                        'name' => trim($identity->first_name.' '.$identity->last_name),
                        'email' => $email,
                        'password' => Str::password(40),
                    ]);
                }

                $account = ApplicantAccount::query()->create([
                    'applicant_identity_id' => $identity->id,
                    'user_id' => $user->id,
                    'setup_completed_at' => now(),
                ]);
            }

            $user = $account->user;
            if ($user === null) {
                return null;
            }

            ApplicantCapabilityGrants::sync($user);
            app(ClaimPreApplicationInquiries::class)->claimForUser($user);

            $this->recordAuditEvent->handle('admission.applicant_voucher_login', [
                'account_id' => $account->id,
                'identity_id' => $identity->id,
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
            ], (string) $user->id);

            return $user;
        });
    }
}
