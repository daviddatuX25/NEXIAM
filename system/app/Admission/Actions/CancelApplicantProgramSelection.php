<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\SelectionGuard;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramSelection;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-090: reasoned pre-enrolling cancellation of the standing selection.
 * Releases the quota reservation; the applicant may select again while
 * pre-enrolling. Blocked once enrollment processing has started.
 */
class CancelApplicantProgramSelection
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $applicant,
        string $reason,
        string $idempotencyKey,
    ): ProgramSelection {
        $this->idempotency->claim($applicant, 'applicant.program.cancel', 'attempt:'.$attempt->id, [
            'reason' => $reason,
        ], $idempotencyKey);

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $attempt->academic_cycle_id, fn (): ProgramSelection => DB::transaction(function () use ($attempt, $applicant, $reason, $idempotencyKey): ProgramSelection {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            SelectionGuard::assertSelectable($locked, 'Cancelling your program choice');

            $selection = SelectionGuard::lockStandingSelection($locked);

            if ($selection === null) {
                throw new RuntimeException('There is no current program selection to cancel.');
            }

            if ($selection->idempotency_key === $idempotencyKey && $selection->status === ProgramSelection::STATUS_CANCELLED) {
                return $selection;
            }

            SelectionGuard::terminalize($selection, ProgramSelection::STATUS_CANCELLED, $reason);

            $this->recordAuditEvent->handle('program.selection_cancelled', [
                'attempt_id' => $attempt->id,
                'selection_id' => $selection->id,
                'program_id' => $selection->program_id,
                'reason' => $reason,
            ], (string) $applicant->id);

            return $selection;
        }));
    }
}
