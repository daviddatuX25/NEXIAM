<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function count;

final class MaterializeOmrCanonicalDraft
{
    public function __construct(
        private readonly CanonicalArtifactMaterializer $materializer,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly CurrentOmrDigitalVerification $verification,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, int $expectedLockVersion): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        if ((int) $draft->lock_version !== $expectedLockVersion) {
            throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.');
        }
        if ($draft->isCompiled()) {
            throw new OmrAuthoringDraftException('draft_read_only', 'Compiled drafts are immutable.');
        }
        $profile = $draft->markerProfile;
        $previousCanonical = (array) (($draft->authoring_json['canonical'] ?? []) ?: []);
        $sourcePdfAssetId = $draft->source_kind !== OmrTemplateAuthoringDraft::SOURCE_HTML
            ? (int) (($previousCanonical['source_pdf_asset_id'] ?? null) ?: ($previousCanonical['pdf_asset_id'] ?? 0))
            : null;

        $artifactSet = $this->materializer->materialize($draft->fresh(['template', 'markerProfile']), $profile);
        $rasterAssets = [];
        foreach ($artifactSet->pages as $page) {
            $asset = $this->assets->putDraftAsset(
                $draft,
                OmrTemplateAsset::KIND_RASTER_PAGE,
                $page['bytes'],
                'image/png',
                $page['page_no'],
                [...$page['metadata'], 'artifact_hash' => $artifactSet->artifactHash],
            );
            $rasterAssets[] = ['page_no' => $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        }
        $isUploaded = OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL;
        $pdfAsset = $isUploaded ? null : $this->assets->putDraftAsset(
            $draft,
            OmrTemplateAsset::KIND_PRINT_PDF,
            $artifactSet->pdfBytes,
            'application/pdf',
            null,
            ['artifact_hash' => $artifactSet->artifactHash, 'actual_size_instruction' => 'Print at 100% / actual size — do not fit to page.'],
        );
        $templateAsset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_TEMPLATE, $artifactSet->file('template.json'), 'application/json', null, ['artifact_hash' => $artifactSet->artifactHash]);
        $configAsset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_CONFIG, $artifactSet->file('config.json'), 'application/json', null, ['artifact_hash' => $artifactSet->artifactHash]);
        $manifestAsset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_MANIFEST, $artifactSet->file('manifest.json'), 'application/json', null, ['artifact_hash' => $artifactSet->artifactHash]);
        $referenceAssets = [];
        foreach ($artifactSet->files as $name => $bytes) {
            if (! str_starts_with($name, 'reference-page-')) {
                continue;
            }
            $pageNo = (int) preg_replace('/\D+/', '', $name);
            $asset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_REFERENCE_PAGE, $bytes, 'image/svg+xml', $pageNo, ['artifact_hash' => $artifactSet->artifactHash]);
            $referenceAssets[] = ['page_no' => $pageNo, 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        }
        $markerAsset = $profile !== null
            ? $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_MARKER_PROFILE, $artifactSet->file(basename((string) $profile->asset_path)), 'image/jpeg', null, ['artifact_hash' => $artifactSet->artifactHash, 'marker_profile_id' => $profile->id])
            : null;

        return DB::transaction(function () use ($draft, $actor, $expectedLockVersion, $sourcePdfAssetId, $artifactSet, $rasterAssets, $pdfAsset, $templateAsset, $configAsset, $manifestAsset, $referenceAssets, $markerAsset, $isUploaded): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.');
            }
            $authoring = (array) $locked->authoring_json;
            $canonical = (array) ($authoring['canonical'] ?? []);
            $previousArtifactHash = (string) ($canonical['artifact_set']['artifact_hash'] ?? $canonical['artifact_hash'] ?? '');
            $canonical['raster_assets'] = $rasterAssets;
            $canonical['pdf_asset_id'] = $pdfAsset?->id;
            $canonical['artifact_hash'] = $artifactSet->artifactHash;
            if (! $isUploaded && $sourcePdfAssetId !== null && $sourcePdfAssetId > 0) {
                $canonical['source_pdf_asset_id'] = $sourcePdfAssetId;
            } else {
                $canonical['source_pdf_asset_id'] = null;
            }
            $canonical['artifact_set'] = [
                'schema' => 'nexiam.omr.canonical-artifact-set.v1',
                'registration_mode' => $artifactSet->definition['registration_mode'],
                'qr_mode' => $artifactSet->definition['qr_mode'],
                'compiled_pages' => $artifactSet->definition['compiled_pages'],
                'artifact_hash' => $artifactSet->artifactHash,
                'geometry_identity' => $artifactSet->geometryIdentity,
                'mapping_hash' => $artifactSet->geometryIdentity,
                'definition_hash' => $artifactSet->definition['source_hash'],
                'insets_px' => $artifactSet->definition['insets_px'] ?? [],
                'candidate_version' => $artifactSet->definition['candidate_version'],
                'template_hash' => $artifactSet->templateHash,
                'config_hash' => $artifactSet->configHash,
                'pdf_hash' => $isUploaded ? null : $artifactSet->pdfHash,
                'reference_hash' => $artifactSet->referenceHash,
                'marker_hash' => $artifactSet->markerHash,
                'native_manifest_asset_id' => $manifestAsset->id,
                'raster_assets' => $rasterAssets,
                'pdf_asset_id' => $pdfAsset?->id,
                'native_template_asset_id' => $templateAsset->id,
                'native_config_asset_id' => $configAsset->id,
                'reference_assets' => $referenceAssets,
                'marker_asset_id' => $markerAsset?->id,
                'template' => $artifactSet->template,
                'review_manifest_hash' => $artifactSet->reviewManifestHash,
                'review_manifest' => $artifactSet->reviewManifest,
                'config' => $artifactSet->config,
                'manifest' => $artifactSet->manifest,
            ];
            $canonical['settings'] = [
                ...(array) ($canonical['settings'] ?? []),
                'paper_width_mm' => $artifactSet->definition['paper']['width_mm'],
                'paper_height_mm' => $artifactSet->definition['paper']['height_mm'],
            ];
            $authoring['canonical'] = $canonical;
            $isIdenticalArtifact = $previousArtifactHash !== '' && hash_equals($previousArtifactHash, $artifactSet->artifactHash);
            $matchingVerification = $isIdenticalArtifact
                ? $this->verification->findForTarget($locked->id, $artifactSet->artifactHash, $artifactSet->geometryIdentity, $locked)
                : null;

            if ($matchingVerification !== null) {
                $authoring['verification'] = $matchingVerification->rebuildVerificationPayload($locked);
                $nextStage = OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED;
            } else {
                $authoring['verification'] = null;
                $nextStage = $locked->stage === OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED
                    ? OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE
                    : $locked->stage;
            }

            $locked->forceFill([
                'stage' => $nextStage,
                'authoring_json' => $authoring,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
            $saved = $locked->fresh();
            $this->recordAuditEvent->handle('omr.canonical.artifacts_materialized', [
                'draft_id' => $saved->id,
                'artifact_hash' => $artifactSet->artifactHash,
                'definition_hash' => $artifactSet->definition['source_hash'],
                'registration_mode' => $artifactSet->definition['registration_mode'],
                'qr_mode' => $artifactSet->definition['qr_mode'],
                'compiled_pages' => $artifactSet->definition['compiled_pages'],
                'candidate_version' => $artifactSet->definition['candidate_version'],
                'raster_count' => count($rasterAssets),
            ], (string) $actor->id);

            return $saved;
        });
    }
}
