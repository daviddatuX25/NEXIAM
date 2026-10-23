<?php

namespace App\Jobs\Assessment;

use App\Assessment\Actions\Omr\InspectAnswerSheetImport;
use App\Models\Assessment\AnswerSheetImportOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class InspectAnswerSheetImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public string $operationId, public int $generation)
    {
        $this->onQueue('default');
    }

    public function handle(InspectAnswerSheetImport $inspection): void
    {
        $inspection->handle($this->operationId, $this->generation);
    }

    public function failed(?Throwable $exception): void
    {
        AnswerSheetImportOperation::query()
            ->whereKey($this->operationId)
            ->where('generation', $this->generation)
            ->whereIn('phase', [
                AnswerSheetImportOperation::PHASE_QUEUED,
                AnswerSheetImportOperation::PHASE_CHECKING,
            ])
            ->update([
                'phase' => AnswerSheetImportOperation::PHASE_FAILED,
                'revision' => DB::raw('revision + 1'),
                'error_code' => 'inspection_worker_failed',
                'safe_error' => 'The archive could not be checked after several attempts. Retry the check or choose a replacement archive.',
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
