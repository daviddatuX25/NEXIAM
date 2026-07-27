<?php

namespace App\Http\Controllers\Grading;

use App\Http\Controllers\Controller;
use App\Models\AptitudeArea;
use App\Models\GradingSession;
use App\Services\ApplicationPipelineService;
use App\Services\AuditService;
use App\Services\GradingSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradingSessionController extends Controller
{
    public function __construct(
        private GradingSessionService $gradingService
    ) {}

    public function show(GradingSession $grading_session): Response
    {
        $session = $grading_session->load(['examSession.room', 'applicants.application']);

        $domainsTotal = AptitudeArea::where('is_active', true)->count();
        $scoresByApplicant = $session->applicantScores()
            ->select('applicant_id')
            ->selectRaw('COUNT(DISTINCT aptitude_area_id) as domains_complete')
            ->groupBy('applicant_id')
            ->pluck('domains_complete', 'applicant_id');
        $applicants = $session->applicants->map(function ($a) use ($domainsTotal, $scoresByApplicant) {
            $scoresCount = $scoresByApplicant[$a->id] ?? 0;
            $scored = $domainsTotal > 0 && $scoresCount >= $domainsTotal;

            return [
                'id' => $a->id,
                'applicant_id' => $a->id,
                'name' => $a->application ? trim(implode(' ', array_filter([$a->application->first_name, $a->application->middle_name, $a->application->last_name, $a->application->suffix]))) : '—',
                'reference' => $a->application?->reference_number ?? '—',
                'scored' => $scored,
                'domains_complete' => $scoresCount,
                'printed' => (bool) $a->pivot->result_printed_at,
            ];
        });

        return Inertia::render('Grading/Session', [
            'sessionId' => (string) $session->id,
            'session' => [
                'id' => $session->id,
                'exam_session_id' => $session->examSession?->id,
                'exam_date' => $session->examSession?->date?->format('Y-m-d'),
                'exam_time' => $session->examSession?->start_time,
                'room_name' => $session->examSession?->room?->name ?? '—',
                'exam_session_type' => $session->examSession?->type ?? 'scheduled',
                'opened_at' => $session->opened_at?->toIso8601String(),
            ],
            'applicants' => $applicants->values()->all(),
            'workflowStatus' => $session->workflowStatus(),
        ]);
    }

    public function updateWorkflowStatus(Request $request, GradingSession $grading_session): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:in_progress,finalized']])['status'];
        $backendStatus = $status === 'finalized' ? GradingSession::STATUS_FINALIZED : GradingSession::STATUS_IN_PROGRESS;
        $this->gradingService->updateWorkflowStatus($grading_session, $backendStatus);

        if ($backendStatus === GradingSession::STATUS_FINALIZED) {
            app(AuditService::class)->log('grading_session.finalized', GradingSession::class, $grading_session->id, [
                'status' => GradingSession::STATUS_IN_PROGRESS,
            ], [
                'status' => GradingSession::STATUS_FINALIZED,
                'actor_id' => $request->user()->id,
            ], "Grading session {$grading_session->id} finalized");

            // Pipeline hook: mark all applicants in this session as graded
            $pipeline = app(ApplicationPipelineService::class);
            $grading_session->load('applicants.application');
            foreach ($grading_session->applicants as $applicant) {
                if ($applicant->application) {
                    $pipeline->transition($applicant->application, 'graded', [
                        'grading_session_id' => $grading_session->id,
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Status updated.');
    }

}
