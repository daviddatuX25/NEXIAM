<?php

namespace App\Console\Commands;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Printing\PrintJob;
use App\Support\SensitiveAssetStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Deletes ready print artifacts past their retention window and records the
 * cleanup. Sensitive (result-sheet) artifacts are removed through the
 * sensitive store; blank sheets from the private disk.
 */
class PruneResultSheetPrintJobs extends Command
{
    protected $signature = 'result-sheet:prune {--dry-run : Report without deleting}';

    protected $description = 'Delete ready print artifacts older than the retention window';

    public function handle(SensitiveAssetStore $assetStore, RecordAuditEvent $recordAuditEvent): int
    {
        $days = (int) config('result-sheet.retention_days', 30);
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $jobs = PrintJob::query()
            ->where('status', PrintJob::STATUS_READY)
            ->whereNotNull('file_path')
            ->where('completed_at', '<', $cutoff)
            ->get();

        $count = 0;

        foreach ($jobs as $job) {
            $path = (string) $job->file_path;
            $deleted = $this->deleteArtifact($job, $path, $assetStore);

            if (! $deleted) {
                $this->warn("Retention: artifact missing for job {$job->id} ({$path})");

                continue;
            }

            $count++;

            if (! $dryRun) {
                $job->update(['file_path' => null, 'file_checksum' => null]);

                $recordAuditEvent->handle('printing.job_pruned', [
                    'print_job_id' => $job->id,
                    'artifact_type' => $job->artifact_type,
                    'path' => $path,
                ]);
            }
        }

        $this->info($dryRun
            ? "Retention dry-run: {$count} ready artifacts expired."
            : "Retention pass: {$count} ready artifacts pruned.");

        return self::SUCCESS;
    }

    private function deleteArtifact(PrintJob $job, string $path, SensitiveAssetStore $assetStore): bool
    {
        try {
            if ($job->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET) {
                $assetStore->delete($path);

                return true;
            }

            if (! Storage::disk('local')->exists($path)) {
                return false;
            }

            Storage::disk('local')->delete($path);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
