<?php

namespace App\ResultSheet\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Printing\RenderPrintJob;
use App\Models\Printing\PrintJob;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\ReleaseResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Queues one or many released result sheets through the print pipeline
 * (single + bulk ≤200, copies 1–10). Pins the release ids + versions and the
 * active template identity so retries render from the same inputs; duplicate
 * live requests return the existing job.
 */
final class RequestResultSheetPrint
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  list<int>  $releaseIds
     */
    public function handle(User $actor, array $releaseIds, int $copies = 1): PrintJob
    {
        if (! $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [])->allowed) {
            throw new RuntimeException('The result release capability is required to print result sheets.');
        }

        $releaseIds = array_values(array_unique(array_map('intval', $releaseIds)));

        if ($releaseIds === [] || count($releaseIds) > 200) {
            throw new RuntimeException('Select between 1 and 200 released applicants.');
        }

        $copies = max(1, min(10, $copies));

        $releases = ResultRelease::query()
            ->whereIn('id', $releaseIds)
            ->where('is_current', true)
            ->whereNull('unreleased_at')
            ->get()
            ->keyBy('id');

        if (count($releases) !== count($releaseIds)) {
            throw new RuntimeException('Only current, released records can be printed.');
        }

        if ($releases->contains(fn (ResultRelease $release): bool => $release->mode === ReleaseResult::MODE_ONLINE)) {
            throw new RuntimeException('Result sheets cannot be printed for online releases.');
        }

        $template = ResultSheetTemplate::active();

        if ($template === null) {
            throw new RuntimeException('No active result sheet template.');
        }

        return DB::transaction(function () use ($actor, $releaseIds, $copies, $releases, $template): PrintJob {
            $existing = $this->findLiveDuplicate($releaseIds, $copies);

            if ($existing !== null) {
                return $existing;
            }

            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_RESULT_SHEET,
                'scope_type' => 'result_release',
                'scope_id' => $releaseIds[0],
                'requested_by_user_id' => $actor->id,
                'pinned_input_json' => [
                    'release_ids' => $releaseIds,
                    'release_versions' => $releases->mapWithKeys(
                        fn (ResultRelease $release): array => [$release->id => $release->release_version]
                    )->all(),
                    'copies' => $copies,
                    'template_id' => $template->id,
                    'template_checksum' => $template->document_checksum
                        ?? hash('sha256', (string) $template->content),
                ],
                'progress_total' => count($releaseIds),
                'progress_done' => 0,
            ]);

            $this->recordAuditEvent->handle('printing.result_sheets_requested', [
                'print_job_id' => $job->id,
                'release_ids' => $releaseIds,
                'copies' => $copies,
                'template_id' => $template->id,
            ], (string) $actor->id);

            RenderPrintJob::dispatch($job->id)->afterCommit();

            return $job;
        });
    }

    /**
     * @param  list<int>  $releaseIds
     */
    private function findLiveDuplicate(array $releaseIds, int $copies): ?PrintJob
    {
        return PrintJob::query()
            ->where('artifact_type', PrintJob::ARTIFACT_RESULT_SHEET)
            ->whereIn('status', [PrintJob::STATUS_QUEUED, PrintJob::STATUS_RENDERING, PrintJob::STATUS_READY])
            ->orderByDesc('id')
            ->get()
            ->first(function (PrintJob $job) use ($releaseIds, $copies): bool {
                $pinned = $job->pinned_input_json ?? [];

                return ($pinned['release_ids'] ?? null) == $releaseIds
                    && (int) ($pinned['copies'] ?? 1) === $copies;
            });
    }
}
