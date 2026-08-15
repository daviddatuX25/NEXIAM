<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CreateOmrCaptureBatch
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        GradingSession $gradingSession,
        User $actor,
        int $templateVersionId,
        int $bookletVersionId,
        string $idempotencyKey,
    ): OmrCaptureBatch {
        return DB::transaction(function () use ($gradingSession, $actor, $templateVersionId, $bookletVersionId, $idempotencyKey): OmrCaptureBatch {
            $session = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);

            if ($session->status === GradingSession::STATUS_FINALIZED) {
                throw new RuntimeException('Grading session is finalized; OMR capture is closed.');
            }

            $pair = OmrTemplateBookletPair::query()
                ->with(['templateVersion', 'bookletVersion'])
                ->where('template_version_id', $templateVersionId)
                ->where('booklet_version_id', $bookletVersionId)
                ->first();

            if ($pair === null || ! $pair->isUsable()) {
                throw new RuntimeException('The selected OMR template and booklet pair is not active.');
            }

            $active = OmrCaptureBatch::query()
                ->where('grading_session_id', $session->id)
                ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($active !== null) {
                if ((int) $active->template_version_id !== $templateVersionId
                    || (int) $active->booklet_version_id !== $bookletVersionId) {
                    throw new RuntimeException('This grading session already has an active OMR batch with different pinned versions.');
                }

                return $active;
            }

            $batch = OmrCaptureBatch::query()->create([
                'grading_session_id' => $session->id,
                'template_version_id' => $templateVersionId,
                'booklet_version_id' => $bookletVersionId,
                'status' => OmrCaptureBatch::STATUS_OPEN,
                'version' => 0,
                'uploaded_by_user_id' => $actor->id,
                'opened_by_user_id' => $actor->id,
                'opened_at' => now(),
            ]);

            $this->recordAuditEvent->handle('grading.omr_batch_opened', [
                'batch_id' => $batch->id,
                'grading_session_id' => $session->id,
                'template_version_id' => $templateVersionId,
                'booklet_version_id' => $bookletVersionId,
                'idempotency_key_present' => $idempotencyKey !== '',
            ], (string) $actor->id);

            return $batch;
        });
    }
}
