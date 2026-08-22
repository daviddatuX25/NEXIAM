<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrSourceAdapterRegistry;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Foundation\Actions\RecordAuditEvent;
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

use function config;
use function file_get_contents;
use function hash;
use function is_string;

final class RenderOmrSourcePagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly int $draftId,
        public readonly int $sourceAssetId,
        public readonly string $sourceKind,
        public readonly int $actorId,
        public readonly array $options = [],
    ) {
        $this->timeout = (int) config('omr.canonical.limits.conversion_timeout_seconds_per_page', 300)
            * (int) config('omr.canonical.limits.max_pages_per_source', 20);
    }

    public function handle(
        OmrSourceAdapterRegistry $registry,
        OmrTemplateAssetStore $assets,
        OmrAuthoringDraftService $drafts,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $draft = OmrTemplateAuthoringDraft::query()->findOrFail($this->draftId);
        $runState = (array) ($draft->authoring_json['source_run'] ?? []);
        if ((int) ($runState['source_asset_id'] ?? 0) !== $this->sourceAssetId) {
            return;
        }
        if (($runState['cancel_requested'] ?? false) === true) {
            $authoring = (array) $draft->authoring_json;
            $authoring['source_run'] = [...$runState, 'status' => 'cancelled'];
            $draft->forceFill(['authoring_json' => $authoring])->save();
            return;
        }
        $authoring = (array) $draft->authoring_json;
        $authoring['source_run'] = [...$runState, 'status' => 'processing'];
        $draft->forceFill(['authoring_json' => $authoring])->save();
        $sourceAsset = OmrTemplateAsset::query()
            ->whereKey($this->sourceAssetId)
            ->where('authoring_draft_id', $draft->id)
            ->firstOrFail();
        $sourceBytes = Storage::disk($sourceAsset->disk)->get($sourceAsset->path);
        if (! is_string($sourceBytes) || hash('sha256', $sourceBytes) !== $sourceAsset->sha256) {
            throw new RuntimeException('asset_integrity_failure');
        }
        $extension = strtolower((string) pathinfo((string) ($sourceAsset->metadata['original_name'] ?? ''), PATHINFO_EXTENSION));
        $temporary = tempnam(sys_get_temp_dir(), 'nexiam-omr-source-');
        if ($temporary === false) {
            throw new RuntimeException('source_temp_write_failed');
        }
        $sourcePath = $temporary.($extension !== '' ? '.'.$extension : '');
        if (! rename($temporary, $sourcePath) || file_put_contents($sourcePath, $sourceBytes) === false) {
            throw new RuntimeException('source_temp_write_failed');
        }

        $adapter = $registry->get($this->sourceKind);
        $result = $adapter->convert($sourcePath, $this->options);
        $pageManifest = [];
        foreach ($result->pages as $page) {
            $pageBytes = (string) file_get_contents($page['path']);
            $pageAsset = $assets->putDraftAsset(
                $draft,
                OmrTemplateAsset::KIND_INTERMEDIATE_PAGE,
                $pageBytes,
                (string) $page['mime'],
                (int) $page['page_no'],
                [
                    'width' => $page['width'],
                    'height' => $page['height'],
                    'width_mm' => $page['width_mm'],
                    'height_mm' => $page['height_mm'],
                    'orientation' => $page['orientation'],
                    'metadata' => $page['metadata'],
                ],
            );
            $pageManifest[] = [
                'page_no' => $page['page_no'],
                'asset_id' => $pageAsset->id,
                'sha256' => $pageAsset->sha256,
                'width' => $page['width'],
                'height' => $page['height'],
                'width_mm' => $page['width_mm'],
                'height_mm' => $page['height_mm'],
                'orientation' => $page['orientation'],
                'confirmed' => false,
                'rejection_reason' => null,
            ];
        }
        if (isset($result->metadata['pdf_path']) && is_string($result->metadata['pdf_path']) && is_file($result->metadata['pdf_path'])) {
            $pdfBytes = (string) file_get_contents($result->metadata['pdf_path']);
            $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_PRINT_PDF, $pdfBytes, 'application/pdf', null, [
                'converter_version' => $result->converterVersion,
                'settings_hash' => $result->settingsHash,
            ]);
        }

        $sourceAssetId = $this->sourceAssetId;
        DB::transaction(function () use ($draft, $result, $pageManifest, $sourceAssetId): void {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $authoring = (array) $locked->authoring_json;
            if ((int) ($authoring['source_run']['source_asset_id'] ?? 0) !== $sourceAssetId) {
                return;
            }
            $authoring['source'] = [
                'sha256' => $result->sourceSha256,
                'converter_version' => $result->converterVersion,
                'settings_hash' => $result->settingsHash,
                'metadata' => $result->metadata,
            ];
            $authoring['pages'] = $pageManifest;
            $authoring['source_run'] = [
                ...(array) ($authoring['source_run'] ?? []),
                'status' => 'succeeded',
                'progress_done' => 1,
                'progress_total' => 1,
            ];
            $authoring['verification'] = null;
            $locked->forceFill([
                'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
                'authoring_json' => $authoring,
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
        });

        $recordAuditEvent->handle('omr.canonical.source_pages_rendered', [
            'draft_id' => $draft->id,
            'source_asset_id' => $sourceAsset->id,
            'source_sha256' => $result->sourceSha256,
            'converter_version' => $result->converterVersion,
            'settings_hash' => $result->settingsHash,
            'page_count' => $result->pageCount(),
        ], (string) $this->actorId);
        @unlink($sourcePath);
    }
    public function failed(?Throwable $exception): void
    {
        $draft = OmrTemplateAuthoringDraft::query()->find($this->draftId);
        if ($draft !== null) {
            $authoring = (array) $draft->authoring_json;
            if ((int) ($authoring['source_run']['source_asset_id'] ?? 0) !== $this->sourceAssetId) {
                return;
            }
            $authoring['source_run'] = [
                ...(array) ($authoring['source_run'] ?? []),
                'status' => 'failed',
                'safe_error' => $exception?->getMessage() ?? 'source_conversion_failed',
            ];
            $draft->forceFill(['authoring_json' => $authoring])->save();
        }
        app(RecordAuditEvent::class)->handle('omr.canonical.source_pages_failed', [
            'draft_id' => $this->draftId,
            'source_asset_id' => $this->sourceAssetId,
            'error' => $exception?->getMessage() ?? 'unknown',
        ], (string) $this->actorId);
    }
}
