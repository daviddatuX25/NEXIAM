<?php

namespace App\ResultSheet\Pipeline;

use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\ResultSheet\Rendering\PreparedResultDocumentRenderer;
use App\Support\SensitiveAssetStore;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Renders an individual print item asynchronously.
 * Idempotent: safe to re-run on failed items.
 */
class FillAndConvertJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $printJobItemId) {}

    public function handle(
        PreparedResultDocumentRenderer $preparedRenderer,
        SensitiveAssetStore $assetStore,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $claimToken = (string) Str::uuid();
        $claim = DB::transaction(function () use ($claimToken): ?array {
            // 1. Resolve parent ID without locking
            $itemJobId = PrintJobItem::query()->where('id', $this->printJobItemId)->value('print_job_id');
            if ($itemJobId === null) {
                return null;
            }

            // 2. Lock parent row first (global parent-then-item lock order)
            $job = PrintJob::query()->lockForUpdate()->find($itemJobId);
            if ($job === null) {
                return null;
            }

            // 3. Lock item row second
            $item = PrintJobItem::query()->lockForUpdate()->find($this->printJobItemId);
            if ($item === null || $item->status === PrintJobItem::STATUS_READY) {
                return null;
            }
            $now = now();
            $isQueued = $item->status === PrintJobItem::STATUS_QUEUED;
            $isExpiredClaim = in_array($item->status, [PrintJobItem::STATUS_FILLING, PrintJobItem::STATUS_CONVERTING], true)
                && $item->claim_expires_at !== null
                && $item->claim_expires_at->isPast();

            if (! $isQueued && ! $isExpiredClaim) {
                return null;
            }

            $item->update([
                'status' => PrintJobItem::STATUS_FILLING,
                'claim_token' => $claimToken,
                'claimed_at' => $now,
                'claim_expires_at' => $now->copy()->addMinutes(5),
                'attempt_count' => ($item->attempt_count ?? 0) + 1,
            ]);

            if ($job->status === PrintJob::STATUS_QUEUED) {
                $job->update(['status' => PrintJob::STATUS_RENDERING]);
            }

            return ['item' => $item, 'job' => $job];
        });

        if ($claim === null) {
            return;
        }

        /** @var PrintJobItem $item */
        $item = $claim['item'];
        /** @var PrintJob $job */
        $job = $claim['job'];
        try {
            $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];

            $attempt = $item->admissionAttempt;

            if ($attempt === null) {
                throw new RuntimeException('attempt_missing');
            }

            $document = ResultDocument::query()->find($item->result_document_id);
            $expectedVersion = (int) ($pinned['document_versions'][$item->result_document_id] ?? 0);
            $expectedChecksum = $pinned['document_checksums'][$item->result_document_id] ?? null;
            if (
                $document === null
                || $document->status !== ResultDocument::STATUS_READY
                || (int) $attempt->current_result_document_id !== (int) $document->id
                || ($expectedVersion > 0 && (int) $document->version !== $expectedVersion)
                || ($expectedChecksum !== null && $document->checksum !== $expectedChecksum)
            ) {
                throw new RuntimeException('document_stale');
            }

            $conversionClaimed = PrintJobItem::query()
                ->whereKey($item->id)
                ->where('claim_token', $claimToken)
                ->where('status', PrintJobItem::STATUS_FILLING)
                ->update(['status' => PrintJobItem::STATUS_CONVERTING]);
            if ($conversionClaimed === 0) {
                return;
            }
            $item->status = PrintJobItem::STATUS_CONVERTING;
            $artifacts = $preparedRenderer->render($document);
            $bytes = $artifacts['pdf'];

            $checksum = hash('sha256', $bytes);
            $path = 'print-jobs/'.$item->print_job_id.'/items/'.$item->id.'-'.$checksum.'.pdf';
            $assetStore->store($bytes, $path);

            $updates = [
                'status' => PrintJobItem::STATUS_READY,
                'file_path' => $path,
                'file_checksum' => $checksum,
                'safe_error' => null,
            ];

            if ($artifacts['docx'] !== null) {
                $docxChecksum = hash('sha256', $artifacts['docx']);
                $docxPath = 'print-jobs/'.$item->print_job_id.'/items/'.$item->id.'-'.$docxChecksum.'.docx';
                $assetStore->store($artifacts['docx'], $docxPath);
                $updates['docx_path'] = $docxPath;
                $updates['docx_checksum'] = $docxChecksum;
            }

            $settled = DB::transaction(function () use ($job, $item, $claimToken, $updates, $document, $path, $bytes, $checksum): bool {
                $lockedJob = PrintJob::query()->lockForUpdate()->find($job->id);
                if ($lockedJob === null) {
                    return false;
                }

                $affected = PrintJobItem::query()
                    ->where('id', $item->id)
                    ->where('claim_token', $claimToken)
                    ->whereIn('status', [PrintJobItem::STATUS_FILLING, PrintJobItem::STATUS_CONVERTING])
                    ->update(array_merge($updates, [
                        'claim_token' => null,
                        'claim_expires_at' => null,
                    ]));

                if ($affected > 0) {
                    ResultDocument::query()
                        ->whereKey($document->id)
                        ->update([
                            'artifact_manifest->pdf' => [
                                'path' => $path,
                                'size' => strlen($bytes),
                                'sha256' => $checksum,
                                'status' => 'ready',
                                'generated_at' => now()->toIso8601String(),
                            ],
                        ]);
                    $lockedJob->increment('progress_done');

                    return true;
                }

                return false;
            });

            if (! $settled) {
                // Worker lost the lease before committing; exit cleanly without mutating content-addressed paths
                return;
            }

            // Rendering produces a file artifact only; physical print evidence
            // is recorded later by a staff-confirmed receipt.
        } catch (Throwable $exception) {
            DB::transaction(function () use ($job, $item, $claimToken, $exception): void {
                $lockedJob = PrintJob::query()->lockForUpdate()->find($job->id);
                if ($lockedJob === null) {
                    return;
                }

                $affected = PrintJobItem::query()
                    ->where('id', $item->id)
                    ->where('claim_token', $claimToken)
                    ->update([
                        'status' => PrintJobItem::STATUS_FAILED,
                        'safe_error' => $this->safeError($exception),
                        'claim_token' => null,
                        'claim_expires_at' => null,
                    ]);

                if ($affected > 0) {
                    $lockedJob->increment('progress_done');
                }
            });
        }
    }

    private function safeError(Throwable $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'libreoffice_unavailable') => 'libreoffice_unavailable',
            str_contains($message, 'template_missing') => 'template_missing',
            str_contains($message, 'template_changed') => 'template_changed',
            str_contains($message, 'document_stale') => 'document_stale',
            str_contains($message, 'attempt_missing') => 'attempt_missing',
            default => 'render_failed',
        };
    }
}
