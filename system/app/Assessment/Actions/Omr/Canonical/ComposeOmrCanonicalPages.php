<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalComposer;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ComposeOmrCanonicalPages
{
    public function __construct(
        private readonly OmrCanonicalComposer $composer,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param array<string, mixed> $settings */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, array $settings): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        $result = $this->composer->compose($draft->fresh(), $settings);
        if (count($result->pages) !== (int) $draft->requested_page_count) {
            throw new \RuntimeException('composed_page_count_mismatch');
        }
        $rasterAssets = [];
        foreach ($result->pages as $page) {
            $asset = $this->assets->putDraftAsset(
                $draft,
                OmrTemplateAsset::KIND_RASTER_PAGE,
                $page['bytes'],
                $page['mime'],
                $page['page_no'],
                $page['metadata'],
            );
            $rasterAssets[] = ['page_no' => $page['page_no'], 'asset_id' => $asset->id, 'sha256' => $asset->sha256];
        }
        $isUploaded = OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL;
        $pdfAsset = $isUploaded ? null : $this->assets->putDraftAsset(
            $draft,
            OmrTemplateAsset::KIND_PRINT_PDF,
            $result->pdfBytes,
            'application/pdf',
            null,
            ['actual_size_instruction' => 'Print at 100% / actual size — do not fit to page.'],
        );

        return DB::transaction(function () use ($draft, $actor, $settings, $result, $rasterAssets, $pdfAsset, $isUploaded): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $authoring = (array) $locked->authoring_json;
            $authoring['canonical'] = [
                'settings' => $result->settings,
                'raster_assets' => $rasterAssets,
                'pdf_asset_id' => $pdfAsset?->id,
                'source_pdf_asset_id' => $pdfAsset?->id,
                'composition_hash' => $result->compositionHash,
                ...($isUploaded ? [] : ['actual_size_instruction' => 'Print at 100% / actual size — do not fit to page.']),
            ];
            $authoring['mapping'] = null;
            $authoring['verification'] = null;
            $locked->forceFill([
                'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
                'authoring_json' => $authoring,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
            $saved = $locked->fresh();

            $this->recordAuditEvent->handle('omr.canonical.pages_composed', [
                'draft_id' => $saved->id,
                'composition_hash' => $result->compositionHash,
                'pdf_asset_id' => $pdfAsset?->id,
                'raster_assets' => $rasterAssets,
                'settings' => $settings,
            ], (string) $actor->id);

            return $saved;
        });
    }
}
