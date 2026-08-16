<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\ScoreConverter;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\ScoreRecord;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Commit reviewed OMR sheets into score records (OMR-5): every non-abstained
 * reviewed sheet writes/upserts `score_records` with source=omr, the capture
 * sheet provenance, per-area confidence, and converted output from the live
 * conversion table. Replaying on a confirmed batch is a no-op.
 */
final class ConfirmOmrCaptureBatch
{
    public function __construct(
        private readonly ScoreConverter $scoreConverter,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrCaptureBatch $batch, User $actor): OmrCaptureBatch
    {
        return DB::transaction(function () use ($batch, $actor): OmrCaptureBatch {
            $batch = OmrCaptureBatch::query()->findOrFail($batch->id);
            $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($batch->grading_session_id);
            $batch = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status === OmrCaptureBatch::STATUS_CONFIRMED) {
                return $batch;
            }

            if ($batch->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('Only an open OMR batch can be confirmed.');
            }

            if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                throw new RuntimeException('Grading session is finalized; OMR confirm is closed.');
            }

            $sheets = $batch->sheets()->orderBy('id')->lockForUpdate()->get();

            if ($sheets->isEmpty()) {
                throw new RuntimeException('Nothing to confirm.');
            }

            $boundAttempts = [];
            foreach ($sheets as $sheet) {
                if ($sheet->admission_attempt_id === null) {
                    continue;
                }
                if (isset($boundAttempts[(int) $sheet->admission_attempt_id])) {
                    throw new RuntimeException('Two sheets are bound to the same examinee; resolve the duplicate before confirming.');
                }
                $boundAttempts[(int) $sheet->admission_attempt_id] = true;
            }

            $batch->update([
                'status' => OmrCaptureBatch::STATUS_CLOSING,
                'version' => (int) $batch->version + 1,
                'closing_by_user_id' => $actor->id,
                'closing_at' => now(),
            ]);

            $unreviewed = [];

            foreach ($sheets as $sheet) {
                /** @var OmrCaptureSheet $sheet */
                if ($sheet->status === OmrCaptureSheet::STATUS_COMMITTED) {
                    continue;
                }

                if ($sheet->status !== OmrCaptureSheet::STATUS_REVIEWED) {
                    $unreviewed[] = '#'.$sheet->id;
                }

            }

            if ($unreviewed !== []) {
                throw new RuntimeException('Sheets '.implode(', ', $unreviewed).' must be reviewed before confirming the batch.');
            }

            foreach ($sheets as $sheet) {
                /** @var OmrCaptureSheet $sheet */
                if ($sheet->status !== OmrCaptureSheet::STATUS_COMMITTED
                    && ! $sheet->wasAbstained()
                    && $sheet->hasOpenRequiredIssues()) {
                    throw new RuntimeException('Resolve required OMR issues on sheet #'.$sheet->id.' before confirming.');
                }
            }

            foreach ($sheets as $sheet) {
                /** @var OmrCaptureSheet $sheet */
                if ($sheet->status === OmrCaptureSheet::STATUS_COMMITTED) {
                    continue;
                }

                if (! $sheet->wasAbstained()) {
                    $this->writeSheet($sheet, $gradingSession, $actor);
                }

                $sheet->update([
                    'status' => OmrCaptureSheet::STATUS_COMMITTED,
                    'version' => (int) $sheet->version + 1,
                    'committed_by_user_id' => $actor->id,
                    'committed_at' => now(),
                ]);
            }

            $batch->update([
                'status' => OmrCaptureBatch::STATUS_CONFIRMED,
                'version' => (int) $batch->version + 1,
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->recordAuditEvent->handle('grading.omr_batch_confirmed', [
                'batch_id' => $batch->id,
                'grading_session_id' => $gradingSession->id,
                'sheet_count' => $sheets->count(),
            ], (string) $actor->id);

            return $batch->fresh(['sheets']) ?? $batch;
        });
    }

    private function writeSheet(OmrCaptureSheet $sheet, GradingSession $gradingSession, User $actor): void
    {
        if ($sheet->admission_attempt_id === null
            || ! GradingRoster::includesAttempt($gradingSession, (int) $sheet->admission_attempt_id)) {
            throw new RuntimeException('Bind this sheet to an eligible examinee before confirming.');
        }

        $scores = $sheet->reviewedScores();
        if ($scores === []) {
            throw new RuntimeException('The reviewed sheet has no derived scores.');
        }

        $areas = AptitudeArea::query()
            ->whereIn('id', array_map('intval', array_keys($scores)))
            ->get()
            ->keyBy('id');

        foreach ($scores as $areaId => $proposal) {
            $area = $areas->get((int) $areaId);
            if ($area === null) {
                throw new RuntimeException('Unknown aptitude area on sheet.');
            }

            $score = (int) ($proposal['score'] ?? -1);
            if ($score < 0 || $score > (int) $area->max_items) {
                throw new RuntimeException("Score for {$area->code} is outside its allowed range.");
            }

            $converted = $this->scoreConverter->convert($area, $score);
            if ($converted === null) {
                throw new RuntimeException("Score {$score} for {$area->code} is outside its conversion table.");
            }

            $record = ScoreRecord::query()
                ->where('grading_session_id', $gradingSession->id)
                ->where('admission_attempt_id', $sheet->admission_attempt_id)
                ->where('aptitude_area_id', (int) $areaId)
                ->first();

            if ($record !== null && $record->source !== ScoreRecord::SOURCE_OMR) {
                throw new RuntimeException("An existing manual score blocks OMR import for {$area->code}.");
            }

            $record ??= new ScoreRecord([
                'grading_session_id' => $gradingSession->id,
                'admission_attempt_id' => $sheet->admission_attempt_id,
                'aptitude_area_id' => (int) $areaId,
            ]);
            $record->fill([
                'score' => number_format($score, 2, '.', ''),
                'converted_score' => $converted,
                'source' => ScoreRecord::SOURCE_OMR,
                'capture_sheet_id' => $sheet->id,
                'confidence' => array_key_exists('confidence', $proposal) && is_numeric($proposal['confidence'])
                    ? (float) $proposal['confidence']
                    : null,
                'recorded_by_user_id' => $actor->id,
                'idempotency_key' => 'omr:'.$sheet->batch_id.':'.$sheet->admission_attempt_id.':'.$areaId,
            ]);
            $record->save();

            $this->recordAuditEvent->handle('grading.omr_score_recorded', [
                'score_record_id' => $record->id,
                'sheet_id' => $sheet->id,
                'attempt_id' => $sheet->admission_attempt_id,
                'area_id' => (int) $areaId,
                'area_code' => $area->code,
            ], (string) $actor->id);
        }
    }
}
