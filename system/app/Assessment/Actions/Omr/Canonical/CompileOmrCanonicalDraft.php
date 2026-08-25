<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalNativeBundleCompiler;
use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_intersect_key;
use function array_keys;
use function count;
use function hash;
use function json_encode;

final class CompileOmrCanonicalDraft
{
    public function __construct(
        private readonly OmrCanonicalNativeBundleCompiler $compiler,
        private readonly OmrMarkerProfileService $profiles,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly CanonicalArtifactMaterializer $artifacts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrTemplateAuthoringDraft $draft, User $actor): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        $draft = $draft->fresh(['markerProfile', 'assets']);
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED) {
            throw new OmrAuthoringDraftException('compile_gate_missing', 'Digital verification is required before compile.');
        }
        $profile = $this->profiles->assertQualified($draft->markerProfile);
        $authoring = (array) $draft->authoring_json;
        if (($authoring['verification']['status'] ?? null) !== 'passed') {
            throw new OmrAuthoringDraftException('compile_gate_missing', 'The draft has no passing digital verification evidence.');
        }
        $artifactSet = (array) ($authoring['canonical']['artifact_set'] ?? []);
        $verifiedArtifactHash = (string) ($authoring['verification']['artifact_hash'] ?? '');
        $candidateArtifactHash = (string) ($artifactSet['artifact_hash'] ?? '');
        if ($artifactSet === [] || $verifiedArtifactHash === '' || $candidateArtifactHash === ''
            || ! hash_equals($verifiedArtifactHash, $candidateArtifactHash)) {
            throw new OmrAuthoringDraftException('compile_candidate_stale', 'Digital verification does not match the current materialized artifact set.');
        }
        $candidateVersion = (int) ($artifactSet['candidate_version'] ?? 0);
        $currentArtifact = $this->artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        if (! hash_equals($candidateArtifactHash, $currentArtifact->artifactHash)) {
            throw new OmrAuthoringDraftException('compile_candidate_stale', 'Marker qualification or printable geometry changed after digital verification.');
        }
        unset($currentArtifact);
        $expectedLockVersion = (int) $draft->lock_version;
        $manifest = $this->compactManifest($draft, $profile);
        $compiled = $this->compiler->compile($draft, $profile, $manifest);
        $version = $compiled['version'];

        try {
            DB::transaction(function () use ($draft, $version, $authoring, $manifest, $expectedLockVersion, $candidateArtifactHash): void {
                $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
                $lockedAuthoring = (array) $locked->authoring_json;
                $lockedArtifactHash = (string) ($lockedAuthoring['canonical']['artifact_set']['artifact_hash'] ?? '');
                if ((int) $locked->lock_version !== $expectedLockVersion
                    || $locked->stage !== OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED
                    || $lockedArtifactHash === ''
                    || ! hash_equals($lockedArtifactHash, $candidateArtifactHash)) {
                    throw new OmrAuthoringDraftException('compile_concurrency_conflict', 'The draft changed while compile was running.');
                }
                foreach ($draft->assets as $asset) {
                    $this->assets->pinToVersion($asset, $version);
                }
                $nextAuthoring = [...$authoring, 'compiled' => ['version_id' => $version->id, 'bundle_hash' => $version->native_bundle_hash, 'manifest' => $manifest]];
                $locked->forceFill([
                    'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
                    'compiled_version_id' => $version->id,
                    'authoring_json' => $nextAuthoring,
                    'lock_version' => (int) $locked->lock_version + 1,
                ])->save();
            });
        } catch (\Throwable $exception) {
            $version->canonicalAssets()->delete();
            Storage::disk('local')->deleteDirectory('omr-canonical/versions/'.$version->id);
            $version->delete();
            Storage::disk('local')->deleteDirectory($compiled['relative_dir']);
            throw $exception;
        }

        $saved = $draft->fresh();
        $this->recordAuditEvent->handle('omr.canonical.draft_compiled', [
            'draft_id' => $saved->id,
            'template_version_id' => $version->id,
            'bundle_hash' => $version->native_bundle_hash,
            'authoring_manifest' => $manifest,
        ], (string) $actor->id);

        return $saved;
    }

    /** @return array<string, mixed> */
    private function compactManifest(OmrTemplateAuthoringDraft $draft, $profile): array
    {
        $authoring = (array) $draft->authoring_json;
        $source = (array) ($authoring['source'] ?? []);
        $canonical = (array) ($authoring['canonical'] ?? []);
        $mapping = (array) ($authoring['mapping'] ?? []);
        $verification = (array) ($authoring['verification'] ?? []);

        return [
            'source_sha256' => $source['sha256'] ?? null,
            'converter_version' => $source['converter_version'] ?? null,
            'settings_hash' => $source['settings_hash'] ?? null,
            'composition_hash' => $canonical['composition_hash'] ?? null,
            'overlay_hash' => $authoring['overlay']['hash'] ?? null,
            'overlay' => $authoring['overlay'] ?? null,
            'mapping_hash' => $mapping['mapping_hash'] ?? null,
            'verification_hashes' => $verification['hashes'] ?? [],
            'marker_profile' => [
                'id' => $profile->id,
                'code' => $profile->code,
                'version' => $profile->version,
                'asset_sha256' => $profile->asset_sha256,
            ],
            'paper' => [
                'width_mm' => $canonical['settings']['paper_width_mm'] ?? null,
                'height_mm' => $canonical['settings']['paper_height_mm'] ?? null,
            ],
            'marker_insets_mm' => $authoring['marker']['insets_mm'] ?? $canonical['settings']['marker_inset_mm'] ?? null,
            'page_count' => count((array) ($authoring['pages'] ?? [])),
            'canonical_raster_hashes' => array_values(array_intersect_key(
                (array) ($canonical['raster_assets'] ?? []),
                array_flip(array_keys((array) ($canonical['raster_assets'] ?? []))),
            )),
        ];
    }
}
