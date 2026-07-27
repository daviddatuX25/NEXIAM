<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAcademicYear;
use App\Models\AcademicYear;
use App\Models\ConsultationSummary;
use App\Models\Course;
use App\Models\GradingSession;
use App\Models\SystemSetting;
use App\Notifications\ResultReleased;
use App\Notifications\ResultReleasedF2F;
use App\Services\ApplicationPipelineService;
use App\Services\AuditService;
use App\Services\ConsultationSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseController extends Controller
{
    use ResolvesAcademicYear;

    public function index(Request $request): Response
    {
        $mode = SystemSetting::releaseMode();
        $courses = Course::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        [$activeAcademicYear, $queryAcademicYearId] = $this->resolveAcademicYear($request);

        $query = ConsultationSummary::with([
            'applicant.application.coursePreference1:id,name,code',
            'applicant.application.coursePreference2:id,name,code',
            'applicant.application.coursePreference3:id,name,code',
            'applicant.gradingSessions',
            'recommendedCourse:id,name,code',
        ])
            ->whereIn('status', ['draft', 'released']);

        if ($queryAcademicYearId !== null) {
            $query->whereHas('applicant.application', fn ($q) => $q->where('academic_year_id', $queryAcademicYearId));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('applicant', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhereHas('application', function ($q2) use ($search) {
                        $q2->where('reference_number', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $summaries = $query
            ->orderBy('updated_at', 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(function ($summary) {
                $app = $summary->applicant?->application;
                if ($summary->applicant && $app) {
                    $summary->applicant->setAttribute('full_name', trim(implode(' ', array_filter([
                        $app->first_name,
                        $app->middle_name,
                        $app->last_name,
                        $app->suffix,
                    ]))));
                    $summary->applicant->setAttribute('reference_number', $app->reference_number ?? null);

                    $app->setAttribute('course_preferences', [
                        ['rank' => 1, 'course' => $app->coursePreference1 ? ['id' => $app->coursePreference1->id, 'code' => $app->coursePreference1->code, 'name' => $app->coursePreference1->name] : null],
                        ['rank' => 2, 'course' => $app->coursePreference2 ? ['id' => $app->coursePreference2->id, 'code' => $app->coursePreference2->code, 'name' => $app->coursePreference2->name] : null],
                        ['rank' => 3, 'course' => $app->coursePreference3 ? ['id' => $app->coursePreference3->id, 'code' => $app->coursePreference3->code, 'name' => $app->coursePreference3->name] : null],
                    ]);
                }

                $printed = $summary->applicant?->gradingSessions
                    ?->some(fn ($gs) => (bool) $gs->pivot->result_printed_at) ?? false;

                $summary->setAttribute('printed', $printed);
                $summary->setAttribute('grading_session_id', $summary->applicant?->gradingSessions->first()?->id);

                return $summary;
            });

        $gradingSessionsQuery = GradingSession::where('status', GradingSession::STATUS_FINALIZED)
            ->with('examSession.room');

        if ($queryAcademicYearId !== null) {
            $gradingSessionsQuery->whereHas('examSession', fn ($q) => $q->where('academic_year_id', $queryAcademicYearId));
        }

        return Inertia::render('Release/Index', [
            'summaries' => $summaries,
            'release_mode' => $mode,
            'courses' => $courses,
            'filters' => $request->only(['search', 'status', 'academic_year_id']),
            'seasons' => $this->academicYearOptions(),
            'active_season_id' => $activeAcademicYear?->id,
            'gradingSessions' => $gradingSessionsQuery
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => 'Session #'.$s->id,
                    'exam_date' => $s->examSession?->date?->format('M j, Y'),
                    'room_name' => $s->examSession?->room?->name ?? '—',
                ])
                ->values()
                ->all(),
        ]);
    }

    public function storeOrUpdateByApplicant(Request $request, int $applicantId): RedirectResponse
    {
        $summary = app(ConsultationSummaryService::class)->getOrCreateForApplicant($applicantId);

        $releaseMode = SystemSetting::releaseMode();

        $rules = [
            'recommended_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'counselor_comments' => ['nullable', 'string', 'max:5000'],
        ];

        if ($releaseMode === 'online') {
            $rules['counselor_comments'] = ['required', 'string', 'max:5000'];
        }

        $validated = $request->validate($rules);

        $summary->update([
            'recommended_course_id' => $validated['recommended_course_id'] ?? null,
            'counselor_comments' => $validated['counselor_comments'] ?? null,
        ]);

        app(AuditService::class)->log('release.saved', ConsultationSummary::class, $summary->id, [], [
            'applicant_id' => $applicantId,
            'recommended_course_id' => $validated['recommended_course_id'] ?? null,
        ]);

        return back()->with('success', 'Summary updated.');
    }

    public function release(Request $request, ConsultationSummary $summary): RedirectResponse
    {
        if ($summary->status === ConsultationSummary::STATUS_RELEASED) {
            return back()->with('error', 'Already released.');
        }

        $summary->update([
            'status' => ConsultationSummary::STATUS_RELEASED,
            'released_at' => now(),
            'released_by' => auth()->id(),
        ]);

        $mode = SystemSetting::releaseMode();
        $context = $request->input('release_context', $mode);

        if ($context === 'f2f') {
            $summary->applicant->notify(new ResultReleasedF2F($summary));
        } else {
            $summary->applicant->notify(new ResultReleased($summary));
        }

        // Pipeline hook: mark released
        if ($summary->applicant?->application) {
            app(ApplicationPipelineService::class)->transition(
                $summary->applicant->application,
                'released',
                ['released_at' => now()->toIso8601String()]
            );
        }

        app(AuditService::class)->log('release.released', ConsultationSummary::class, $summary->id, [], [
            'applicant_id' => $summary->applicant_id,
            'release_mode' => $context,
        ]);

        return back()->with('success', 'Result released.');
    }

    public function releaseBulk(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:consultation_summaries,id',
        ])['ids'];

        $mode = SystemSetting::releaseMode();
        $context = $request->input('release_context', $mode);

        $summaries = ConsultationSummary::whereIn('id', $ids)
            ->where('status', '!=', ConsultationSummary::STATUS_RELEASED)
            ->get();

        foreach ($summaries as $summary) {
            $summary->update([
                'status' => ConsultationSummary::STATUS_RELEASED,
                'released_at' => now(),
                'released_by' => auth()->id(),
            ]);

            if ($context === 'f2f') {
                $summary->applicant->notify(new ResultReleasedF2F($summary));
            } else {
                $summary->applicant->notify(new ResultReleased($summary));
            }

            // Pipeline hook: mark released
            if ($summary->applicant?->application) {
                app(ApplicationPipelineService::class)->transition(
                    $summary->applicant->application,
                    'released',
                    ['released_at' => now()->toIso8601String()]
                );
            }
        }

        app(AuditService::class)->log('release.bulk_released', null, null, [], [
            'count' => count($summaries),
            'mode' => $context,
        ], 'Bulk released '.count($summaries).' results');

        return back()->with('success', count($summaries).' result(s) released.');
    }

    public function unrelease(ConsultationSummary $summary): RedirectResponse
    {
        if ($summary->status !== ConsultationSummary::STATUS_RELEASED) {
            return back()->with('error', 'Only released results can be reversed.');
        }

        $summary->update([
            'status' => ConsultationSummary::STATUS_DRAFT,
            'released_at' => null,
            'released_by' => null,
        ]);

        // Pipeline hook: revert back to graded
        if ($summary->applicant?->application) {
            app(ApplicationPipelineService::class)->transition(
                $summary->applicant->application,
                'graded',
                ['unreleased_at' => now()->toIso8601String(), 'unreleased_by' => auth()->id()]
            );
        }

        app(AuditService::class)->log('release.unreleased', ConsultationSummary::class, $summary->id, [], [
            'applicant_id' => $summary->applicant_id,
        ]);

        return back()->with('success', 'Release reversed — result moved back to draft.');
    }

    public function releaseAll(Request $request): RedirectResponse
    {
        $mode = SystemSetting::releaseMode();

        if ($mode === 'f2f') {
            return back()->with('error', 'Release All is not available in F2F mode.');
        }

        $activeAcademicYear = AcademicYear::active();

        $releaseAllQuery = ConsultationSummary::with('applicant')
            ->where('status', '!=', ConsultationSummary::STATUS_RELEASED);

        if ($activeAcademicYear !== null) {
            $releaseAllQuery->whereHas('applicant.application', fn ($q) => $q->forAcademicYear($activeAcademicYear));
        }

        $summaries = $releaseAllQuery->get();

        $releasedCount = 0;

        foreach ($summaries as $summary) {
            $summary->update([
                'status' => ConsultationSummary::STATUS_RELEASED,
                'released_at' => now(),
                'released_by' => auth()->id(),
            ]);

            $summary->applicant->notify(new ResultReleased($summary));

            // Pipeline hook: mark released
            if ($summary->applicant?->application) {
                app(ApplicationPipelineService::class)->transition(
                    $summary->applicant->application,
                    'released',
                    ['released_at' => now()->toIso8601String()]
                );
            }

            $releasedCount++;
        }

        if ($releasedCount === 0) {
            return back()->with('info', 'All results have already been released.');
        }

        app(AuditService::class)->log('release.all_released', null, null, [], [
            'count' => $releasedCount,
        ], "Released all {$releasedCount} results");

        return back()->with('success', "{$releasedCount} result(s) released.");
    }
}
