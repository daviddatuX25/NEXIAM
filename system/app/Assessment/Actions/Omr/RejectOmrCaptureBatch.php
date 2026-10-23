<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reject an empty open batch without deleting committed evidence.
 *
 * Sheet cleanup remains an explicit workflow concern; this action refuses
 * non-empty batches instead of bypassing retention and audit invariants.
 */
final class RejectOmrCaptureBatch
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(OmrCaptureBatch $batch, User $actor): OmrCaptureBatch
    {
        return DB::transaction(function () use ($batch, $actor): OmrCaptureBatch {
            $batch = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status === OmrCaptureBatch::STATUS_REJECTED) {
                return $batch;
            }

            if ($batch->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Only an open OMR batch can be rejected.');
            }

            if ($batch->sheets()->exists()) {
                throw new RuntimeException('Non-empty OMR batches require the cleanup workflow before rejection.');
            }

            $batch->update([
                'status' => OmrCaptureBatch::STATUS_REJECTED,
                'version' => (int) $batch->version + 1,
                'rejecting_by_user_id' => $actor->id,
                'rejecting_at' => now(),
                'confirmed_at' => now(),
            ]);

            $this->recordAuditEvent->handle('grading.omr_batch_rejected', [
                'batch_id' => $batch->id,
                'grading_session_id' => $batch->grading_session_id,
            ], (string) $actor->id);

            return $batch;
        });
    }
}
