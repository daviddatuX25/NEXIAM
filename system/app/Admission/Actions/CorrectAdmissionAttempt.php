<?php

namespace App\Admission\Actions;

use App\Admission\Support\SyncApplicantIdentity;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registrar in-office field correction (Path A).
 * pending → pending (quiet); needs_correction → pending (clears waiting-on-applicant).
 * Never deletes history (INV-016). Does not request applicant resubmission (Path B).
 */
class CorrectAdmissionAttempt
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SyncApplicantIdentity $syncIdentity,
    ) {}

    /**
     * @param  array{
     *   preferred_program_code?: string|null,
     *   program_preference_1_code?: string|null,
     *   program_preference_2_code?: string|null,
     *   program_preference_3_code?: string|null,
     *   contact_email?: string|null,
     *   contact_phone?: string|null,
     *   applicant_type?: string|null,
     *   last_school_enrolled?: string|null,
     *   strand?: string|null,
     *   correction_notes?: string|null,
     *   address_line?: string|null,
     *   city?: string|null,
     *   province?: string|null,
     *   zip_code?: string|null,
     * }  $changes
     */
    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, array $changes): AdmissionAttempt
    {
        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $changes): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if (! in_array($attempt->status, [
                AdmissionAttempt::STATUS_PENDING,
                AdmissionAttempt::STATUS_NEEDS_CORRECTION,
            ], true)) {
                throw new RuntimeException('Attempt is not eligible for correction.');
            }

            $identityResult = $this->syncIdentity->handle($attempt, $changes, allowBirthdate: true);

            $priorStatus = $attempt->status;
            $returnedToPending = $priorStatus === AdmissionAttempt::STATUS_NEEDS_CORRECTION;

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
                'correction_notes',
                'address_line',
                'city',
                'province',
                'zip_code',
            ];

            $payload = ['version' => $attempt->version + 1];
            if ($returnedToPending) {
                $payload['status'] = AdmissionAttempt::STATUS_PENDING;
            }

            foreach ($fillable as $field) {
                if (array_key_exists($field, $changes)) {
                    $payload[$field] = $changes[$field];
                }
            }

            foreach ($identityResult['synced_attempt_contact'] as $contactField => $contactValue) {
                if (! array_key_exists($contactField, $payload) || (string) ($payload[$contactField] ?? '') === (string) ($attempt->{$contactField} ?? '')) {
                    $payload[$contactField] = $contactValue;
                }
            }

            if (isset($payload['program_preference_1_code']) && ! isset($payload['preferred_program_code'])) {
                $payload['preferred_program_code'] = $payload['program_preference_1_code'];
            }

            $attempt->fill($payload);
            $attempt->save();

            $fields = array_values(array_diff(array_keys($payload), ['version', 'status']));

            $auditPayload = [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'fields' => $fields,
                'identity_fields' => array_keys($identityResult['changed']),
                'actor_kind' => 'registrar',
                'visibility' => 'registrar_inplace',
                'prior_status' => $priorStatus,
                'returned_to_pending' => $returnedToPending,
            ];

            if ($identityResult['changed'] !== []) {
                $auditPayload['before_identity'] = $identityResult['before'];
                $auditPayload['after_identity'] = $identityResult['after'];
            }

            $this->recordAuditEvent->handle('admission.attempt_corrected', $auditPayload, (string) $actor->id);

            return $attempt->fresh(['identity', 'gradeValues']);
        });
    }
}
