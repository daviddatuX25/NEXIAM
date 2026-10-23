<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\AssessmentAdministrationFinalizationPredicate;
use App\Assessment\Support\GradingRoster;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Scheduling\Actions\AutoAssignEligibleAttempt;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FinalizeGradingSession
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AutoAssignEligibleAttempt $autoAssign,
        private readonly AssessmentAdministrationFinalizationPredicate $predicate,
        private readonly FreezeAssessmentResultSet $freezeAssessmentResultSet,
    ) {}

    /**
     * Finalize a grading session once every eligible roster attempt has a score
     * record for every active aptitude area (REQ-VS-009). Version-bumped and
     * immutable. Absent/not-submitted attempts are outside the roster (GRE-4).
     */
    public function handle(GradingSession $gradingSession, User $actor, ?int $expectedVersion = null): GradingSession
    {
        $academicCycleId = (int) $gradingSession->examSession()->value('academic_cycle_id');
        $finalized = $this->planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            $academicCycleId,
            function () use ($gradingSession, $actor, $expectedVersion): GradingSession {
                $gradingSession = GradingSession::query()
                    ->with('examSession.academicCycle')
                    ->lockForUpdate()
                    ->findOrFail($gradingSession->id);
                $freshActor = User::query()->with('staffProfile')->findOrFail($actor->id);
                $actorInstitutionId = (int) ($freshActor->staffProfile?->institution_id ?? Institution::current()->id);

                if ((int) $gradingSession->examSession?->academicCycle?->institution_id !== $actorInstitutionId) {
                    throw new RuntimeException('Grading session is outside the actor institution scope.');
                }

                if ($expectedVersion !== null && $expectedVersion < 1) {
                    throw new RuntimeException('Expected grading session version must be at least 1.');
                }

                if ($gradingSession->status === GradingSession::STATUS_FINALIZED) {
                    return $gradingSession->fresh();
                }

                if ($expectedVersion !== null && (int) $gradingSession->version !== $expectedVersion) {
                    throw new RuntimeException('Stale grading session version.');
                }
                $eligibleAssignments = GradingRoster::queryFor($gradingSession)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $assignmentIds = $eligibleAssignments->pluck('id')->filter()->all();
                $activeAdministrations = AssessmentAdministration::query()
                    ->whereIn('exam_assignment_id', $assignmentIds)
                    ->active()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $predicateResult = $this->predicate->evaluate($gradingSession);
                if (! $predicateResult['ok']) {
                    throw new RuntimeException($predicateResult['reason'] ?? 'Grading session cannot be finalized.');
                }

                $now = now();
                $gradingSession->update([
                    'status' => GradingSession::STATUS_FINALIZED,
                    'finalized_at' => $now,
                    'finalized_by_user_id' => $actor->id,
                    'version' => $gradingSession->version + 1,
                ]);

                foreach ($activeAdministrations as $administration) {
                    $this->freezeAssessmentResultSet->handle($administration, $gradingSession, $actor);
                }

                $this->recordAuditEvent->handle('grading.session_finalized', [
                    'grading_session_id' => $gradingSession->id,
                    'exam_session_id' => $gradingSession->exam_session_id,
                ], (string) $actor->id);

                return $gradingSession->fresh();
            },
        );

        DB::afterCommit(function () use ($finalized, $actor): void {
            $rosterAttempts = AdmissionAttempt::query()
                ->whereIn('id', GradingRoster::queryFor($finalized)->pluck('admission_attempt_id'))
                ->get();

            foreach ($rosterAttempts as $rosterAttempt) {
                try {
                    $this->autoAssign->handle(AssignmentInvariant::DOMAIN_CONSULTATION, $rosterAttempt, $actor);
                } catch (\Throwable $exception) {
                    Log::error('Consultation auto-assign post-grading finalization failed: '.$exception->getMessage(), ['exception' => $exception]);
                }
            }
        });

        return $finalized;
    }

    public function execute(User $actor, GradingSession $session, int $expectedVersion): GradingSession
    {
        if ($expectedVersion < 1) {
            throw new RuntimeException('Expected grading session version must be at least 1.');
        }

        return $this->handle($session, $actor, $expectedVersion);
    }
}
