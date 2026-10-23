<?php

namespace App\Admission\Actions;

use App\Admission\Mail\ApplicantAccessCredentialMail;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Testing\PracticeWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class IssueApplicantAccessChallenge
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ApplicantIdentity $identity,
        AdmissionAttempt $attempt,
        string $tokenHash,
        string $purpose,
        string $requestKey,
        ?CarbonInterface $expiresAt = null,
    ): ApplicantAccessChallenge {
        $resolvedExpiresAt = $expiresAt ?? ($purpose === 'login_voucher' ? now()->addHours(24) : now()->addYear());

        return DB::transaction(function () use ($identity, $attempt, $tokenHash, $purpose, $requestKey, $resolvedExpiresAt): ApplicantAccessChallenge {
            $challenge = ApplicantAccessChallenge::query()->create([
                'applicant_identity_id' => $identity->id,
                'admission_attempt_id' => $attempt->id,
                'purpose' => $purpose,
                'token_hash' => $tokenHash,
                'request_key' => $requestKey,
                // D-085: durable access key until registrar reissue (consumed_at = revoked); 24h for login_voucher.
                'expires_at' => $resolvedExpiresAt,
            ]);

            $this->recordAuditEvent->handle('admission.access_challenge_issued', [
                'challenge_id' => $challenge->id,
                'attempt_id' => $attempt->id,
                'purpose' => $purpose,
            ], 'system');

            return $challenge;
        });
    }

    /**
     * @return array{
     *     origin: string,
     *     access_key: string,
     *     voucher_token: string,
     *     qr_url: string,
     *     voucher_expires_at: string,
     *     email_status: string,
     *     recipient_email: string|null,
     *     reference_code: string,
     *     applicant_name: string,
     *     birthdate: string|null
     * }
     */
    public function issueHandoffBundle(
        ApplicantIdentity $identity,
        AdmissionAttempt $attempt,
        string $accessKey,
        string $origin,
        string $requestKeyPrefix,
    ): array {
        $voucherToken = bin2hex(random_bytes(16));
        $expiresAt = now()->addHours(24);

        $this->handle(
            $identity,
            $attempt,
            hash('sha256', $voucherToken),
            'login_voucher',
            $requestKeyPrefix.'-voucher-'.$attempt->id.'-'.now()->timestamp,
            $expiresAt,
        );

        $qrUrl = url('/applicant/login').'#v='.$voucherToken.'&k='.rawurlencode($accessKey);
        $recipientEmail = trim((string) ($attempt->contact_email ?: $identity->email ?: '')) ?: null;
        $applicantName = trim($identity->first_name.' '.$identity->last_name);
        $expiresAtIso = $expiresAt->toIso8601String();

        if ($recipientEmail !== null && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $isPractice = ! empty($attempt->practice_fixture_set_id)
                || ! empty($identity->practice_fixture_set_id)
                || app(PracticeWorkspace::class)->currentId() !== null;

            if ($isPractice) {
                $emailStatus = 'suppressed_practice';
            } else {
                try {
                    Mail::to($recipientEmail)->send(new ApplicantAccessCredentialMail(
                        applicantName: $applicantName,
                        referenceCode: (string) $attempt->reference_code,
                        accessKey: $accessKey,
                        qrLoginUrl: $qrUrl,
                        expiresAtIso: $expiresAtIso,
                    ));
                    $emailStatus = 'queued';
                } catch (\Throwable) {
                    $emailStatus = 'failed';
                }
            }
        } else {
            $emailStatus = 'skipped_no_email';
        }

        return [
            'origin' => $origin,
            'access_key' => $accessKey,
            'voucher_token' => $voucherToken,
            'qr_url' => $qrUrl,
            'voucher_expires_at' => $expiresAtIso,
            'email_status' => $emailStatus,
            'recipient_email' => $recipientEmail,
            'reference_code' => (string) $attempt->reference_code,
            'applicant_name' => $applicantName,
            'birthdate' => $identity->birthdate?->format('Y-m-d'),
        ];
    }
}
