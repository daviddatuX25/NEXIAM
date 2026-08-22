<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Admission\Support\CommandIdempotency;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RenderOmrBlankInstanceBatchJob;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Foundation\Operation;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function config;

final class PrintOmrBlankInstances
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrAnswerSheetTemplateVersion $version, User $actor, int $quantity, string $idempotencyKey): Operation
    {
        if ($version->status !== OmrAnswerSheetTemplateVersion::STATUS_ACTIVE) {
            throw new \RuntimeException('template_not_active');
        }
        return DB::transaction(function () use ($version, $actor, $quantity, $idempotencyKey): Operation {
            $operation = $this->idempotency->claim($actor, 'omr.canonical.blank_print', (string) $version->id, [
                'template_version_id' => $version->id,
                'quantity' => $quantity,
            ], $idempotencyKey);
            if (! $operation->wasRecentlyCreated) {
                return $operation;
            }
            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_BLANK_SHEET,
                'scope_type' => 'template_version',
                'scope_id' => $version->id,
                'status' => PrintJob::STATUS_QUEUED,
                'requested_by_user_id' => $actor->id,
                'progress_total' => $quantity,
                'progress_done' => 0,
                'pinned_input_json' => [
                    'template_version_id' => $version->id,
                    'native_bundle_hash' => $version->native_bundle_hash,
                    'quantity' => $quantity,
                    'chunk_size' => (int) config('omr.canonical.limits.blank_print_chunk', 100),
                ],
            ]);
            RenderOmrBlankInstanceBatchJob::dispatch($job->id, (int) $actor->id)->afterCommit();
            $this->recordAuditEvent->handle('omr.canonical.blank_print_queued', [
                'print_job_id' => $job->id,
                'template_version_id' => $version->id,
                'quantity' => $quantity,
            ], (string) $actor->id);

            return $operation;
        });
    }
}
