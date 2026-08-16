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
 * D-090: applicant self-selects a program from the current pool. When a
 * standing non-terminal selection exists (e.g. an unacknowledged guidance
 * default), the change is an atomic transfer: terminalize old + release
 * reservation + reserve new under the shared lock order.
 */
class SelectApplicantProgram
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
        $this->idempotency->claim($applicant, 'applicant.program.select', 'attempt:'.$attempt->id, [
            'program' => $programCode,
        ], $idempotencyKey);

        $replayed = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('idempotency_key', $idempotencyKey)
            ->where('source', ProgramSelection::SOURCE_STUDENT_SELF)
            ->first();

        if ($replayed !== null) {
            return $replayed;
        }

        return DB::transaction(function () use ($attempt, $applicant, $programCode, $idempotencyKey): ProgramSelection {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            SelectionGuard::assertSelectable($locked, 'Changing your program choice');

            $pool = ProgramPool::currentFor($locked->id);

            if ($pool === null) {
                throw new RuntimeException('No Guidance program pool has been set for this application yet.');
            }

            $programId = $pool->options()->with('program')->get()
                ->first(fn ($option) => $option->program->code === $programCode)?->program_id;

            if ($programId === null) {
                throw new RuntimeException('That program is not available in the current Guidance pool.');
            }

            SelectionGuard::assertOptionEffective($locked, $pool, $programId);

            QuotaReservation::lockQuotaRows($locked, [$programId]);
            QuotaReservation::assertCapacityFor($locked, $programId);

            $standing = SelectionGuard::lockStandingSelection($locked);

            if ($standing !== null && $standing->program_id === $programId) {
                // Same program: acknowledge-as-active when it was a pending default.
                if ($standing->status === ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT) {
                    $standing->forceFill([
                        'status' => ProgramSelection::STATUS_ACTIVE,
                        'source' => ProgramSelection::SOURCE_STUDENT_SELF,
                        'actor_user_id' => $applicant->id,
                        'applicant_acknowledged_at' => now(),
                        'idempotency_key' => $idempotencyKey,
                        'updated_at' => now(),
                    ])->save();
                    $this->audit($locked, $standing, 'program.selection_acknowledged');

                    return $standing;
                }

                return $standing;
            }

            if ($standing !== null) {
                SelectionGuard::terminalize($standing, ProgramSelection::STATUS_SUPERSEDED, 'Applicant chose another program');
            }

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

            $this->audit($locked, $selection, 'program.selection_created');

            return $selection;
        });
    }

    private function audit(AdmissionAttempt $attempt, ProgramSelection $selection, string $event): void
    {
        $this->recordAuditEvent->handle($event, [
            'attempt_id' => $attempt->id,
            'selection_id' => $selection->id,
            'program_id' => $selection->program_id,
            'status' => $selection->status,
            'source' => $selection->source,
        ], (string) $selection->actor_user_id);
    }
}
