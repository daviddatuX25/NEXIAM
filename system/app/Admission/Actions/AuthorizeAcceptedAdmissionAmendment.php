<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\SyncApplicantIdentity;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Post-accept authorized demographic/field amendment (scalable C6 path).
 *
 * - Status stays accepted → isExamEligible() remains true → no retake / reassignment required
 * - Attempt id + reference_code stay stable (ExamAssignment FK unchanged)
 * - Identity core may be corrected with duplicate-key guard + full audit
 * - Requires explicit authority reason (never quiet CorrectAdmissionAttempt)
 */
class AuthorizeAcceptedAdmissionAmendment
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SyncApplicantIdentity $syncIdentity,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function handle(
        AdmissionAttempt $attempt,
        User $actor,
        int $expectedVersion,
        string $reason,
        array $changes,
    ): AdmissionAttempt {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Authority reason is required for post-accept amendment.');
        }

        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $reason, $changes): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                throw new RuntimeException('Only accepted applications use authorized amendment. Use Update for applicant while pending.');
            }

            if (AttemptProgramFacts::isClearanceConfirmed($attempt)) {
                throw new RuntimeException('Admission clearance is confirmed. Reopen clearance with a reason before amending this application.');
            }

            if (AttemptProgramFacts::activeStamp($attempt) !== null) {
                throw new RuntimeException('This admission has effective legacy enrollment history and is read-only pending reviewed disposition.');
            }

            $beforeAttempt = $attempt->only([
                'contact_email', 'contact_phone', 'applicant_type', 'last_school_enrolled', 'strand',
                'program_preference_1_code', 'program_preference_2_code', 'program_preference_3_code',
                'preferred_program_code', 'address_line', 'city', 'province', 'zip_code', 'gwa',
            ]);

            $identityResult = $this->syncIdentity->handle($attempt, $changes, allowBirthdate: true);
            $identityChanged = $identityResult['changed'];
            $attemptFields = $this->attemptFieldPayload($changes);

            foreach ($identityResult['synced_attempt_contact'] as $contactField => $contactValue) {
                if (! array_key_exists($contactField, $attemptFields) || (string) ($attemptFields[$contactField] ?? '') === (string) ($attempt->{$contactField} ?? '')) {
                    $attemptFields[$contactField] = $contactValue;
                }
            }
            if ($identityChanged === [] && $attemptFields === []) {
                throw new RuntimeException('No fields to amend.');
            }

            $attempt->fill(array_merge($attemptFields, [
                'version' => $attempt->version + 1,
            ]));
            $attempt->save();

            $this->recordAuditEvent->handle('admission.accepted_amendment_authorized', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'reason' => $reason,
                'status_unchanged' => AdmissionAttempt::STATUS_ACCEPTED,
                'exam_eligible' => true,
                'retake_required' => false,
                'identity_fields' => array_keys($identityChanged),
                'attempt_fields' => array_keys($attemptFields),
                'before_identity' => $identityResult['before'],
                'after_identity' => $identityResult['after'],
                'before_attempt' => $beforeAttempt,
                'assignment_count' => $attempt->examAssignments()->count(),
            ], (string) $actor->id);

            return $attempt->fresh(['identity', 'gradeValues']);
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function attemptFieldPayload(array $changes): array
    {
        $fillable = [
            'preferred_program_code',
            'program_preference_1_code',
            'program_preference_2_code',
            'program_preference_3_code',
            'contact_email',
            'contact_phone',
            'applicant_type',
            'last_school_enrolled',
            'strand',
            'address_line',
            'city',
            'province',
            'zip_code',
        ];

        $payload = [];
        foreach ($fillable as $field) {
            if (array_key_exists($field, $changes)) {
                $payload[$field] = $changes[$field];
            }
        }

        if (isset($payload['program_preference_1_code']) && ! isset($payload['preferred_program_code'])) {
            $payload['preferred_program_code'] = $payload['program_preference_1_code'];
        }

        return $payload;
    }
}
