<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Queries\OmrRunStatusSnapshot;
use App\Assessment\Omr\Review\GradingOmrEffectiveAnswerProjector;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Staff review of one sheet: confirm derived scores from server-projected
 * effective answers or abstain with a required reason (OMR-4).
 * Client aggregate score overrides and issue bypasses are rejected.
 */
final class ReviewOmrCaptureSheet
{
    private readonly GradingOmrEffectiveAnswerProjector $projector;

    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        ?GradingOmrEffectiveAnswerProjector $projector = null,
    ) {
        $this->projector = $projector ?? app(GradingOmrEffectiveAnswerProjector::class);
    }

    public function handle(
        OmrCaptureSheet $sheet,
        User $actor,
        ?string $abstainReason = null,
        ?int $_admissionAttemptId = null,
        ?int $expectedRevision = null,
    ): OmrCaptureSheet {
        return DB::transaction(function () use ($sheet, $actor, $abstainReason, $expectedRevision): OmrCaptureSheet {
            $batch = OmrCaptureBatch::query()
                ->lockForUpdate()
                ->findOrFail($sheet->batch_id);
            $sheet = OmrCaptureSheet::query()->lockForUpdate()->findOrFail($sheet->id);
            $sheet->setRelation('batch', $batch);
            $sheet->loadMissing('batch.gradingSession');
            $this->assertExpectedRevision($batch, $expectedRevision);

            if ($sheet->batch?->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Sheets can only be reviewed while the batch is open.');
            }

            if (! in_array($sheet->status, [OmrCaptureSheet::STATUS_NEEDS_REVIEW, OmrCaptureSheet::STATUS_REVIEWED], true)) {
                throw new RuntimeException('This OMR sheet is not ready for review.');
            }

            if ($sheet->status === OmrCaptureSheet::STATUS_REVIEWED && $abstainReason === null) {
                return $sheet;
            }

            $openRequiredIssues = $sheet->issues()
                ->where('state', 'open')
                ->whereIn('severity', ['required', 'terminal'])
                ->get();
            $abstained = $abstainReason !== null;
            $reason = $abstained ? trim($abstainReason) : null;

            if ($abstained && $reason === '') {
                throw new RuntimeException('An abstention reason is required.');
            }

            if ($abstained && $openRequiredIssues->isNotEmpty()) {
                foreach ($openRequiredIssues as $issue) {
                    $issue->update([
                        'state' => 'resolved',
                        'resolution_code' => 'abstained',
                        'resolution_reason' => $reason,
                        'resolved_by_user_id' => $actor->id,
                        'resolved_at' => now(),
                        'version' => (int) $issue->version + 1,
                    ]);
                }
            }

            $projection = $this->projector->project($sheet);

            $reviewedJson = $abstained
                ? [
                    'abstained' => true,
                    'reason' => $reason,
                    'per_area_scores' => [],
                    'provenance' => $projection['provenance'],
                    'is_complete' => false,
                    'unresolved_item_keys' => $projection['unresolved_item_keys'],
                    'stale_item_keys' => $projection['stale_item_keys'],
                ]
                : [
                    'abstained' => false,
                    'reason' => null,
                    'per_area_scores' => $projection['per_area_scores'],
                    'provenance' => $projection['provenance'],
                    'is_complete' => $projection['is_complete'],
                    'unresolved_item_keys' => $projection['unresolved_item_keys'],
                    'stale_item_keys' => $projection['stale_item_keys'],
                ];

            $sheet->update([
                'admission_attempt_id' => $projection['provenance']['admission_attempt_id'],
                'booklet_set_id' => $projection['provenance']['booklet_set_id'],
                'set_resolution_reason' => 'submission_frozen_seat',
                'status' => OmrCaptureSheet::STATUS_REVIEWED,
                'review_draft_json' => [
                    'is_complete' => $projection['is_complete'],
                    'has_unresolved' => $projection['has_unresolved'],
                    'has_stale' => $projection['has_stale'],
                    'per_area_scores' => $projection['per_area_scores'],
                    'provenance' => $projection['provenance'],
                    'unresolved_item_keys' => $projection['unresolved_item_keys'],
                    'stale_item_keys' => $projection['stale_item_keys'],
                    'regenerated_at' => now()->toIso8601String(),
                ],
                'reviewed_json' => $reviewedJson,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'version' => (int) $sheet->version + 1,
            ]);
            $this->recordAuditEvent->handle(
                $abstained ? 'grading.omr_sheet_abstained' : 'grading.omr_sheet_reviewed',
                [
                    'sheet_id' => $sheet->id,
                    'batch_id' => $sheet->batch_id,
                    'attempt_id' => $sheet->admission_attempt_id,
                    'reason_present' => $reason !== null,
                ],
                (string) $actor->id,
            );

            return $sheet->fresh(['batch', 'admissionAttempt']) ?? $sheet;
        });
    }

    private function assertExpectedRevision(OmrCaptureBatch $batch, ?int $expectedRevision): void
    {
        if ($expectedRevision === null) {
            return;
        }

        $revision = app(OmrRunStatusSnapshot::class)
            ->forBatch($batch->load(['sheets.pages', 'sheets.issues', 'sheets.processingRuns']))['revision'];

        if ($revision !== $expectedRevision) {
            throw new RuntimeException('The OMR run changed after validation. Refresh the current status before continuing.');
        }
    }
}
