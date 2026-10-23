<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationRun;

use function array_key_exists;
use function gc_collect_cycles;
use function gc_mem_caches;
use function hash_equals;
use function in_array;

/**
 * Rebuilds only the pinned diagnostic target for one isolated queue step.
 * Proof image bytes are deliberately excluded by default; the physical
 * challenge path opts into its integrity-checked persisted print PDF.
 *
 * @return array{draft:OmrTemplateAuthoringDraft,artifact:CanonicalArtifactSet,plan:OmrProofPlan}
 */
final class OmrVerificationTargetLoader
{
    public function __construct(
        private readonly CanonicalArtifactMaterializer $artifacts,
        private readonly OmrProofPlanner $planner,
        private readonly OmrVerificationRunService $runs,
        private readonly CurrentOmrDigitalVerification $digitalVerification,
    ) {}

    /**
     * @return array{draft:OmrTemplateAuthoringDraft,artifact:CanonicalArtifactSet,plan:OmrProofPlan}
     */
    public function load(OmrTemplateVerificationRun $run, bool $includePrintPdf = false): array
    {
        if ($run->draft_id === null) {
            throw new OmrDiagnosticTargetException('target_kind_unsupported', 'Digital verification runs currently require a draft candidate target.');
        }
        $draft = OmrTemplateAuthoringDraft::query()
            ->with(['markerProfile', 'template', 'assets'])
            ->findOrFail($run->draft_id);
        if ($draft->isCompiled()) {
            throw new OmrDiagnosticTargetException('candidate_draft_compiled', 'The draft is compiled; its immutable version target owns diagnostics now.');
        }
        $profile = $this->markerProfileForMode($draft);
        $this->assertDocumentBoundMarkerFree($draft);

        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        try {
            $currentMapping = OmrCanonicalGeometryIdentity::forDraft(
                $draft,
                $profile,
                $candidateVersion > 0 ? $candidateVersion : null,
            );
        } catch (\Throwable) {
            $currentMapping = '';
        }
        if ($currentMapping === '' || ! hash_equals((string) $run->mapping_hash, $currentMapping)) {
            $this->runs->fail($run->id, 'stale_target', 'The draft geometry changed after the run was pinned; rematerialize and start a new run.');
            throw new OmrDiagnosticTargetException('candidate_geometry_stale', 'The draft geometry changed after the run was pinned.');
        }

        $artifact = $this->artifacts->loadPersisted($draft, $profile, includePrintPdf: $includePrintPdf);
        if (! hash_equals((string) $run->artifact_hash, $artifact->artifactHash)) {
            $this->runs->fail($run->id, 'stale_target', 'The draft artifact changed after the run was pinned; rematerialize and start a new run.');
            throw new OmrDiagnosticTargetException('candidate_artifact_stale', 'The draft artifact changed after the run was pinned.');
        }

        $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, $profile);
        $plan = $this->planner->plan($target, ProofPurpose::Digital, (string) ($run->plan_version ?? 'v1'));
        if (! hash_equals($run->plan_hash, $plan->planHash())) {
            $this->runs->fail($run->id, 'stale_target', 'The proof plan for the current target no longer matches the pinned plan; start a new run.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The proof plan for the current target no longer matches the pinned plan.');
        }
        if (! $plan->bindsTo($run->pinnedTargetHashes())) {
            $this->runs->fail($run->id, 'stale_target', 'The target hashes drifted from the pinned run; start a new run.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The target hashes drifted from the pinned run.');
        }

        $diagnosticArtifact = $includePrintPdf ? $artifact : $artifact->forDiagnostics();
        unset($target, $artifact, $profile);
        $draft->unsetRelation('markerProfile')->unsetRelation('template')->unsetRelation('assets');
        gc_collect_cycles();
        gc_mem_caches();

