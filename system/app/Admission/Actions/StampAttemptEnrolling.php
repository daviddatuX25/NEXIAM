<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\CommandIdempotency;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-091: Registrar records sheet receipt and starts enrollment processing.
 * The provisional program comes from the current selection/default or the
 * presented sheet; Registrar may record attributable confirmation of a
 * pending default atomically. An unresolved follow-up is shown but does not
 * block `enrolling`.
 */
class StampAttemptEnrolling
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $registrar,
        ?string $provisionalProgramCode,
        ?string $paperReference,
        string $reason,
        string $idempotencyKey,
        bool $acknowledgePendingDefault = false,
    ): EnrollmentStamp {
        $this->idempotency->claim($registrar, 'registrar.enrollment.stamp_enrolling', 'attempt:'.$attempt->id, [
            'provisional_program' => $provisionalProgramCode,
            'paper_reference' => $paperReference,
            'reason' => $reason,
            'acknowledge_pending_default' => $acknowledgePendingDefault,
        ], $idempotencyKey);

        $replayed = EnrollmentStamp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($replayed !== null) {
            return $replayed;
        }

        return $this->planningMutex->run(AssignmentPlanningMutex::DOMAIN_QUOTA, (int) $attempt->academic_cycle_id, fn (): EnrollmentStamp => DB::transaction(function () use ($attempt, $registrar, $provisionalProgramCode, $paperReference, $reason, $idempotencyKey, $acknowledgePendingDefault): EnrollmentStamp {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! AttemptProgramFacts::isAccepted($locked)) {
                throw new RuntimeException('Application must be accepted before enrollment processing can start.');
            }

            if (! AttemptProgramFacts::isResultReleased($locked)) {
                throw new RuntimeException('The result must be released before enrollment processing can start.');
            }

            if (! AttemptProgramFacts::isAdvised($locked)) {
                throw new RuntimeException('A confirmed consultation decision is required before enrollment processing can start.');
            }

            if (AttemptProgramFacts::isEnrolling($locked) || AttemptProgramFacts::isEnrolled($locked)) {
                throw new RuntimeException('Enrollment processing has already started for this applicant.');
            }

            $standing = ProgramSelection::query()
                ->where('admission_attempt_id', $locked->id)
                ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
                ->lockForUpdate()
                ->first();

            $programId = null;
            if ($provisionalProgramCode !== null) {
                $programId = Program::query()->where('code', $provisionalProgramCode)->value('id');
            } elseif ($standing !== null) {
                $programId = $standing->program_id;
            }

            if ($programId === null) {
                throw new RuntimeException('A provisional program is required: record it from the sheet or the current selection.');
            }

            // Attributable confirmation of a pending guidance default from the sheet.
            if ($acknowledgePendingDefault && $standing !== null && $standing->status === ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT) {
                $standing->forceFill([
                    'status' => ProgramSelection::STATUS_ACTIVE,
                    'applicant_acknowledged_at' => now(),
                    'updated_at' => now(),
                ])->save();
            }

            $stamp = EnrollmentStamp::query()->create([
                'admission_attempt_id' => $locked->id,
                'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
                'provisional_program_id' => $programId,
                'stamped_by_user_id' => $registrar->id,
                'selection_id' => $standing?->id,
                'paper_reference' => $paperReference,
                'reason' => $reason,
                'version' => 1,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            $this->recordAuditEvent->handle('enrollment.enrolling_stamped', [
                'attempt_id' => $locked->id,
                'stamp_id' => $stamp->id,
                'provisional_program_id' => $programId,
                'paper_reference' => $paperReference,
                'open_follow_up' => AttemptProgramFacts::hasOpenFollowUp($locked),
            ], (string) $registrar->id);

            return $stamp;
        }));
    }
}
