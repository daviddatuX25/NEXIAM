<?php

namespace App\ResultSheet\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Pipeline\AssemblePrintJob;
use App\ResultSheet\Pipeline\FillAndConvertJob;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Queues current prepared result documents through the print pipeline. The
 * document IDs, versions, checksums and active template identity are pinned
 * so retries render from the same approved inputs.
 */
final class RequestResultSheetPrint
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<int>  $documentIds
     */
    public function handle(User $actor, array $documentIds, int $copies = 1): PrintJob
    {
        $canPrint = $this->authorizer->allowsAny($actor, [
            CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
            CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
        ], []);
        if (! $canPrint) {
            throw new RuntimeException('The result release or result prepare capability is required to print result sheets.');
        }

        $documentIds = array_values(array_unique(array_map('intval', $documentIds)));

        if ($documentIds === [] || count($documentIds) > 200) {
            throw new RuntimeException('Select between 1 and 200 result documents.');
        }

        $copies = max(1, min(10, $copies));

        $documents = ResultDocument::query()
            ->whereIn('id', $documentIds)
            ->where('status', ResultDocument::STATUS_READY)
            ->get()
            ->keyBy('id');

        if ($documents->count() !== count($documentIds)) {
            throw new RuntimeException('Only ready result documents can be printed.');
        }

        $template = ResultSheetTemplate::active();

        if ($template === null) {
            throw new RuntimeException('No active result sheet template.');
        }

        return DB::transaction(function () use ($actor, $documentIds, $copies, $documents, $template): PrintJob {
            $attemptIds = $documents->pluck('admission_attempt_id')->unique()->sort()->values();
            $attempts = AdmissionAttempt::query()
                ->forWorkspace()
                ->whereIn('id', $attemptIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($attempts->count() !== $attemptIds->count()) {
                throw new AuthorizationException('One or more result documents are outside the current workspace.');
            }

            $documents = ResultDocument::query()
                ->whereIn('id', $documentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($documents->count() !== count($documentIds)) {
                throw new RuntimeException('One or more result documents no longer exist.');
            }
            foreach ($documents as $document) {
                $attempt = $attempts->get($document->admission_attempt_id);
                if (! $this->authorizer->allowsAny($actor, [
                    CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
                    CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
                ], ['institution_id' => (int) $attempt->institution_id])) {
                    throw new AuthorizationException('One or more result documents are outside the authorized institution.');
                }
                if ($document->status !== ResultDocument::STATUS_READY) {
                    throw new RuntimeException('Only the current ready result document can be printed.');
                }
                if ((int) $attempt->current_result_document_id !== (int) $document->id) {
                    throw new RuntimeException('Only the current ready result document can be printed.');
                }
            }
            $existing = $this->findLiveDuplicate($documentIds, $copies);

            if ($existing !== null) {
                return $existing;
            }

            $releases = ResultRelease::query()
                ->whereIn('result_document_id', $documentIds)
                ->where('is_current', true)
                ->whereNull('unreleased_at')
                ->get();

            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_RESULT_SHEET,
                'scope_type' => 'result_document',
                'scope_id' => $documentIds[0],
                'requested_by_user_id' => $actor->id,
                'pinned_input_json' => [
                    'document_ids' => $documentIds,
                    'release_ids' => $releases->isNotEmpty() ? $releases->pluck('id')->values()->all() : [],
                    'document_versions' => $documents->mapWithKeys(
                        fn (ResultDocument $document): array => [$document->id => $document->version]
                    )->all(),
                    'document_checksums' => $documents->mapWithKeys(
                        fn (ResultDocument $document): array => [$document->id => $document->checksum]
                    )->all(),
                    'copies' => $copies,
                    'template_id' => $template->id,
                    'template_checksum' => $template->printChecksum(),
                ],
                'progress_total' => count($documentIds),
                'progress_done' => 0,
            ]);

            foreach ($documentIds as $idx => $documentId) {
                $document = $documents->get($documentId);
                $item = new PrintJobItem([
                    'print_job_id' => $job->id,
                    'admission_attempt_id' => $document->admission_attempt_id,
                    'result_release_id' => null,
                    'item_index' => $idx,
                    'status' => PrintJobItem::STATUS_QUEUED,
                ]);
                $item->result_document_id = $document->id;
                $item->save();
            }
            $this->recordAuditEvent->handle('printing.result_sheets_requested', [
                'print_job_id' => $job->id,
                'document_ids' => $documentIds,
                'copies' => $copies,
                'template_id' => $template->id,
            ], (string) $actor->id);

            DB::afterCommit(function () use ($job): void {
                $itemJobs = $job->items()->orderBy('item_index')->get()
                    ->map(fn (PrintJobItem $item): FillAndConvertJob => new FillAndConvertJob($item->id))
                    ->all();

                Bus::batch($itemJobs)
                    ->name('result-sheet-print-'.$job->id)
                    ->finally(function (Batch $batch) use ($job): void {
                        AssemblePrintJob::dispatch($job->id);
                    })
                    ->dispatch();
            });

            return $job;
        });
    }

    /**
     * @param  list<int>  $documentIds
     */
    private function findLiveDuplicate(array $documentIds, int $copies): ?PrintJob
    {
        return PrintJob::query()
            ->where('artifact_type', PrintJob::ARTIFACT_RESULT_SHEET)
            ->where('is_preview', false)
            ->whereIn('status', [PrintJob::STATUS_QUEUED, PrintJob::STATUS_RENDERING, PrintJob::STATUS_READY])
            ->orderByDesc('id')
            ->get()
            ->first(function (PrintJob $job) use ($documentIds, $copies): bool {
                $pinned = $job->pinned_input_json ?? [];

                $matches = ($pinned['document_ids'] ?? null) == $documentIds
                    && (int) ($pinned['copies'] ?? 1) === $copies;

                if (! $matches) {
                    return false;
                }

                if ($job->status === PrintJob::STATUS_READY) {
                    $manifest = is_array($job->output_manifest) ? $job->output_manifest : [];
                    $pdfPath = $manifest['pdf']['path'] ?? $job->file_path;
                    if ($pdfPath === null || ! Storage::disk('sensitive')->exists((string) $pdfPath)) {
                        return false;
                    }
                }

                return true;
            });
    }
}
