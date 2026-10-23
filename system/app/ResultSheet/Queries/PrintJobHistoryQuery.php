<?php

namespace App\ResultSheet\Queries;

use App\Models\Assessment\GradingSession;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;

/**
 * Returns recent print jobs associated with a grading session or scope,
 * including per-item progress, failure counts, and artifact download links.
 */
class PrintJobHistoryQuery
{
    /**
     * @return list<array{id: int, status: string, artifact_type: string, progress_done: int, progress_total: int, safe_error: string|null, requested_by: string, requested_at: string, completed_at: string|null, download_url: string|null, failed_items: int, ready_items: int}>
     */
    public function forSession(GradingSession $session, int $limit = 10): array
    {
        $attemptIds = $session->scoredAttemptIds();
        $releaseIds = ResultRelease::query()->whereIn('admission_attempt_id', $attemptIds)->pluck('id')->all();
        $documentIds = ResultDocument::query()->whereIn('admission_attempt_id', $attemptIds)->pluck('id')->all();

        $jobIds = PrintJobItem::query()
            ->whereIn('result_release_id', $releaseIds)
            ->orWhereIn('result_document_id', $documentIds)
            ->orWhereIn('admission_attempt_id', $attemptIds)
            ->pluck('print_job_id')
            ->unique()
            ->all();

        $scopedJobIds = PrintJob::query()
            ->where('artifact_type', PrintJob::ARTIFACT_RESULT_SHEET)
            ->where('scope_type', 'result_document')
            ->whereIn('scope_id', $documentIds)
            ->pluck('id')
            ->all();
        $jobIds = array_values(array_unique([...$jobIds, ...$scopedJobIds]));

        $jobs = PrintJob::query()
            ->where('artifact_type', PrintJob::ARTIFACT_RESULT_SHEET)
            ->where('is_preview', false)
            ->whereIn('id', $jobIds)
            ->with(['requestedBy', 'items'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $jobs->map(function (PrintJob $job): array {
            $failedCount = $job->items->where('status', PrintJobItem::STATUS_FAILED)->count();
            $readyCount = $job->items->where('status', PrintJobItem::STATUS_READY)->count();

            return [
                'id' => $job->id,
                'status' => $job->status,
                'artifact_type' => $job->artifact_type,
                'progress_done' => $job->progress_done,
                'progress_total' => $job->progress_total,
                'safe_error' => $job->safe_error,
                'requested_by' => $job->requestedBy?->name ?? 'System',
                'requested_at' => $job->created_at?->diffForHumans() ?? '—',
                'completed_at' => $job->completed_at?->diffForHumans(),
                'download_url' => $job->isReady() ? route('printing.jobs.download', $job) : null,
                'outputs' => $job->isReady() && is_array($job->output_manifest)
                    ? array_keys($job->output_manifest)
                    : ($job->isReady() ? ['pdf'] : []),
                'failed_items' => $failedCount,
                'ready_items' => $readyCount,
            ];
        })->values()->all();
    }
}
