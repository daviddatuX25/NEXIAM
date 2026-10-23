<?php

namespace App\Assessment\Actions\Omr;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ResolveOmrDuplicateDisposition
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrCaptureSheet $survivor,
        OmrCaptureSheet $rejected,
        string $reason,
        User $actor,
        int $expectedSurvivorVersion,
        int $expectedRejectedVersion,
    ): OmrCaptureSheet {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)) {
            throw new RuntimeException('You are not authorized to resolve duplicate OMR captures.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A duplicate disposition reason is required.');
        }
        if ((int) $survivor->id === (int) $rejected->id) {
            throw new RuntimeException('The surviving and rejected captures must be different sheets.');
        }

        return DB::transaction(function () use ($survivor, $rejected, $reason, $actor, $expectedSurvivorVersion, $expectedRejectedVersion): OmrCaptureSheet {
            $sheets = OmrCaptureSheet::query()
                ->whereKey([$survivor->id, $rejected->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $survivor = $sheets->get($survivor->id);
            $rejected = $sheets->get($rejected->id);

            if ($survivor === null || $rejected === null) {
                throw new RuntimeException('Both duplicate captures must exist.');
            }
            $survivor->loadMissing('batch');
            $rejected->loadMissing('batch');
            if ($survivor->batch === null
                || $rejected->batch === null
                || (int) $survivor->batch->grading_session_id !== (int) $rejected->batch->grading_session_id) {
                throw new RuntimeException('Duplicate captures must belong to the same grading session.');
            }
            $sameAttempt = $survivor->admission_attempt_id !== null
                && (int) $survivor->admission_attempt_id === (int) $rejected->admission_attempt_id;
            $survivorApplicantNumber = trim((string) $survivor->detected_applicant_number);
            $sameApplicantNumber = $survivorApplicantNumber !== ''
                && $survivorApplicantNumber === trim((string) $rejected->detected_applicant_number);
            if (! $sameAttempt && ! $sameApplicantNumber) {
                throw new RuntimeException('These captures do not resolve to the same applicant.');
            }
            if (in_array(OmrCaptureSheet::STATUS_FAILED_TERMINAL, [$survivor->status, $rejected->status], true)) {
                throw new RuntimeException('A terminal capture cannot participate in duplicate disposition.');
            }
            if ((int) $survivor->version !== $expectedSurvivorVersion
                || (int) $rejected->version !== $expectedRejectedVersion) {
                throw new RuntimeException('One of these captures changed. Refresh before resolving the duplicate.');
            }
            if ($rejected->batch->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Only a capture in an open OMR batch may be rejected.');
            }

            $rejectedAttemptId = $rejected->admission_attempt_id;
            $rejected->update([
                'status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL,
                'admission_attempt_id' => null,
                'version' => (int) $rejected->version + 1,
            ]);
            $rejected->issues()->create([
                'page_no' => 0,
                'question_no' => null,
                'issue_code' => 'duplicate_rejected',
                'severity' => 'terminal',
                'state' => 'resolved',
                'resolution_code' => 'duplicate_disposition',
                'resolution_reason' => $reason,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'evidence_json' => ['surviving_sheet_id' => $survivor->id],
                'version' => 1,
            ]);
            foreach ($survivor->issues()->where('issue_code', 'applicant_duplicate')->where('state', 'open')->get() as $issue) {
                $issue->update([
                    'state' => 'resolved',
                    'resolution_code' => 'duplicate_disposition',
                    'resolution_reason' => $reason,
                    'resolved_by_user_id' => $actor->id,
                    'resolved_at' => now(),
                    'version' => (int) $issue->version + 1,
                ]);
            }

            $this->recordAuditEvent->handle('grading.omr_duplicate_disposition_recorded', [
                'grading_session_id' => $survivor->batch->grading_session_id,
                'survivor_batch_id' => $survivor->batch_id,
                'rejected_batch_id' => $rejected->batch_id,
                'surviving_sheet_id' => $survivor->id,
                'rejected_sheet_id' => $rejected->id,
                'rejected_attempt_id' => $rejectedAttemptId,
                'survivor_evidence' => $survivor->proposal_json,
                'rejected_evidence' => $rejected->proposal_json,
                'reason' => $reason,
                'resolved_at' => now()->toIso8601String(),
            ], (string) $actor->id);

            return $survivor->fresh() ?? $survivor;
        });
    }
}
