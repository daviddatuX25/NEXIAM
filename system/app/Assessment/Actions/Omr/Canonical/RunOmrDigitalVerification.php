<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function array_map;

final class RunOmrDigitalVerification
{
    public function __construct(
        private readonly OmrDigitalProofGenerator $proofs,
        private readonly OmrNoGoMatrixRunner $matrix,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrTemplateAuthoringDraft $draft, User $actor): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE) {
            throw new \RuntimeException('digital_verification_stage_invalid');
        }
        $proof = $this->proofs->generate($draft->fresh());
        $matrix = $this->matrix->run($proof);
        $proofAssets = [];
        foreach ($proof['pages'] as $page) {
            $asset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_DIGITAL_PROOF, $page['bytes'], 'image/png', $page['page_no'], [
                'proof_hash' => $proof['proof_hash'],
                'answer_vector_hash' => hash('sha256', json_encode($proof['answer_vector'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ]);
            $proofAssets[] = ['page_no' => $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        }

        return DB::transaction(function () use ($draft, $actor, $proof, $matrix, $proofAssets): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $authoring = (array) $locked->authoring_json;
            $verificationHash = hash('sha256', json_encode([
                'proof_hash' => $proof['proof_hash'],
                'matrix_hash' => $matrix['matrix_hash'],
                'proof_assets' => $proofAssets,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $authoring['verification'] = [
                'status' => 'passed',
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
                'proof_hash' => $proof['proof_hash'],
                'matrix_hash' => $matrix['matrix_hash'],
                'verification_hash' => $verificationHash,
            ], (string) $actor->id);

            return $saved;
        });
    }
}
