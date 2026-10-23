<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\QuotaReservation;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramPoolOption;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-090: append the next immutable pool version (with options + advised
 * default) atomically with a pending-acknowledgement selection and FCFS
 * quota reservation. Requires result release + confirmed consultation.
 * Counselor confirmation alone chooses pool + default.
 */
class SetGuidanceProgramPool
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    /**
     * @param  list<array{program_code: string, display_order: int, basis: string}>  $options
     */
    public function handle(
        AdmissionAttempt $attempt,
        User $counselor,
        string $defaultProgramCode,
        array $options,
        ?string $reason,
        string $idempotencyKey,
    ): ProgramPool {
        $this->idempotency->claim($counselor, 'guidance.pool.set', 'attempt:'.$attempt->id, [
            'default_program' => $defaultProgramCode,
            'options' => $options,
            'reason' => $reason,
        ], $idempotencyKey);

        $replayed = ProgramPool::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($replayed !== null) {
            return $replayed;
        }

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $attempt->academic_cycle_id, fn (): ProgramPool => DB::transaction(function () use ($attempt, $counselor, $defaultProgramCode, $options, $reason, $idempotencyKey): ProgramPool {
            // Lock the attempt root first; serialize pool-version allocation on the attempt row.
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! AttemptProgramFacts::isAccepted($locked)) {
                throw new RuntimeException('Application must be accepted before a program pool can be set.');
            }

            if (! AttemptProgramFacts::isResultReady($locked)) {
                throw new RuntimeException('Consultation decision requires finalized grading and a prepared result document.');
            }

            if (! AttemptProgramFacts::isAdvised($locked)) {
                throw new RuntimeException('A confirmed consultation decision is required before the program pool can be set.');
            }

            if (AttemptProgramFacts::isEnrolling($locked) || AttemptProgramFacts::isEnrolled($locked)) {
                throw new RuntimeException('The program pool cannot change after enrollment processing has started.');
            }

            $current = ProgramPool::query()
                ->where('admission_attempt_id', $attempt->id)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            $optionCodes = collect($options)->pluck('program_code')->unique();
            $programs = Program::query()
                ->where('institution_id', $locked->institution_id)
                ->whereIn('code', $optionCodes)
                ->get()
                ->keyBy('code');

            foreach ($optionCodes as $programCode) {
                if (! $programs->has($programCode)) {
                    throw new RuntimeException('Unknown program code: '.$programCode);
                }
            }

            if (! $optionCodes->contains($defaultProgramCode) || ! $programs->has($defaultProgramCode)) {
                throw new RuntimeException('The advised default program is not in the option list.');
            }

            $nextVersion = ($current === null ? 0 : $current->version) + 1;

            $pool = ProgramPool::query()->create([
                'admission_attempt_id' => $attempt->id,
                'version' => $nextVersion,
                'predecessor_id' => $current?->id,
                'actor_user_id' => $counselor->id,
                'reason' => $reason,
                'source' => 'guidance',
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            $optionRows = [];
            foreach ($options as $option) {
                $optionRows[] = [
                    'program_pool_id' => $pool->id,
                    'program_id' => $programs->get($option['program_code'])->id,
                    'display_order' => $option['display_order'],
                    'basis' => $option['basis'],
                    'created_at' => now(),
                ];
            }

            ProgramPoolOption::query()->insert($optionRows);

            $defaultProgramId = $programs->get($defaultProgramCode)->id;
            $defaultOption = ProgramPoolOption::query()
                ->where('program_pool_id', $pool->id)
                ->where('program_id', $defaultProgramId)
                ->firstOrFail();

            // Atomic advised-default reservation with the shared FCFS guard.
            QuotaReservation::lockQuotaRows($locked, [$defaultProgramId]);
            QuotaReservation::assertCapacityFor($locked, $defaultProgramId);

            // A standing non-terminal selection survives pool supersession: only
            // cancel/transfer may terminalize it (never a silent pool rewrite).
            $standing = ProgramSelection::query()
                ->where('admission_attempt_id', $attempt->id)
                ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($standing === null) {
                ProgramSelection::query()->create([
                    'admission_attempt_id' => $attempt->id,
                    'program_pool_id' => $pool->id,
                    'program_pool_option_id' => $defaultOption->id,
                    'program_id' => $defaultProgramId,
                    'academic_cycle_id' => $attempt->academic_cycle_id,
                    'status' => ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT,
                    'source' => ProgramSelection::SOURCE_GUIDANCE_DEFAULT,
                    'actor_user_id' => $counselor->id,
                    'version' => 1,
                    'created_at' => now(),
                ]);
            }

            $this->recordAuditEvent->handle('program.pool_set', [
                'attempt_id' => $attempt->id,
                'pool_id' => $pool->id,
                'version' => $nextVersion,
                'default_program' => $defaultProgramCode,
            ], (string) $counselor->id);

            return $pool;
        }));
    }
}
