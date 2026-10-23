<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\QuotaReservation;
use App\Admission\Support\SelectionGuard;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-089: direct assigned-PH decline of an acknowledged (or explicitly
 * follow-up-targeted) current selection — allowed only before `enrolling`.
 * Writes the immutable decline authority and releases the reservation.
 */
class DeclineApplicantProgramSelection
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $ph,
        string $programCode,
        string $reason,
        string $idempotencyKey,
    ): ProgramDecline {
        $this->idempotency->claim($ph, 'program_head.selection.decline', 'attempt:'.$attempt->id, [
            'program' => $programCode,
            'reason' => $reason,
        ], $idempotencyKey);

        $replayed = ProgramDecline::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($replayed !== null) {
            return $replayed;
        }

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $attempt->academic_cycle_id, fn (): ProgramDecline => DB::transaction(function () use ($attempt, $ph, $programCode, $reason, $idempotencyKey): ProgramDecline {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (AttemptProgramFacts::isEnrolling($locked) || AttemptProgramFacts::isEnrolled($locked)) {
                throw new RuntimeException('Direct selection decline is only allowed before enrollment processing starts.');
            }

            $program = Program::query()->where('code', $programCode)->firstOrFail();
            $pool = ProgramPool::currentFor($locked->id);

            if ($pool === null) {
                throw new RuntimeException('No Guidance program pool exists for this application yet.');
            }

            $standing = ProgramSelection::query()
                ->where('admission_attempt_id', $locked->id)
                ->where('program_id', $program->id)
                ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($standing === null) {
                throw new RuntimeException('This applicant has no current selection for that program.');
            }

            QuotaReservation::lockQuotaRows($locked, [$program->id]);

            $decline = ProgramDecline::query()->create([
                'admission_attempt_id' => $locked->id,
                'program_id' => $program->id,
                'program_pool_id' => $pool->id,
                'selection_id' => $standing->id,
                'declined_by_user_id' => $ph->id,
                'source' => ProgramDecline::SOURCE_DIRECT_SELECTION,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            SelectionGuard::terminalize($standing, ProgramSelection::STATUS_PROGRAM_DECLINED, 'Assigned Program Head declined this program.');

            $this->recordAuditEvent->handle('program.selection_declined', [
                'attempt_id' => $locked->id,
                'program_id' => $program->id,
                'selection_id' => $standing->id,
                'decline_id' => $decline->id,
                'reason' => $reason,
            ], (string) $ph->id);

            return $decline;
        }));
    }
}
