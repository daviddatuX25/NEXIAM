<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\GradingRoster;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinalizeGradingSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    /**
     * Finalize a grading session once every eligible roster attempt has a score
     * record for every active aptitude area (REQ-VS-009). Version-bumped and
     * immutable. Absent/not-submitted attempts are outside the roster (GRE-4).
     */
    public function handle(GradingSession $gradingSession, User $actor, int $expectedVersion): GradingSession
    {
        return DB::transaction(function () use ($gradingSession, $actor, $expectedVersion): GradingSession {
            $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);

            if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                return $gradingSession->fresh();
            }

            if ((int) $gradingSession->version !== $expectedVersion) {
                throw new RuntimeException('Stale grading session version.');
            }

            $rosterAttemptIds = GradingRoster::queryFor($gradingSession)->pluck('admission_attempt_id');

            if ($rosterAttemptIds->isEmpty()) {
                throw new RuntimeException('No applicants are assigned to this session; nothing to finalize.');
            }

            $requiredAreas = AptitudeArea::query()->where('is_active', true)->pluck('id');

            $recorded = $gradingSession->scoreRecords()
                ->whereIn('admission_attempt_id', $rosterAttemptIds)
                ->get(['admission_attempt_id', 'aptitude_area_id']);

            $recordedByAttempt = $recorded->groupBy('admission_attempt_id');

            foreach ($rosterAttemptIds as $attemptId) {
                $recordedAreas = $recordedByAttempt->get($attemptId, collect())->pluck('aptitude_area_id');

                foreach ($requiredAreas as $areaId) {
                    if (! $recordedAreas->contains($areaId)) {
                        throw new RuntimeException('Incomplete scores: every applicant must be scored for every aptitude area before finalizing.');
                    }
                }
            }

            $gradingSession->update([
                'status' => GradingSession::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by_user_id' => $actor->id,
                'version' => $gradingSession->version + 1,
            ]);

            $this->recordAuditEvent->handle('grading.session_finalized', [
                'grading_session_id' => $gradingSession->id,
                'exam_session_id' => $gradingSession->exam_session_id,
            ], (string) $actor->id);

            return $gradingSession->fresh();
        });
    }
}
