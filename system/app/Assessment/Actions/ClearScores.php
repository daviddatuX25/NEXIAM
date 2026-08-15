<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\GradingRoster;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClearScores
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    /**
     * Clear every domain score for one attempt in an open grading session (GS-4).
     *
     * Finalized sessions are immutable; attempts outside the roster are rejected.
     */
    public function handle(GradingSession $gradingSession, AdmissionAttempt $attempt, User $actor): void
    {
        DB::transaction(function () use ($gradingSession, $attempt, $actor): void {
            $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);

            if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                throw new RuntimeException('Grading session is finalized.');
            }

            $inRoster = GradingRoster::includesAttempt($gradingSession, (int) $attempt->id);

            if (! $inRoster) {
                throw new RuntimeException('Attempt is not part of this grading session.');
            }

            $cleared = $gradingSession->scoreRecords()
                ->where('admission_attempt_id', $attempt->id)
                ->delete();

            $this->recordAuditEvent->handle('grading.scores_cleared', [
                'grading_session_id' => $gradingSession->id,
                'attempt_id' => $attempt->id,
                'records_cleared' => $cleared,
            ], (string) $actor->id);
        });
    }
}
