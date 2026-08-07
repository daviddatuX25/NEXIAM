<?php

namespace App\Jobs\Foundation;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\Operation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunFoundationSideEffect implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $operationId)
    {
    }

    public function handle(RecordAuditEvent $recordAuditEvent): void
    {
        $operation = Operation::query()->findOrFail($this->operationId);

        if ($operation->status === Operation::STATUS_SUCCEEDED) {
            return;
        }

        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => $operation->attempts + 1,
            'last_error' => null,
        ])->save();

        $path = 'foundation/operations/'.$operation->id.'.json';
        Storage::disk('local')->put($path, json_encode([
            'operation_id' => $operation->id,
            'idempotency_key' => $operation->idempotency_key,
            'payload' => $operation->payload,
        ], JSON_THROW_ON_ERROR));

        $operation->forceFill([
            'status' => Operation::STATUS_SUCCEEDED,
            'finished_at' => now(),
        ])->save();

        $recordAuditEvent->handle('foundation.operation_succeeded', [
            'operation_id' => $operation->id,
            'storage_path' => $path,
            'checksum' => hash('sha256', Storage::disk('local')->get($path)),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $operation = Operation::query()->find($this->operationId);

        if ($operation === null) {
            return;
        }

        $operation->forceFill([
            'status' => Operation::STATUS_FAILED,
            'last_error' => $exception?->getMessage(),
            'finished_at' => now(),
        ])->save();
    }
}
