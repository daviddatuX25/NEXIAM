<?php

namespace App\Assessment\Actions\Omr\Verification;

use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function array_key_exists;
use function count;
use function hash;
use function hash_equals;
use function in_array;
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
        private readonly OmrPhysicalAssuranceCoordinator $coordinator,
    ) {}

    public function handle(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        bool $allowDigitalVerifiedStage = false,
        ?int $supersedePhysicalRunId = null,
    ): OmrTemplateVerificationRun {
        $this->drafts->assertOwner($draft, $actor);
        $draft = $draft->fresh(['markerProfile', 'template', 'assets']);
        $allowedStages = [OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE];
        if ($allowDigitalVerifiedStage) {
            $allowedStages[] = OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED;
        }
        if (! in_array($draft->stage, $allowedStages, true)) {
            throw new OmrAuthoringDraftException('digital_verification_stage_invalid', 'Digital verification requires a mapping-complete draft.');
        }
        $artifactSet = (array) ($draft->authoring_json['canonical']['artifact_set'] ?? []);
        $profile = $this->markerProfileForMode($draft, $draft->markerProfile);
        if ($artifactSet === []) {
            $draft = $this->materialize->handle($draft, $actor, (int) $draft->lock_version);
            $draft = $draft->fresh(['markerProfile', 'template', 'assets']);
            $profile = $this->markerProfileForMode($draft, $draft->markerProfile);
        }
        $expectedLockVersion = (int) $draft->lock_version;
        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        $materialized = $this->artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        $registrationMode = $draft->registration_mode;
        $pinnedProfile = $profile === null ? null : [
            'id' => (int) $profile->id,
            'version' => (int) $profile->version,
            'asset_sha256' => (string) $profile->asset_sha256,
        ];
        $persistedArtifactHash = (string) (($draft->authoring_json['canonical']['artifact_set']['artifact_hash'] ?? ''));
        if ($persistedArtifactHash !== '' && ! hash_equals($persistedArtifactHash, $materialized->artifactHash)) {
            throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The draft artifact set changed after materialization; rematerialize before verifying.');
        }

        $target = new CandidateArtifactDiagnosticTarget($draft, $materialized, $profile);
        $plan = $this->planner->plan($target, ProofPurpose::Digital);
        $pinned = $plan->targetHashes;
        $expectedVectorHash = hash('sha256', json_encode($plan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        // Resolve the target before looking for a resumable run. A draft can
        // retain an active physical run pinned to an older artifact/mapping;
        // that historical row must never become the digital response.
        $active = $this->runs->activeRunForDraft($draft->id);
        if ($active !== null && (int) $active->id !== $supersedePhysicalRunId) {
            $targetMatches = $this->runTargetMatches($active, $pinned);
            if ($targetMatches && $active->mode !== OmrTemplateVerificationRun::MODE_DIGITAL) {
                throw new OmrAuthoringDraftException(
                    'verification_active_run_mode_conflict',
                    'A physical verification run is active for the current target; complete it before starting digital verification.',
                );
            }
            if (! $targetMatches) {
                $staleCode = $active->mode === OmrTemplateVerificationRun::MODE_DIGITAL
                    ? 'stale_target'
                    : 'target_changed_superseded';
                $staleDetail = $active->mode === OmrTemplateVerificationRun::MODE_DIGITAL
                    ? 'The previous digital verification targeted an older draft artifact; its evidence remains historical.'
                    : 'The previous physical verification targeted an older draft artifact; its evidence remains historical.';
                $this->runs->fail($active->id, $staleCode, $staleDetail, [
                    'phase_detail' => ['stage' => 'failed', 'current_variant' => null],
                ]);
                $this->recordAuditEvent->handle('omr.verification.failed', [
                    'run_id' => $active->id,
                    'draft_id' => $draft->id,
                    'error_code' => $staleCode,
                    'mode' => $active->mode,
                    'hashes' => $active->pinnedTargetHashes(),
                ], (string) $actor->id);
            } else {
                // A matching digital run is the only resumable response. A
                // stale worker run is failed first, then a fresh current run
                // may be created below.
                $this->runs->finalizeTimedOut($active->id);
                $active = $this->runs->activeRunForDraft(
                    $draft->id,
                    OmrTemplateVerificationRun::MODE_DIGITAL,
                    $pinned,
                );
                if ($active !== null) {
                    return $active;
                }
            }
        }

        $created = false;
        $run = DB::transaction(function () use (&$created, $draft, $actor, $plan, $pinned, $expectedVectorHash, $expectedLockVersion, $supersedePhysicalRunId, $registrationMode, $pinnedProfile): OmrTemplateVerificationRun {
            $lockedDraft = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $lockedDraft->lock_version !== $expectedLockVersion
                || $lockedDraft->registration_mode !== $registrationMode) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed while the current target was being prepared.');
            }
            $lockedProfile = $lockedDraft->markerProfile()->lockForUpdate()->first();
            $lockedProfile = $this->markerProfileForMode($lockedDraft, $lockedProfile);
            if ($pinnedProfile !== null && ($lockedProfile === null
                || (int) $lockedProfile->id !== $pinnedProfile['id']
                || (int) $lockedProfile->version !== $pinnedProfile['version']
                || ! hash_equals($pinnedProfile['asset_sha256'], (string) $lockedProfile->asset_sha256))) {
                throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The approved marker profile changed while verification was starting; prepare it again.');
            }
            $lockedCanonical = (array) (($lockedDraft->authoring_json['canonical'] ?? []) ?: []);
            $lockedArtifactSet = (array) (($lockedCanonical['artifact_set'] ?? []) ?: []);
            $lockedArtifactHash = (string) ($lockedArtifactSet['artifact_hash'] ?? $lockedCanonical['artifact_hash'] ?? '');
            try {
                $lockedGeometryIdentity = OmrCanonicalGeometryIdentity::forDraft($lockedDraft, $lockedProfile);
            } catch (\Throwable) {
                $lockedGeometryIdentity = '';
            }
            if ($lockedArtifactHash === ''
                || ! hash_equals((string) ($pinned['artifact'] ?? ''), $lockedArtifactHash)
                || $lockedGeometryIdentity === ''
                || ! hash_equals((string) ($pinned['mapping'] ?? ''), $lockedGeometryIdentity)) {
                throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The current target changed while verification was starting; prepare it again.');
            }
            if ($supersedePhysicalRunId !== null) {
                $oldRun = OmrTemplateVerificationRun::query()->whereKey($supersedePhysicalRunId)->lockForUpdate()->firstOrFail();
                $oldAllowed = [
                    OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                    OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
                    OmrTemplateVerificationRun::STATUS_FAILED,
                ];
                if ($oldRun->draft_id !== $lockedDraft->id
                    || $oldRun->mode !== OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL
                    || ! in_array($oldRun->status, $oldAllowed, true)) {
                    throw new OmrAuthoringDraftException('challenge_restart_invalid_state', 'The stale rehearsal is no longer eligible for recovery.');
                }
                $oldPinned = $oldRun->pinnedTargetHashes();
                if (hash_equals((string) ($oldPinned['artifact'] ?? ''), (string) ($pinned['artifact'] ?? ''))
                    && hash_equals((string) ($oldPinned['mapping'] ?? ''), (string) ($pinned['mapping'] ?? ''))) {
                    throw new OmrAuthoringDraftException('challenge_restart_target_not_drifted', 'The draft geometry has not drifted; use reference repair instead of restart.');
                }
                $this->coordinator->supersedePhysicalRun($oldRun, $actor, $plan->planHash());
            }

            // Race backstop: the partial unique index also enforces exactly
            // one active run per target, but only an exact current digital run
            // may be resumed by this action.
            $conflict = OmrTemplateVerificationRun::query()
                ->where('draft_id', $draft->id)
                ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();
            if ($conflict !== null) {
                if ($this->runTargetMatches($conflict, $pinned)
                    && $conflict->mode === OmrTemplateVerificationRun::MODE_DIGITAL) {
                    return $conflict;
                }
                if ($this->runTargetMatches($conflict, $pinned)) {
                    throw new OmrAuthoringDraftException(
                        'verification_active_run_mode_conflict',
                        'A physical verification run is active for the current target; complete it before starting digital verification.',
                    );
                }

                throw new OmrAuthoringDraftException(
                    'verification_active_run_target_conflict',
                    'Another verification run is active while the current target is being prepared; refresh and try again.',
                );
            }

            $created = true;

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
                'progress_total' => count(OmrNoGoMatrixRunner::VARIANTS),
                'started_by_user_id' => $actor->id,
                'started_at' => now(),
                'last_heartbeat_at' => now(),
            ]);
        });

        if (! $created) {
            return $run->fresh();
        }

        $this->recordAuditEvent->handle('omr.verification.started', [
            'run_id' => $run->id,
            'draft_id' => $draft->id,
            'plan_hash' => $run->plan_hash,
            'mode' => $run->mode,
            'hashes' => $run->pinnedTargetHashes(),
        ], (string) $actor->id);

        // Dispatch strictly after the run row committed.
        RunOmrTemplateVerificationJob::dispatch($run->id, (int) $run->attempt, (int) $run->optimistic_version)->onQueue('omr-verification')->afterCommit();

        return $run->fresh();
    }

    /** @param array<string, mixed> $targetHashes */
    private function runTargetMatches(OmrTemplateVerificationRun $run, array $targetHashes): bool
    {
        $artifact = (string) ($targetHashes['artifact'] ?? '');
        $mapping = (string) ($targetHashes['mapping'] ?? '');

        return $artifact !== ''
            && $mapping !== ''
            && hash_equals($artifact, (string) $run->artifact_hash)
            && hash_equals($mapping, (string) $run->mapping_hash);
    }

    private function markerProfileForMode(
        OmrTemplateAuthoringDraft $draft,
        ?OmrMarkerProfile $profile,
    ): ?OmrMarkerProfile {
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $artifactSet = (array) ($canonical['artifact_set'] ?? []);

        if ($draft->registration_mode === OmrRegistrationMode::FiducialAnchored) {
            if ($profile === null || (int) $draft->marker_profile_id !== (int) $profile->id) {
                throw new OmrAuthoringDraftException('marker_profile_not_selected', 'Select the same marker profile pinned to this draft before verifying.');
            }
            if ($profile->status === OmrMarkerProfile::STATUS_RETIRED
                || ! in_array($profile->status, [OmrMarkerProfile::STATUS_CANDIDATE, OmrMarkerProfile::STATUS_QUALIFIED], true)) {
                throw new OmrAuthoringDraftException('marker_profile_unqualified', 'Fiducial-anchored verification requires an available marker profile.');
            }

            if ($artifactSet !== []) {
                $pinnedProfile = (array) (($artifactSet['manifest']['marker_profile'] ?? null) ?: []);
                if ((int) ($pinnedProfile['id'] ?? 0) !== (int) $profile->id
                    || (int) ($pinnedProfile['version'] ?? 0) !== (int) $profile->version
                    || ! hash_equals((string) ($pinnedProfile['asset_sha256'] ?? ''), (string) $profile->asset_sha256)
                    || (string) ($pinnedProfile['status'] ?? '') !== (string) $profile->status) {
                    throw new OmrAuthoringDraftException('digital_verification_artifact_stale', 'The anchored artifact no longer matches its approved marker profile.');
                }
            }

            return $profile;
        }

        if ($draft->registration_mode !== OmrRegistrationMode::DocumentBound) {
            throw new OmrAuthoringDraftException('registration_mode_missing', 'The draft registration mode must be explicit before verification.');
        }

        $settings = (array) ($canonical['settings'] ?? []);
        $manifest = (array) ($artifactSet['manifest'] ?? []);
        $template = (array) ($artifactSet['template'] ?? []);
        $hasMarkerPageState = false;
        foreach ((array) ($template['pages'] ?? []) as $page) {
            $page = (array) $page;
            if (array_key_exists('markers', $page) || ($page['preProcessors'] ?? []) !== []) {
                $hasMarkerPageState = true;
                break;
            }
        }
        $hasMarkerAsset = $draft->assets->contains('kind', OmrTemplateAsset::KIND_MARKER_PROFILE);
        if ($profile !== null
            || $draft->marker_profile_id !== null
            || $hasMarkerAsset
            || ! empty($authoring['marker'])
            || array_key_exists('marker_inset_mm', $settings)
            || ($artifactSet !== [] && ($artifactSet['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value)
            || (int) ($artifactSet['marker_asset_id'] ?? 0) > 0
            || (string) ($artifactSet['marker_hash'] ?? '') !== ''
            || array_key_exists('marker_profile', $manifest)
            || $hasMarkerPageState) {
            throw new OmrAuthoringDraftException('registration_mode_incompatible', 'Document-bound verification rejects marker profiles, marker geometry, and marker assets.');
        }

        return null;
    }
}
