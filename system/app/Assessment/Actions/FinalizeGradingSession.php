<?php

namespace App\Assessment\Actions;

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

    public function handle(GradingSession $gradingSession, User $actor, int $expectedVersion): GradingSession
    {
        return DB::transaction(function () use ($gradingSession, $actor, $expectedVersion): GradingSession {
            $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);

            if ((int) $gradingSession->version !== $expectedVersion) {
                throw new RuntimeException('Stale grading session version.');
            }

            $requiredAreas = AptitudeArea::query()->where('is_active', true)->pluck('id');
            $attemptIds = $gradingSession->scoreRecords()->distinct()->pluck('admission_attempt_id');

            foreach ($attemptIds as $attemptId) {
                $recorded = $gradingSession->scoreRecords()
                    ->where('admission_attempt_id', $attemptId)
                    ->pluck('aptitude_area_id');

                foreach ($requiredAreas as $areaId) {
                    if (! $recorded->contains($areaId)) {
                        throw new RuntimeException('Incomplete scores for attempt '.$attemptId);
                    }
                }
            }

            $gradingSession->update([
                'status' => GradingSession::STATUS_FINALIZED,
                'finalized_at' => now(),
                'version' => $gradingSession->version + 1,
            ]);

            $this->recordAuditEvent->handle('grading.session_finalized', [
                'grading_session_id' => $gradingSession->id,
            ], (string) $actor->id);

            return $gradingSession->fresh();
        });
    }
}
