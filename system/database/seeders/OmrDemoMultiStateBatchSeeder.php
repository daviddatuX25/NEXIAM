<?php

namespace Database\Seeders;

use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use Illuminate\Support\Str;

/**
 * Opt-in multi-state OMR batch fixture for capture-surface testing.
 *
 * Founder decision 2026-08-17: the default dataset is the focused
 * near-production state (one needs-review examinee sheet — see
 * {@see OmrDemoBatchSeeder}). This seeder additionally creates the
 * pending-pages (incomplete resumable) and terminal-failed (retryable) sheets
 * so the capture/review/confirm/retry surfaces can be exercised without
 * scanning. Idempotent: it only adds the states that are missing.
 */
class OmrDemoMultiStateBatchSeeder extends OmrDemoBatchSeeder
{
    public function run(): void
    {
        parent::run();

        $ctx = $this->context();
        if ($ctx === null) {
            return;
        }

        $batch = $this->batchFor($ctx);
        if ($batch === null) {
            return;
        }

        if (! $batch->sheets()->where('status', OmrCaptureSheet::STATUS_PENDING_PAGES)->exists()) {
            $this->createPendingSheet($ctx, $batch);
        }

        if (! $batch->sheets()->where('status', OmrCaptureSheet::STATUS_FAILED_TERMINAL)->exists()) {
            $this->createFailedSheet($ctx, $batch);
        }

        if (! $batch->sheets()->where('client_capture_id', 'like', 'omr-demo-dup-%')->exists()
            && $batch->sheets()->where('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW)->exists()) {
            $this->createDuplicateSheet($ctx, $batch);
        }

        $this->command?->info(sprintf('OMR demo batch #%d multi-state: needs-review, incomplete, failed, and duplicate sheets present.', $batch->id));
    }

    protected function createDuplicateSheet(array $ctx, OmrCaptureBatch $batch): void
    {
        $needsReview = $batch->sheets()->where('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW)->first();
        if ($needsReview === null) {
            return;
        }

        $dupCaptureId = 'omr-demo-dup-'.$batch->id.'-'.Str::uuid();
        $dupSheet = OmrCaptureSheet::query()->create([
            ...$needsReview->replicate(['client_capture_id', 'id'])->getAttributes(),
            'batch_id' => $batch->id,
            'client_capture_id' => $dupCaptureId,
            'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
            'admission_attempt_id' => $needsReview->admission_attempt_id,
            'detected_applicant_number' => $needsReview->detected_applicant_number,
            'version' => 1,
            'captured_at' => now()->subMinutes(10),
        ]);

        foreach ($needsReview->pages as $page) {
            $dupSheet->pages()->create(
                $page->replicate(['sheet_id', 'id'])->getAttributes(),
            );
        }
    }
}