        return ['draft' => $draft, 'artifact' => $diagnosticArtifact, 'plan' => $plan];
    }

    /**
     * Load one method-bound physical target without rebuilding candidate bytes.
     *
     * The challenge pins the target and plan on its method row. Physical
     * capture must consume that persisted candidate bundle even when the web
     * and queue workers use different renderer/environment settings.
     *
     * @return array{target:OmrDiagnosticTarget,purpose:ProofPurpose,plan:OmrProofPlan,method:OmrTemplateVerificationMethod}
     */
    public function loadPhysical(OmrTemplateVerificationRun $run, string $methodName): array
    {
        if (! in_array($run->mode, [
            OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
            OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        ], true)) {
            throw new OmrDiagnosticTargetException('capture_mode_invalid', 'Physical captures are accepted only for challenge runs.');
        }
        if (! in_array($methodName, OmrProofPlan::METHODS, true)) {
            throw new OmrDiagnosticTargetException('verification_method_invalid', 'Physical capture method must be pre_shaded or hand_shaded.');
        }

        $method = OmrTemplateVerificationMethod::query()
            ->where('run_id', $run->id)
            ->where('method', $methodName)
            ->first();
        if ($method === null) {
            throw new OmrDiagnosticTargetException('verification_method_missing', 'The requested physical capture method is not part of this run.');
        }

        $purpose = $run->mode === OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION
            ? ProofPurpose::FullQualification
            : ProofPurpose::QuickRehearsal;

        $draft = null;
        if ($run->draft_id !== null) {
            $draft = OmrTemplateAuthoringDraft::query()
                ->with(['markerProfile', 'template', 'assets'])
                ->find($run->draft_id);
            if ($draft === null || $draft->isCompiled()) {
                throw new OmrDiagnosticTargetException('candidate_draft_unavailable', 'The candidate draft for this run is no longer a valid physical target.');
            }
            $profile = $this->markerProfileForMode($draft);
            $this->assertDocumentBoundMarkerFree($draft);

            $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
            try {
                $currentMapping = OmrCanonicalGeometryIdentity::forDraft(
                    $draft,
                    $profile,
                    $candidateVersion > 0 ? $candidateVersion : null,
                );
            } catch (\Throwable) {
                $currentMapping = '';
            }
            if ($currentMapping === '' || ! hash_equals((string) $run->mapping_hash, $currentMapping)) {
                $this->runs->fail($run->id, 'stale_target', 'The draft geometry changed after the run was pinned; rematerialize and start a new run.');
                throw new OmrDiagnosticTargetException('candidate_geometry_stale', 'The draft geometry changed after the run was pinned.');
            }

            $artifact = $this->artifacts->loadPersisted($draft, $profile);
            $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, $profile);
        } else {
            $version = OmrAnswerSheetTemplateVersion::query()->with('template')->find($run->template_version_id);
            if ($version === null) {
                throw new OmrDiagnosticTargetException('compiled_version_missing', 'The compiled template version for this run is no longer available.');
            }
            $target = new CompiledVersionDiagnosticTarget($version);
        }

        if ($draft !== null) {
            $digital = $this->digitalVerification->find($draft);
            if ($digital === null
                || ! hash_equals((string) $digital->artifactHash, (string) $run->artifact_hash)
                || ! hash_equals((string) $digital->run->mapping_hash, (string) $run->mapping_hash)) {
                $this->runs->fail($run->id, 'stale_target', 'The current digital verification no longer authorizes this physical target.');
                throw new OmrDiagnosticTargetException('digital_verification_stale', 'The current digital verification no longer authorizes this physical target.');
            }
        }
        $targetHashes = [
            'artifact' => $target->artifactHash(),
            'native_bundle' => $target->nativeBundleHash(),
            'manifest' => $target->manifestHash(),
            'config' => $target->configHash(),
            'mapping' => $target->mappingHash(),
        ];
        if (! $this->hashesMatch($targetHashes, $run->pinnedTargetHashes())) {
            $this->runs->fail($run->id, 'stale_target', 'The target hashes drifted from the pinned run; start a new challenge.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The physical target no longer matches the pinned run.');
        }
        $expectedTargetKind = $run->draft_id !== null
            ? OmrTemplateVerificationMethod::TARGET_CANDIDATE
            : OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION;
        $expectedTargetId = (int) ($run->draft_id ?? $run->template_version_id);
        $expectedTargetVersion = $draft !== null
            ? (string) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? '') ?: $method->plan_version)
            : (string) $target->targetId();
        if ((string) $method->target_kind !== $expectedTargetKind
            || (int) $method->target_id !== $expectedTargetId
            || (string) $method->target_version !== $expectedTargetVersion
            || (int) $method->required_page_count !== $target->pageCount()
            || (string) $method->target_hash !== (string) $targetHashes['artifact']) {
            throw new OmrDiagnosticTargetException('verification_method_binding_mismatch', 'The requested method is not bound to this physical target.');
        }

        $plan = $this->planner->plan($target, $purpose, (string) $method->plan_version, $methodName);
        if (! hash_equals((string) $method->plan_hash, $plan->planHash())
            || ! hash_equals((string) $method->expected_vector_hash, $plan->expectedVectorHash)
            || ! $plan->bindsTo($targetHashes)) {
            $this->runs->fail($run->id, 'stale_target', 'The physical plan no longer matches the persisted target; start a new challenge.');
            throw new OmrDiagnosticTargetException('plan_target_mismatch', 'The physical plan no longer matches the persisted target.');
        }

        return ['target' => $target, 'purpose' => $purpose, 'plan' => $plan, 'method' => $method];
    }

    /** @param array<string,string> $actual @param array<string,string> $expected */
    private function hashesMatch(array $actual, array $expected): bool
    {
        foreach (['artifact', 'native_bundle', 'manifest', 'config', 'mapping'] as $key) {
            if (! isset($actual[$key], $expected[$key])
                || $actual[$key] === ''
                || $expected[$key] === ''
                || ! hash_equals((string) $expected[$key], (string) $actual[$key])) {
                return false;
            }
        }

        return true;
    }

    private function markerProfileForMode(OmrTemplateAuthoringDraft $draft): ?OmrMarkerProfile
    {
        if ($draft->registration_mode === OmrRegistrationMode::FiducialAnchored) {
            $profile = $draft->markerProfile;
            if ($profile === null || (int) $draft->marker_profile_id !== (int) $profile->id) {
                throw new OmrDiagnosticTargetException('candidate_marker_profile_mismatch', 'The anchored draft marker profile is no longer selected.');
            }
            if ($profile->status === OmrMarkerProfile::STATUS_RETIRED
                || ! in_array($profile->status, [OmrMarkerProfile::STATUS_CANDIDATE, OmrMarkerProfile::STATUS_QUALIFIED], true)) {
                throw new OmrDiagnosticTargetException('candidate_marker_profile_unqualified', 'The anchored draft marker profile is not available for verification.');
            }
            $artifactSet = (array) (($draft->authoring_json['canonical']['artifact_set'] ?? null) ?: []);
            if ($artifactSet !== []) {
                $pinnedProfile = (array) (($artifactSet['manifest']['marker_profile'] ?? null) ?: []);
                if ((int) ($pinnedProfile['id'] ?? 0) !== (int) $profile->id
                    || (int) ($pinnedProfile['version'] ?? 0) !== (int) $profile->version
                    || ! hash_equals((string) ($pinnedProfile['asset_sha256'] ?? ''), (string) $profile->asset_sha256)
                    || (string) ($pinnedProfile['status'] ?? '') !== (string) $profile->status) {
                    throw new OmrDiagnosticTargetException('candidate_marker_profile_mismatch', 'The anchored artifact no longer matches its approved marker profile.');
                }
            }

            return $profile;
        }

        if ($draft->registration_mode !== OmrRegistrationMode::DocumentBound) {
            throw new OmrDiagnosticTargetException('candidate_registration_mode_missing', 'The draft registration policy is not explicit.');
        }

        return null;

    }

    private function assertDocumentBoundMarkerFree(OmrTemplateAuthoringDraft $draft): void
    {
        if ($draft->registration_mode !== OmrRegistrationMode::DocumentBound) {
            return;
        }

        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $settings = (array) ($canonical['settings'] ?? []);
        $artifactSet = (array) ($canonical['artifact_set'] ?? []);
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
        if ($draft->marker_profile_id !== null
            || $draft->markerProfile !== null
            || ! empty($authoring['marker'])
            || $draft->assets->contains('kind', OmrTemplateAsset::KIND_MARKER_PROFILE)
            || array_key_exists('marker_inset_mm', $settings)
            || ($artifactSet !== [] && ($artifactSet['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value)
            || (int) ($artifactSet['marker_asset_id'] ?? 0) > 0
            || (string) ($artifactSet['marker_hash'] ?? '') !== ''
            || array_key_exists('marker_profile', $manifest)
            || $hasMarkerPageState) {
            throw new OmrDiagnosticTargetException('candidate_registration_mode_mismatch', 'A document-bound candidate cannot retain marker profile or marker geometry state.');
        }
    }
}
