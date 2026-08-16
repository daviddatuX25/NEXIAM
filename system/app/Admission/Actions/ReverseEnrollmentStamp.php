<?php

namespace App\Admission\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramSelection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * D-091: ordered reasoned Registrar correction. `enrolled` requires ordered
 * reversal before any prerequisite-invalidating change; `enrolling` may be
 * reversed while pre-`enrolled` state is still coherent. Reversals are
 * append-only rows; the reversal row itself becomes the latest stamp.
 */
class ReverseEnrollmentStamp
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $registrar,
        string $reason,
        string $idempotencyKey,
    ): EnrollmentStamp {
        $this->idempotency->claim($registrar, 'registrar.enrollment.reverse', 'attempt:'.$attempt->id, [
            'reason' => $reason,
        ], $idempotencyKey);

        return DB::transaction(function () use ($attempt, $registrar, $reason, $idempotencyKey): EnrollmentStamp {
            $locked = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $active = EnrollmentStamp::query()
                ->where('admission_attempt_id', $locked->id)
                ->orderByDesc('id')
                ->first();

            if ($active === null || ! $active->isActive()) {
                throw new RuntimeException('There is no active enrollment stamp to reverse.');
            }

            $reversedType = $active->stamp_type === EnrollmentStamp::STAMP_ENROLLED
                ? EnrollmentStamp::STAMP_REVERSE_ENROLLED
                : EnrollmentStamp::STAMP_REVERSE_ENROLLING;

            $reversal = EnrollmentStamp::query()->create([
                'admission_attempt_id' => $locked->id,
                'stamp_type' => $reversedType,
                'provisional_program_id' => $active->provisional_program_id,
                'final_program_id' => $active->final_program_id,
                'stamped_by_user_id' => $registrar->id,
                'selection_id' => $active->selection_id,
                'reason' => 'REVERSAL: '.$reason,
                'version' => $active->version + 1,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            // A reversed enrolled stamp restores the pre-final selection state
            // (paper_finalized -> active) so the applicant is selectable again.
            if ($reversedType === EnrollmentStamp::STAMP_REVERSE_ENROLLED && $active->selection_id !== null) {
                $selection = ProgramSelection::query()->find($active->selection_id);
                if ($selection !== null && $selection->status === ProgramSelection::STATUS_PAPER_FINALIZED) {
                    $selection->forceFill([
                        'status' => ProgramSelection::STATUS_ACTIVE,
                        'terminal_reason' => null,
                        'enrollment_stamp_id' => null,
                        'updated_at' => now(),
                    ])->save();
                }
            }

            $this->recordAuditEvent->handle('enrollment.stamp_reversed', [
                'attempt_id' => $locked->id,
                'reversal_id' => $reversal->id,
                'reversed_stamp_type' => $active->stamp_type,
                'reason' => $reason,
            ], (string) $registrar->id);

            return $reversal;
        });
    }
}
