<?php

namespace App\Admission\Actions;

use App\Admission\Support\ApplicantAccessKey;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-085 registrar-assisted key reset — revoke prior hashes, mint once, audit.
 */
class ReissueApplicantAccessKey
{
    public function __construct(
        private readonly IssueApplicantAccessChallenge $issueChallenge,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @return array{attempt: AdmissionAttempt, access_token: string}
     */
    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, string $reason = ''): array
    {
        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $reason): array {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status === AdmissionAttempt::STATUS_ARCHIVED) {
                throw new RuntimeException('Cannot reissue access key for an archived application.');
            }

            $identity = $attempt->identity()->lockForUpdate()->firstOrFail();

            ApplicantAccessChallenge::query()
                ->where('applicant_identity_id', $identity->id)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get()
                ->each(function (ApplicantAccessChallenge $challenge): void {
                    $challenge->update(['consumed_at' => now()]);
                });

            $token = ApplicantAccessKey::mint();
            $this->issueChallenge->handle(
                $identity,
                $attempt,
                ApplicantAccessKey::hash($token),
                'access_key',
                'reissue-'.$attempt->id.'-'.now()->timestamp,
            );

            $attempt->update([
                'version' => $attempt->version + 1,
            ]);

            $this->recordAuditEvent->handle('admission.access_key_reissued', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'identity_id' => $identity->id,
                'reason' => trim($reason) !== '' ? trim($reason) : null,
            ], (string) $actor->id);

            return [
                'attempt' => $attempt->fresh(),
                'access_token' => $token,
            ];
        });
    }
}
