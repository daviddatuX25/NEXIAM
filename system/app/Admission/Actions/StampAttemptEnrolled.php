<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\QuotaReservation;
use App\Admission\Support\SelectionGuard;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-091: Registrar verifies the completed signed enrollment sheet and stamps
 * `enrolled`. Requires current `enrolling`, no unresolved targeted follow-up,
 * and signed-paper verification. The final paper program may differ from the
 * provisional selection: the old reservation is released, the final
 * commitment recorded, and quota variance reported without changing capacity.
 */
class StampAttemptEnrolled
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $registrar,
        string $finalProgramCode,
        string $paperReference,
        string $paperHash,
        string $verifier,
        string $reason,
        string $idempotencyKey,
    ): EnrollmentStamp {
        $this->idempotency->claim($registrar, 'registrar.enrollment.stamp_enrolled', 'attempt:'.$attempt->id, [
            'final_program' => $finalProgramCode,
            'paper_reference' => $paperReference,
            'paper_hash' => $paperHash,
            'verifier' => $verifier,
            'reason' => $reason,
        ], $idempotencyKey);

        $replayed = EnrollmentStamp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($replayed !== null) {
            return $replayed;
        }

        return DB::transaction(function () use ($attempt, $registrar, $finalProgramCode, $paperReference, $paperHash, $verifier, $reason, $idempotencyKey): EnrollmentStamp {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $enrolling = AttemptProgramFacts::activeStamp($locked);

            if ($enrolling === null || $enrolling->stamp_type !== EnrollmentStamp::STAMP_ENROLLING) {
                throw new RuntimeException('The applicant must be in enrolling status before final enrollment can be recorded.');
            }

            if (AttemptProgramFacts::hasOpenFollowUp($locked)) {
                throw new RuntimeException('Final enrollment is blocked until the assigned Program Head resolves the open follow-up.');
            }

            $finalProgram = Program::query()->where('code', $finalProgramCode)->firstOrFail();

            $standing = ProgramSelection::query()
                ->where('admission_attempt_id', $locked->id)
                ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                ->lockForUpdate()
                ->first();

            // Paper finalization: terminalize the provisional selection, release
            // its reservation, and snapshot variance for the paper truth program.
            $variance = QuotaReservation::varianceSnapshot($locked, $finalProgram->id);

            if ($standing !== null) {
                QuotaReservation::lockQuotaRows($locked, [$standing->program_id, $finalProgram->id]);
                SelectionGuard::terminalize($standing, ProgramSelection::STATUS_PAPER_FINALIZED, 'Signed enrollment sheet verified; final program recorded.');
            }

            $stamp = EnrollmentStamp::query()->create([
                'admission_attempt_id' => $locked->id,
                'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
                'provisional_program_id' => $enrolling->provisional_program_id,
                'final_program_id' => $finalProgram->id,
                'stamped_by_user_id' => $registrar->id,
                'selection_id' => $standing?->id,
                'paper_reference' => $paperReference,
                'paper_hash' => $paperHash,
                'verifier' => $verifier,
                'reason' => $reason,
                'quota_variance' => $variance,
                'version' => $enrolling->version + 1,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            // Link the paper-finalized selection to the stamp that finalized it.
            if ($standing !== null) {
                $standing->forceFill(['enrollment_stamp_id' => $stamp->id, 'updated_at' => now()])->save();
            }

            $this->recordAuditEvent->handle('enrollment.enrolled_stamped', [
                'attempt_id' => $locked->id,
                'stamp_id' => $stamp->id,
                'final_program_id' => $finalProgram->id,
                'paper_reference' => $paperReference,
                'quota_variance' => $variance,
            ], (string) $registrar->id);

            return $stamp;
        });
    }
}
