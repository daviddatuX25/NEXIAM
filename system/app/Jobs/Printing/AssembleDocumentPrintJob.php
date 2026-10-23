<?php

namespace App\Jobs\Printing;

use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\ResultSheet\Rendering\PdfMerger;
use App\Support\SensitiveAssetStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class AssembleDocumentPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public function __construct(public readonly int $printJobId) {}

    public function handle(PdfMerger $merger, SensitiveAssetStore $assetStore): void
    {
        $job = PrintJob::query()->findOrFail($this->printJobId);
        $items = $job->items()->orderBy('item_index')->get();

        if ($items->contains(static fn (PrintJobItem $item): bool => $item->status === PrintJobItem::STATUS_FAILED)) {
            $job->update(['status' => PrintJob::STATUS_FAILED, 'stage' => null]);

            return;
        }
        if ($items->isEmpty()) {
            $job->update(['status' => PrintJob::STATUS_FAILED, 'stage' => null]);

            return;
        }
        if ($items->count() !== (int) $job->progress_total) {
            $job->update(['status' => PrintJob::STATUS_FAILED, 'stage' => null]);

            return;
        }
        if ($items->contains(static fn (PrintJobItem $item): bool => $item->status !== PrintJobItem::STATUS_READY)) {
            $this->release(10);

            return;
        }
        $job->update(['stage' => 'assembling']);

        $chunks = [];
        $manifestItems = [];
        foreach ($items as $item) {
            $path = (string) $item->file_path;
            if ($path === '' || ! is_string($item->file_checksum) || $item->file_checksum === '') {
                $job->update(['status' => PrintJob::STATUS_FAILED, 'stage' => null]);

                return;
            }
            $bytes = $assetStore->read($path, (string) $item->file_checksum);
            $sha256 = hash('sha256', $bytes);
            $chunks[] = $bytes;
            $manifestItems[] = [
                'item_id' => (int) $item->getKey(),
                'item_key' => (string) $item->item_key,
                'work_unit_type' => (string) $item->work_unit_type,
                'checksum' => (string) $item->file_checksum,
                'sha256' => $sha256,
            ];
        }

        $storedPath = null;
        try {
            $pdf = $merger->merge($chunks);
            if ($pdf === '') {
                throw new RuntimeException('document_assembly_empty');
            }
            $sha256 = hash('sha256', $pdf);
            $path = 'print-artifacts/'.$this->printJobId.'_'.$sha256.'.pdf';
            $storedPath = $assetStore->store($pdf, $path);
            $checksum = $assetStore->hmac($pdf);
            $manifest = [
                'artifact_type' => 'office_document',
                'item_count' => count($manifestItems),
                'items' => $manifestItems,
                'checksum' => $checksum,
                'sha256' => $sha256,
                'file_path' => $storedPath,
            ];
            DB::transaction(function () use ($job, $checksum, $storedPath, $manifest): void {
                $locked = PrintJob::query()->lockForUpdate()->findOrFail($job->getKey());
                if ($locked->items()->where('status', '!=', PrintJobItem::STATUS_READY)->exists()) {
                    throw new RuntimeException('document_items_not_settled');
                }
                $locked->update([
                    'status' => PrintJob::STATUS_READY,
                    'stage' => null,
                    'file_path' => $storedPath,
                    'file_checksum' => $checksum,
                    'output_manifest' => $manifest,
                    'completed_at' => now(),
                    'safe_error' => null,
                ]);
            });
            foreach ($items as $item) {
                $assetStore->delete((string) $item->file_path);
            }
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $assetStore->delete($storedPath);
            }
            $job->update(['status' => PrintJob::STATUS_FAILED, 'stage' => null]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        PrintJob::query()->whereKey($this->printJobId)->update([
            'status' => PrintJob::STATUS_FAILED,
            'stage' => null,
        ]);
    }
}
