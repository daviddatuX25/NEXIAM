<?php

namespace App\Admission\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use Illuminate\Support\Facades\DB;

class IssueApplicantAccessChallenge
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        ApplicantIdentity $identity,
        AdmissionAttempt $attempt,
        string $tokenHash,
        string $purpose,
        string $requestKey,
    ): ApplicantAccessChallenge {
        return DB::transaction(function () use ($identity, $attempt, $tokenHash, $purpose, $requestKey): ApplicantAccessChallenge {
            $challenge = ApplicantAccessChallenge::query()->create([
                'applicant_identity_id' => $identity->id,
                'admission_attempt_id' => $attempt->id,
                'purpose' => $purpose,
                'token_hash' => $tokenHash,
                'request_key' => $requestKey,
                // D-085: durable access key until registrar reissue (consumed_at = revoked).
                'expires_at' => now()->addYear(),
            ]);

            $this->recordAuditEvent->handle('admission.access_challenge_issued', [
                'challenge_id' => $challenge->id,
                'attempt_id' => $attempt->id,
                'purpose' => $purpose,
            ], 'system');

            return $challenge;
        });
    }
}
