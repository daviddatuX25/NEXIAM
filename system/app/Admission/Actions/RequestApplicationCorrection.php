<?php

namespace App\Admission\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ask the applicant to fix missing/invalid evidence (GF-06 RequestApplicationCorrection).
 * Distinct from in-place CorrectAdmissionAttempt (which stays in the current queue status).
 */
class RequestApplicationCorrection
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, string $reason): AdmissionAttempt
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Correction reason is required.');
        }

        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $reason): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status !== AdmissionAttempt::STATUS_PENDING) {
                throw new RuntimeException('Only pending attempts can be sent for correction.');
            }

            $attempt->update([
                'status' => AdmissionAttempt::STATUS_NEEDS_CORRECTION,
                'correction_notes' => $reason,
                'version' => $attempt->version + 1,
            ]);

            $this->recordAuditEvent->handle('admission.correction_requested', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'reason' => $reason,
            ], (string) $actor->id);

            return $attempt->fresh();
        });
    }
}
