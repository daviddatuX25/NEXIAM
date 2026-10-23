<?php

namespace App\Assessment\Omr\Queries;

use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrRecoveryCatalog;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrPendingCaptureSet;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class OmrRunStatusSnapshot
{
    public function __construct(
        private readonly OmrCaptureResolver $captureResolver,
        private readonly OmrRecoveryCatalog $recoveryCatalog,
    ) {}

    /**
     * @return array{
     *     session_id: int,
     *     run_id: int,
     *     mode: 'production',
     *     revision: int,
     *     observed_at: string,
     *     stage: string,
     *     raw_status: string,
     *     terminal: bool,
     *     stale_after_ms: int,
     *     progress: array{completed: int, total: int, known: bool},
     *     counts: array<string, int>,
     *     actions: array{capture: bool, confirm: bool, reject: bool},
     *     sheets: list<array<string, mixed>>,
     *     needs_attention: list<array{owner: string, count: int, sheet_ids: list<int>}>
     * }
     */
    public function forBatch(OmrCaptureBatch $batch): array
    {
        $batch->loadMissing(['sheets.pages', 'sheets.issues', 'sheets.processingRuns']);

        $sheets = $batch->sheets->sortByDesc('created_at')->values();
        $counts = $this->counts($sheets);
        $totalPages = $sheets->sum(fn (OmrCaptureSheet $sheet): int => max(1, (int) $sheet->expected_page_count));
        $completedPages = $sheets->sum(fn (OmrCaptureSheet $sheet): int => $sheet->pages
            ->whereIn('status', OmrCaptureSheetPage::TERMINAL_STATUSES)
            ->count());
        $hasUnadoptedSet = OmrPendingCaptureSet::query()
            ->where('production_batch_id', $batch->id)
            ->whereNull('adopted_sheet_id')
            ->whereIn('status', [
                OmrPendingCaptureSet::STATUS_PENDING,
                OmrPendingCaptureSet::STATUS_CAPTURED,
                OmrPendingCaptureSet::STATUS_READY,
            ])
            ->where('expires_at', '>', now())
            ->exists();
        $sheetRows = $sheets->map(fn (OmrCaptureSheet $sheet): array => $this->sheetRow($sheet))->all();
        $busy = $counts['captured'] + $counts['queued'] + $counts['processing'];
        $attention = $counts['needs_review'] + $counts['failed'];
        $ready = $counts['ready'];
        $isOpen = $batch->status === OmrCaptureBatch::STATUS_OPEN;

        return [
            'session_id' => (int) $batch->grading_session_id,
            'run_id' => (int) $batch->id,
            'mode' => 'production',
            'revision' => $this->revision($batch, $sheets),
            'observed_at' => now()->toIso8601String(),
            'stage' => $this->stage($batch, $counts),
            'raw_status' => (string) $batch->status,
            'terminal' => $batch->isImmutable(),
            'stale_after_ms' => 12_000,
            'progress' => [
                'completed' => $completedPages,
                'total' => $totalPages,
                'known' => $totalPages > 0,
            ],
            'counts' => $counts,
            'actions' => [
                'capture' => $isOpen,
                'confirm' => $isOpen && ! $hasUnadoptedSet && $sheets->isNotEmpty() && $busy === 0 && $attention === 0 && $ready === $sheets->count(),
                'reject' => $isOpen,
            ],
            'sheets' => $sheetRows,
            'needs_attention' => $this->attentionGroups($sheetRows),
        ];
    }

    /**
     * @param  Collection<int, OmrCaptureSheet>  $sheets
     * @return array<string, int>
     */
    private function counts(Collection $sheets): array
    {
        $counts = [
            'total' => $sheets->count(),
            'captured' => 0,
            'queued' => 0,
            'processing' => 0,
            'needs_review' => 0,
            'ready' => 0,
            'confirmed' => 0,
            'failed' => 0,
        ];

        foreach ($sheets as $sheet) {
            $key = match ($sheet->status) {
                OmrCaptureSheet::STATUS_RECEIVED, OmrCaptureSheet::STATUS_PENDING_PAGES => 'captured',
                OmrCaptureSheet::STATUS_QUEUED => 'queued',
                OmrCaptureSheet::STATUS_PROCESSING => 'processing',
                OmrCaptureSheet::STATUS_NEEDS_REVIEW => 'needs_review',
                OmrCaptureSheet::STATUS_REVIEWED => 'ready',
                OmrCaptureSheet::STATUS_COMMITTED => 'confirmed',
                OmrCaptureSheet::STATUS_FAILED_TERMINAL => 'failed',
                default => null,
            };

            if ($key !== null) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    private function sheetRow(OmrCaptureSheet $sheet): array
    {
        $resolution = $this->captureResolver->resolveSheet($sheet);
        $failureCode = $resolution->failureCode();
        $recovery = $failureCode === null ? null : $this->recoveryCatalog->for($failureCode);

        if ($recovery !== null) {
            $recovery['support_code'] = sprintf('OMR-%d-%s', $sheet->id, strtoupper($failureCode));
            $recovery['technical_detail'] = $resolution->failure?->context ?? [];
        }

        return [
            'id' => (int) $sheet->id,
            'status' => (string) $sheet->status,
            'version' => (int) $sheet->version,
            'stage' => $this->sheetStage($sheet),
            'resolution' => $resolution->toArray(),
            'recovery' => $recovery,
            'pages' => $sheet->pages->sortBy('page_no')->map(fn (OmrCaptureSheetPage $page): array => [
                'page_no' => (int) $page->page_no,
                'status' => (string) $page->status,
                'captured' => $page->asset_path !== null,
            ])->values()->all(),
        ];
    }

    /** @param array<string, int> $counts */
    private function stage(OmrCaptureBatch $batch, array $counts): string
    {
        if ($batch->status === OmrCaptureBatch::STATUS_CONFIRMED) {
            return 'confirmed';
        }

        if ($counts['processing'] > 0) {
            return 'recognizing';
        }

        if ($counts['queued'] > 0) {
            return 'queued';
        }

        if ($counts['captured'] > 0) {
            return 'captured';
        }

        if ($counts['needs_review'] + $counts['failed'] > 0) {
            return 'needs_review';
        }

        if ($counts['ready'] > 0) {
            return 'ready';
        }

        return (string) $batch->status;
    }

    private function sheetStage(OmrCaptureSheet $sheet): string
    {
        if ($sheet->status === OmrCaptureSheet::STATUS_PROCESSING) {
            $activeRun = $sheet->processingRuns->sortByDesc('id')->first();

            return $activeRun?->status === 'claimed' ? 'recognizing' : 'preparing';
        }

        return match ($sheet->status) {
            OmrCaptureSheet::STATUS_RECEIVED, OmrCaptureSheet::STATUS_PENDING_PAGES => 'captured',
            OmrCaptureSheet::STATUS_NEEDS_REVIEW => 'needs_review',
            OmrCaptureSheet::STATUS_REVIEWED => 'ready',
            OmrCaptureSheet::STATUS_COMMITTED => 'confirmed',
            default => (string) $sheet->status,
        };
    }

    /** @param Collection<int, OmrCaptureSheet> $sheets */
    private function revision(OmrCaptureBatch $batch, Collection $sheets): int
    {
        $pendingSets = OmrPendingCaptureSet::query()
            ->where('production_batch_id', $batch->id)
            ->get(['updated_at', 'revision']);
        $latest = collect([$batch->updated_at])
            ->merge($pendingSets->pluck('updated_at'))
            ->merge($sheets->pluck('updated_at'))
            ->merge($sheets->flatMap->pages->pluck('updated_at'))
            ->merge($sheets->flatMap->issues->pluck('updated_at'))
            ->merge($sheets->flatMap->processingRuns->pluck('updated_at'))
            ->filter(fn (mixed $value): bool => $value instanceof CarbonInterface)
            ->max(fn (CarbonInterface $value): int => $value->getTimestampMs());

        $versionSum = (int) $batch->version
            + $pendingSets->sum(fn (OmrPendingCaptureSet $set): int => (int) $set->revision)
            + $sheets->sum(fn (OmrCaptureSheet $sheet): int => (int) $sheet->version)
            + $sheets->flatMap->issues->sum(fn (mixed $issue): int => (int) $issue->version);

        return max(1, ((int) $latest * 1000) + min(999, $versionSum));
    }

    /**
     * @param  list<array<string, mixed>>  $sheets
     * @return list<array{owner: string, count: int, sheet_ids: list<int>}>
     */
    private function attentionGroups(array $sheets): array
    {
        return collect($sheets)
            ->filter(fn (array $sheet): bool => is_array($sheet['recovery'] ?? null))
            ->groupBy(fn (array $sheet): string => (string) ($sheet['recovery']['owner'] ?? 'OMR operator'))
            ->map(fn (Collection $group, string $owner): array => [
                'owner' => $owner,
                'count' => $group->count(),
                'sheet_ids' => $group->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            ])
            ->values()
            ->all();
    }
}
