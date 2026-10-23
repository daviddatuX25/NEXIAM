<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelAnswerSheetImport
{
    public function __construct(
        private readonly AnswerSheetImportAuthorization $authorization,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, AnswerSheetImportOperation $operation, int $expectedGeneration): AnswerSheetImportOperation
    {
        $this->authorization->assertOwner($actor, $operation);

        return DB::transaction(function () use ($actor, $operation, $expectedGeneration): AnswerSheetImportOperation {
            $locked = AnswerSheetImportOperation::query()
                ->whereKey($operation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $institutionId = $this->authorization->assertOwner($actor, $locked);

            if ($locked->phase === AnswerSheetImportOperation::PHASE_CANCELLED) {
                return $locked;
            }
            if ((int) $locked->generation !== $expectedGeneration
                || ! in_array($locked->phase, [
                    AnswerSheetImportOperation::PHASE_QUEUED,
                    AnswerSheetImportOperation::PHASE_CHECKING,
                    AnswerSheetImportOperation::PHASE_NEEDS_REVIEW,
                    AnswerSheetImportOperation::PHASE_READY_TO_RESTORE,
                    AnswerSheetImportOperation::PHASE_RESTORING,
                ], true)) {
                throw ValidationException::withMessages([
                    'operation' => 'This import can no longer be cancelled. Refresh its status and review the current result.',
                ]);
            }

            $locked->forceFill([
                'phase' => AnswerSheetImportOperation::PHASE_CANCELLED,
                'revision' => (int) $locked->revision + 1,
                'generation' => (int) $locked->generation + 1,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'confirmed_by_user_id' => null,
                'confirmed_review_revision' => null,
                'confirmation_review_token' => null,
                'confirmed_at' => null,
            ])->save();

            $this->recordAuditEvent->handle('omr.answer_sheet_import_cancelled', [
                'operation_id' => $locked->id,
                'institution_id' => $institutionId,
                'generation' => (int) $locked->generation,
            ], (string) $actor->getKey());

            return $locked->fresh();
        }, attempts: 3);
    }
}
