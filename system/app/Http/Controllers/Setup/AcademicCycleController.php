<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Queries\ListApplicationOpeningSubscribersQuery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\ExtendApplicationWindowRequest;
use App\Http\Requests\Setup\StoreAcademicCycleRequest;
use App\Http\Requests\Setup\UpdateAcademicCycleRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Setup\Actions\ActivateAcademicCycle;
use App\Setup\Actions\CreateAcademicCycle;
use App\Setup\Actions\DeactivateAcademicCycle;
use App\Setup\Actions\ExtendApplicationWindow;
use App\Setup\Actions\UpdateAcademicCycle;
use App\Setup\Queries\AcademicCycleCalendarQuery;
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

    public function cycles(
        Request $request,
        CapabilityAuthorizer $authorizer,
        AcademicCycleCalendarQuery $calendar,
        ListApplicationOpeningSubscribersQuery $openingSubscribers,
    ): Response {
        $this->requireCycleSetup($authorizer, $request);

        $institution = Institution::current();
        $actor = $request->user();
        $openingAlertsVisible = $actor instanceof User
            && $openingSubscribers->allowed($actor, $institution);
        $institutionId = $institution->id;
        $cycles = AcademicCycle::query()
            ->where('institution_id', $institutionId)
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
            'application_calendar' => $calendar->forInstitution($institution),
            'opening_alerts_visible' => $openingAlertsVisible,
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function createCycle(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireCycleSetup($authorizer, $request);

        return Inertia::render('Setup/Cycles/Create', [
            'page_state' => 'ready',
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeCycle(
        StoreAcademicCycleRequest $request,
        CapabilityAuthorizer $authorizer,
        CreateAcademicCycle $createCycle,
    ): RedirectResponse {
        $this->requireCycleSetup($authorizer, $request);

        $createCycle->handle($request->validated(), $request->user());

        return redirect()->route('setup.cycles')->with('status', 'Academic year created.');
    }

    public function editCycle(Request $request, AcademicCycle $cycle, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireCycleSetup($authorizer, $request);
        $this->requireCurrentInstitution($cycle);

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
        UpdateAcademicCycle $updateCycle,
    ): RedirectResponse {
        $this->requireCycleSetup($authorizer, $request);
        $this->requireCurrentInstitution($cycle);

        $updateCycle->handle($cycle, $request->validated(), $request->user());

        return redirect()->route('setup.cycles')->with('status', 'Academic year updated.');
    }

    public function activateCycle(
        Request $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        ActivateAcademicCycle $activateCycle,
    ): RedirectResponse {
        $this->requireCycleSetup($authorizer, $request);
        $this->requireCurrentInstitution($cycle);

        $activateCycle->handle($cycle, $request->user());

        return redirect()->route('setup.cycles')->with('status', 'Academic year set as active.');
    }

    public function deactivateCycle(
        Request $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        DeactivateAcademicCycle $deactivateCycle,
    ): RedirectResponse {
        $this->requireCycleSetup($authorizer, $request);
        $this->requireCurrentInstitution($cycle);

        $deactivateCycle->handle($cycle, $request->user());

        return redirect()->route('setup.cycles')->with('status', 'Academic year deactivated. Applications are closed until one is activated.');
    }

    public function extendApplicationWindow(
        ExtendApplicationWindowRequest $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        ExtendApplicationWindow $extend,
    ): RedirectResponse {
        $this->requireCycleSetup($authorizer, $request);
        $this->requireCurrentInstitution($cycle);

        $data = $request->validated();
        $extend->handle(
            $request->user(),
            $cycle,
            (int) $data['window_id'],
            (int) $data['days'],
            $data['baseline'],
            $data['idempotency_key'],
            $data['reason'],
        );

        return redirect()->route('setup.cycles')->with('status', 'Application window extended.');
    }

    private function requireCurrentInstitution(AcademicCycle $cycle): void
    {
        abort_unless((int) $cycle->institution_id === (int) Institution::current()->id, 404);
    }

    private function requireCycleSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        $scope = ['institution_id' => (int) Institution::current()->id];

        abort_unless(
            $authorizer->allows($request->user(), CapabilityCatalog::SETUP_CYCLES_MANAGE, $scope)
                || $authorizer->allows($request->user(), CapabilityCatalog::SETUP_INSTITUTION_MANAGE, $scope),
            403,
        );
    }
}
