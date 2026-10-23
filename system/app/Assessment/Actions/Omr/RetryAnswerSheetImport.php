<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\InspectAnswerSheetImportJob;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetryAnswerSheetImport
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

            if ((int) $locked->generation !== $expectedGeneration) {
                throw ValidationException::withMessages([
                    'operation' => 'This import changed. Refresh its status before retrying.',
                ]);
            }
            $leaseExpired = $locked->lease_expires_at !== null && $locked->lease_expires_at->isPast();
            $missingLeaseTooLong = $locked->lease_expires_at === null
                && in_array($locked->phase, [
                    AnswerSheetImportOperation::PHASE_CHECKING,
                    AnswerSheetImportOperation::PHASE_RESTORING,
                ], true)
                && $locked->updated_at !== null
                && $locked->updated_at->addMinutes(15)->isPast();
            $queuedTooLong = $locked->phase === AnswerSheetImportOperation::PHASE_QUEUED
                && $locked->created_at !== null
                && $locked->created_at->addMinutes(15)->isPast();
            $recoverable = $locked->phase === AnswerSheetImportOperation::PHASE_FAILED
                || ($locked->phase === AnswerSheetImportOperation::PHASE_QUEUED && $queuedTooLong)
                || (in_array($locked->phase, [
                    AnswerSheetImportOperation::PHASE_CHECKING,
                    AnswerSheetImportOperation::PHASE_RESTORING,
                ], true) && ($leaseExpired || $missingLeaseTooLong));

            if (! $recoverable) {
                throw ValidationException::withMessages([
                    'operation' => 'This import cannot be retried yet. Resolve its current review or wait for the active check to finish.',
                ]);
            }

            $locked->forceFill([
                'phase' => AnswerSheetImportOperation::PHASE_QUEUED,
                'revision' => (int) $locked->revision + 1,
                'generation' => (int) $locked->generation + 1,
                'review_revision' => (int) $locked->review_revision + 1,
                'parser_version' => null,
                'progress_done' => 0,
                'progress_total' => 0,
                'progress_unit' => 'archive_members',
                'report_json' => null,
                'report_sha256' => null,
                'error_code' => null,
                'safe_error' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'confirmed_by_user_id' => null,
                'confirmed_review_revision' => null,
                'confirmation_review_token' => null,
                'confirmed_at' => null,
            ])->save();

            $this->recordAuditEvent->handle('omr.answer_sheet_import_reinspection_queued', [
                'operation_id' => $locked->id,
                'institution_id' => $institutionId,
                'generation' => (int) $locked->generation,
            ], (string) $actor->getKey());

            InspectAnswerSheetImportJob::dispatch((string) $locked->id, (int) $locked->generation)->afterCommit();

            return $locked->fresh();
        }, attempts: 3);
    }
}
