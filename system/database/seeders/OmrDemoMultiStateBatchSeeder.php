<?php

namespace Database\Seeders;

use App\Models\Assessment\OmrCaptureSheet;

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

        $this->command?->info(sprintf('OMR demo batch #%d multi-state: needs-review, incomplete, and failed sheets present.', $batch->id));
    }
}
