<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDirectAssessmentRequest;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ExamSession;
use App\Models\SystemSetting;
use App\Services\DirectAssessmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DirectAssessmentController extends Controller
{
    public function __construct(
        private DirectAssessmentService $service
    ) {}

    public function create(): Response
    {
        if (! SystemSetting::allowDirectAssessment()) {
            abort(403);
        }

        $academicYears = AcademicYear::query()
            ->orderByDesc('academic_year')
            ->orderBy('semester')
            ->get(['id', 'academic_year', 'semester', 'is_active']);

        $activeAcademicYear = AcademicYear::active();

        $applicants = Applicant::query()
            ->whereHas('application', fn ($q) => $q->where('status', 'accepted'))
            ->whereDoesntHave('examSessions', fn ($q) => $q->whereNotIn('status', [ExamSession::STATUS_CANCELLED]))
            ->with('application:id,first_name,middle_name,last_name,suffix,reference_number')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->application ? trim(implode(' ', array_filter([$a->application->first_name, $a->application->middle_name, $a->application->last_name, $a->application->suffix]))) : '—',
                'reference' => $a->application?->reference_number ?? '—',
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/DirectAssessment/Create', [
            'academicYears' => $academicYears,
            'applicants' => $applicants,
            'activeAcademicYearId' => $activeAcademicYear?->id,
            'storeRoute' => route('admin.direct-assessments.store'),
        ]);
    }

    public function store(StoreDirectAssessmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $gradingSession = $this->service->create(
            academicYear: $academicYear,
            applicantIds: $validated['applicant_ids'],
            openedBy: $request->user(),
            label: $validated['label'] ?? null
        );

        // Users with grading access go directly to the grading page
        if ($request->user()->hasAnyRole(['super_admin', 'test_administrator'])) {
            return redirect()
                ->route('admin.grading.sessions.show', $gradingSession->id)
                ->with('success', 'Direct assessment session created. You can now encode scores.');
        }

        // Registrar/staff without grading access — redirect back with confirmation dialog
        return redirect()
            ->route('admin.direct-assessments.create')
            ->with('direct_assessment_created', true)
            ->with('success', 'Direct assessment session created successfully. Please contact a Test Administrator to begin score encoding.');
    }
}
