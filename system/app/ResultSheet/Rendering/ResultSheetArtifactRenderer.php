<?php

namespace App\ResultSheet\Rendering;

use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\Support\SensitiveAssetStore;
use RuntimeException;
use Throwable;

/**
 * Pipeline artifact renderer for `result_sheet` jobs (registry entry behind
 * RenderPrintJob). Renders every pinned release through ResultSheetRenderer,
 * stores per-item PDFs on the sensitive disk, records a per-item manifest
 * (ready/failed with safe errors), and returns the merged stack PDF (copies
 * applied) for the job artifact.
 *
 * @return array{bytes: string, manifest: array<int, array<string, mixed>>}
 */
final class ResultSheetArtifactRenderer
{
    public function __construct(
        private readonly ResultSheetRenderer $sheetRenderer,
        private readonly PreparedResultDocumentRenderer $preparedDocumentRenderer,
        private readonly ResultSheetDataProvider $dataProvider,
        private readonly PdfMerger $merger,
        private readonly SensitiveAssetStore $assetStore,
    ) {}

    /**
     * @return array{bytes: string, manifest: array<int, array<string, mixed>>}
     */
    public function render(PrintJob $job): array
    {
        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
        $documentIds = array_values(array_unique(array_map('intval', $pinned['document_ids'] ?? [])));
        $releaseIds = array_values(array_unique(array_map('intval', $pinned['release_ids'] ?? [])));
        $copies = max(1, min(10, (int) ($pinned['copies'] ?? 1)));

        $template = ResultSheetTemplate::query()->find((int) ($pinned['template_id'] ?? 0));

        if ($template === null && $documentIds === []) {
            throw new RuntimeException('template_missing');
        }

        if ($documentIds === [] && $releaseIds === []) {
            throw new RuntimeException('release_list_empty');
        }

        $documents = $documentIds === []
            ? collect()
            : ResultDocument::query()
                ->whereIn('id', $documentIds)
                ->with('admissionAttempt.identity')
                ->get()
                ->keyBy('id');
        $releases = $documentIds !== []
            ? collect()
            : ResultRelease::query()
                ->whereIn('id', $releaseIds)
                ->with('admissionAttempt.identity')
                ->get()
                ->keyBy('id');
        $pinnedIds = $documentIds !== [] ? $documentIds : $releaseIds;

        $manifest = [];
        $sheets = [];

        foreach ($pinnedIds as $pinnedId) {
            $document = $documentIds !== [] ? $documents->get($pinnedId) : null;
            $release = $documentIds === [] ? $releases->get($pinnedId) : null;
            $attempt = $document?->admissionAttempt ?? $release?->admissionAttempt;
            $item = PrintJobItem::query()
                ->where('print_job_id', $job->id)
                ->when(
                    $documentIds !== [],
                    fn ($query) => $query->where('result_document_id', $pinnedId),
                    fn ($query) => $query->where('result_release_id', $pinnedId),
                )
                ->first();
            $manifestKey = $document?->id ?? $release?->id ?? $pinnedId;

            if (($document !== null && (
                $document->status !== ResultDocument::STATUS_READY
                || (int) $attempt?->current_result_document_id !== (int) $document->id
            )) || ($documentIds === [] && (
                $release === null || ! $release->is_current || $release->unreleased_at !== null
            ))) {
                $manifest[$manifestKey] = ['status' => 'failed', 'safe_error' => 'release_stale'];
                $item?->update(['status' => PrintJobItem::STATUS_FAILED, 'safe_error' => 'release_stale']);

                continue;
            }

            if ($attempt === null) {
                $manifest[$manifestKey] = ['status' => 'failed', 'safe_error' => 'result_document_missing'];
                $item?->update(['status' => PrintJobItem::STATUS_FAILED, 'safe_error' => 'result_document_missing']);

                continue;
            }

            $item?->update(['status' => PrintJobItem::STATUS_CONVERTING]);
            try {

                if ($document !== null) {
                    $bytes = $this->preparedDocumentRenderer->render($document)['pdf'];
                } else {
                    $data = $this->dataProvider->forAttempt($attempt);
                    $bytes = $this->sheetRenderer->renderForApplicants($template, [$data]);
                }

                $checksum = hash('sha256', $bytes);
                $path = 'print-jobs/'.$job->id.'/items/'.$manifestKey.'-'.$checksum.'.pdf';
                $this->assetStore->store($bytes, $path);

                $manifest[$manifestKey] = [
                    'status' => 'ready',
                    'path' => $path,
                    'checksum' => $checksum,
                    'document_version' => $document?->version,
                    'release_version' => $release?->release_version,
                ];

                $item?->update([
                    'status' => PrintJobItem::STATUS_READY,
                    'file_path' => $path,
                    'file_checksum' => $checksum,
                    'safe_error' => null,
                ]);

                for ($i = 0; $i < $copies; $i++) {
                    $sheets[] = $bytes;
                }
            } catch (Throwable $exception) {
                $safeErr = $this->safeError($exception);
                $manifest[$manifestKey] = [
                    'status' => 'failed',
                    'safe_error' => $safeErr,
                ];
                $item?->update([
                    'status' => PrintJobItem::STATUS_FAILED,
                    'safe_error' => $safeErr,
                ]);
            }
        }

        if ($sheets === []) {
            throw new AllItemsFailedException($manifest);
        }

        return [
            'bytes' => $this->merger->merge($sheets),
            'manifest' => $manifest,
        ];
    }

    private function safeError(Throwable $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'libreoffice_unavailable') => 'libreoffice_unavailable',
            str_contains($message, 'template_missing') => 'template_missing',
            default => 'render_failed',
        };
    }
}
