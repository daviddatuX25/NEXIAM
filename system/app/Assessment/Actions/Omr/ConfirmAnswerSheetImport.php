<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RestoreAnswerSheetImportJob;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmAnswerSheetImport
{
    public function __construct(
        private readonly AnswerSheetImportAuthorization $authorization,
        private readonly AnswerSheetImportReviewToken $reviewTokens,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        User $actor,
        AnswerSheetImportOperation $operation,
        int $reviewRevision,
        string $reviewToken,
    ): AnswerSheetImportOperation {
        $this->authorization->assertOwner($actor, $operation);

        return DB::transaction(function () use ($actor, $operation, $reviewRevision, $reviewToken): AnswerSheetImportOperation {
            $locked = AnswerSheetImportOperation::query()
                ->whereKey($operation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $institutionId = $this->authorization->assertOwner($actor, $locked);

            if ($locked->result_draft_id !== null || $locked->phase === AnswerSheetImportOperation::PHASE_COMPLETED) {
                if ($locked->phase !== AnswerSheetImportOperation::PHASE_COMPLETED
                    || $reviewRevision !== (int) $locked->confirmed_review_revision
                    || ! hash_equals((string) $locked->confirmation_review_token, $reviewToken)) {
                    throw ValidationException::withMessages([
                        'review' => 'This restore request no longer matches the completed review.',
                    ]);
                }

                return $locked;
            }

            if ($locked->phase === AnswerSheetImportOperation::PHASE_RESTORING) {
                if ($reviewRevision !== (int) $locked->confirmed_review_revision
                    || ! hash_equals((string) $locked->confirmation_review_token, $reviewToken)) {
                    throw ValidationException::withMessages([
                        'review' => 'This restore is already running from a different review.',
                    ]);
                }

                return $locked;
            }

            $report = (array) ($locked->report_json ?? []);
            if ($locked->phase !== AnswerSheetImportOperation::PHASE_READY_TO_RESTORE
                || $reviewRevision !== (int) $locked->review_revision
                || ! (bool) ($report['can_restore'] ?? false)
                || ! hash_equals($this->reviewTokens->forOperation($locked), $reviewToken)) {
                throw ValidationException::withMessages([
                    'review' => 'This review changed. Refresh the report before confirming the restore.',
                ]);
            }

            $locked->forceFill([
                'phase' => AnswerSheetImportOperation::PHASE_RESTORING,
                'revision' => (int) $locked->revision + 1,
                'generation' => (int) $locked->generation + 1,
                'confirmed_by_user_id' => $actor->getKey(),
                'confirmed_review_revision' => $reviewRevision,
                'confirmation_review_token' => $reviewToken,
                'confirmed_at' => now(),
                'progress_done' => 0,
                'progress_total' => 0,
                'progress_unit' => 'archive_members',
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
                'safe_error' => null,
                'error_code' => null,
            ])->save();

            $this->recordAuditEvent->handle('omr.answer_sheet_import_restore_confirmed', [
                'operation_id' => $locked->id,
                'institution_id' => $institutionId,
                'review_revision' => $reviewRevision,
                'archive_sha256' => $locked->archive_sha256,
            ], (string) $actor->getKey());

            RestoreAnswerSheetImportJob::dispatch((string) $locked->id, (int) $locked->generation)->afterCommit();

            return $locked->fresh();
        }, attempts: 3);
    }
}
