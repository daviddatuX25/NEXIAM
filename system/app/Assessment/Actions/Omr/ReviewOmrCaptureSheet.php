<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Support\GradingRoster;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Staff review of one sheet: confirm (optionally adjusting per-area raw
 * scores) or abstain with a required reason (OMR-4). Scores are validated to
 * [0, max_items]; conversion coverage is enforced at batch confirm.
 */
final class ReviewOmrCaptureSheet
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    /**
     * @param  array<int, array{aptitude_area_id: int, score: float|int|null}>  $adjustedScores
     */
    public function handle(
        OmrCaptureSheet $sheet,
        User $actor,
        ?string $abstainReason = null,
        array $adjustedScores = [],
        ?int $admissionAttemptId = null,
    ): OmrCaptureSheet {
        return DB::transaction(function () use ($sheet, $actor, $abstainReason, $adjustedScores, $admissionAttemptId): OmrCaptureSheet {
            $sheet = OmrCaptureSheet::query()->lockForUpdate()->findOrFail($sheet->id);
            $sheet->loadMissing('batch.gradingSession');

            if ($sheet->batch?->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Sheets can only be reviewed while the batch is open.');
            }

            if ($admissionAttemptId !== null
                && ! GradingRoster::includesAttempt($sheet->batch->gradingSession, $admissionAttemptId)) {
                throw new RuntimeException('Selected examinee is not part of this grading roster.');
            }

            if ($admissionAttemptId !== null
                && $sheet->batch->sheets()
                    ->where('admission_attempt_id', $admissionAttemptId)
                    ->whereKeyNot($sheet->id)
                    ->exists()) {
                throw new RuntimeException('This examinee is already bound to another sheet in this batch.');
            }

            if (! in_array($sheet->status, [OmrCaptureSheet::STATUS_NEEDS_REVIEW, OmrCaptureSheet::STATUS_REVIEWED], true)) {
                throw new RuntimeException('This OMR sheet is not ready for review.');
            }

            if ($sheet->status === OmrCaptureSheet::STATUS_REVIEWED) {
                return $sheet;
            }

            $proposals = $sheet->proposedScores();
            $areas = AptitudeArea::query()->where('is_active', true)->get()->keyBy('id');

            foreach ($adjustedScores as $entry) {
                $areaId = (int) ($entry['aptitude_area_id'] ?? 0);
                $area = $areas->get($areaId);
                $score = $entry['score'] ?? null;

                if ($area === null) {
                    throw new RuntimeException('Unknown aptitude area.');
                }

                if ($score === null || $score === '' || (float) $score < 0 || (float) $score > (int) $area->max_items) {
                    throw new RuntimeException("Score for {$area->code} must be between 0 and {$area->max_items}.");
                }

                $proposals[$areaId] = [
                    'score' => (float) $score,
                    'confidence' => 1.0,
                ];
            }

            $abstained = $abstainReason !== null;
            $reason = $abstained ? trim($abstainReason) : null;

            if ($abstained && $reason === '') {
                throw new RuntimeException('An abstention reason is required.');
            }

            $sheet->update([
                'admission_attempt_id' => $admissionAttemptId ?? $sheet->admission_attempt_id,
                'status' => OmrCaptureSheet::STATUS_REVIEWED,
                'reviewed_json' => [
                    'abstained' => $abstained,
                    'reason' => $reason,
                    'per_area_scores' => $proposals,
                ],
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
}
