<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
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

/**
 * Staff-initiated re-analysis of a failed or already-reviewed sheet.
 * Creates the NEXT processing run against the same immutable pins and
 * dispatches only after commit. One active run per sheet is enforced.
 */
final class RetryOmrCaptureSheet
{
    public function __construct(
        private readonly SensitiveAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrCaptureSheet $sheet,
        User $actor,
        int $pageNo = 1,
        ?int $expectedRevision = null,
    ): OmrProcessingRun {
        $run = DB::transaction(function () use ($sheet, $actor, $pageNo, $expectedRevision): OmrProcessingRun {
            $batch = OmrCaptureBatch::query()
                ->lockForUpdate()
                ->findOrFail($sheet->batch_id);
            $sheet = OmrCaptureSheet::query()
                ->with(['batch.templateVersion', 'batch.bookletVersion', 'processingRuns', 'pages'])
                ->lockForUpdate()
                ->findOrFail($sheet->id);
            $this->assertExpectedRevision($batch, $expectedRevision);

            if ($sheet->batch?->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Sheets can only be retried while the batch is open.');
            }

            if (! in_array($sheet->status, [
                OmrCaptureSheet::STATUS_FAILED_TERMINAL,
                OmrCaptureSheet::STATUS_NEEDS_REVIEW,
            ], true)) {
                throw new RuntimeException('This OMR sheet is not eligible for re-analysis.');
            }

            if ((int) $sheet->expected_page_count <= 1 && $sheet->pages()->doesntExist()) {
                $page = $sheet;
            } else {
                $page = $sheet->pages()->where('page_no', $pageNo)->first();
                if ($page === null) {
                    throw new RuntimeException("Page {$pageNo} of this sheet is missing.");
                }
            }

            $hasActiveRun = $sheet->processingRuns
                ->where('page_no', $pageNo)
                ->contains(fn (OmrProcessingRun $run): bool => $run->isActive());

            if ($hasActiveRun) {
                throw new RuntimeException("Page {$pageNo} already has an active analysis run.");
            }

            if ($page->asset_path === null || $page->asset_path === '') {
                throw new RuntimeException('The original capture asset is missing; re-analysis is unavailable.');
            }

            $bytes = $this->assetStore->read((string) $page->asset_path, (string) $page->asset_hmac);

            $driver = (string) config('omr.provider.driver', 'fake');
            $providerName = $driver === 'fake'
                ? (string) config('omr.provider.name', 'nexiam.fake')
                : (string) $sheet->batch->templateVersion->engine_name;
            $providerVersion = $driver === 'fake'
                ? (string) config('omr.provider.version', '1')
                : (string) ($sheet->batch->templateVersion->engine_commit ?: 'unknown');
            $providerCommit = $driver === 'fake'
                ? (config('omr.provider.commit') !== null ? (string) config('omr.provider.commit') : null)
                : ($sheet->batch->templateVersion->engine_commit !== null
                    ? (string) $sheet->batch->templateVersion->engine_commit
                    : null);

            $run = $sheet->processingRuns()->create([
                'actor_id' => $actor->id,
                'run_no' => ((int) $sheet->processingRuns->max('run_no')) + 1,
                'run_uuid' => Str::uuid()->toString(),
                'status' => OmrProcessingRun::STATUS_PENDING,
                'page_no' => $pageNo,
                'provider_name' => $providerName,
                'page_revision' => $page instanceof OmrCaptureSheetPage
                    ? max(1, (int) $page->current_revision)
                    : 1,
                'provider_version' => $providerVersion,
                'provider_commit' => $providerCommit,
                'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
                'expected_sheet_version' => (int) $sheet->version,
                'input_hash' => hash('sha256', $bytes),
                'template_hash' => (string) $sheet->batch->templateVersion->native_bundle_hash,
                'booklet_hash' => (string) $sheet->batch->bookletVersion->answer_key_hash,
                'native_bundle_hash' => (string) $sheet->batch->templateVersion->native_bundle_hash,
                'manifest_hash' => (string) $sheet->batch->templateVersion->review_manifest_hash,
                'config_hash' => (string) $sheet->batch->templateVersion->config_hash,
                'patch_identity' => (string) $sheet->batch->templateVersion->patch_identity,
                'attempts' => 0,
            ]);

            $sheet->forceFill(['status' => OmrCaptureSheet::STATUS_QUEUED])->save();
            if ($page instanceof OmrCaptureSheet) {
                $sheet->forceFill(['current_processing_run_id' => $run->id])->save();
            } else {
                $page->forceFill(['status' => OmrCaptureSheetPage::STATUS_QUEUED, 'current_processing_run_id' => $run->id])->save();
            }

            $this->recordAuditEvent->handle('grading.omr_sheet_retried', [
                'sheet_id' => $sheet->id,
                'batch_id' => $sheet->batch_id,
                'processing_run_id' => $run->id,
                'run_no' => $run->run_no,
            ], (string) $actor->id);

            return $run;
        });

        AnalyzeOmrCaptureSheetJob::dispatch($run->id)->afterCommit();

        return $run;
    }

    private function assertExpectedRevision(OmrCaptureBatch $batch, ?int $expectedRevision): void
    {
        if ($expectedRevision === null) {
            return;
        }

        $revision = app(OmrRunStatusSnapshot::class)
            ->forBatch($batch->load(['sheets.pages', 'sheets.issues', 'sheets.processingRuns']))['revision'];

        if ($revision !== $expectedRevision) {
            throw new RuntimeException('The OMR run changed after validation. Refresh the current status before continuing.');
        }
    }
}
