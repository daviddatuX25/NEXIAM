<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;
use function strlen;

/**
 * Replaces a not-yet-enqueued page photo of an incomplete sheet set. Once a
 * set is enqueued (analysis started), pages are immutable evidence.
 */
final class ReplaceOmrSheetPage
{
    public function __construct(
        private readonly SensitiveAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrCaptureSheet $sheet, int $pageNo, string $imageBytes, string $mime, User $actor): OmrCaptureSheet
    {
        return DB::transaction(function () use ($sheet, $pageNo, $imageBytes, $mime, $actor): OmrCaptureSheet {
            $sheet = OmrCaptureSheet::query()->with('batch', 'pages')->lockForUpdate()->findOrFail($sheet->id);

            if ($sheet->batch?->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Sheets can only be replaced while the batch is open.');
            }

            if ($sheet->status !== OmrCaptureSheet::STATUS_PENDING_PAGES) {
                throw new RuntimeException('Only not-yet-enqueued pages can be replaced.');
            }

            $page = $sheet->pages()->where('page_no', $pageNo)->first();

            if ($page === null || $page->asset_path === null) {
                throw new RuntimeException("Page {$pageNo} has not been captured yet.");
            }

            $newPath = $this->assetStore->store(
                $imageBytes,
                'omr/assets/'.Str::uuid()->toString().'.bin',
            );

            $oldPath = (string) $page->asset_path;
            $page->update([
                'asset_path' => $newPath,
                'asset_hmac' => $this->assetStore->hmac($imageBytes),
                'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
                'asset_key_id' => (string) config('omr.keyring.active_key_id'),
                'asset_format_version' => 'v1',
                'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                'original_mime' => $mime,
                'original_bytes' => strlen($imageBytes),
            ]);

            $this->assetStore->delete($oldPath);

            $this->recordAuditEvent->handle('grading.omr_sheet_page_replaced', [
                'sheet_id' => $sheet->id,
                'batch_id' => $sheet->batch_id,
                'page_no' => $pageNo,
                'new_input_hash' => hash('sha256', $imageBytes),
            ], (string) $actor->id);

            return $sheet;
        });
    }
}
