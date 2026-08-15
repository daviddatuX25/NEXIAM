<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\ScoreConverter;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordScores
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ScoreConverter $scoreConverter,
    ) {}

    /**
     * Transactionally upsert scores for many aptitude areas for one attempt
     * in a grading session (REQ-VS-021 manual part).
     *
     * Conversion (SCV-3): every score is resolved through its area's
     * conversion table and the output is frozen on `converted_score`. A raw
     * score with no covering row is rejected (SCV-4).
     *
     * Idempotency: each domain row is upserted on the unique triple
     * (grading_session_id, admission_attempt_id, aptitude_area_id) with a
     * deterministic per-domain key `{key}:{attempt}:{area}`, so replaying the
     * same request is a no-op (same values, no new rows).
     *
     * @param  array<int, array{aptitude_area_id: int, score: float|string}>  $scores
     * @return list<ScoreRecord>
     */
    public function handle(
        GradingSession $gradingSession,
        AdmissionAttempt $attempt,
        array $scores,
        User $actor,
        string $idempotencyKey,
    ): array {
        return DB::transaction(function () use ($gradingSession, $attempt, $scores, $actor, $idempotencyKey): array {
            $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);

            if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                throw new RuntimeException('Grading session is finalized.');
            }

            $inRoster = GradingRoster::includesAttempt($gradingSession, (int) $attempt->id);

            if (! $inRoster) {
                throw new RuntimeException('Attempt is not part of this grading session.');
            }

            $records = [];

            foreach ($scores as $entry) {
                $areaId = (int) $entry['aptitude_area_id'];
                $area = AptitudeArea::query()->find($areaId);

                $converted = null;
                if ($area !== null) {
                    $converted = $this->scoreConverter->convert($area, (int) $entry['score']);

                    if ($converted === null) {
                        throw new RuntimeException(
                            "Score {$entry['score']} for {$area->code} is outside its conversion table.",
                        );
                    }
                }

                $record = ScoreRecord::query()->updateOrCreate(
                    [
                        'grading_session_id' => $gradingSession->id,
                        'admission_attempt_id' => $attempt->id,
                        'aptitude_area_id' => $areaId,
                    ],
                    [
                        'score' => number_format((float) $entry['score'], 2, '.', ''),
                        'converted_score' => $converted,
                        'recorded_by_user_id' => $actor->id,
                        'idempotency_key' => $idempotencyKey.':'.$attempt->id.':'.$areaId,
                    ],
                );

                $this->recordAuditEvent->handle('grading.score_recorded', [
                    'score_record_id' => $record->id,
                    'attempt_id' => $attempt->id,
                    'area_id' => $areaId,
                    'area_code' => $area?->code,
                ], (string) $actor->id);

                $records[] = $record;
            }

            return $records;
        });
    }
}
