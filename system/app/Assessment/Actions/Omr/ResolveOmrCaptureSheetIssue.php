<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheetIssue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ResolveOmrCaptureSheetIssue
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        OmrCaptureSheetIssue $issue,
        User $actor,
        string $resolutionCode,
        string $reason,
    ): OmrCaptureSheetIssue {
        return DB::transaction(function () use ($issue, $actor, $resolutionCode, $reason): OmrCaptureSheetIssue {
            $issue = OmrCaptureSheetIssue::query()->lockForUpdate()->findOrFail($issue->id);
            $issue->loadMissing('sheet.batch');

            if ($issue->sheet?->batch?->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('OMR issues can only be resolved while the batch is open.');
            }

            if ($issue->state !== 'open') {
                return $issue;
            }
            $isItemIssue = ($issue->question_no !== null && ! in_array($issue->issue_code, ['identity_conflict', 'identity_unresolved', 'birthdate_invalid', 'sheet_misaligned'], true))
                || in_array($issue->issue_code, ['ambiguous_mark', 'unreadable', 'blank_mark', 'low_confidence', 'machine_uncertain'], true);

            if ($isItemIssue) {
                throw new RuntimeException('Item-level issues must be resolved through item review.');
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw new RuntimeException('A resolution reason is required.');
            }

            if (! in_array($resolutionCode, ['manual_review', 'abstained'], true)) {
                throw new RuntimeException('Unsupported OMR issue resolution.');
            }

            $issue->update([
                'state' => 'resolved',
                'resolution_code' => $resolutionCode,
                'resolution_reason' => $reason,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'version' => (int) $issue->version + 1,
            ]);

            $this->recordAuditEvent->handle('grading.omr_issue_resolved', [
                'issue_id' => $issue->id,
                'sheet_id' => $issue->sheet_id,
                'resolution_code' => $resolutionCode,
            ], (string) $actor->id);

            return $issue->fresh(['sheet']) ?? $issue;
        });
    }
}
