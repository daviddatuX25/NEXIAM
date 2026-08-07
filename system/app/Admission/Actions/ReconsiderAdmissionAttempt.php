<?php

namespace App\Admission\Actions;

use App\Admission\Events\AdmissionAttemptReconsidered;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pull acceptance (or dismissal) back for decision-level mistakes (GF-06).
 * Does not cascade-delete identity, history, or exam assignments — emits
 * AdmissionAttemptReconsidered so scheduling can reconcile without a patch.
 *
 * Distinct from AuthorizeAcceptedAdmissionAmendment (typo/name fix, stay accepted, no retake).
 */
class ReconsiderAdmissionAttempt
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, string $reason): AdmissionAttempt
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Authority reason is required to reconsider acceptance.');
        }

        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $reason): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                throw new RuntimeException('Only accepted applications can be reconsidered here. Use Revert for dismissed/archived.');
            }

            $priorStatus = $attempt->status;
            $assignmentCount = $attempt->examAssignments()->count();

            $attempt->update([
                'status' => AdmissionAttempt::STATUS_PENDING,
                'accepted_at' => null,
                'version' => $attempt->version + 1,
                'correction_notes' => trim(
                    ($attempt->correction_notes ? $attempt->correction_notes."\n" : '').
                    'Reconsidered: '.$reason
                ),
            ]);

            $this->recordAuditEvent->handle('admission.attempt_reconsidered', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'prior_status' => $priorStatus,
                'reason' => $reason,
                'exam_eligible' => false,
                'active_assignment_count' => $assignmentCount,
                'assignments_preserved' => true,
            ], (string) $actor->id);

            $fresh = $attempt->fresh();

            AdmissionAttemptReconsidered::dispatch(
                $fresh,
                $actor,
                $reason,
                $assignmentCount,
                $priorStatus,
            );

            return $fresh;
        });
    }
}
