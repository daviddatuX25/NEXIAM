<?php

namespace App\ResultSheet\Pipeline;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\ResultSheet\Rendering\DocxMerger;
use App\ResultSheet\Rendering\PdfMerger;
use App\Support\SensitiveAssetStore;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tail job executed after item rendering batch settles.
 * Merges ready item PDFs into the final print stack and updates the parent job status.
 */
class AssemblePrintJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $printJobId) {}

    public function handle(
        PdfMerger $merger,
        DocxMerger $docxMerger,
        SensitiveAssetStore $assetStore,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $job = DB::transaction(function (): ?PrintJob {
            $locked = PrintJob::query()->lockForUpdate()->find($this->printJobId);
            if ($locked === null || $locked->status === PrintJob::STATUS_READY) {
                return null;
            }

            return $locked;
        });

        if ($job === null) {
            return;
        }
        $items = PrintJobItem::query()
            ->where('print_job_id', $this->printJobId)
            ->orderBy('item_index')
            ->get();

        $readyItems = $items->filter(fn (PrintJobItem $item): bool => $item->status === PrintJobItem::STATUS_READY && $item->file_path !== null);
        $settledCount = $items->filter(fn (PrintJobItem $item): bool => in_array($item->status, [PrintJobItem::STATUS_READY, PrintJobItem::STATUS_FAILED], true))->count();

        if ($settledCount < $items->count()) {
            return;
        }

        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
        $copies = max(1, min(10, (int) ($pinned['copies'] ?? 1)));
        // Synchronize manifest on the parent job
        $manifest = [];
        foreach ($items as $item) {
            $key = $item->result_document_id ?? $item->result_release_id ?? $item->admission_attempt_id;
            $manifest[$key] = [
                'status' => $item->status,
                'path' => $item->file_path,
                'checksum' => $item->file_checksum,
                'docx_path' => $item->docx_path,
                'docx_checksum' => $item->docx_checksum,
                'safe_error' => $item->safe_error,
            ];
        }

        if ($readyItems->isEmpty()) {
            $safeError = $items->first(fn ($i) => $i->safe_error !== null)?->safe_error ?? 'all_items_failed';

            DB::transaction(function () use ($job, $safeError, $manifest, $settledCount, $items): void {
                $job->update([
                    'status' => PrintJob::STATUS_FAILED,
                    'safe_error' => $safeError,
                    'per_item_manifest' => $manifest,
                    'progress_done' => $settledCount,
                    'progress_total' => $items->count(),
                    'completed_at' => now(),
                ]);
            });

            $recordAuditEvent->handle('printing.job_failed', [
                'print_job_id' => $job->id,
                'artifact_type' => $job->artifact_type,
                'scope' => $job->scope_type.':'.$job->scope_id,
                'safe_error' => $safeError,
            ], (string) ($job->requested_by_user_id ?? 'system'));

            return;
        }

        $storedPaths = [];
        try {
            $sheets = [];
            foreach ($readyItems as $item) {
                $bytes = $assetStore->read((string) $item->file_path);
                for ($i = 0; $i < $copies; $i++) {
                    $sheets[] = $bytes;
                }
            }

            $mergedBytes = $merger->merge($sheets);
            $checksum = hash('sha256', $mergedBytes);
            $path = 'print-jobs/'.$job->id.'/'.$checksum.'.pdf';
            $assetStore->store($mergedBytes, $path);
            $storedPaths[] = $path;

            $outputManifest = [
                'pdf' => ['path' => $path, 'checksum' => $checksum],
            ];
            $docxItems = $readyItems->filter(fn (PrintJobItem $item): bool => $item->docx_path !== null);

            if ($docxItems->isNotEmpty()) {
                $documents = $docxItems->map(fn (PrintJobItem $item): string => $assetStore->read((string) $item->docx_path))->values()->all();
                $mergedDocx = $docxMerger->merge($documents);
                $docxChecksum = hash('sha256', $mergedDocx);
                $docxPath = 'print-jobs/'.$job->id.'/'.$docxChecksum.'.docx';
                $assetStore->store($mergedDocx, $docxPath);
                $storedPaths[] = $docxPath;
                $zipBytes = $this->zipDocuments($documents);
                $zipChecksum = hash('sha256', $zipBytes);
                $zipPath = 'print-jobs/'.$job->id.'/'.$zipChecksum.'.zip';
                $assetStore->store($zipBytes, $zipPath);
                $storedPaths[] = $zipPath;
                $outputManifest['docx'] = ['path' => $docxPath, 'checksum' => $docxChecksum];
                $outputManifest['zip'] = ['path' => $zipPath, 'checksum' => $zipChecksum];
            }

            DB::transaction(function () use ($job, $path, $checksum, $outputManifest, $manifest, $settledCount, $items): void {
                $job->update([
                    'status' => PrintJob::STATUS_READY,
                    'file_path' => $path,
                    'file_checksum' => $checksum,
                    'output_manifest' => $outputManifest,
                    'safe_error' => null,
                    'per_item_manifest' => $manifest,
                    'progress_done' => $settledCount,
                    'progress_total' => $items->count(),
                    'completed_at' => now(),
                ]);
            });

            $recordAuditEvent->handle('printing.job_ready', [
                'print_job_id' => $job->id,
                'artifact_type' => $job->artifact_type,
                'scope' => $job->scope_type.':'.$job->scope_id,
                'checksum' => $checksum,
            ], (string) ($job->requested_by_user_id ?? 'system'));
        } catch (Throwable $exception) {
            foreach ($storedPaths as $cleanupPath) {
                try {
                    $assetStore->delete($cleanupPath);
                } catch (Throwable) {
                }
            }
            DB::transaction(function () use ($job, $manifest, $settledCount): void {
                $job->update([
                    'status' => PrintJob::STATUS_FAILED,
                    'safe_error' => 'render_failed',
                    'per_item_manifest' => $manifest,
                    'progress_done' => $settledCount,
                    'completed_at' => now(),
                ]);
            });

            $recordAuditEvent->handle('printing.job_failed', [
                'print_job_id' => $job->id,
                'artifact_type' => $job->artifact_type,
                'scope' => $job->scope_type.':'.$job->scope_id,
                'safe_error' => 'render_failed',
            ], (string) ($job->requested_by_user_id ?? 'system'));
        }
    }

    /**
     * @param  list<string>  $documents
     */
    private function zipDocuments(array $documents): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'result-docx-');
        if ($tempFile === false) {
            throw new \RuntimeException('Unable to allocate DOCX ZIP path.');
        }
        $zipPath = $tempFile.'.zip';

        try {
            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create DOCX ZIP.');
            }

            foreach ($documents as $index => $docxBytes) {
                $zip->addFromString(sprintf('result-sheet-%03d.docx', $index + 1), $docxBytes);
            }

            $zip->close();

            return (string) file_get_contents($zipPath);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }
    }
}
