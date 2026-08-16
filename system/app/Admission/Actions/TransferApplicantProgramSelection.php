<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\QuotaReservation;
use App\Admission\Support\SelectionGuard;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * D-090: applicant transfers the standing selection to another current-pool
 * program. Atomic reserve-new + release-old; fails without any change.
 */
class TransferApplicantProgramSelection
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $applicant,
        string $programCode,
        string $idempotencyKey,
    ): ProgramSelection {
        $this->idempotency->claim($applicant, 'applicant.program.transfer', 'attempt:'.$attempt->id, [
            'program' => $programCode,
        ], $idempotencyKey);

        $replayed = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', ProgramSelection::STATUS_ACTIVE)
            ->first();

        if ($replayed !== null) {
            return $replayed;
        }

        return DB::transaction(function () use ($attempt, $applicant, $programCode, $idempotencyKey): ProgramSelection {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            SelectionGuard::assertSelectable($locked, 'Transferring your program choice');

            $pool = ProgramPool::currentFor($attempt->id);

            if ($pool === null) {
                throw new RuntimeException('No Guidance program pool has been set for this application yet.');
            }

            $programId = $pool->options()->with('program')->get()
                ->first(fn ($option) => $option->program->code === $programCode)?->program_id;

            if ($programId === null) {
                throw new RuntimeException('That program is not available in the current Guidance pool.');
            }

            SelectionGuard::assertOptionEffective($locked, $pool, $programId);

            $standing = SelectionGuard::lockStandingSelection($locked);

            if ($standing === null) {
                throw new RuntimeException('There is no current program selection to transfer.');
            }

            if ($standing->program_id === $programId) {
                return $standing;
            }

            // Reserve new before releasing old: either both happen or neither.
            QuotaReservation::lockQuotaRows($locked, [$standing->program_id, $programId]);
            QuotaReservation::assertCapacityFor($locked, $programId);

            SelectionGuard::terminalize($standing, ProgramSelection::STATUS_SUPERSEDED, 'Applicant transferred to another program');

            $selection = ProgramSelection::query()->create([
                'admission_attempt_id' => $locked->id,
                'program_pool_id' => $pool->id,
                'program_pool_option_id' => $pool->options()->where('program_id', $programId)->firstOrFail()->id,
                'program_id' => $programId,
                'academic_cycle_id' => $locked->academic_cycle_id,
                'status' => ProgramSelection::STATUS_ACTIVE,
                'source' => ProgramSelection::SOURCE_STUDENT_SELF,
                'actor_user_id' => $applicant->id,
                'applicant_acknowledged_at' => now(),
                'commitment_identity' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'version' => 1,
                'created_at' => now(),
            ]);

            $this->recordAuditEvent->handle('program.selection_transferred', [
                'attempt_id' => $attempt->id,
                'from_selection_id' => $standing->id,
                'selection_id' => $selection->id,
                'program_id' => $selection->program_id,
            ], (string) $applicant->id);

            return $selection;
        });
    }
}
