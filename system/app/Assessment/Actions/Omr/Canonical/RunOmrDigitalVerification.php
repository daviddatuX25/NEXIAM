<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Jobs\Assessment\RunOmrNoGoMatrixJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


final class RunOmrDigitalVerification
{
    public function __construct(
        private readonly OmrDigitalProofGenerator $proofs,
        private readonly OmrProofPlanner $planner,
        private readonly OmrNoGoMatrixRunner $matrix,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly MaterializeOmrCanonicalDraft $materialize,
        private readonly CanonicalArtifactMaterializer $artifacts,
    ) {
    }
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        $draft = $draft->fresh(['markerProfile', 'template', 'assets']);
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE) {
            throw new \RuntimeException('digital_verification_stage_invalid');
        }
        $artifactSet = (array) ($draft->authoring_json['canonical']['artifact_set'] ?? []);
        if ($artifactSet === []) {
            $draft = $this->materialize->handle($draft, $actor, (int) $draft->lock_version);
            $draft = $draft->fresh(['markerProfile', 'template', 'assets']);
        }
        $expectedLockVersion = (int) $draft->lock_version;
        $profile = $draft->markerProfile;
        if ($profile === null) {
            throw new \RuntimeException('marker_profile_not_selected');
        }
        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        $materialized = $this->artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        $persistedArtifactHash = (string) (($draft->authoring_json['canonical']['artifact_set']['artifact_hash'] ?? ''));
        if ($persistedArtifactHash !== '' && ! hash_equals($persistedArtifactHash, $materialized->artifactHash)) {
            throw new \RuntimeException('digital_verification_artifact_stale');
        }
        $artifactHash = $materialized->artifactHash;
        $target = new CandidateArtifactDiagnosticTarget($draft, $materialized, $profile);
        $plan = $this->planner->plan($target, ProofPurpose::Digital);
        unset($materialized);
        $proof = $this->proofs->generate($draft, $plan);
        try {
            $proofAssets = [];
            foreach ($proof['pages'] as $page) {
                $asset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_DIGITAL_PROOF, $page['bytes'], 'image/png', $page['page_no'], [
                    'proof_hash' => $proof['proof_hash'],
                    'answer_vector_hash' => hash('sha256', json_encode($proof['answer_vector'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                ]);
                $proofAssets[] = ['page_no' => $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
            }
            $proofForMatrix = [
                'proof_hash' => $proof['proof_hash'],
                'answer_vector' => $proof['answer_vector'],
                'pages' => [],
            ];
            foreach ($proofAssets as $index => $proofAsset) {
                $proofForMatrix['pages'][$index] = [
                    'page_no' => $proofAsset['page_no'],
                    'asset_id' => $proofAsset['asset_id'],
                ];
            }
            foreach ($proof['pages'] as &$page) {
                unset($page['bytes']);
            }
            unset($page);
            RunOmrNoGoMatrixJob::dispatchSync($draft->id, $proofForMatrix);
            $matrix = (array) (($draft->fresh()->authoring_json['verification']['matrix_pending'] ?? null) ?: []);
            if ($matrix === []) {
                throw new \RuntimeException('digital_verification_matrix_missing');
            }

            return DB::transaction(function () use ($draft, $actor, $artifactHash, $proof, $matrix, $proofAssets, $expectedLockVersion): OmrTemplateAuthoringDraft {
                $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
                $authoring = (array) $locked->authoring_json;
                $currentArtifactHash = (string) ($authoring['canonical']['artifact_set']['artifact_hash'] ?? '');
                if ((int) $locked->lock_version !== $expectedLockVersion
                    || $locked->stage !== OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE
                    || $currentArtifactHash === ''
                    || ! hash_equals($currentArtifactHash, $artifactHash)) {
                    throw new \RuntimeException('digital_verification_concurrency_conflict');
                }
                $verificationHash = hash('sha256', json_encode([
                    'artifact_hash' => $artifactHash,
                    'proof_hash' => $proof['proof_hash'],
                    'matrix_hash' => $matrix['matrix_hash'],
                    'proof_assets' => $proofAssets,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $authoring['verification'] = [
                    'status' => 'passed',
                    'artifact_hash' => $artifactHash,
                    'proof_hash' => $proof['proof_hash'],
                    'proof_assets' => $proofAssets,
                    'answer_vector' => $proof['answer_vector'],
                    'matrix' => $matrix,
                    'hashes' => ['digital_verification' => $verificationHash],
                    'threshold_status' => 'Unqualified',
                ];
                $locked->forceFill([
                    'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
                    'authoring_json' => $authoring,
                    'lock_version' => (int) $locked->lock_version + 1,
                ])->save();
                $saved = $locked->fresh();
                $this->recordAuditEvent->handle('omr.canonical.digital_verified', [
                    'draft_id' => $saved->id,
                    'artifact_hash' => $artifactHash,
                    'proof_hash' => $proof['proof_hash'],
                    'matrix_hash' => $matrix['matrix_hash'],
                    'verification_hash' => $verificationHash,
                ], (string) $actor->id);

                return $saved;
            });
        } catch (\Throwable $exception) {
            $this->deleteUncommittedProofAssets($draft, (string) $proof['proof_hash']);
            throw $exception;
        }
    }

    private function deleteUncommittedProofAssets(OmrTemplateAuthoringDraft $draft, string $proofHash): void
    {
        $assets = OmrTemplateAsset::query()
            ->where('authoring_draft_id', $draft->id)
            ->whereIn('kind', [OmrTemplateAsset::KIND_DIGITAL_PROOF, OmrTemplateAsset::KIND_EVIDENCE])
            ->get()
            ->filter(static fn (OmrTemplateAsset $asset): bool => ($asset->metadata['proof_hash'] ?? null) === $proofHash);
        foreach ($assets as $asset) {
            Storage::disk($asset->disk)->delete($asset->path);
            $asset->delete();
        }
    }
}
