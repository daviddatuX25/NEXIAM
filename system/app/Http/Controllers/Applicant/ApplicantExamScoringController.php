<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantExamScoringController extends Controller
{
    public function show(Request $request): Response
    {
        $identityId = ApplicantScope::identityIdForUser($request->user());
        $attempt = $identityId === null
            ? null
            : AdmissionAttempt::query()
                ->select(['id', 'applicant_identity_id'])
                ->where('applicant_identity_id', $identityId)
                ->latest('id')
                ->first();

        if ($attempt === null) {
            return Inertia::render('Applicant/ExamScoring', [
                'page_state' => 'denied',
                'scoring' => ['status' => 'not_started', 'recordings' => [], 'finalization' => null],
            ]);
        }

        $assignment = ExamAssignment::query()
            ->select(['id', 'exam_session_id', 'admission_attempt_id', 'status'])
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        $administration = $assignment === null
            ? null
            : AssessmentAdministration::query()
                ->select(['id', 'exam_assignment_id', 'admission_attempt_id', 'binding_revision'])
                ->where('exam_assignment_id', $assignment->id)
                ->where('admission_attempt_id', $attempt->id)
                ->active()
                ->latest('binding_revision')
                ->first();

        if ($administration !== null) {
            return Inertia::render('Applicant/ExamScoring', [
                'page_state' => 'ready',
                'scoring' => $this->administrationScoring($administration, $assignment),
            ]);
        }

        $gradingSession = $assignment === null
            ? null
            : GradingSession::query()
                ->select(['id', 'exam_session_id', 'status', 'finalized_by_user_id', 'finalized_at'])
                ->where('exam_session_id', $assignment->exam_session_id)
                ->first();

        return Inertia::render('Applicant/ExamScoring', [
            'page_state' => 'ready',
            'scoring' => $this->legacyScoring($gradingSession, $attempt),
        ]);
    }

    /** @return array{status: string, recordings: list<array{recorded_by: string|null, recorded_at: string|null}>, finalization: array{finalized_by: string|null, finalized_at: string|null}|null} */
    private function administrationScoring(AssessmentAdministration $administration, ExamAssignment $assignment): array
    {
        $currentRunIds = AssessmentRawResultRevision::query()
            ->select(['id', 'scoring_run_id'])
            ->where('administration_id', $administration->id)
            ->where('is_current', true)
            ->distinct()
            ->pluck('scoring_run_id');
        $hasCurrentRevision = $currentRunIds->isNotEmpty();
        $runs = AssessmentScoringRun::query()
            ->select(['id', 'administration_id', 'actor_user_id', 'created_at'])
            ->with('actor:id,name')
            ->where('administration_id', $administration->id)
            ->whereIn('id', $currentRunIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $recordings = $runs
            ->unique(fn (AssessmentScoringRun $run): string => ($run->actor_user_id ?? 'unknown').'|'.$run->created_at?->format('U.u'))
            ->map(static fn (AssessmentScoringRun $run): array => [
                'recorded_by' => $run->actor?->name,
                'recorded_at' => $run->created_at?->toIso8601String(),
            ])->values()->all();

        $gradingSessionId = GradingSession::query()
            ->where('exam_session_id', $assignment->exam_session_id)
            ->value('id');
        $resultSet = $gradingSessionId === null
            ? null
            : AssessmentResultSet::query()
                ->select(['id', 'administration_id', 'grading_session_id', 'status', 'finalized_by_user_id', 'finalized_at'])
                ->where('administration_id', $administration->id)
                ->where('grading_session_id', $gradingSessionId)
                ->where('status', AssessmentResultSet::STATUS_FINALIZED)
                ->latest('id')
                ->first();
        $finalization = null;
        if ($resultSet !== null) {
            $finalization = [
                'finalized_by' => $resultSet->finalized_by_user_id === null
                    ? null
                    : User::query()->whereKey($resultSet->finalized_by_user_id)->value('name'),
                'finalized_at' => $resultSet->finalized_at?->toIso8601String(),
            ];
        }

        return [
            'status' => $finalization !== null
                ? 'finalized'
                : ($hasCurrentRevision ? 'in_progress' : 'not_started'),
            'recordings' => $recordings,
            'finalization' => $finalization,
        ];
    }

    /** @return array{status: string, recordings: list<array{recorded_by: string|null, recorded_at: string|null}>, finalization: array{finalized_by: string|null, finalized_at: string|null}|null} */
    private function legacyScoring(?GradingSession $gradingSession, AdmissionAttempt $attempt): array
    {
        if ($gradingSession === null) {
            return ['status' => 'not_started', 'recordings' => [], 'finalization' => null];
        }

        $scores = ScoreRecord::query()
            ->select(['id', 'recorded_by_user_id', 'updated_at'])
            ->with('recordedBy:id,name')
            ->where('grading_session_id', $gradingSession->id)
            ->where('admission_attempt_id', $attempt->id)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();
        $recordings = $scores
            ->unique(fn (ScoreRecord $score): string => ($score->recorded_by_user_id ?? 'unknown').'|'.$score->updated_at?->format('U.u'))
            ->map(static fn (ScoreRecord $score): array => [
                'recorded_by' => $score->recordedBy?->name,
                'recorded_at' => $score->updated_at?->toIso8601String(),
            ])->values()->all();

        $isFinalized = $gradingSession->status === GradingSession::STATUS_FINALIZED;
        $finalization = $isFinalized ? [
            'finalized_by' => User::query()
                ->whereKey($gradingSession->finalized_by_user_id)
                ->value('name'),
            'finalized_at' => $gradingSession->finalized_at?->toIso8601String(),
        ] : null;

        return [
            'status' => $isFinalized ? 'finalized' : ($scores->isNotEmpty() ? 'in_progress' : 'not_started'),
            'recordings' => $recordings,
            'finalization' => $finalization,
        ];
    }
}
