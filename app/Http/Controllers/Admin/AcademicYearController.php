<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Requests\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', AcademicYear::class);

        $academicYears = AcademicYear::query()
            ->withCount('applications')
            ->orderByDesc('academic_year')
            ->orderBy('semester')
            ->paginate(20)
            ->through(function (AcademicYear $ay) {
                return [
                    'id' => $ay->id,
                    'academic_year' => $ay->academic_year,
                    'semester' => $ay->semester,
                    'semester_label' => $ay->semesterLabel(),
                    'label' => $ay->academic_year.' – '.$ay->semesterLabel(),
                    'is_active' => $ay->is_active,
                    'application_window' => $ay->applicationWindowLabel(),
                    'applications_count' => $ay->applications_count,
                ];
            });

        return Inertia::render('Admin/AcademicYears/Index', [
            'academic_years' => $academicYears,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AcademicYear::class);

        return Inertia::render('Admin/AcademicYears/Create');
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        $this->authorize('create', AcademicYear::class);
        $ay = AcademicYear::create($request->validated());

        app(AuditService::class)->log('academic_year.created', AcademicYear::class, $ay->id, [], $request->validated());

        return redirect()->route('admin.academic-years.index')->with('success', 'Academic year created.');
    }

    public function edit(AcademicYear $academic_year): Response
    {
        $this->authorize('update', $academic_year);

        return Inertia::render('Admin/AcademicYears/Edit', [
            'academicYear' => [
                'id' => $academic_year->id,
                'academic_year' => $academic_year->academic_year,
                'semester' => $academic_year->semester,
                'is_active' => $academic_year->is_active,
                'application_start_date' => $academic_year->application_start_date?->toDateString(),
                'application_end_date' => $academic_year->application_end_date?->toDateString(),
            ],
        ]);
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academic_year): RedirectResponse
    {
        $this->authorize('update', $academic_year);
        $academic_year->update($request->validated());

        app(AuditService::class)->log('academic_year.updated', AcademicYear::class, $academic_year->id, [], $request->validated());

        return redirect()->route('admin.academic-years.index')->with('success', 'Academic year updated.');
    }

    public function deactivate(AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $academicYear->update(['is_active' => false]);

        app(AuditService::class)->log('academic_year.deactivated', AcademicYear::class, $academicYear->id);

        return back()->with('success', 'Academic year deactivated. Applications are now closed.');
    }

    public function activate(AcademicYear $academic_year): RedirectResponse
    {
        $this->authorize('activate', $academic_year);
        $academic_year->activate();

        app(AuditService::class)->log('academic_year.activated', AcademicYear::class, $academic_year->id);

        return redirect()->route('admin.academic-years.index')->with('success', 'Academic year set as active.');
    }
}
