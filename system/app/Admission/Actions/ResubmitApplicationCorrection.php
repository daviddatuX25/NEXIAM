<?php

namespace App\Admission\Actions;

use App\Admission\Support\SyncAdmissionAttemptGrades;
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
        private readonly SyncAdmissionAttemptGrades $syncGrades,
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
     *   grades?: array<string, string>,
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

            if (isset($payload['program_preference_1_code'])) {
                $payload['preferred_program_code'] = $payload['program_preference_1_code'];
            }

            $grades = is_array($changes['grades'] ?? null) ? $changes['grades'] : [];
            $grades = array_filter(
                $grades,
                fn ($value, $code) => is_string($code) && $value !== null && $value !== '',
                ARRAY_FILTER_USE_BOTH,
            );

            if (isset($grades['GWA'])) {
                $payload['gwa'] = $grades['GWA'];
            }

            $notes = trim((string) ($changes['resubmission_notes'] ?? ''));
            if ($notes !== '') {
                $prior = trim((string) $attempt->correction_notes);
                $payload['correction_notes'] = ($prior !== '' ? $prior."\n" : '').'Resubmitted: '.$notes;
            }

            $attempt->fill($payload);
            $attempt->save();

            $gradeCodes = $this->syncGrades->handle($attempt, $grades);

            $this->recordAuditEvent->handle('admission.correction_resubmitted', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'actor_user_id' => $actor->id,
                'grade_codes' => $gradeCodes,
            ], (string) $actor->id);

            return $attempt->fresh(['gradeValues']);
        });
    }
}
