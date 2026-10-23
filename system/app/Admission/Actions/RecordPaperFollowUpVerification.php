<?php

namespace App\Admission\Actions;

use App\Admission\Exceptions\PaperVerificationGatedException;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-026 / AC-S03 / AC-S04: Registrar verification of physical countersigned
 * paper follow-up endorsement.
 *
 * Paper verification is fail-closed and strictly gated pending approved
 * institutional paper specimen and signatory policy (BR-026 / AC-S04).
 * Never fabricates a digital Program Head identity.
 */
class RecordPaperFollowUpVerification
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Determines whether institutional paper specimen policy has been approved and activated.
     * Defaults to false until founder provides approved specimen and signatory rules.
     */
    public function isSpecimenPolicyActive(): bool
    {
        return (bool) Config::get('admission.paper_follow_up_specimen_active', false);
    }

    /**
     * Record verification of an institutional paper follow-up result.
     *
     * @throws PaperVerificationGatedException
     * @throws RuntimeException
     */
    public function handle(
        ProgramFollowUp $followUp,
        User $registrar,
        string $paperReference,
        string $idempotencyKey,
    ): ProgramFollowUp {
        // Enforce prerequisite gate: fail-closed unless explicit institutional paper specimen policy is active.
        if (! $this->isSpecimenPolicyActive()) {
            throw new PaperVerificationGatedException(
                'Physical paper follow-up verification is currently gated pending approved institutional paper specimen and signatory policy.'
            );
        }

        return DB::transaction(function () use ($followUp, $registrar, $paperReference, $idempotencyKey): ProgramFollowUp {
            $attempt = AdmissionAttempt::query()
                ->whereKey($followUp->admission_attempt_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = ProgramFollowUp::query()
                ->whereKey($followUp->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->admission_attempt_id !== (int) $attempt->id) {
                throw new RuntimeException('Follow-up does not belong to the locked admission attempt.');
            }

            if (! $locked->isOpen()) {
                throw new RuntimeException('This follow-up is already resolved.');
            }

            // In accordance with provenance requirements, mark follow-up as paper cleared without fabricating PH user
            $locked->forceFill([
                'status' => ProgramFollowUp::STATUS_CLEARED_BY_PH,
                'resolved_by_user_id' => $registrar->id,
                'resolution_reason' => 'Paper verified: '.$paperReference,
                'idempotency_key' => $idempotencyKey,
                'resolved_at' => now(),
            ])->save();

            $this->recordAuditEvent->handle('admission.follow_up.paper_verified', [
                'follow_up_id' => $locked->id,
                'admission_attempt_id' => $attempt->id,
                'program_id' => $locked->program_id,
                'verifier_user_id' => $registrar->id,
                'paper_reference' => $paperReference,
                'provenance' => 'paper',
                'idempotency_key' => $idempotencyKey,
            ], (string) $registrar->id);

            return $locked->fresh();
        });
    }
}
