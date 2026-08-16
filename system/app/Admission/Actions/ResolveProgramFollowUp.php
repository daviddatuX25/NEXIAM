<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\QuotaReservation;
use App\Admission\Support\SelectionGuard;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-089: assigned Program Head clears / declines / requests appearance on a
 * targeted follow-up. A decline writes the immutable decline authority and
 * terminalizes any eligible provisional selection; `enrolling` stays intact.
 * request_appearance is non-terminal.
 */
class ResolveProgramFollowUp
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        ProgramFollowUp $followUp,
        User $ph,
        string $outcome,
        ?string $reason,
        string $idempotencyKey,
    ): ProgramFollowUp {
        $this->idempotency->claim($ph, 'program_head.follow_up.resolve', 'follow_up:'.$followUp->id, [
            'outcome' => $outcome,
            'reason' => $reason,
        ], $idempotencyKey);

        return DB::transaction(function () use ($followUp, $ph, $outcome, $reason, $idempotencyKey): ProgramFollowUp {
            $locked = ProgramFollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('This follow-up is already resolved.');
            }

            if (! in_array($outcome, [
                ProgramFollowUp::STATUS_CLEARED_BY_PH,
                ProgramFollowUp::STATUS_DECLINED_BY_PH,
                ProgramFollowUp::STATUS_APPEARANCE_REQUESTED,
            ], true)) {
                throw new RuntimeException('Unknown follow-up outcome.');
            }

            if ($outcome !== ProgramFollowUp::STATUS_APPEARANCE_REQUESTED && blank($reason)) {
                throw new RuntimeException('A resolution reason is required for a terminal outcome.');
            }

            $attempt = AdmissionAttempt::query()->whereKey($locked->admission_attempt_id)->lockForUpdate()->firstOrFail();

            // Terminal decline: append immutable decline authority + release selection.
            if ($outcome === ProgramFollowUp::STATUS_DECLINED_BY_PH) {
                ProgramDecline::query()->create([
                    'admission_attempt_id' => $attempt->id,
                    'program_id' => $locked->program_id,
                    'program_pool_id' => $locked->program_pool_id ?? ProgramPool::currentFor($attempt->id)?->id,
                    'follow_up_id' => $locked->id,
                    'declined_by_user_id' => $ph->id,
                    'source' => ProgramDecline::SOURCE_FOLLOW_UP,
                    'reason' => (string) $reason,
                    'idempotency_key' => $idempotencyKey.'-decline',
                    'created_at' => now(),
                ]);

                $standing = ProgramSelection::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('program_id', $locked->program_id)
                    ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                    ->lockForUpdate()
                    ->first();

                if ($standing !== null && ! AttemptProgramFacts::isEnrolled($attempt)) {
                    QuotaReservation::lockQuotaRows($attempt, [$locked->program_id]);
                    SelectionGuard::terminalize($standing, ProgramSelection::STATUS_PROGRAM_DECLINED, 'Assigned Program Head declined this program.');
                }
            }

            $locked->forceFill([
                'status' => $outcome,
                'resolved_by_user_id' => $ph->id,
                'resolution_reason' => $reason,
                'version' => $locked->version + 1,
                'resolved_at' => $outcome === ProgramFollowUp::STATUS_APPEARANCE_REQUESTED ? null : now(),
                'updated_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle('program.follow_up_resolved', [
                'follow_up_id' => $locked->id,
                'attempt_id' => $attempt->id,
                'program_id' => $locked->program_id,
                'outcome' => $outcome,
                'reason' => $reason,
            ], (string) $ph->id);

            return $locked->fresh();
        });
    }
}
