<?php

namespace App\ResultSheet\Pipeline;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Printing\PrintJob;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Rendering\ResultSheetRenderer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
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

/**
 * Ephemeral DOCX/ODT preview job. Its output is the same PDF renderer used by
 * real print items; preview rows are tagged and pruned after 24 hours.
 */
class PreviewResultSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 80;

    public function __construct(public readonly int $printJobId) {}

    public function handle(
        ResultSheetRenderer $renderer,
        ResultSheetPlaceholderService $placeholders,
        SensitiveAssetStore $assetStore,
        RecordAuditEvent $audit,
    ): void {
        $claimToken = (string) Str::uuid();
        $claimed = DB::transaction(function () use ($claimToken, &$job): bool {
            $locked = PrintJob::query()->lockForUpdate()->find($this->printJobId);
            if ($locked === null || ! $locked->is_preview) {
                return false;
            }

            if ($locked->status === PrintJob::STATUS_READY) {
                $job = $locked;

                return false;
            }

            $now = now();
            $canClaim = $locked->status === PrintJob::STATUS_QUEUED
                || ($locked->status === PrintJob::STATUS_RENDERING && $locked->claim_expires_at !== null && $locked->claim_expires_at->isPast());

            if (! $canClaim) {
                return false;
            }

            $locked->update([
                'status' => PrintJob::STATUS_RENDERING,
                'claim_token' => $claimToken,
                'claimed_at' => $now,
                'claim_expires_at' => $now->copy()->addMinutes(5),
                'attempt_count' => ($locked->attempt_count ?? 0) + 1,
            ]);

            $job = $locked;

            return true;
        });

        if ($job === null || ! $job->is_preview) {
            return;
        }

        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
        $templateSnapshot = is_array($pinned['template_snapshot'] ?? null)
            ? $pinned['template_snapshot']
            : null;
        $templateId = is_numeric($pinned['template_id'] ?? null)
            && (int) $pinned['template_id'] > 0
            ? (int) $pinned['template_id']
            : null;
        if ($templateId === null && is_numeric($templateSnapshot['id'] ?? null) && (int) $templateSnapshot['id'] > 0) {
            $templateId = (int) $templateSnapshot['id'];
        }

        $templateLookup = is_array($pinned['template_lookup'] ?? null)
            ? $pinned['template_lookup']
            : null;
        $sourcePath = is_string($pinned['source_path'] ?? null)
            ? $pinned['source_path']
            : null;
        if ($sourcePath === null && is_string($templateSnapshot['document_path'] ?? null)) {
            $sourcePath = $templateSnapshot['document_path'];
        }
        $cleanupPaths = $this->cleanupPaths($pinned, $sourcePath);

        if (! $claimed) {
            return;
        }
        $sourceChecksum = null;
        $expectedSourceChecksum = is_string($pinned['source_checksum'] ?? null)
            ? $pinned['source_checksum']
            : (is_string($templateSnapshot['document_checksum'] ?? null)
                ? $templateSnapshot['document_checksum']
                : null);

        try {
            $template = null;
            if ($templateSnapshot !== null) {
                $template = new ResultSheetTemplate($templateSnapshot);
                if ($templateId !== null) {
                    $template->setAttribute('id', $templateId);
                }
            } elseif ($templateId !== null) {
                $template = ResultSheetTemplate::query()->find($templateId);
            }

            if ($template === null) {
                throw new RuntimeException('template_missing');
            }
            if ($sourcePath === null || ! Storage::disk('local')->exists($sourcePath)) {
                throw new RuntimeException('template_document_missing');
            }

            if ($expectedSourceChecksum !== null && $expectedSourceChecksum !== '') {
                try {
                    $sourceBytes = (string) Storage::disk('local')->get($sourcePath);
                } catch (Throwable $exception) {
                    throw new RuntimeException('template_document_missing', 0, $exception);
                }

                $sourceChecksum = hash('sha256', $sourceBytes);
                if (! hash_equals($expectedSourceChecksum, $sourceChecksum)) {
                    throw new RuntimeException('preview_source_integrity_failed');
                }
            }

            $template->setAttribute('document_path', $sourcePath);
            if ($sourceChecksum !== null) {
                $template->setAttribute('document_checksum', $sourceChecksum);
            }
            if (is_string($pinned['source_extension'] ?? null)) {
                $template->setAttribute('document_extension', $pinned['source_extension']);
            }

            $data = is_array($pinned['applicant_data'] ?? null)
                ? $pinned['applicant_data']
                : $placeholders->sampleData();
            $bytes = $renderer->renderForApplicants($template, [$data]);
            $checksum = hash('sha256', $bytes);
            $path = 'print-previews/'.$job->id.'/'.$claimToken.'-'.$checksum.'.pdf';
            $assetStore->store($bytes, $path);

            $settled = DB::transaction(function () use ($job, $claimToken, $path, $checksum): bool {
                $affected = PrintJob::query()
                    ->where('id', $job->id)
                    ->where('claim_token', $claimToken)
                    ->where('status', PrintJob::STATUS_RENDERING)
                    ->update([
                        'status' => PrintJob::STATUS_READY,
                        'file_path' => $path,
                        'file_checksum' => $checksum,
                        'progress_done' => 1,
                        'progress_total' => 1,
                        'safe_error' => null,
                        'completed_at' => now(),
                        'claim_token' => null,
                        'claim_expires_at' => null,
                    ]);

                return $affected > 0;
            });

            if ($settled) {
                $this->cleanupSources($cleanupPaths);
                $audit->handle('printing.preview_ready', [
                    'print_job_id' => $job->id,
                    'template_id' => $templateId,
                    'template_lookup' => $templateLookup,
                    'source_checksum' => $sourceChecksum ?? $expectedSourceChecksum,
                    'checksum' => $checksum,
                ], (string) ($job->requested_by_user_id ?? 'system'));
            } else {
                $assetStore->delete($path);
            }
        } catch (Throwable $exception) {
            $safeError = str_contains($exception->getMessage(), 'libreoffice_unavailable')
                ? 'libreoffice_unavailable'
                : (str_contains($exception->getMessage(), 'preview_source_integrity_failed')
                    ? 'preview_source_integrity_failed'
                    : 'preview_failed');

            $settled = DB::transaction(function () use ($job, $claimToken, $safeError): bool {
                $affected = PrintJob::query()
                    ->where('id', $job->id)
                    ->where('claim_token', $claimToken)
                    ->where('status', PrintJob::STATUS_RENDERING)
                    ->update([
                        'status' => PrintJob::STATUS_FAILED,
                        'safe_error' => $safeError,
                        'completed_at' => now(),
                        'claim_token' => null,
                        'claim_expires_at' => null,
                    ]);

                return $affected > 0;
            });

            if ($settled) {
                $this->cleanupSources($cleanupPaths);
                $audit->handle('printing.preview_failed', [
                    'print_job_id' => $job->id,
                    'template_id' => $templateId,
                    'template_lookup' => $templateLookup,
                    'source_checksum' => $sourceChecksum ?? $expectedSourceChecksum,
                    'safe_error' => $safeError,
                ], (string) ($job->requested_by_user_id ?? 'system'));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $pinned
     * @return array<int, string>
     */
    private function cleanupPaths(array $pinned, ?string $sourcePath): array
    {
        $paths = [];

        if (is_array($pinned['cleanup_source_paths'] ?? null)) {
            foreach ($pinned['cleanup_source_paths'] as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        if (($pinned['cleanup_source'] ?? false) && $sourcePath !== null) {
            $paths[] = $sourcePath;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function cleanupSources(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
