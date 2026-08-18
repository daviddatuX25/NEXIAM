<?php

namespace App\Jobs\Printing;

use App\Assessment\Omr\NativeBundle;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Printing\PrintJob;
use App\Printing\PrintRenderer;
use App\Support\SensitiveAssetStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function hash;
use function strlen;

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

        $job->update(['status' => PrintJob::STATUS_RENDERING]);

        try {
            [$bytes, $manifest] = $this->renderArtifact($job, $renderer);
            $checksum = hash('sha256', $bytes);

            $path = 'print-jobs/'.$job->id.'/'.$checksum.'.pdf';
            $sensitive = $job->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET;

            if ($sensitive) {
                $assetStore->store($bytes, $path);
            } else {
                Storage::disk('local')->put($path, $bytes);
            }

            $updates = [
                'status' => PrintJob::STATUS_READY,
                'file_path' => $path,
                'file_checksum' => $checksum,
                'safe_error' => null,
                'progress_done' => $job->progress_total,
                'completed_at' => now(),
            ];

            if ($manifest !== null) {
                $updates['per_item_manifest'] = $manifest;
            }

            $job->update($updates);

            if ($job->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET) {
                $this->markPrinted($job);
            }

            $recordAuditEvent->handle('printing.job_ready', [
                'print_job_id' => $job->id,
                'artifact_type' => $job->artifact_type,
                'scope' => $job->scope_type.':'.$job->scope_id,
                'checksum' => $checksum,
            ], (string) ($job->requested_by_user_id ?? 'system'));
        } catch (Throwable $exception) {
            $safeError = $this->safeError($exception);
            $updates = [
                'status' => PrintJob::STATUS_FAILED,
                'safe_error' => $safeError,
                'completed_at' => now(),
            ];

            if ($job->per_item_manifest !== null) {
                $updates['per_item_manifest'] = $job->per_item_manifest;
            } elseif ($exception instanceof \App\ResultSheet\Rendering\AllItemsFailedException) {
                $updates['per_item_manifest'] = $exception->manifest;
            }

            $job->update($updates);

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
     * Dispatch to the artifact renderer for this job type. Blank sheets use
     * the prepared-HTML path through the PrintRenderer interface; result
     * sheets produce PDF bytes directly through ResultSheetArtifactRenderer
     * (with a per-item manifest). Returns bytes + optional manifest.
     *
     * @return array{0: string, 1: array<int, array<string, mixed>>|null}
     */
    private function renderArtifact(PrintJob $job, PrintRenderer $renderer): array
    {
        if ($job->artifact_type === PrintJob::ARTIFACT_RESULT_SHEET) {
            $result = app(\App\ResultSheet\Rendering\ResultSheetArtifactRenderer::class)->render($job);

            return [$result['bytes'], $result['manifest']];
        }

        $payload = $this->renderPayload($job);

        return [$renderer->render($payload['html'], $payload['options']), null];
    }

    /**
     * Set result_printed_at idempotently (only where still null) on the pinned
     * release records once a result-sheet artifact is ready.
     */
    private function markPrinted(PrintJob $job): void
    {
        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
        $releaseIds = array_map('intval', $pinned['release_ids'] ?? []);

        if ($releaseIds === []) {
            return;
        }

        \App\Models\Release\ResultRelease::query()
            ->whereIn('id', $releaseIds)
            ->whereNull('result_printed_at')
            ->update(['result_printed_at' => now()]);
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
                ? \App\Models\Assessment\OmrAnswerSheetTemplateVersion::find($versionId)
                : null;

            $templateJson = is_array($templateVersion?->native_bundle_json) && isset($templateVersion->native_bundle_json['template'])
                ? $templateVersion->native_bundle_json['template']
                : null;

            if ($templateJson === null) {
                $bundle = NativeBundle::load(
                    (string) ($pinned['bundle_code'] ?? 'SYN-OMR-ONE-PAGE'),
                    (int) ($pinned['bundle_version'] ?? 1),
                );
                $templateJson = $bundle->template;
            }

            $renderer = app(\App\Printing\BundleRenderer::class);
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

        $overlay = \App\Models\Assessment\OmrAnswerSheetTemplateVersion::query()
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
