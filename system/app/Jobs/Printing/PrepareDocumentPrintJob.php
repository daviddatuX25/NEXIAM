<?php

namespace App\Jobs\Printing;

use App\DocumentTemplate\MountSlotRegistry;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Reports\ReportTemplate;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PrepareDocumentPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @param array<string, mixed> $scope */
    public function __construct(
        public readonly PrintJob $printJob,
        public readonly ReportTemplate $template,
        public readonly array $scope,
    ) {}

    public function handle(): void
    {
        $workUnits = $this->materializeWorkUnits();
        $items = DB::transaction(function () use ($workUnits): array {
            $job = PrintJob::query()->lockForUpdate()->findOrFail($this->printJob->getKey());
            $job->items()->delete();

            $items = [];
            foreach ($workUnits as $index => $workUnit) {
                $item = $job->items()->create([
                    'item_key' => $workUnit['item_key'],
                    'work_unit_type' => $workUnit['work_unit_type'],
                    'admission_attempt_id' => $workUnit['admission_attempt_id'],
                    'item_index' => $index,
                    'status' => PrintJobItem::STATUS_QUEUED,
                ]);
                $items[] = [$item, $workUnit['payload']];
            }

            $job->update([
                'progress_total' => count($items),
                'progress_done' => 0,
                'status' => PrintJob::STATUS_RENDERING,
                'stage' => 'filling',
            ]);

            return $items;
        });

        if ($items === []) {
            $this->printJob->update(['status' => PrintJob::STATUS_FAILED, 'stage' => null]);

            return;
        }

        $renderJobs = array_map(
            fn (array $entry): RenderDocumentPrintItem => new RenderDocumentPrintItem(
                (int) $this->printJob->getKey(),
                (int) $entry[0]->getKey(),
                (int) $this->template->getKey(),
                $entry[1],
            ),
            $items,
        );

        $printJobId = (int) $this->printJob->getKey();
        Bus::batch($renderJobs)
            ->then(fn () => AssembleDocumentPrintJob::dispatch($printJobId))
            ->catch(fn (Batch $batch, Throwable $exception) => PrintJob::query()->whereKey($printJobId)->update(['status' => PrintJob::STATUS_FAILED]))
            ->dispatch();
    }

    public function failed(Throwable $e): void
    {
        PrintJob::query()->whereKey($this->printJob->getKey())->update([
            'status' => PrintJob::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ]);
    }

    /** @return list<array{item_key: string, work_unit_type: string, admission_attempt_id: ?int, payload: array<string, mixed>}> */
    private function materializeWorkUnits(): array
    {
        $grain = (string) ($this->template->grain ?? MountSlotRegistry::GRAIN_CYCLE_PROGRAM);
        $workUnits = [];

        if ($grain === MountSlotRegistry::GRAIN_STUDENT) {
            $attemptIds = array_values(array_unique(array_filter(array_map('intval', (array) ($this->scope['attempt_ids'] ?? $this->scope['student_ids'] ?? [])))));
            $attempts = AdmissionAttempt::query()
                ->forWorkspace()
                ->whereIn('id', $attemptIds)
                ->orderBy('id')
                ->get(['id']);
            foreach ($attempts as $attempt) {
                $id = (int) $attempt->getKey();
                $workUnits[] = [
                    'item_key' => 'student:'.$id,
                    'work_unit_type' => 'student',
                    'admission_attempt_id' => $id,
                    'payload' => ['grain' => $grain, 'attempt_id' => $id],
                ];
            }

            return $workUnits;
        }

        if ($grain === MountSlotRegistry::GRAIN_SESSION) {
            $sessionIds = array_values(array_unique(array_filter(array_map('intval', (array) ($this->scope['session_ids'] ?? (isset($this->scope['session_id']) ? [$this->scope['session_id']] : []))))));
            foreach ($sessionIds as $sessionId) {
                $candidateIds = array_values(array_unique(array_filter(array_map('intval', (array) ($this->scope['session_examinees'][$sessionId] ?? $this->scope['attempt_ids'] ?? [])))));
                $examinees = AdmissionAttempt::query()
                    ->forWorkspace()
                    ->whereHas('examAssignments', static fn ($query) => $query->where('exam_session_id', $sessionId));
                if ($candidateIds !== []) {
                    $examinees->whereIn('id', $candidateIds);
                }
                $attemptIds = $examinees->orderBy('id')
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();
                $workUnits[] = [
                    'item_key' => 'session:'.$sessionId,
                    'work_unit_type' => 'session',
                    'admission_attempt_id' => null,
                    'payload' => ['grain' => $grain, 'session_id' => $sessionId, 'attempt_ids' => $attemptIds],
                ];
            }

            return $workUnits;
        }

        $providedChunks = $this->scope['chunks'] ?? null;
        if (is_array($providedChunks) && $providedChunks !== []) {
            foreach (array_values($providedChunks) as $index => $chunk) {
                if (! is_array($chunk)) {
                    continue;
                }
                $key = (string) ($chunk['item_key'] ?? 'cycle_program:'.($chunk['program_code'] ?? ($chunk['cycle_id'] ?? $index)).':'.$index);
                $workUnits[] = [
                    'item_key' => $key,
                    'work_unit_type' => 'cycle_program',
                    'admission_attempt_id' => null,
                    'payload' => ['grain' => MountSlotRegistry::GRAIN_CYCLE_PROGRAM, ...$chunk],
                ];
            }

            return $workUnits;
        }

        $programCodes = array_values(array_unique(array_filter(array_map('strval', (array) ($this->scope['program_codes'] ?? [])))));
        if ($programCodes === [] && isset($this->scope['program_code'])) {
            $programCodes = [(string) $this->scope['program_code']];
        }
        if ($programCodes === []) {
            $programCodes = [''];
        }
        foreach ($programCodes as $programCode) {
            $cycleId = isset($this->scope['cycle_id']) ? (int) $this->scope['cycle_id'] : null;
            $workUnits[] = [
                'item_key' => 'cycle_program:'.($cycleId ?? 'all').':'.($programCode !== '' ? $programCode : 'all'),
                'work_unit_type' => 'cycle_program',
                'admission_attempt_id' => null,
                'payload' => [
                    'grain' => MountSlotRegistry::GRAIN_CYCLE_PROGRAM,
                    'cycle_id' => $cycleId,
                    'program_code' => $programCode !== '' ? $programCode : null,
                    'department_code' => $this->scope['department_code'] ?? null,
                ],
            ];
        }

        return $workUnits;
    }
}
