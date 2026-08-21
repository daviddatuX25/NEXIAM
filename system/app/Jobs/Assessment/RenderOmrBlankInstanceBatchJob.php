<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrBlankPrintOrchestrator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

final class RenderOmrBlankInstanceBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly int $printJobId, public readonly int $actorId)
    {
    }

    public function handle(
        OmrBlankInstanceService $instances,
        OmrBlankPrintOrchestrator $orchestrator,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $job = PrintJob::query()->findOrFail($this->printJobId);
        $versionId = (int) ($job->pinned_input_json['template_version_id'] ?? 0);
        $quantity = (int) ($job->pinned_input_json['quantity'] ?? 0);
        $chunkSize = (int) ($job->pinned_input_json['chunk_size'] ?? config('omr.canonical.limits.blank_print_chunk', 100));
        $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($versionId);
        $actor = User::query()->findOrFail($this->actorId);
        $staticAsset = $version->canonicalAssets()->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)->orderBy('id')->first();
        if ($staticAsset === null) {
            throw new RuntimeException('canonical_print_pdf_missing');
        }
        $staticPdf = Storage::disk($staticAsset->disk)->get($staticAsset->path);
        if (! is_string($staticPdf) || hash('sha256', $staticPdf) !== $staticAsset->sha256) {
            throw new RuntimeException('asset_integrity_failure');
        }

        $pageCount = $version->pageCount();
        $manifest = [];
        $job->forceFill(['status' => PrintJob::STATUS_RENDERING])->save();
        $zipPath = tempnam(sys_get_temp_dir(), 'nexiam-blank-print-');
        if ($zipPath === false) {
            throw new RuntimeException('blank_print_zip_unavailable');
        }
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('blank_print_zip_unavailable');
        }
        $zipClosed = false;

        try {
            for ($index = 0; $index < $quantity; $index++) {
                $chunkNo = intdiv($index, max(1, $chunkSize)) + 1;
                $storedPaths = [];
                try {
                    $instanceCode = $instances->newInstanceCode();
                    $displayCode = $instances->displayCodeFor($instanceCode);
                    $overlay = $version->authoring_manifest['overlay'] ?? null;
                    $artifacts = $orchestrator->renderSet($staticPdf, $instanceCode, $displayCode, $pageCount, null, $overlay);
                    $pageManifest = [];
                    foreach ($artifacts as $artifact) {
                        $path = sprintf('omr-canonical/print-jobs/%d/chunk-%04d/%s-page-%03d.pdf', $job->id, $chunkNo, $instanceCode, $artifact['page_no']);
                        Storage::disk('local')->put($path, $artifact['bytes']);
                        $storedPaths[] = $path;
                        $archiveName = sprintf('chunk-%04d/%s/page-%03d.pdf', $chunkNo, $instanceCode, $artifact['page_no']);
                        $zip->addFromString($archiveName, $artifact['bytes']);
                        $pageManifest[] = [
                            'page_no' => $artifact['page_no'],
                            'path' => $path,
                            'archive_name' => $archiveName,
                            'sha256' => $artifact['sha256'],
                            'qr_payload_hash' => $artifact['payload_hash'],
                        ];
                    }
                    $instance = $instances->createPrintedSet(
                        $version,
                        $actor,
                        $pageCount,
                        array_column($artifacts, 'sha256'),
                        $job->id,
                        null,
                        $instanceCode,
                        $displayCode,
                    );
                    $manifest[] = [
                        'status' => 'printed',
                        'chunk_no' => $chunkNo,
                        'instance_id' => $instance->id,
                        'instance_code' => $instance->instance_code,
                        'display_code' => $instance->display_code,
                        'pages' => $pageManifest,
                    ];
                } catch (Throwable $exception) {
                    foreach ($storedPaths as $path) {
                        Storage::disk('local')->delete($path);
                    }
                    $manifest[] = ['status' => 'failed', 'chunk_no' => $chunkNo, 'error' => $exception->getMessage(), 'index' => $index];
                }
                $job->forceFill(['progress_done' => $index + 1, 'per_item_manifest' => $manifest])->save();
            }

            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $zip->addFromString('manifest.json', $manifestJson);
            $zip->close();
            $zipClosed = true;
            $zipBytes = (string) file_get_contents($zipPath);
            $downloadPath = 'omr-canonical/print-jobs/'.$job->id.'/blank-instances.zip';
            Storage::disk('local')->put($downloadPath, $zipBytes);
            $failed = count(array_filter($manifest, static fn (array $item): bool => $item['status'] === 'failed'));
            $job->forceFill([
                'status' => PrintJob::STATUS_READY,
                'file_path' => $downloadPath,
                'file_checksum' => hash('sha256', $zipBytes),
                'safe_error' => $failed > 0 ? 'partial_failure' : null,
                'completed_at' => now(),
                'per_item_manifest' => $manifest,
            ])->save();
            $recordAuditEvent->handle('omr.canonical.blank_print_completed', [
                'print_job_id' => $job->id,
                'template_version_id' => $version->id,
                'printed_count' => $quantity - $failed,
                'failed_count' => $failed,
                'chunk_size' => $chunkSize,
                'download_sha256' => $job->file_checksum,
            ], (string) $this->actorId);
        } finally {
            if (! $zipClosed) {
                $zip->close();
            }
            @unlink($zipPath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        PrintJob::query()->whereKey($this->printJobId)->update(['status' => PrintJob::STATUS_FAILED, 'safe_error' => 'blank_print_failed']);
        app(RecordAuditEvent::class)->handle('omr.canonical.blank_print_failed', [
            'print_job_id' => $this->printJobId,
            'error' => $exception?->getMessage() ?? 'unknown',
        ], (string) $this->actorId);
    }
}
