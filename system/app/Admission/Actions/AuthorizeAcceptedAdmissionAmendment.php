<?php

namespace App\Admission\Actions;

use App\Admission\Support\NormalizedIdentityKey;
use App\Admission\Support\SyncAdmissionAttemptGrades;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
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
        private readonly SyncAdmissionAttemptGrades $syncGrades,
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

            $identity = ApplicantIdentity::query()
                ->lockForUpdate()
                ->findOrFail($attempt->applicant_identity_id);

            $beforeIdentity = $identity->only([
                'first_name', 'middle_name', 'last_name', 'suffix', 'birthdate', 'sex', 'email', 'phone', 'normalized_key',
            ]);
            $beforeAttempt = $attempt->only([
                'contact_email', 'contact_phone', 'applicant_type', 'last_school_enrolled', 'strand',
                'program_preference_1_code', 'program_preference_2_code', 'program_preference_3_code',
                'preferred_program_code', 'address_line', 'city', 'province', 'zip_code', 'gwa',
            ]);

            $identityChanged = $this->applyIdentityChanges($identity, $changes);
            $attemptFields = $this->attemptFieldPayload($changes);
            $grades = is_array($changes['grades'] ?? null) ? $changes['grades'] : [];
            if (isset($grades['GWA']) && $grades['GWA'] !== '') {
                $attemptFields['gwa'] = $grades['GWA'];
            }

            if ($identityChanged === [] && $attemptFields === [] && $grades === []) {
                throw new RuntimeException('No fields to amend.');
            }

            if ($identityChanged !== []) {
                $identity->save();
            }

            $attempt->fill(array_merge($attemptFields, [
                'version' => $attempt->version + 1,
            ]));
            $attempt->save();

            $gradeCodes = $this->syncGrades->handle($attempt, $grades);

            $this->recordAuditEvent->handle('admission.accepted_amendment_authorized', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'reason' => $reason,
                'status_unchanged' => AdmissionAttempt::STATUS_ACCEPTED,
                'exam_eligible' => true,
                'retake_required' => false,
                'identity_fields' => array_keys($identityChanged),
                'attempt_fields' => array_keys($attemptFields),
                'grade_codes' => $gradeCodes,
                'before_identity' => $beforeIdentity,
                'after_identity' => $identity->fresh()->only([
                    'first_name', 'middle_name', 'last_name', 'suffix', 'birthdate', 'sex', 'email', 'phone', 'normalized_key',
                ]),
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
    private function applyIdentityChanges(ApplicantIdentity $identity, array $changes): array
    {
        $map = [
            'identity_first_name' => 'first_name',
            'identity_middle_name' => 'middle_name',
            'identity_last_name' => 'last_name',
            'identity_suffix' => 'suffix',
            'identity_birthdate' => 'birthdate',
            'identity_sex' => 'sex',
            'identity_email' => 'email',
            'identity_phone' => 'phone',
        ];

        $changed = [];
        foreach ($map as $input => $column) {
            if (! array_key_exists($input, $changes) || $changes[$input] === null) {
                continue;
            }
            $value = is_string($changes[$input]) ? trim($changes[$input]) : $changes[$input];
            if ($column === 'birthdate' && is_string($value) && $value !== '') {
                $value = substr($value, 0, 10);
            }
            if ((string) $identity->{$column} === (string) $value) {
                continue;
            }
            $identity->{$column} = $value === '' ? null : $value;
            $changed[$column] = $value;
        }

        if ($changed === []) {
            return [];
        }

        $birthdate = $identity->birthdate instanceof \DateTimeInterface
            ? $identity->birthdate->format('Y-m-d')
            : (string) $identity->birthdate;

        $newKey = NormalizedIdentityKey::make(
            (string) $identity->first_name,
            (string) $identity->last_name,
            $birthdate,
            (string) $identity->sex,
            $identity->middle_name,
        );

        $conflict = ApplicantIdentity::query()
            ->where('institution_id', $identity->institution_id)
            ->where('normalized_key', $newKey)
            ->where('id', '!=', $identity->id)
            ->exists();

        if ($conflict) {
            throw new RuntimeException('Identity amendment conflicts with another applicant record.');
        }

        $identity->normalized_key = $newKey;
        $changed['normalized_key'] = $newKey;

        return $changed;
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
