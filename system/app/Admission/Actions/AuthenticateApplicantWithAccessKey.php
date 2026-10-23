<?php

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\ApplicantCapabilityGrants;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * D-085: authenticate with access key + birthdate (both required).
 * Does not consume the access key — it remains valid until registrar reissue revokes it.
 */
class AuthenticateApplicantWithAccessKey
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(string $accessKey, string $birthdate): User
    {
        if (! ApplicantAccessKey::isValidShape($accessKey)) {
            throw new RuntimeException('Invalid access key or birthdate.');
        }

        $tokenHash = ApplicantAccessKey::hash($accessKey);

        return DB::transaction(function () use ($tokenHash, $birthdate): User {
            $challenge = ApplicantAccessChallenge::query()
                ->where('token_hash', $tokenHash)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($challenge === null) {
                throw new RuntimeException('Invalid access key or birthdate.');
            }

            $identity = $challenge->identity()->lockForUpdate()->first();
            if ($identity === null) {
                throw new RuntimeException('Invalid access key or birthdate.');
            }

            $storedBirthdate = (string) ($identity->birthdate?->format('Y-m-d') ?? '');
            if ($storedBirthdate === '' || ! hash_equals($storedBirthdate, $birthdate)) {
                throw new RuntimeException('Invalid access key or birthdate.');
            }

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
                        throw new RuntimeException('This email cannot be used for applicant portal access.');
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
                throw new RuntimeException('Invalid access key or birthdate.');
            }

            ApplicantCapabilityGrants::sync($user);
            app(ClaimPreApplicationInquiries::class)->claimForUser($user);

            $this->recordAuditEvent->handle('admission.applicant_login', [
                'account_id' => $account->id,
                'identity_id' => $identity->id,
                'user_id' => $user->id,
            ], (string) $user->id);

            return $user;
        });
    }
}
