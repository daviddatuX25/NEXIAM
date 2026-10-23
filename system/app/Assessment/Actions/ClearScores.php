<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\GradingRoster;
use App\Assessment\Support\IndividualResultMutationFence;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClearScores
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly IndividualResultMutationFence $mutationFence,
    ) {}

    /**
     * Clear every domain score for one attempt in an open grading session (GS-4).
     *
     * Finalized sessions are immutable; attempts outside the roster are rejected.
     */
    public function handle(GradingSession $gradingSession, AdmissionAttempt $attempt, User $actor): void
    {
        $academicCycleId = (int) $gradingSession->examSession()->value('academic_cycle_id');
        $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            $academicCycleId,
            function () use ($gradingSession, $attempt, $actor): void {
                DB::transaction(function () use ($gradingSession, $attempt, $actor): void {
                    $gradingSession = GradingSession::query()->lockForUpdate()->findOrFail($gradingSession->id);

                    if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                        throw new RuntimeException('Grading session is finalized.');
                    }
                    if (! GradingRoster::includesAttempt($gradingSession, (int) $attempt->id)) {
                        throw new RuntimeException('Attempt is not part of this grading session.');
                    }

                    $assignment = ExamAssignment::query()
                        ->where('exam_session_id', $gradingSession->exam_session_id)
                        ->where('admission_attempt_id', $attempt->id)
                        ->active()
                        ->first();
                    $administration = $assignment
                        ? AssessmentAdministration::query()->where('exam_assignment_id', $assignment->id)->active()->lockForUpdate()->first()
                        : null;

                    if ($administration !== null) {
                        $now = now();
                        $this->mutationFence->assertMutable($administration);
                        AssessmentRawResultRevision::query()
                            ->where('administration_id', $administration->id)
                            ->where('is_current', true)
                            ->update(['is_current' => false, 'superseded_at' => $now]);
                        AssessmentConversionRevision::query()
                            ->where('administration_id', $administration->id)
                            ->where('is_current', true)
                            ->update(['is_current' => false, 'superseded_at' => $now]);
                        $administration->update(['binding_state' => AssessmentAdministration::STATE_RAW_INCOMPLETE]);
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
            },
        );
    }
}
