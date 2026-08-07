<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Setup\Actions\UpsertProgramQuota;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Institutional Setup hub + catalog CRUD (OpenSpec institutional-setup-readiness).
 * Aptitude is TA-gated; institutional catalog uses shared setup.manage.
 */
class SetupController extends Controller
{
    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ComputeSetupReadinessQuery $readiness,
    ): Response {
        $caps = $this->capabilities($authorizer, $request);
        if (! $caps['setup'] && ! $caps['assessment'] && ! $caps['quota']) {
            abort(403);
        }

        return Inertia::render('Setup/Index', [
            'page_state' => 'ready',
            'readiness' => $readiness->handle(),
            'capabilities' => $caps,
        ]);
    }

    public function programs(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Programs', [
            'page_state' => 'ready',
            'programs' => Program::query()->orderBy('code')->get([
                'id', 'code', 'name', 'is_active', 'institution_id',
            ]),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeProgram(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('programs', 'code')->where('institution_id', $institution->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $program = Program::query()->create([
            'institution_id' => $institution->id,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $audit->handle('setup.program_upserted', [
            'program_id' => $program->id,
            'code' => $program->code,
            'is_active' => $program->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Program saved.');
    }

    public function updateProgram(
        Request $request,
        Program $program,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $program->update($validated);

        $audit->handle('setup.program_upserted', [
            'program_id' => $program->id,
            'code' => $program->code,
            'is_active' => $program->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Program updated.');
    }

    public function rooms(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Rooms', [
            'page_state' => 'ready',
            'rooms' => Room::query()->orderBy('code')->get([
                'id', 'code', 'name', 'capacity', 'is_active', 'institution_id',
            ]),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeRoom(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('rooms', 'code')->where('institution_id', $institution->id)],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $room = Room::query()->create([
            'institution_id' => $institution->id,
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $audit->handle('setup.room_upserted', [
            'room_id' => $room->id,
            'code' => $room->code,
            'capacity' => $room->capacity,
            'is_active' => $room->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Room saved.');
    }

    public function updateRoom(
        Request $request,
        Room $room,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);

        $room->update($validated);

        $audit->handle('setup.room_upserted', [
            'room_id' => $room->id,
            'code' => $room->code,
            'capacity' => $room->capacity,
            'is_active' => $room->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Room updated.');
    }

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

    public function storeCycle(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

        $validated = $request->validate([
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', Rule::in(['1', '2'])],
            'application_start_date' => ['nullable', 'date'],
            'application_end_date' => ['nullable', 'date', 'after_or_equal:application_start_date'],
        ]);

        $schoolYear = trim($validated['school_year']);
        $semester = $validated['semester'];
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

        $start = $validated['application_start_date'] ?? null;
        $end = $validated['application_end_date'] ?? null;

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
        Request $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $validated = $request->validate([
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', Rule::in(['1', '2'])],
            'application_start_date' => ['nullable', 'date'],
            'application_end_date' => ['nullable', 'date', 'after_or_equal:application_start_date'],
        ]);

        $schoolYear = trim($validated['school_year']);
        $semester = $validated['semester'];

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
        $start = $validated['application_start_date'] ?? null;
        $end = $validated['application_end_date'] ?? null;

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

    public function storeWindow(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        $validated = $request->validate([
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'status' => ['required', Rule::in(['draft', 'published', 'closed'])],
        ]);

        $window = ApplicationWindow::query()->updateOrCreate(
            [
                'academic_cycle_id' => $validated['academic_cycle_id'],
                'code' => strtoupper($validated['code']),
            ],
            [
                'name' => $validated['name'],
                'opens_at' => $validated['opens_at'],
                'closes_at' => $validated['closes_at'],
                'status' => $validated['status'],
            ],
        );

        $audit->handle('setup.window_upserted', [
            'window_id' => $window->id,
            'code' => $window->code,
            'status' => $window->status,
        ], (string) $request->user()->id);

        return back()->with('status', 'Application window saved.');
    }

    public function privacy(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Privacy', [
            'page_state' => 'ready',
            'policies' => PrivacyPolicy::query()->orderByDesc('id')->get([
                'id', 'title', 'content', 'is_active',
            ]),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storePrivacy(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! empty($validated['is_active'])) {
            PrivacyPolicy::query()->where('is_active', true)->update(['is_active' => false]);
        }

        $policy = PrivacyPolicy::query()->create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        $audit->handle('setup.privacy_upserted', [
            'policy_id' => $policy->id,
            'is_active' => $policy->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Privacy policy saved.');
    }

    public function activatePrivacy(
        Request $request,
        PrivacyPolicy $policy,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        PrivacyPolicy::query()->where('is_active', true)->update(['is_active' => false]);
        $policy->update(['is_active' => true]);

        $audit->handle('setup.privacy_activated', [
            'policy_id' => $policy->id,
        ], (string) $request->user()->id);

        return back()->with('status', 'Privacy policy activated.');
    }

    public function grades(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

        return Inertia::render('Setup/Grades', [
            'page_state' => 'ready',
            'fields' => IntakeGradeField::query()
                ->where('institution_id', $institution->id)
                ->orderBy('sort_order')
                ->get(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeGrade(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('intake_grade_fields', 'code')->where('institution_id', $institution->id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'field_kind' => ['required', Rule::in([IntakeGradeField::KIND_GWA, IntakeGradeField::KIND_SUBJECT])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $field = IntakeGradeField::query()->create([
            'institution_id' => $institution->id,
            'code' => strtoupper($validated['code']),
            'label' => $validated['label'],
            'field_kind' => $validated['field_kind'],
            'sort_order' => (int) ($validated['sort_order'] ?? 100),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'min_value' => 0,
            'max_value' => 100,
        ]);

        $audit->handle('setup.grade_field_upserted', [
            'field_id' => $field->id,
            'code' => $field->code,
            'is_active' => $field->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Grade field saved.');
    }

    public function updateGrade(
        Request $request,
        IntakeGradeField $field,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_required' => ['required', 'boolean'],
        ]);

        $field->update($validated);

        $audit->handle('setup.grade_field_upserted', [
            'field_id' => $field->id,
            'code' => $field->code,
            'is_active' => $field->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Grade field updated.');
    }

    public function aptitude(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/Aptitude', [
            'page_state' => 'ready',
            'areas' => AptitudeArea::query()->orderBy('code')->get([
                'id', 'code', 'name', 'version', 'is_active',
            ]),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeAptitude(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireAssessment($authorizer, $request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $area = AptitudeArea::query()->updateOrCreate(
            [
                'code' => strtoupper($validated['code']),
                'version' => $validated['version'],
            ],
            [
                'name' => $validated['name'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ],
        );

        $audit->handle('setup.aptitude_upserted', [
            'area_id' => $area->id,
            'code' => $area->code,
            'is_active' => $area->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Aptitude area saved.');
    }

    public function updateAptitude(
        Request $request,
        AptitudeArea $area,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $area->update($validated);

        $audit->handle('setup.aptitude_upserted', [
            'area_id' => $area->id,
            'code' => $area->code,
            'is_active' => $area->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Aptitude area updated.');
    }

    public function quotas(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
            abort(403);
        }

        /** @var User $actor */
        $actor = $request->user();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->first()
            ?? AcademicCycle::query()->orderByDesc('opens_on')->first();

        $programsQuery = Program::query()->where('is_active', true)->orderBy('code');
        if ($actor->hasRole('program_head')) {
            $codes = $actor->actorScope()->programCodes;
            $programsQuery->whereIn('code', $codes === [] ? ['__none__'] : $codes);
        }

        $programs = $programsQuery->get(['id', 'code', 'name']);

        $quotas = ProgramQuota::query()
            ->with(['program:id,code,name', 'lastMutatedBy:id,name', 'changes' => fn ($q) => $q->limit(8)->with('actor:id,name')])
            ->when($cycle, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->when(
                $actor->hasRole('program_head'),
                fn ($q) => $q->whereIn('program_id', $programs->pluck('id')),
            )
            ->orderBy('program_id')
            ->get();

        return Inertia::render('Setup/Quotas', [
            'page_state' => 'ready',
            'cycle' => $cycle ? [
                'id' => $cycle->id,
                'code' => $cycle->code,
                'name' => $cycle->name,
                'school_year' => $cycle->school_year,
                'semester' => $cycle->semester,
                'staff_label' => $cycle->staffLabel(),
            ] : null,
            'programs' => $programs,
            'quotas' => $quotas->map(fn (ProgramQuota $quota) => [
                'id' => $quota->id,
                'program_id' => $quota->program_id,
                'program_code' => $quota->program?->code,
                'program_name' => $quota->program?->name,
                'slots' => $quota->slots,
                'last_notice' => $quota->last_notice,
                'last_mutated_by' => $quota->lastMutatedBy?->name,
                'updated_at' => $quota->updated_at?->toIso8601String(),
                'changes' => $quota->changes->map(fn ($change) => [
                    'id' => $change->id,
                    'actor' => $change->actor?->name,
                    'from_slots' => $change->from_slots,
                    'to_slots' => $change->to_slots,
                    'notice' => $change->notice,
                    'created_at' => $change->created_at?->toIso8601String(),
                ])->values(),
            ])->values(),
            'capabilities' => $this->capabilities($authorizer, $request),
            'is_program_head' => $actor->hasRole('program_head'),
        ]);
    }

    public function storeQuota(
        Request $request,
        CapabilityAuthorizer $authorizer,
        UpsertProgramQuota $upsert,
    ): RedirectResponse {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
            abort(403);
        }

        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'slots' => ['required', 'integer', 'min:0', 'max:100000'],
            'notice' => ['nullable', 'string', 'max:2000'],
        ]);

        $program = Program::query()->findOrFail($validated['program_id']);
        $cycle = AcademicCycle::query()->findOrFail($validated['academic_cycle_id']);

        try {
            $upsert->handle(
                actor: $request->user(),
                program: $program,
                cycle: $cycle,
                slots: (int) $validated['slots'],
                notice: $validated['notice'] ?? null,
            );
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('status', 'Program quota saved.');
    }

    /**
     * @return array{setup: bool, assessment: bool, quota: bool}
     */
    private function capabilities(CapabilityAuthorizer $authorizer, Request $request): array
    {
        $user = $request->user();

        return [
            'setup' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_SETUP_MANAGE),
            'assessment' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
            'quota' => $authorizer->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE),
        ];
    }

    private function requireSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::REGISTRAR_SETUP_MANAGE), 403);
    }

    private function requireAssessment(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), 403);
    }
}
