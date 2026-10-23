<?php

namespace App\Admission\Actions;

use App\Admission\Support\SyncApplicantIdentity;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applicant (or registrar) resubmits after RequestApplicationCorrection (GF-06).
 * needs_correction → pending; never deletes history.
 */
class ResubmitApplicationCorrection
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SyncApplicantIdentity $syncIdentity,
    ) {}

    /**
     * @param  array{
     *   contact_email?: string|null,
     *   contact_phone?: string|null,
     *   applicant_type?: string|null,
     *   program_preference_1_code?: string|null,
     *   program_preference_2_code?: string|null,
     *   program_preference_3_code?: string|null,
     *   address_line?: string|null,
     *   city?: string|null,
     *   province?: string|null,
     *   zip_code?: string|null,
     *   last_school_enrolled?: string|null,
     *   strand?: string|null,
     *   resubmission_notes?: string|null,
     * }  $changes
     */
    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, array $changes): AdmissionAttempt
    {
        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $changes): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status !== AdmissionAttempt::STATUS_NEEDS_CORRECTION) {
                throw new RuntimeException('Attempt is not awaiting correction.');
            }

            $identityResult = $this->syncIdentity->handle($attempt, $changes, allowBirthdate: false);

            $fillable = [
                'contact_email',
                'contact_phone',
                'applicant_type',
                'program_preference_1_code',
                'program_preference_2_code',
                'program_preference_3_code',
                'address_line',
                'city',
                'province',
                'zip_code',
                'last_school_enrolled',
                'strand',
            ];

            $payload = [
                'status' => AdmissionAttempt::STATUS_PENDING,
                'version' => $attempt->version + 1,
            ];

            foreach ($fillable as $field) {
                if (array_key_exists($field, $changes) && $changes[$field] !== null) {
                    $payload[$field] = $changes[$field];
                }
            }

            foreach ($identityResult['synced_attempt_contact'] as $contactField => $contactValue) {
                if (! array_key_exists($contactField, $payload) || (string) ($payload[$contactField] ?? '') === (string) ($attempt->{$contactField} ?? '')) {
                    $payload[$contactField] = $contactValue;
                }
            }

            if (isset($payload['program_preference_1_code'])) {
                $payload['preferred_program_code'] = $payload['program_preference_1_code'];
            }

            $notes = trim((string) ($changes['resubmission_notes'] ?? ''));
            if ($notes !== '') {
                $prior = trim((string) $attempt->correction_notes);
                $payload['correction_notes'] = ($prior !== '' ? $prior."\n" : '').'Resubmitted: '.$notes;
            }

            $attempt->fill($payload);
            $attempt->save();

            $auditPayload = [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'actor_user_id' => $actor->id,
                'identity_fields' => array_keys($identityResult['changed']),
            ];

            if ($identityResult['changed'] !== []) {
                $auditPayload['before_identity'] = $identityResult['before'];
                $auditPayload['after_identity'] = $identityResult['after'];
            }

            $this->recordAuditEvent->handle('admission.correction_resubmitted', $auditPayload, (string) $actor->id);

            return $attempt->fresh(['identity', 'gradeValues']);
        });
    }
}
