<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;

/**
 * Retry semantics (OpenSpec 35.25): retry creates the NEXT attempt against
 * the SAME pinned plan/hashes. Only a failed run can be retried and only
 * when no other run is active for the same target; the durable job then
 * re-executes against the pinned hashes and fails closed if the target
 * drifted. Completed evidence from prior attempts is preserved.
 */
final class RetryOmrTemplateVerification
{
    public function __construct(
        private readonly OmrVerificationRunService $runs,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrTemplateVerificationRun $run, User $actor): OmrTemplateVerificationRun
    {
        $retried = $this->runs->retry($run->id);

        $this->recordAuditEvent->handle('omr.verification.retried', [
            'run_id' => $retried->id,
            'attempt' => $retried->attempt,
            'plan_hash' => $retried->plan_hash,
            'mode' => $retried->mode,
        ], (string) $actor->id);

        RunOmrTemplateVerificationJob::dispatch($retried->id);

        return $retried->fresh();
    }
}
