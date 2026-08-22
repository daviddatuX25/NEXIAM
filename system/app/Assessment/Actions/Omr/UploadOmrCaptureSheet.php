<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\OmrDetectionRequest;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\AnalyzeOmrCaptureSheetJob;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;
use function strlen;

/**
 * Page-aware OMR upload coordinator.
 *
 * The first page creates the logical sheet; later pages attach by
 * (batch, client_capture_id) in any arrival order. Analysis dispatches only
 * after every page of the set has landed. Single-page bundles keep the exact
 * legacy behavior (asset mirrored on the sheet row).
 */
final class UploadOmrCaptureSheet
{
    public function __construct(
        private readonly SensitiveAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(
        OmrCaptureBatch $batch,
        string $imageBytes,
        string $mime,
        string $captureMode,
        string $clientCaptureId,
        User $actor,
        ?int $admissionAttemptId = null,
        int $pageNo = 1,
    ): OmrCaptureSheet {
        if (! in_array($captureMode, [OmrCaptureSheet::MODE_MANUAL, OmrCaptureSheet::MODE_AUTO], true)) {
            throw new RuntimeException('Unsupported OMR capture mode.');
        }

        return DB::transaction(function () use ($batch, $imageBytes, $mime, $captureMode, $clientCaptureId, $actor, $admissionAttemptId, $pageNo): OmrCaptureSheet {
            $batch = OmrCaptureBatch::query()
                ->with(['templateVersion', 'bookletVersion'])
                ->lockForUpdate()
                ->findOrFail($batch->id);

            if ($batch->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Sheets can only be uploaded while the OMR batch is open.');
            }

            if ($batch->templateVersion === null || $batch->bookletVersion === null) {
                throw new RuntimeException('The OMR batch is missing its immutable template or booklet pin.');
            }

            $pageCount = (int) $batch->templateVersion->pageCount();
            if ($pageNo < 1 || $pageNo > $pageCount) {
                throw new RuntimeException("Page {$pageNo} is outside this sheet's page count of {$pageCount}.");
            }

            $sheet = OmrCaptureSheet::query()
                ->where('batch_id', $batch->id)
                ->where('client_capture_id', $clientCaptureId)
                ->lockForUpdate()
                ->first();

            $existingPage = $sheet?->pages()->where('page_no', $pageNo)->first();
            if ($existingPage !== null && $existingPage->asset_path !== null) {
                throw new RuntimeException("Page {$pageNo} of this sheet set was already uploaded.");
            }

            $path = $this->assetStore->store(
                $imageBytes,
                'omr/assets/'.Str::uuid()->toString().'.bin',
            );

            $pageFields = [
                'sheet_id' => $sheet->id ?? null,
                'page_no' => $pageNo,
                'status' => OmrCaptureSheetPage::STATUS_QUEUED,
                'asset_path' => $path,
                'asset_hmac' => $this->assetStore->hmac($imageBytes),
                'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
                'asset_format_version' => 'v1',
                'asset_key_id' => (string) config('omr.keyring.active_key_id'),
                'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                'original_mime' => $mime,
                'original_bytes' => strlen($imageBytes),
            ];

            if ($sheet === null) {
                $sheetFields = [
                    'batch_id' => $batch->id,
                    'admission_attempt_id' => $admissionAttemptId,
                    'status' => $pageCount === 1
                        ? OmrCaptureSheet::STATUS_QUEUED
                        : OmrCaptureSheet::STATUS_PENDING_PAGES,
                    'capture_mode' => $captureMode,
                    'version' => 0,
                    'expected_page_count' => $pageCount,
                    'client_capture_id' => $clientCaptureId,
                    'captured_by_user_id' => $actor->id,
                    'captured_at' => now(),
                ];

                // Single-page sheets must carry the asset mirror at insert
                // time: `omr_capture_sheets_asset_check` requires
                // `asset_path IS NOT NULL` unless `expected_page_count > 1`.
                if ($pageCount === 1) {
                    $sheetFields += [
                        'asset_path' => $path,
                        'asset_hmac' => $pageFields['asset_hmac'],
                        'asset_hmac_key_id' => $pageFields['asset_hmac_key_id'],
                        'asset_format_version' => $pageFields['asset_format_version'],
                        'asset_key_id' => $pageFields['asset_key_id'],
                        'asset_state' => $pageFields['asset_state'],
                        'original_mime' => $mime,
                        'original_bytes' => strlen($imageBytes),
                    ];
                }

                $sheet = OmrCaptureSheet::query()->create($sheetFields);
            }

            if ($existingPage !== null) {
                $existingPage->update($pageFields);
                $page = $existingPage;
            } else {
                $page = $sheet->pages()->create($pageFields);
            }

            // Single-page legacy mirror: keep the asset on the sheet row too.
            if ($pageCount === 1) {
                $sheet->update([
                    'original_mime' => $mime,
                    'original_bytes' => strlen($imageBytes),
                    'asset_path' => $path,
                    'asset_hmac' => $pageFields['asset_hmac'],
                    'asset_hmac_key_id' => $pageFields['asset_hmac_key_id'],
                    'asset_format_version' => $pageFields['asset_format_version'],
                    'asset_key_id' => $pageFields['asset_key_id'],
                    'asset_state' => $pageFields['asset_state'],
                ]);
            }

            $runIds = [];
            if ($sheet->pages()->count() >= $pageCount) {
                $sheet->update(['status' => OmrCaptureSheet::STATUS_QUEUED]);

                $driver = (string) config('omr.provider.driver', 'fake');
                $providerName = $driver === 'fake'
                    ? (string) config('omr.provider.name', 'nexiam.fake')
                    : (string) $batch->templateVersion->engine_name;
                $providerVersion = $driver === 'fake'
                    ? (string) config('omr.provider.version', '1')
                    : (string) ($batch->templateVersion->engine_commit ?: 'unknown');
                $providerCommit = $driver === 'fake'
                    ? (config('omr.provider.commit') !== null ? (string) config('omr.provider.commit') : null)
                    : ($batch->templateVersion->engine_commit !== null ? (string) $batch->templateVersion->engine_commit : null);

                foreach ($sheet->pages()->orderBy('page_no')->get() as $pageRow) {
                    $pageBytes = $pageRow->id === $page->id
                        ? $imageBytes
                        : $this->assetStore->read((string) $pageRow->asset_path, (string) $pageRow->asset_hmac);

                    $run = $sheet->processingRuns()->create([
                        'actor_id' => $actor->id,
                        'run_no' => ((int) $sheet->processingRuns()->max('run_no')) + 1,
                        'run_uuid' => Str::uuid()->toString(),
                        'status' => OmrProcessingRun::STATUS_PENDING,
                        'page_no' => (int) $pageRow->page_no,
                        'provider_name' => $providerName,
                        'provider_version' => $providerVersion,
                        'provider_commit' => $providerCommit,
                        'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
                        'expected_sheet_version' => (int) $sheet->version,
                        'input_hash' => hash('sha256', $pageBytes),
                        'template_hash' => (string) $batch->templateVersion->native_bundle_hash,
                        'booklet_hash' => (string) $batch->bookletVersion->answer_key_hash,
                        'native_bundle_hash' => (string) $batch->templateVersion->native_bundle_hash,
                        'manifest_hash' => (string) $batch->templateVersion->review_manifest_hash,
                        'config_hash' => (string) $batch->templateVersion->config_hash,
                        'patch_identity' => (string) $batch->templateVersion->patch_identity,
                        'attempts' => 0,
                    ]);

                    $pageRow->forceFill(['current_processing_run_id' => $run->id])->save();
                    if ($pageCount === 1) {
                        $sheet->forceFill(['current_processing_run_id' => $run->id])->save();
                    }
                    $runIds[] = (int) $run->id;
                }
            }

            $this->recordAuditEvent->handle('grading.omr_sheet_received', [
                'sheet_id' => $sheet->id,
                'batch_id' => $batch->id,
                'capture_mode' => $captureMode,
                'client_capture_id' => $clientCaptureId,
                'page_no' => $pageNo,
                'page_count' => $pageCount,
                'processing_run_ids' => $runIds,
            ], (string) $actor->id);

            foreach ($runIds as $runId) {
                AnalyzeOmrCaptureSheetJob::dispatch($runId)->afterCommit();
            }

            return $sheet;
        });
    }
}
