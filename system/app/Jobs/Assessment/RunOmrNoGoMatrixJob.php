<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

use function hash;
use function is_array;
use function is_string;

final class RunOmrNoGoMatrixJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /** @param array<string,mixed> $proof proof pages carry draft asset ids, never raw bytes */
    public function __construct(
        public readonly int $draftId,
        public readonly array $proof,
    ) {
    }

    public function handle(
        CanonicalArtifactMaterializer $artifacts,
        OmrNoGoMatrixRunner $runner,
        OmrTemplateAssetStore $assetStore,
    ): void {
        $draft = OmrTemplateAuthoringDraft::query()->with(['markerProfile', 'template', 'assets'])->findOrFail($this->draftId);
        $profile = $draft->markerProfile;
        if ($profile === null) {
            throw new RuntimeException('marker_profile_not_selected');
        }
        $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
        $artifact = $artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        $expectedArtifactHash = (string) (($draft->authoring_json['canonical']['artifact_set']['artifact_hash'] ?? ''));
        if ($expectedArtifactHash !== '' && ! hash_equals($expectedArtifactHash, $artifact->artifactHash)) {
            throw new RuntimeException('digital_verification_artifact_stale');
        }
        $artifact = $artifact->forDiagnostics();
        gc_collect_cycles();
        $proof = $this->proof;
        foreach ((array) ($proof['pages'] ?? []) as $index => $page) {
            $asset = $draft->assets->firstWhere('id', (int) ($page['asset_id'] ?? 0));
            if ($asset === null) {
                throw new RuntimeException('digital_proof_asset_missing');
            }
            $bytes = Storage::disk($asset->disk)->get($asset->path);
            if (! is_string($bytes) || hash('sha256', $bytes) !== $asset->sha256) {
                throw new RuntimeException('digital_proof_asset_integrity_failure');
            }
            $proof['pages'][$index]['bytes'] = $bytes;
        }
        $createdEvidenceAssets = [];
        try {
            $matrix = $runner->run(
                $artifact,
                $proof,
                function (string $variantName, string $fileName, string $bytes) use ($assetStore, $draft, $proof, &$createdEvidenceAssets): array {
                    $asset = $assetStore->putDraftAsset(
                        $draft,
                        OmrTemplateAsset::KIND_EVIDENCE,
                        $bytes,
                        'image/png',
                        1,
                        ['proof_hash' => $proof['proof_hash'], 'variant' => $variantName, 'role' => $fileName],
                    );
                    if ($asset->wasRecentlyCreated) {
                        $createdEvidenceAssets[] = $asset;
                    }

                    return ['role' => $fileName, 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
                },
            );
        } catch (Throwable $exception) {
            foreach ($createdEvidenceAssets as $asset) {
                Storage::disk($asset->disk)->delete($asset->path);
                $asset->delete();
            }
            throw $exception;
        }
        $matrix['evidence_assets'] = array_map(
            static fn (array $variant): array => (array) ($variant['evidence_assets'] ?? []),
            array_filter((array) ($matrix['variants'] ?? []), 'is_array'),
        );

        DB::transaction(function () use ($draft, $matrix): void {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $authoring = (array) $locked->authoring_json;
            $verification = (array) ($authoring['verification'] ?? []);
            $verification['matrix_pending'] = $matrix;
            $authoring['verification'] = $verification;
            $locked->forceFill(['authoring_json' => $authoring])->save();
        });
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('OMR digital no-go matrix failed', ['draft_id' => $this->draftId, 'exception' => $exception]);
    }
}
