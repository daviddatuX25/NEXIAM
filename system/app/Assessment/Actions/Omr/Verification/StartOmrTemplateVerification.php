<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function hash;
use function hash_equals;
use function is_array;
use function json_encode;

/**
 * Idempotently start a durable digital verification run (OpenSpec 35.22).
 *
 * The run is pinned to the CURRENT target/proof-plan hashes at creation;
 * replay (an active run already exists for the target, or the same
 * idempotency key was already served) returns the existing run without a new
 * dispatch. `RunOmrTemplateVerificationJob` is dispatched only after the run
 * row commits, and the endpoint returns 202 with the status URL.
 */
final class StartOmrTemplateVerification
{
    public function __construct(
        private readonly OmrAuthoringDraftService $drafts,
        private readonly CanonicalArtifactMaterializer $artifacts,
        private readonly OmrProofPlanner $planner,
        private readonly OmrVerificationRunService $runs,
        private readonly MaterializeOmrCanonicalDraft $materialize,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrTemplateAuthoringDraft $draft, User $actor): OmrTemplateVerificationRun
    {
        $this->drafts->assertOwner($draft, $actor);
        $draft = $draft->fresh(['markerProfile', 'template', 'assets']);
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE) {
            throw new OmrAuthoringDraftException('digital_verification_stage_invalid', 'Digital verification requires a mapping-complete draft.');
        }

        // A stuck worker must never be replayed: finalize stale-heartbeat
        // runs first, then replay only genuinely live runs (idempotent start).
        $active = $this->runs->activeRunForDraft($draft->id);
        if ($active !== null) {
            $this->runs->finalizeTimedOut($active->id);
        }
        $active = $this->runs->activeRunForDraft($draft->id);
        if ($active !== null) {
            return $active;
        }

        $artifactSet = (array) ($draft->authoring_json['canonical']['artifact_set'] ?? []);
        if ($artifactSet === []) {
            $draft = $this->materialize->handle($draft, $actor, (int) $draft->lock_version);
            $draft = $draft->fresh(['markerProfile', 'template', 'assets']);
        }
        $profile = $draft->markerProfile;
        if ($profile === null) {
            throw new OmrAuthoringDraftException('marker_profile_not_selected', 'Select a marker profile before verifying the draft.');
        }
        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        $materialized = $this->artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        $persistedArtifactHash = (string) (($draft->authoring_json['canonical']['artifact_set']['artifact_hash'] ?? ''));
        if ($persistedArtifactHash !== '' && ! hash_equals($persistedArtifactHash, $materialized->artifactHash)) {
            throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The draft artifact set changed after materialization; rematerialize before verifying.');
        }

        $target = new CandidateArtifactDiagnosticTarget($draft, $materialized, $profile);
        $plan = $this->planner->plan($target, ProofPurpose::Digital);
        $pinned = $plan->targetHashes;
        $expectedVectorHash = hash('sha256', json_encode($plan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $run = DB::transaction(function () use ($draft, $actor, $plan, $pinned, $expectedVectorHash): OmrTemplateVerificationRun {
            // Race backstop: the partial unique index also enforces exactly
            // one active run per target, but fail gracefully before insert.
            $conflict = OmrTemplateVerificationRun::query()
                ->where('draft_id', $draft->id)
                ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();
            if ($conflict !== null) {
                return $conflict;
            }

            return OmrTemplateVerificationRun::query()->create([
                'draft_id' => $draft->id,
                'status' => OmrTemplateVerificationRun::STATUS_QUEUED,
                'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
                'attempt' => 1,
                'plan_version' => $plan->planVersion,
                'plan_hash' => $plan->planHash(),
                'expected_vector_hash' => $expectedVectorHash,
                'artifact_hash' => $pinned['artifact'],
                'native_bundle_hash' => $pinned['native_bundle'],
                'manifest_hash' => $pinned['manifest'],
                'config_hash' => $pinned['config'],
                'mapping_hash' => $pinned['mapping'],
                'progress_done' => 0,
                'progress_total' => 0,
                'started_by_user_id' => $actor->id,
                'started_at' => now(),
                'last_heartbeat_at' => now(),
            ]);
        });

        $this->recordAuditEvent->handle('omr.verification.started', [
            'run_id' => $run->id,
            'draft_id' => $draft->id,
            'plan_hash' => $run->plan_hash,
            'mode' => $run->mode,
            'hashes' => $run->pinnedTargetHashes(),
        ], (string) $actor->id);

        // Dispatch strictly after the run row committed.
        RunOmrTemplateVerificationJob::dispatch($run->id);

        return $run->fresh();
    }
}
