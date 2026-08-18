<?php

namespace App\ResultSheet\Rendering;

use App\Models\Printing\PrintJob;
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
        private readonly ResultSheetDataProvider $dataProvider,
        private readonly PdfMerger $merger,
        private readonly SensitiveAssetStore $assetStore,
    ) {
    }

    /**
     * @return array{bytes: string, manifest: array<int, array<string, mixed>>}
     */
    public function render(PrintJob $job): array
    {
        $pinned = is_array($job->pinned_input_json) ? $job->pinned_input_json : [];
        $releaseIds = array_values(array_unique(array_map('intval', $pinned['release_ids'] ?? [])));
        $copies = max(1, min(10, (int) ($pinned['copies'] ?? 1)));

        $template = ResultSheetTemplate::query()->find((int) ($pinned['template_id'] ?? 0));

        if ($template === null) {
            throw new RuntimeException('template_missing');
        }

        if ($releaseIds === []) {
            throw new RuntimeException('release_list_empty');
        }

        $releases = ResultRelease::query()
            ->whereIn('id', $releaseIds)
            ->with('admissionAttempt.identity')
            ->get()
            ->keyBy('id');

        $manifest = [];
        $sheets = [];

        foreach ($releaseIds as $releaseId) {
            $release = $releases->get($releaseId);

            if ($release === null || ! $release->is_current || $release->unreleased_at !== null) {
                $manifest[$releaseId] = ['status' => 'failed', 'safe_error' => 'release_stale'];

                continue;
            }

            try {
                $data = $this->dataProvider->forAttempt($release->admissionAttempt);
                $bytes = $this->sheetRenderer->renderForApplicants($template, [$data]);

                $checksum = hash('sha256', $bytes);
                $path = 'print-jobs/'.$job->id.'/items/'.$releaseId.'-'.$checksum.'.pdf';
                $this->assetStore->store($bytes, $path);

                $manifest[$releaseId] = [
                    'status' => 'ready',
                    'path' => $path,
                    'checksum' => $checksum,
                    'release_version' => $release->release_version,
                ];

                for ($i = 0; $i < $copies; $i++) {
                    $sheets[] = $bytes;
                }
            } catch (Throwable $exception) {
                $manifest[$releaseId] = [
                    'status' => 'failed',
                    'safe_error' => $this->safeError($exception),
                ];
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
