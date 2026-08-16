<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-089: Guidance opens a targeted PH edge-case follow-up before enrolling.
 * Requires result release + confirmed consultation; target program must be in
 * preference/pool/current selection. Guidance cannot settle or withdraw a
 * PH-routed follow-up after it is opened.
 */
class OpenProgramFollowUp
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $guidance,
        string $programCode,
        string $reason,
        string $idempotencyKey,
    ): ProgramFollowUp {
        $this->idempotency->claim($guidance, 'guidance.follow_up.open', 'attempt:'.$attempt->id, [
            'program' => $programCode,
            'reason' => $reason,
        ], $idempotencyKey);

        $replayed = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($replayed !== null) {
            return $replayed;
        }

        return DB::transaction(function () use ($attempt, $guidance, $programCode, $reason, $idempotencyKey): ProgramFollowUp {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! AttemptProgramFacts::isAccepted($locked)) {
                throw new RuntimeException('Application must be accepted before a follow-up can be opened.');
            }

            if (! AttemptProgramFacts::isResultReleased($locked)) {
                throw new RuntimeException('The result must be released before a follow-up can be opened.');
            }

            if (! AttemptProgramFacts::isAdvised($locked)) {
                throw new RuntimeException('A confirmed consultation decision is required before opening a follow-up.');
            }

            if (AttemptProgramFacts::isEnrolling($locked) || AttemptProgramFacts::isEnrolled($locked)) {
                throw new RuntimeException('New follow-ups can only be opened before enrollment processing starts.');
            }

            $program = Program::query()->where('code', $programCode)->firstOrFail();

            $pool = ProgramPool::currentFor($locked->id);
            $poolOption = $pool?->options()->where('program_id', $program->id)->exists();

            $inPreference = in_array($programCode, [
                $locked->program_preference_1_code,
                $locked->program_preference_2_code,
                $locked->program_preference_3_code,
            ], true);

            $selection = AttemptProgramFacts::currentSelection($locked);
            $selectedProgram = $selection?->program_id === $program->id;

            if (! $poolOption && ! $inPreference && ! $selectedProgram) {
                throw new RuntimeException('The follow-up target must be in the applicant\'s preferences, current pool, or current selection.');
            }

            $followUp = ProgramFollowUp::query()->create([
                'admission_attempt_id' => $locked->id,
                'program_id' => $program->id,
                'program_pool_id' => $pool?->id,
                'opened_by_user_id' => $guidance->id,
                'status' => ProgramFollowUp::STATUS_OPEN,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'version' => 1,
                'opened_at' => now(),
                'created_at' => now(),
            ]);

            $this->recordAuditEvent->handle('program.follow_up_opened', [
                'follow_up_id' => $followUp->id,
                'attempt_id' => $locked->id,
                'program_id' => $program->id,
                'reason' => $reason,
            ], (string) $guidance->id);

            return $followUp;
        });
    }
}
