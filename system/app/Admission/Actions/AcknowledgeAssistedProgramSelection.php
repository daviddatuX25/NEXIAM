<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\SelectionGuard;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Operation;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-090: applicant acknowledges the Guidance advised default. A pending
 * acknowledgement becomes the standing active selection; quota stays held.
 */
class AcknowledgeAssistedProgramSelection
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $applicant,
        string $idempotencyKey,
    ): ProgramSelection {
        $operation = $this->idempotency->claim($applicant, 'applicant.program.acknowledge', 'attempt:'.$attempt->id, [], $idempotencyKey);

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $attempt->academic_cycle_id, fn (): ProgramSelection => DB::transaction(function () use ($attempt, $applicant, $idempotencyKey, $operation): ProgramSelection {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $operation->refresh();

            $payload = $operation->getAttribute('payload');
            if (is_array($payload) && array_key_exists('selection_id', $payload)) {
                $selection = ProgramSelection::query()->find($payload['selection_id']);

                if ($selection === null) {
                    throw new RuntimeException('The acknowledged program selection receipt references a missing selection.');
                }

                return $selection;
            }

            SelectionGuard::assertSelectable($locked, 'Acknowledging the advised default');

            $selection = SelectionGuard::lockStandingSelection($locked);

            if ($selection === null || $selection->source !== ProgramSelection::SOURCE_GUIDANCE_DEFAULT) {
                throw new RuntimeException('There is no pending advised default to acknowledge.');
            }

            if ($selection->idempotency_key === $idempotencyKey && $selection->status === ProgramSelection::STATUS_ACTIVE) {
                $this->persistSelectionReceipt($operation, $selection);

                return $selection;
            }

            $selection->forceFill([
                'status' => ProgramSelection::STATUS_ACTIVE,
                'actor_user_id' => $applicant->id,
                'applicant_acknowledged_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'updated_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle('program.selection_acknowledged', [
                'attempt_id' => $locked->id,
                'selection_id' => $selection->id,
                'program_id' => $selection->program_id,
            ], (string) $applicant->id);

            $this->persistSelectionReceipt($operation, $selection);

            return $selection;
        }));
    }

    private function persistSelectionReceipt(Operation $operation, ProgramSelection $selection): void
    {
        $payload = $operation->getAttribute('payload');

        $operation->forceFill([
            'payload' => [...(is_array($payload) ? $payload : []), 'selection_id' => $selection->id],
        ])->save();
    }
}
