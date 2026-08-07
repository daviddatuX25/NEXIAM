<?php

namespace App\Assessment\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordManualScore
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        GradingSession $gradingSession,
        AdmissionAttempt $attempt,
        AptitudeArea $area,
        User $actor,
        string $score,
        string $idempotencyKey,
    ): ScoreRecord {
        $existing = ScoreRecord::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($gradingSession, $attempt, $area, $actor, $score, $idempotencyKey): ScoreRecord {
            if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                throw new \RuntimeException('Grading session is finalized.');
            }

            $record = ScoreRecord::query()->create([
                'grading_session_id' => $gradingSession->id,
                'admission_attempt_id' => $attempt->id,
                'aptitude_area_id' => $area->id,
                'score' => $score,
                'recorded_by_user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->recordAuditEvent->handle('grading.score_recorded', [
                'score_record_id' => $record->id,
                'attempt_id' => $attempt->id,
                'area_id' => $area->id,
            ], (string) $actor->id);

            return $record;
        });
    }
}
