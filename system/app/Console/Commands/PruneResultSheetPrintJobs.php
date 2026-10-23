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

        // Prune regular expired jobs (older than 30 days) and preview jobs (older than 24 hours)
        $previewCutoff = now()->subDay();

        $jobs = PrintJob::query()
            ->whereIn('artifact_type', [PrintJob::ARTIFACT_RESULT_SHEET, PrintJob::ARTIFACT_BLANK_SHEET])
            ->where(function ($query) use ($cutoff, $previewCutoff) {
                $query->where(function ($q) use ($cutoff) {
                    $q->where(fn ($sub) => $sub->where('is_preview', false)->orWhereNull('is_preview'))
                        ->where('status', PrintJob::STATUS_READY)
                        ->whereNotNull('file_path')
                        ->where('completed_at', '<', $cutoff);
                })->orWhere(function ($q) use ($previewCutoff) {
                    $q->where('is_preview', true)
                        ->whereIn('status', [PrintJob::STATUS_READY, PrintJob::STATUS_FAILED])
                        ->where(function ($dateQuery) use ($previewCutoff) {
                            $dateQuery->where(function ($sub) use ($previewCutoff) {
                                $sub->whereNotNull('completed_at')
                                    ->where('completed_at', '<', $previewCutoff);
                            })->orWhere(function ($sub) use ($previewCutoff) {
                                $sub->whereNull('completed_at')
                                    ->where('created_at', '<', $previewCutoff);
                            });
                        });
                });
            })
            ->with('items')
            ->get();

        $count = 0;

        foreach ($jobs as $job) {
            $path = (string) $job->file_path;
            if (! $dryRun && $path !== '') {
                $this->deleteArtifact($job, $path, $assetStore);
            }

            // Clean item files
            foreach ($job->items as $item) {
                if (! $dryRun && $item->file_path !== null) {
                    $this->deleteArtifact($job, $item->file_path, $assetStore);
                }
                if (! $dryRun && $item->docx_path !== null) {
                    $this->deleteArtifact($job, $item->docx_path, $assetStore);
                }
                if (! $dryRun) {
                    $item->update([
                        'file_path' => null,
                        'file_checksum' => null,
                        'docx_path' => null,
                        'docx_checksum' => null,
                    ]);
                }
            }
            foreach ((array) $job->output_manifest as $output) {
                if (! $dryRun && is_array($output) && ! empty($output['path'])) {
                    $this->deleteArtifact($job, (string) $output['path'], $assetStore);
                }
            }
            if (! $dryRun && $job->artifact_type === PrintJob::ARTIFACT_BLANK_SHEET) {
                $manifest = is_array($job->output_manifest) ? $job->output_manifest : [];
                foreach ((array) ($manifest['copies'] ?? []) as $copy) {
                    foreach ((array) (is_array($copy) ? ($copy['pages'] ?? []) : []) as $page) {
                        $path = is_array($page) ? ($page['path'] ?? null) : null;
                        $jobPathPrefix = 'omr-canonical/print-jobs/'.$job->id.'/qr-disabled/';
                        if (is_string($path) && str_starts_with($path, $jobPathPrefix)) {
                            $this->deleteArtifact($job, $path, $assetStore);
                        }
                    }
                }
            }
            // Clean any orphaned temporary preview sources
            if ($job->is_preview && is_array($job->pinned_input_json)) {
                $pinned = $job->pinned_input_json;
                $cleanupPaths = (array) ($pinned['cleanup_source_paths'] ?? []);
                if (! empty($pinned['source_path']) && str_starts_with((string) $pinned['source_path'], 'result-sheet-templates/tmp/')) {
                    $cleanupPaths[] = (string) $pinned['source_path'];
                }
                foreach (array_unique($cleanupPaths) as $tmpPath) {
                    if (! $dryRun && Storage::disk('local')->exists($tmpPath)) {
                        Storage::disk('local')->delete($tmpPath);
                    }
                }
            }

            $count++;

            if (! $dryRun) {
                $job->update(['file_path' => null, 'file_checksum' => null, 'output_manifest' => null]);

                $recordAuditEvent->handle('printing.job_pruned', [
                    'print_job_id' => $job->id,
                    'artifact_type' => $job->artifact_type,
                    'is_preview' => $job->is_preview,
                    'path' => $path,
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function deleteArtifact(PrintJob $job, string $path, SensitiveAssetStore $assetStore): bool
    {
        try {
            if ($job->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET) {
                $assetStore->delete($path);

                return true;
            }

            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            return true;
        } catch (RuntimeException) {
            return true;
        }
    }
}
