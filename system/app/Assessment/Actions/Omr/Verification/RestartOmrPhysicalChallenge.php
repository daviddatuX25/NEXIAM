<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;

use function hash_equals;
use function in_array;

/**
 * Current-target recovery for quick rehearsal challenges whose canonical target
 * has drifted (OpenSpec 35.27).
 *
 * The command materializes the candidate with the current draft lock and starts
 * a fresh durable digital run. Only the normal challenge command may create the
 * next quick rehearsal after that run passes; the old challenge/evidence remains
 * historical and is never reused.
 *
 * Any materialization or verification-start failure leaves the old pre-evidence
 * challenge untouched. StartOmrTemplateVerification owns the row-locked,
 * atomic supersession once the current target is ready.
 */
final class RestartOmrPhysicalChallenge
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly MaterializeOmrCanonicalDraft $materialize,
        private readonly StartOmrTemplateVerification $startVerification,
    ) {}

    /**
     * Prepare the current candidate and start a durable digital re-verification.
     * A quick challenge is created later by the normal challenge command after
     * this new digital run passes; the stale physical history is never reused.
     *
     * @return array{
     *     run_id: int,
     *     status: string,
     *     status_url: string,
     *     challenge_id: null,
     *     challenge_code: null,
     *     run: OmrTemplateVerificationRun,
     *     challenge: null,
     *     digital_reverification: bool,
     *     lock_version: int,
     * }
     */
    public function handle(OmrTemplateVerificationRun $run, User $actor): array
    {
        OmrSourcePolicy::assertSystemDesignedRun($run);
        $draft = $run->draft;
        if ($draft === null) {
            throw new OmrAuthoringDraftException('draft_missing', 'The authoring draft is no longer available.');
        }
        $this->drafts->assertOwner($draft, $actor);

        if ($run->mode !== OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL || $run->draft_id === null) {
            throw new OmrAuthoringDraftException('challenge_restart_not_quick_rehearsal', 'Challenge restart is only supported for candidate quick rehearsal runs.');
        }

        $allowedStatuses = [
            OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
            OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
            OmrTemplateVerificationRun::STATUS_FAILED,
        ];
        if (! in_array($run->status, $allowedStatuses, true)) {
            throw new OmrAuthoringDraftException('challenge_restart_invalid_state', "Challenge restart is not permitted in run status '{$run->status}'.");
        }
        if ($run->captures()->exists()) {
            throw new OmrAuthoringDraftException('challenge_restart_evidence_exists', 'Cannot restart challenge once evidence captures exist; evidence is immutable.');
        }

        $challenge = $run->challenges()->latest('id')->first();
        if ($challenge === null) {
            throw new OmrAuthoringDraftException('challenge_missing', 'No physical challenge found for this verification run.');
        }
        if ($challenge->status === OmrPhysicalChallenge::STATUS_SUPERSEDED || $run->error_code === 'target_changed_superseded') {
            throw new OmrAuthoringDraftException('challenge_restart_already_started', 'This rehearsal has already been superseded by current-target re-verification.');
        }
        if ($challenge->captures()->exists()) {
            throw new OmrAuthoringDraftException('challenge_restart_evidence_exists', 'Cannot restart challenge once evidence captures exist; evidence is immutable.');
        }

        // Materialize through the normal action using the current optimistic lock.
        // Any failure leaves the old run/challenge untouched and retryable.
        $prepared = $this->materialize->handle(
            $draft->fresh(['markerProfile', 'template', 'assets']),
            $actor,
            (int) $draft->lock_version,
        );
        $authoring = (array) $prepared->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $artifactSet = (array) ($canonical['artifact_set'] ?? []);
        $currentArtifactHash = (string) ($artifactSet['artifact_hash'] ?? $canonical['artifact_hash'] ?? '');
        $currentGeometryIdentity = (string) ($artifactSet['geometry_identity'] ?? '');
        if ($currentArtifactHash === '' || $currentGeometryIdentity === '') {
            throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The current candidate could not be materialized for re-verification.');
        }

        $pinned = $run->pinnedTargetHashes();
        if (hash_equals((string) ($pinned['artifact'] ?? ''), $currentArtifactHash)
            && hash_equals((string) ($pinned['mapping'] ?? ''), $currentGeometryIdentity)) {
            throw new OmrAuthoringDraftException('challenge_restart_target_not_drifted', 'The draft geometry has not drifted; use reference repair instead of restart.');
        }

        // StartOmrTemplateVerification atomically locks the prepared draft and
        // supersedes this stale physical run in the same run-creation transaction.
        $digitalRun = $this->startVerification->handle($prepared, $actor, true, (int) $run->id);

        return [
            'run_id' => (int) $digitalRun->id,
            'status' => (string) $digitalRun->status,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $digitalRun->id]),
            'challenge_id' => null,
            'challenge_code' => null,
            'run' => $digitalRun,
            'challenge' => null,
            'digital_reverification' => true,
            'lock_version' => (int) $prepared->lock_version,
        ];
    }
}
