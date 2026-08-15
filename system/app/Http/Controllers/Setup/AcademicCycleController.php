<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreAcademicCycleRequest;
use App\Http\Requests\Setup\UpdateAcademicCycleRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Academic year lifecycle: list → separate create page → edit/activate on the
 * list (legacy pipeline shape). One coherent domain — the largest Setup surface.
 */
class AcademicCycleController extends Controller
{
    use HasSetupCapabilities;

    public function cycles(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        $cycles = AcademicCycle::query()
            ->with(['applicationWindows' => fn ($q) => $q->orderByDesc('id')])
            ->orderByDesc('school_year')
            ->orderBy('semester')
            ->get()
            ->map(fn (AcademicCycle $cycle) => [
                'id' => $cycle->id,
                'school_year' => $cycle->school_year,
                'semester' => $cycle->semester,
                'semester_label' => $cycle->semesterLabel(),
                'label' => $cycle->staffLabel(),
                'is_active' => $cycle->is_active,
                'application_window' => $cycle->applicationWindowLabel(),
            ]);

        return Inertia::render('Setup/Cycles', [
            'page_state' => 'ready',
            'cycles' => $cycles,
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function createCycle(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Cycles/Create', [
            'page_state' => 'ready',
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeCycle(StoreAcademicCycleRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        $institution = Institution::current();
        $schoolYear = trim($request->validated('school_year'));
        $semester = $request->validated('semester');
        $semesterLabel = $semester === '1' ? '1st Semester' : '2nd Semester';
        $staffName = "{$schoolYear} {$semesterLabel}";
        $code = 'AY-'.strtoupper(str_replace(' ', '', $schoolYear)).'-S'.$semester;

        $exists = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'school_year' => 'That school year and semester already exist.',
            ]);
        }

        $start = $request->validated('application_start_date');
        $end = $request->validated('application_end_date');

        $cycle = AcademicCycle::query()->create([
            'institution_id' => $institution->id,
            'code' => $code,
            'name' => $staffName,
            'school_year' => $schoolYear,
            'semester' => $semester,
            'opens_on' => $start ?? now()->toDateString(),
            'closes_on' => $end ?? now()->addMonths(6)->toDateString(),
            'is_active' => false,
        ]);

        if ($start || $end) {
            ApplicationWindow::query()->create([
                'academic_cycle_id' => $cycle->id,
                'code' => $code.'-WIN',
                'name' => $staffName.' application window',
                'opens_at' => ($start ?? now()->toDateString()).' 00:00:00',
                'closes_at' => ($end ?? now()->addMonths(6)->toDateString()).' 23:59:59',
                'status' => 'draft',
            ]);
        }

        $audit->handle('setup.cycle_upserted', [
            'cycle_id' => $cycle->id,
            'code' => $cycle->code,
            'school_year' => $cycle->school_year,
            'semester' => $cycle->semester,
        ], (string) $request->user()->id);

        return redirect()->route('setup.cycles')->with('status', 'Academic year created.');
    }

    public function editCycle(Request $request, AcademicCycle $cycle, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        $window = $cycle->primaryWindow();

        return Inertia::render('Setup/Cycles/Edit', [
            'page_state' => 'ready',
            'cycle' => [
                'id' => $cycle->id,
                'school_year' => $cycle->school_year,
                'semester' => $cycle->semester,
                'is_active' => $cycle->is_active,
                'application_start_date' => $window?->opens_at?->toDateString() ?? $cycle->opens_on?->toDateString(),
                'application_end_date' => $window?->closes_at?->toDateString() ?? $cycle->closes_on?->toDateString(),
            ],
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function updateCycle(
        UpdateAcademicCycleRequest $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $schoolYear = trim($request->validated('school_year'));
        $semester = $request->validated('semester');

        $duplicate = AcademicCycle::query()
            ->where('institution_id', $cycle->institution_id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->where('id', '!=', $cycle->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'school_year' => 'That school year and semester already exist.',
            ]);
        }

        $semesterLabel = $semester === '1' ? '1st Semester' : '2nd Semester';
        $staffName = "{$schoolYear} {$semesterLabel}";
        $start = $request->validated('application_start_date');
        $end = $request->validated('application_end_date');

        $cycle->update([
            'school_year' => $schoolYear,
            'semester' => $semester,
            'name' => $staffName,
            'opens_on' => $start ?? $cycle->opens_on,
            'closes_on' => $end ?? $cycle->closes_on,
        ]);

        if ($start || $end) {
            $window = $cycle->primaryWindow();
            $payload = [
                'name' => $staffName.' application window',
                'opens_at' => ($start ?? $cycle->opens_on?->toDateString()).' 00:00:00',
                'closes_at' => ($end ?? $cycle->closes_on?->toDateString()).' 23:59:59',
            ];

            if ($window !== null) {
                $window->update($payload);
            } else {
                ApplicationWindow::query()->create([
                    'academic_cycle_id' => $cycle->id,
                    'code' => $cycle->code.'-WIN',
                    ...$payload,
                    'status' => $cycle->is_active ? 'published' : 'draft',
                ]);
            }
        }

        $audit->handle('setup.cycle_upserted', [
            'cycle_id' => $cycle->id,
            'code' => $cycle->code,
            'school_year' => $cycle->school_year,
            'semester' => $cycle->semester,
        ], (string) $request->user()->id);

        return redirect()->route('setup.cycles')->with('status', 'Academic year updated.');
    }

    public function activateCycle(
        Request $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $cycle->activate();

        $audit->handle('setup.cycle_activated', [
            'cycle_id' => $cycle->id,
            'code' => $cycle->code,
        ], (string) $request->user()->id);

        return redirect()->route('setup.cycles')->with('status', 'Academic year set as active.');
    }

    public function deactivateCycle(
        Request $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $cycle->deactivate();

        $audit->handle('setup.cycle_deactivated', [
            'cycle_id' => $cycle->id,
            'code' => $cycle->code,
        ], (string) $request->user()->id);

        return redirect()->route('setup.cycles')->with('status', 'Academic year deactivated. Applications are closed until one is activated.');
    }
}
