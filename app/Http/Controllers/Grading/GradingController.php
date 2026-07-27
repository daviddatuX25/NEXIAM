<?php

namespace App\Http\Controllers\Grading;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGradingSessionRequest;
use App\Models\AcademicYear;
use App\Models\AptitudeArea;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Services\GradingSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradingController extends Controller
{
    public function __construct(
        private GradingSessionService $gradingService
    ) {}

    public function index(Request $request): Response
    {
        $activeAcademicYear = AcademicYear::active();

        $activeAreaCount = AptitudeArea::where('is_active', true)->count();

        $gradingQuery = GradingSession::query()
            ->with(['examSession.room', 'examSession.academicYear'])
            ->withCount('applicants')
            ->orderByDesc('opened_at');
        if ($activeAcademicYear !== null) {
            $gradingQuery->whereHas('examSession', fn ($q) => $q->forAcademicYear($activeAcademicYear));
        }
        $gradingSessions = $gradingQuery->get()
            ->map(function (GradingSession $gs) use ($activeAreaCount) {
                $scoresByApplicant = $gs->applicantScores()
                    ->select('applicant_id')
                    ->selectRaw('COUNT(DISTINCT aptitude_area_id) as domains_complete')
                    ->groupBy('applicant_id')
                    ->pluck('domains_complete', 'applicant_id');

                $applicantsScored = $activeAreaCount > 0
                    ? $scoresByApplicant->filter(fn ($count) => $count >= $activeAreaCount)->count()
                    : 0;

                return [
                    'id' => $gs->id,
                    'exam_session_id' => $gs->exam_session_id,
                    'exam_date' => $gs->examSession?->date?->format('Y-m-d'),
                    'exam_time' => $gs->examSession?->start_time,
                    'room_name' => $gs->examSession?->room?->name ?? '—',
                    'exam_session_type' => $gs->examSession?->type ?? 'scheduled',
                    'status' => $gs->status,
                    'opened_at' => $gs->opened_at?->toIso8601String(),
                    'finalized_at' => $gs->finalized_at?->toIso8601String(),
                    'applicants_total' => $gs->applicants_count ?? 0,
                    'applicants_scored' => $applicantsScored,
                ];
            });

        return Inertia::render('Grading/Dashboard', [
            'title' => 'Grading',
            'description' => 'Input and manage exam scores.',
            'grading_sessions' => $gradingSessions->values()->all(),
            'aptitude_areas_count' => $activeAreaCount,
        ]);
    }

    public function store(StoreGradingSessionRequest $request): RedirectResponse
    {
        $examSession = ExamSession::findOrFail($request->validated('exam_session_id'));
        $session = $this->gradingService->openForExamSession($examSession, $request->user());

        return redirect()->route('grading.sessions.show', $session->id)
            ->with('success', 'Grading session opened.');
    }
}
