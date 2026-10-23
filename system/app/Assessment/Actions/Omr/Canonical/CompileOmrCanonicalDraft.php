<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalNativeBundleCompiler;
use App\Assessment\Omr\Canonical\OmrMappingValidator;
use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function array_intersect_key;
use function array_keys;
use function array_values;
use function count;
use function hash_equals;
use function is_string;

final class CompileOmrCanonicalDraft
{
    public function __construct(
        private readonly OmrCanonicalNativeBundleCompiler $compiler,
        private readonly OmrMarkerProfileService $profiles,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly OmrMappingValidator $mappingValidator,
        private readonly CanonicalArtifactMaterializer $artifacts,
        private readonly CurrentOmrDigitalVerification $verification,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(OmrTemplateAuthoringDraft $draft, User $actor): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        $draft = $draft->fresh(['markerProfile', 'assets']);

        if ($draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED && $draft->compiled_version_id !== null) {
            $compiledVersion = OmrAnswerSheetTemplateVersion::query()->find($draft->compiled_version_id);
            if ($compiledVersion !== null) {
                return $draft;
            }
        }
        $authoring = (array) $draft->authoring_json;
        $isUploaded = in_array($draft->source_kind, [
            OmrTemplateAuthoringDraft::SOURCE_PDF,
            OmrTemplateAuthoringDraft::SOURCE_IMAGE,
            OmrTemplateAuthoringDraft::SOURCE_DOCX,
        ], true);
        if ($isUploaded) {
            $input = $authoring['mapping']['input'] ?? null;
            $result = is_array($input) ? $this->mappingValidator->validate($input, [
                'page_count' => (int) $draft->requested_page_count,
                'require_declaration' => true,
                'source_kind' => $draft->source_kind,
                'is_uploaded' => true,
            ]) : ['valid' => false];
            if (! $result['valid']) {
                throw new OmrAuthoringDraftException('compile_mapping_incomplete', 'Declare every physical response location and Applicant Number method before compiling an uploaded sheet.');
            }
        }
        $physicalInventory = (array) data_get($authoring, 'studio.physicalInventory', []);
        if ((int) ($physicalInventory['revision'] ?? 0) < 1 || (array) ($physicalInventory['sections'] ?? []) === []) {
            throw new OmrAuthoringDraftException('compile_physical_inventory_incomplete', 'Manage Printed sections and physical locations before compiling this Answer Sheet.');
        }
        $artifactSet = (array) ($authoring['canonical']['artifact_set'] ?? []);
        $candidateArtifactHash = (string) ($artifactSet['artifact_hash'] ?? '');
        $candidateGeometryIdentity = (string) ($artifactSet['geometry_identity'] ?? '');
        $verifiedMatch = $this->verification->find($draft)
            ?? $this->verification->findForTarget(
                (int) $draft->id,
                $candidateArtifactHash,
                $candidateGeometryIdentity,
                $draft,
            );
        if ($verifiedMatch === null) {
            throw new OmrAuthoringDraftException('compile_gate_missing', 'Digital verification is required before compile.');
        }

        $profile = $draft->registration_mode === OmrRegistrationMode::FiducialAnchored
            ? $this->profiles->assertQualified($draft->markerProfile)
            : null;
        if ($candidateArtifactHash === '' || ! hash_equals($verifiedMatch->artifactHash, $candidateArtifactHash)) {
            throw new OmrAuthoringDraftException('compile_candidate_stale', 'Digital verification does not match the current materialized artifact set.');
        }

        $candidateVersion = (int) ($artifactSet['candidate_version'] ?? 0);
        $currentArtifact = $this->artifacts->materialize($draft, $profile, $candidateVersion > 0 ? $candidateVersion : null);
        if (! hash_equals($candidateArtifactHash, $currentArtifact->artifactHash)) {
            throw new OmrAuthoringDraftException('compile_candidate_stale', 'Registration policy or printable geometry changed after digital verification.');
        }
        unset($currentArtifact);

        $expectedLockVersion = (int) $draft->lock_version;

        $verificationPayload = (isset($authoring['verification']['status']) && $authoring['verification']['status'] === 'passed' && hash_equals((string) ($authoring['verification']['artifact_hash'] ?? ''), $verifiedMatch->artifactHash))
            ? $authoring['verification']
            : $verifiedMatch->rebuildVerificationPayload($draft);

        if (! isset($verificationPayload['hashes']['matrix_hash'])) {
            $verificationPayload['hashes'] = [
                ...((array) ($verificationPayload['hashes'] ?? [])),
                'matrix_hash' => $verifiedMatch->matrixHash,
                'proof_hash' => $verifiedMatch->proofHash,
                'expected_vector_hash' => $verifiedMatch->expectedVectorHash,
                'clean_vector_hash' => $verifiedMatch->cleanVectorHash,
                'run_id' => (string) $verifiedMatch->run->id,
            ];
        }

        $authoring['verification'] = $verificationPayload;
        $manifest = $this->compactManifest($draft, $profile, $verificationPayload);
        $compiled = $this->compiler->compile($draft, $profile, $manifest, $actor);
        $version = $compiled['version'];

        try {
            DB::transaction(function () use ($draft, $version, $manifest, $expectedLockVersion, $candidateArtifactHash, $verificationPayload, $artifactSet): void {
                $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
                $lockedAuthoring = (array) $locked->authoring_json;
                $lockedArtifactHash = (string) ($lockedAuthoring['canonical']['artifact_set']['artifact_hash'] ?? '');
                if ((int) $locked->lock_version !== $expectedLockVersion
                    || $lockedArtifactHash === ''
                    || ! hash_equals($lockedArtifactHash, $candidateArtifactHash)) {
                    throw new OmrAuthoringDraftException('compile_concurrency_conflict', 'The draft changed while compile was running.');
                }
                $assetsById = $draft->assets->keyBy('id');
                foreach ($this->currentArtifactAssets($artifactSet, $draft->source_kind) as $assetId => $expectedAsset) {
                    $asset = $assetsById->get($assetId);
                    if (! $asset instanceof OmrTemplateAsset
                        || (int) $asset->authoring_draft_id !== (int) $draft->id
                        || $asset->template_version_id !== null
                        || $asset->kind !== $expectedAsset['kind']
                        || ($expectedAsset['sha256'] !== null && ! hash_equals($expectedAsset['sha256'], (string) $asset->sha256))) {
                        throw new OmrAuthoringDraftException('compile_candidate_stale', 'Materialized artifact assets changed before compile.');
                    }
                    $this->assets->pinToVersion($asset, $version);
                }
                $version->forceFill([
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
                ])->save();

                $nextAuthoring = [
                    ...$lockedAuthoring,
                    'verification' => $verificationPayload,
                    'compiled' => ['version_id' => $version->id, 'bundle_hash' => $version->native_bundle_hash, 'manifest' => $manifest],
                ];
                $locked->forceFill([
                    'template_id' => $version->template_id,
                    'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
                    'compiled_version_id' => $version->id,
                    'authoring_json' => $nextAuthoring,
                    'lock_version' => (int) $locked->lock_version + 1,
                ])->save();
            });
        } catch (\Throwable $exception) {
            $version->refresh();
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
            'registration_mode' => $version->registration_mode->value,
            'qr_mode' => $version->qr_mode->value,
            'compiled_pages' => $manifest['compiled_pages'],
            'bundle_hash' => $version->native_bundle_hash,
            'authoring_manifest' => $manifest,
        ], (string) $actor->id);

