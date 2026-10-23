<?php

namespace App\Foundation\Actions;

use App\Jobs\Foundation\RunFoundationSideEffect;
use App\Models\Foundation\Operation;
use Illuminate\Support\Facades\DB;

class CommitFoundationDemo
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $idempotencyKey, array $payload = []): Operation
    {
        $existing = Operation::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($idempotencyKey, $payload): Operation {
            $operation = Operation::query()->create([
                'idempotency_key' => $idempotencyKey,
                'type' => 'foundation.demo_side_effect',
                'status' => Operation::STATUS_PENDING,
                'attempts' => 0,
                'payload' => $payload,
            ]);

            $this->recordAuditEvent->handle('foundation.operation_committed', [
                'operation_id' => $operation->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Database queue connection uses after_commit=true; dispatch stays
            // inside the transaction so the job is released only after commit.
            RunFoundationSideEffect::dispatch($operation->id);

            return $operation;
        });
    }
}
