<?php

namespace App\Admission\Actions;

use App\Admission\Support\SyncAdmissionAttemptGrades;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applicant OWN quiet amendment while still pending (before accept/pipeline freeze).
 * Distinct from Request/Resubmit correction and from post-accept authority reconsider (HOLD).
 */
class AmendPendingAdmissionAttempt
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SyncAdmissionAttemptGrades $syncGrades,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, array $changes): AdmissionAttempt
    {
        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $changes): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status !== AdmissionAttempt::STATUS_PENDING) {
                throw new RuntimeException(
                    'Only pending applications can be quietly amended. After acceptance, changes need registrar authority.',
                );
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

            $payload = ['version' => $attempt->version + 1];
            $changed = [];
            foreach ($fillable as $field) {
                if (array_key_exists($field, $changes) && $changes[$field] !== null) {
                    $payload[$field] = $changes[$field];
                    $changed[] = $field;
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
                $changed[] = 'gwa';
            }

            if ($changed === [] && $grades === []) {
                throw new RuntimeException('No fields to update.');
            }

            $attempt->fill($payload);
            $attempt->save();

            $gradeCodes = $this->syncGrades->handle($attempt, $grades);
            if ($gradeCodes !== []) {
                $changed[] = 'grades';
            }

            // Audited, but not a loud queue flag — pending self-fix is quiet by product rule.
            $this->recordAuditEvent->handle('admission.applicant_amended_pending', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'fields' => $changed,
                'grade_codes' => $gradeCodes,
                'visibility' => 'quiet_pending',
            ], (string) $actor->id);

            return $attempt->fresh(['gradeValues']);
        });
    }
}
