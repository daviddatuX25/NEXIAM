<?php

namespace App\Jobs\Printing;

use App\Assessment\Omr\NativeBundle;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Printing\PrintJob;
use App\Printing\BundleRenderer;
use App\Printing\PrintRenderer;
use App\ResultSheet\Pipeline\AssemblePrintJob;
use App\ResultSheet\Pipeline\FillAndConvertJob;
use App\ResultSheet\Rendering\DocxMerger;
use App\ResultSheet\Rendering\PdfMerger;
use App\ResultSheet\Rendering\PreparedResultDocumentRenderer;
use App\Support\SensitiveAssetStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

use function hash;

/**
 * Renders one print job through the PrintRenderer interface, stores the
 * artifact, and marks the job ready/failed with safe codes. Artifacts with
 * applicant data use the sensitive disk; everything else the private disk.
 */
final class RenderPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(public readonly int $printJobId)
    {
        $this->tries = 3;
        $this->timeout = 120;
    }

    public function failed(?Throwable $exception): void
    {
        $job = PrintJob::query()->find($this->printJobId);
        if ($job !== null && $job->status !== PrintJob::STATUS_READY) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'safe_error' => $job->safe_error ?: 'render_failed',
                'completed_at' => now(),
            ]);
        }
    }

    public function handle(
        PrintRenderer $renderer,
        SensitiveAssetStore $assetStore,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $job = PrintJob::query()->lockForUpdate()->find($this->printJobId);
        if ($job === null || ! in_array($job->status, [PrintJob::STATUS_QUEUED, PrintJob::STATUS_FAILED], true)) {
            return;
        }

        if ($job->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET) {
            $job->update(['status' => PrintJob::STATUS_RENDERING]);
            $container = app();

            foreach ($job->items()->orderBy('item_index')->get() as $item) {
                (new FillAndConvertJob($item->id))->handle(
                    $container->make(PreparedResultDocumentRenderer::class),
                    $container->make(SensitiveAssetStore::class),
                );
            }

            (new AssemblePrintJob($job->id))->handle(
                $container->make(PdfMerger::class),
                $container->make(DocxMerger::class),
                $container->make(SensitiveAssetStore::class),
                $container->make(RecordAuditEvent::class),
            );

            return;
        }

        $job->update(['status' => PrintJob::STATUS_RENDERING]);

        try {
            $payload = $this->renderPayload($job);
            $bytes = $renderer->render($payload['html'], $payload['options']);
            $checksum = hash('sha256', $bytes);
            $path = 'print-jobs/'.$job->id.'/'.$checksum.'.pdf';
            Storage::disk('local')->put($path, $bytes);

            $job->update([
                'status' => PrintJob::STATUS_READY,
                'file_path' => $path,
                'file_checksum' => $checksum,
                'safe_error' => null,
                'progress_done' => $job->progress_total,
                'completed_at' => now(),
            ]);

            $recordAuditEvent->handle('printing.job_ready', [
                'print_job_id' => $job->id,
                'artifact_type' => $job->artifact_type,
                'scope' => $job->scope_type.':'.$job->scope_id,
                'checksum' => $checksum,
            ], (string) ($job->requested_by_user_id ?? 'system'));
        } catch (Throwable $exception) {
            $safeError = $this->safeError($exception);
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'safe_error' => $safeError,
                'completed_at' => now(),
            ]);

            $recordAuditEvent->handle('printing.job_failed', [
                'print_job_id' => $job->id,
                'artifact_type' => $job->artifact_type,
                'scope' => $job->scope_type.':'.$job->scope_id,
                'safe_error' => $safeError,
            ], (string) ($job->requested_by_user_id ?? 'system'));

            throw $exception;
        }
    }

    /**
     * @return array{html: string, options: array<string, mixed>}
     */
    private function renderPayload(PrintJob $job): array
    {
        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];

        if ($job->artifact_type === PrintJob::ARTIFACT_BLANK_SHEET) {
            $versionId = (int) ($pinned['template_version_id'] ?? 0);
            $templateVersion = $versionId > 0
                ? OmrAnswerSheetTemplateVersion::query()->with('template')->find($versionId)
                : null;

            if ($templateVersion === null
                || (int) $templateVersion->template_id !== (int) ($pinned['template_id'] ?? 0)
                || (int) $templateVersion->template?->institution_id !== (int) ($pinned['institution_id'] ?? 0)
                || (int) $templateVersion->version !== (int) ($pinned['bundle_version'] ?? 0)
                || (string) $templateVersion->template?->code !== (string) ($pinned['bundle_code'] ?? '')
                || ! is_string($templateVersion->native_bundle_hash)
                || ! is_string($pinned['bundle_hash'] ?? null)
                || ! hash_equals($templateVersion->native_bundle_hash, $pinned['bundle_hash'])) {
                throw new RuntimeException('OMR native bundle version pin is missing or does not match its owner.');
            }

            $bundle = NativeBundle::loadForVersion($templateVersion);
            if (! hash_equals($pinned['bundle_hash'], $bundle->bundleHash)) {
                throw new RuntimeException('OMR native bundle hash differs from the queued version pin.');
            }
            $templateJson = $bundle->template;
            $renderer = app(BundleRenderer::class);
            $paper = $renderer->paper();

            return [
                'html' => $renderer->renderHtml($templateJson, ['overlay' => $this->overlayFor($pinned)]),
                'options' => ['format' => 'custom', 'orientation' => 'portrait', 'width_mm' => $paper['width'], 'height_mm' => $paper['height']],
            ];
        }

        throw new RuntimeException('Unsupported print artifact type.');
    }

    /**
     * @param  array<string, mixed>  $pinned
     * @return array<string, mixed>|null
     */
    private function overlayFor(array $pinned): ?array
    {
        $versionId = (int) ($pinned['template_version_id'] ?? 0);
        if ($versionId <= 0) {
            return null;
        }

        $overlay = OmrAnswerSheetTemplateVersion::query()
            ->whereKey($versionId)
            ->value('print_overlay_json');

        return is_array($overlay) ? $overlay : null;
    }

    private function safeError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'does not match configured') || str_contains($message, 'never scaled')) {
            return 'paper_mismatch';
        }
        if (str_contains($message, 'OMR native bundle')) {
            return 'bundle_invalid';
        }
        if (str_contains($message, 'all_items_failed') || str_contains($message, 'release_stale')) {
            return 'all_items_failed';
        }
        if (str_contains($message, 'template_missing') || str_contains($message, 'libreoffice_unavailable')) {
            return 'libreoffice_unavailable';
        }

        return 'render_failed';
    }
}
