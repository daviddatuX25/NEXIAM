<?php

namespace App\Admission\Actions;

use App\Admission\Events\AdmissionAttemptReconsidered;
use App\Admission\Support\AttemptProgramFacts;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use App\Scheduling\Actions\ReconcileReconsideredAttempt;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\Log;
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
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly ReconcileReconsideredAttempt $reconciliation,
    ) {}

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, string $reason): AdmissionAttempt
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Authority reason is required to reconsider acceptance.');
        }

        $reconsidered = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_EXAM,
            (int) $attempt->academic_cycle_id,
            fn (): AdmissionAttempt => $this->planningMutex->run(
                AssignmentPlanningMutex::DOMAIN_CONSULTATION,
                (int) $attempt->academic_cycle_id,
                function () use ($attempt, $actor, $expectedVersion, $reason): AdmissionAttempt {
                    $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                    if ((int) $attempt->version !== $expectedVersion) {
                        throw new RuntimeException('Stale admission attempt version.');
                    }

                    if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
                        throw new RuntimeException('Only accepted applications can be reconsidered here. Use Revert for dismissed/archived.');
                    }

                    if (AttemptProgramFacts::isClearanceConfirmed($attempt)) {
                        throw new RuntimeException('Admission clearance is confirmed. Reopen clearance with a reason before reconsidering this application.');
                    }

                    if (AttemptProgramFacts::activeStamp($attempt) !== null) {
                        throw new RuntimeException('This admission has effective legacy enrollment history and is read-only pending reviewed disposition.');
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
                },
            ),
        );

        try {
            $this->reconciliation->handle($reconsidered, $actor, $reason);
        } catch (\Throwable $exception) {
            Log::error('Reconsideration reconciliation failed: '.$exception->getMessage(), ['exception' => $exception]);
        }

        return $reconsidered;
    }
}
