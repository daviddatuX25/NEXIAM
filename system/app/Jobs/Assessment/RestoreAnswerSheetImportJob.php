<?php

namespace App\Jobs\Assessment;

use App\Assessment\Actions\Omr\RestoreAnswerSheetImport;
use App\Models\Assessment\AnswerSheetImportOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RestoreAnswerSheetImportJob implements ShouldQueue
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

    public function handle(RestoreAnswerSheetImport $restore): void
    {
        $restore->handle($this->operationId, $this->generation);
    }

    public function failed(?Throwable $exception): void
    {
        AnswerSheetImportOperation::query()
            ->whereKey($this->operationId)
            ->where('generation', $this->generation)
            ->where('phase', AnswerSheetImportOperation::PHASE_RESTORING)
            ->update([
                'phase' => AnswerSheetImportOperation::PHASE_FAILED,
                'revision' => DB::raw('revision + 1'),
                'error_code' => 'restore_worker_failed',
                'safe_error' => 'The Answer Sheet could not be restored after several attempts. Retry the checked archive or choose a replacement.',
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