        return $saved;
    }

    /**
     * @param  array<string, mixed>|null  $verificationOverride
     * @return array<string, mixed>
     */
    private function compactManifest(OmrTemplateAuthoringDraft $draft, ?OmrMarkerProfile $profile, ?array $verificationOverride = null): array
    {
        $authoring = (array) $draft->authoring_json;
        $source = (array) ($authoring['source'] ?? []);
        $canonical = (array) ($authoring['canonical'] ?? []);
        $mapping = (array) ($authoring['mapping'] ?? []);
        $verification = $verificationOverride ?? (array) ($authoring['verification'] ?? []);

        $artifactSet = (array) ($canonical['artifact_set'] ?? []);
        $geometryIdentity = (string) ($artifactSet['geometry_identity'] ?? $mapping['mapping_hash'] ?? '');

        return [
            'registration_mode' => $draft->registration_mode->value,
            'source_kind' => in_array($draft->source_kind, [
                OmrTemplateAuthoringDraft::SOURCE_PDF,
                OmrTemplateAuthoringDraft::SOURCE_IMAGE,
                OmrTemplateAuthoringDraft::SOURCE_DOCX,
            ], true) ? 'uploaded_original' : 'system_designed',
            'physical_inventory' => $authoring['studio']['physicalInventory'] ?? null,
            'declared_inventory' => $mapping['input']['declared_inventory'] ?? null,
            'identity_method' => $mapping['input']['identity'] ?? null,
            'qr_mode' => $draft->qr_mode->value,
            'compiled_pages' => $artifactSet['compiled_pages'] ?? [],
            'source_sha256' => $source['sha256'] ?? null,
            'converter_version' => $source['converter_version'] ?? null,
            'settings_hash' => $source['settings_hash'] ?? null,
            'composition_hash' => $canonical['composition_hash'] ?? null,
            'overlay_hash' => $authoring['overlay']['hash'] ?? null,
            'overlay' => $authoring['overlay'] ?? null,
            'geometry_identity' => $geometryIdentity !== '' ? $geometryIdentity : null,
            'mapping_hash' => $geometryIdentity !== '' ? $geometryIdentity : ($mapping['mapping_hash'] ?? null),
            'artifact_set' => $artifactSet,
            'verification_hashes' => $verification['hashes'] ?? [],
            ...($profile !== null ? ['marker_profile' => [
                'id' => $profile->id,
                'code' => $profile->code,
                'version' => $profile->version,
                'asset_sha256' => $profile->asset_sha256,
            ]] : []),
            'paper' => [
                'width_mm' => $canonical['settings']['paper_width_mm'] ?? null,
                'height_mm' => $canonical['settings']['paper_height_mm'] ?? null,
            ],
            ...($profile !== null ? ['marker_insets_mm' => $authoring['marker']['insets_mm'] ?? $canonical['settings']['marker_inset_mm'] ?? null] : []),
            'page_count' => count((array) ($artifactSet['compiled_pages'] ?? [])),
            'canonical_raster_hashes' => array_values(array_intersect_key(
                (array) ($canonical['raster_assets'] ?? []),
                array_flip(array_keys((array) ($canonical['raster_assets'] ?? []))),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $artifactSet
     * @return array<int, array{kind: string, sha256: ?string}>
     */
    private function currentArtifactAssets(array $artifactSet, string $sourceKind): array
    {
        $assets = [];
        $expected = [
            ['native_template_asset_id', OmrTemplateAsset::KIND_NATIVE_TEMPLATE, $artifactSet['template_hash'] ?? null],
            ['native_config_asset_id', OmrTemplateAsset::KIND_NATIVE_CONFIG, $artifactSet['config_hash'] ?? null],
            ['native_manifest_asset_id', OmrTemplateAsset::KIND_NATIVE_MANIFEST, null],
        ];
        if (in_array($sourceKind, [OmrTemplateAuthoringDraft::SOURCE_PDF, OmrTemplateAuthoringDraft::SOURCE_IMAGE, OmrTemplateAuthoringDraft::SOURCE_DOCX], true)) {
            if ((int) ($artifactSet['pdf_asset_id'] ?? 0) > 0 || ($artifactSet['pdf_hash'] ?? null) !== null) {
                throw new OmrAuthoringDraftException('compile_uploaded_print_asset_forbidden', 'Licensed uploaded sheets cannot have a generated print artifact.');
            }
        } else {
            $expected[] = ['pdf_asset_id', OmrTemplateAsset::KIND_PRINT_PDF, $artifactSet['pdf_hash'] ?? null];
        }
        foreach ($expected as [$idKey, $kind, $sha256]) {
            $this->addArtifactAsset($assets, (int) ($artifactSet[$idKey] ?? 0), $kind, $sha256);
        }

        $pageCount = count((array) ($artifactSet['compiled_pages'] ?? []));
        if ($pageCount < 1 || $pageCount > 4) {
            throw new OmrAuthoringDraftException('compile_candidate_stale', 'Materialized artifact set has an invalid page count.');
        }

        foreach ([
            ['raster_assets', OmrTemplateAsset::KIND_RASTER_PAGE],
            ['reference_assets', OmrTemplateAsset::KIND_REFERENCE_PAGE],
        ] as [$key, $kind]) {
            $pageAssets = array_values((array) ($artifactSet[$key] ?? []));
            if (count($pageAssets) !== $pageCount) {
                throw new OmrAuthoringDraftException('compile_candidate_stale', 'Materialized artifact set is missing a page asset.');
            }

            foreach ($pageAssets as $index => $pageAsset) {
                $pageAsset = (array) $pageAsset;
                if ((int) ($pageAsset['page_no'] ?? 0) !== $index + 1) {
                    throw new OmrAuthoringDraftException('compile_candidate_stale', 'Materialized artifact page assets are out of order.');
                }
                $this->addArtifactAsset($assets, (int) ($pageAsset['asset_id'] ?? 0), $kind, $pageAsset['sha256'] ?? null);
            }
        }

        $markerAssetId = (int) ($artifactSet['marker_asset_id'] ?? 0);
        if ($markerAssetId > 0) {
            $this->addArtifactAsset($assets, $markerAssetId, OmrTemplateAsset::KIND_MARKER_PROFILE, $artifactSet['marker_hash'] ?? null);
        }

        return $assets;
    }

    /**
     * @param  array<int, array{kind: string, sha256: ?string}>  $assets
     */
    private function addArtifactAsset(array &$assets, int $assetId, string $kind, mixed $sha256): void
    {
        $expectedHash = is_string($sha256) && $sha256 !== '' ? $sha256 : null;
        if ($assetId <= 0 || ($kind !== OmrTemplateAsset::KIND_NATIVE_MANIFEST && $expectedHash === null)) {
            throw new OmrAuthoringDraftException('compile_candidate_stale', 'Materialized artifact set is missing an asset identity.');
        }

        $expectation = ['kind' => $kind, 'sha256' => $expectedHash];
        if (isset($assets[$assetId]) && $assets[$assetId] !== $expectation) {
            throw new OmrAuthoringDraftException('compile_candidate_stale', 'Materialized artifact asset identity is inconsistent.');
        }
        $assets[$assetId] = $expectation;
    }
}
