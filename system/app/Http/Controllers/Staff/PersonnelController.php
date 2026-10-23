<?php

namespace App\Http\Controllers\Staff;

use App\Auth\Support\StaffMfaPolicy;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\DisableStaffMfaRequest;
use App\Http\Requests\Staff\PrepareStaffAccountsRequest;
use App\Http\Requests\Staff\PreviewStaffAccountPreparationRequest;
use App\Http\Requests\Staff\RequireStaffMfaRequest;
use App\Http\Requests\Staff\ResetStaffAccountsRequest;
use App\Http\Requests\Staff\StoreStaffAccountRequest;
use App\Http\Requests\Staff\UpdateStaffAccountRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Staffing\InstitutionalSetupDelegation;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\ActivateStaffAccount;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Actions\DeactivateStaffAccount;
use App\Staffing\Actions\DisableStaffMfa;
use App\Staffing\Actions\PrepareStaffAccountRecipients;
use App\Staffing\Actions\RequireStaffMfa;
use App\Staffing\Actions\ResetStaffAccountForSetup;
use App\Staffing\Actions\SyncStaffRoles;
use App\Staffing\Actions\UpdateStaffAssignment;
use App\Staffing\Queries\StaffAccountReadiness;
use App\Staffing\Support\EligibleSetupRecipients;
use App\Staffing\Support\EligibleTaRecipients;
use App\Staffing\Support\StaffRoleCeiling;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Thin controller: read projections + action delegation.
 * Validation lives in Store/UpdateStaffAccountRequest; role/office resolution
 * lives in StaffRoleCeiling; domain writes in Staffing\Actions.
 */
class PersonnelController extends Controller
{
    private const OFFICE_LABELS = [
        'admin' => 'Admin Office',
        'registrar' => 'Registrar Office',
        'guidance' => 'Guidance Office',
        'program' => 'Academic Programs',
    ];

    public function index(
        Request $request,
        StaffRoleCeiling $ceiling,
        EligibleTaRecipients $ta,
        EligibleSetupRecipients $setup,
        CapabilityAuthorizer $authorizer,
        StaffMfaPolicy $mfaPolicy,
        StaffAccountReadiness $staffReadiness,
    ): Response {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $actorScope = $actor->actorScope();
        $canDelegateSetup = $authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_DELEGATE);
        $canManageHr = $authorizer->allows($actor, CapabilityCatalog::HR_STAFF_MANAGE);
        $programCatalog = Program::query()
            ->active()
            ->where('institution_id', $actorScope->institutionId)
            ->with('department:id,code,name')
            ->orderBy('code')
            ->get(['id', 'department_id', 'code', 'name']);
        $departments = $programCatalog
            ->pluck('department')
            ->filter()
            ->unique('id')
            ->sortBy('code')
            ->values();
        $departmentOptions = $departments->map(fn ($department) => [
            'value' => $department->code,
            'label' => "{$department->code} — {$department->name}",
        ])->values();
        $programOptions = $programCatalog->map(fn (Program $program) => [
            'value' => $program->code,
            'label' => "{$program->code} — {$program->name}",
            'department_code' => $program->department?->code,
        ])->values();
        $departmentCodeByProgram = $programCatalog->mapWithKeys(fn (Program $program) => [
            $program->code => $program->department?->code,
        ])->all();
        $departmentInput = $request->input('department');
        $department = is_string($departmentInput) && $departments->contains(
            fn ($knownDepartment) => $knownDepartment->code === $departmentInput
        )
            ? $departmentInput
            : null;
        $programInput = $request->input('program');
        $knownProgramCodes = $programCatalog->pluck('code');
        $program = is_string($programInput) && $knownProgramCodes->contains($programInput)
            ? $programInput
            : null;

        $query = User::query()
            ->whereHas('staffProfile')
            ->with(['roles:id,name', 'staffProfile.department:id,code', 'authenticationState.attempts'])
            ->when(
                $actorScope->institutionId !== null,
                fn ($q) => $q->whereHas(
                    'staffProfile',
                    fn ($q) => $q->where('institution_id', $actorScope->institutionId)
                )
            )
            ->when(
                ! $ceiling->isFullHr($actor) && ! $canDelegateSetup,
                fn ($q) => $q->whereHas(
                    'staffProfile',
                    fn ($q) => $q->where('office', $actorScope->office)
                )
            );
        $officeInput = $request->input('office');
        $office = is_string($officeInput) && array_key_exists($officeInput, self::OFFICE_LABELS)
            ? $officeInput
            : null;
        if ($office !== null) {
            $query->whereHas('staffProfile', fn ($q) => $q->where('office', $office));
        }

        $hasAcademicFilters = $office === null || $office === 'program';
        if (! $hasAcademicFilters) {
            $department = null;
            $program = null;
        }

        if ($hasAcademicFilters && $program !== null) {
            $query->whereHas('staffProfile', fn ($q) => $q->whereJsonContains('program_codes', $program));
        }
        if ($hasAcademicFilters && $department !== null) {
            $selectedDepartment = $departments->firstWhere('code', $department);
            $deptId = $selectedDepartment->getKey();
            $deptProgramCodes = $programCatalog
                ->filter(fn (Program $program) => (int) $program->department_id === (int) $deptId)
                ->pluck('code');
            $query->whereHas('staffProfile', function ($q) use ($deptId, $deptProgramCodes) {
                $q->where('department_id', $deptId);
                foreach ($deptProgramCodes as $code) {
                    $q->orWhereJsonContains('program_codes', $code);
                }
            });
        }

        $search = $request->input('search');
        if ($search !== null && $search !== '') {
            TokenizedInnerMatchScope::apply($query, $search, [
                fn ($sub, $pattern) => TokenizedInnerMatchScope::orWhereColumnLike($sub, 'users.name', $pattern),
                fn ($sub, $pattern) => TokenizedInnerMatchScope::orWhereColumnLike($sub, 'users.email', $pattern),
                fn ($sub, $pattern) => $sub->orWhereHas('staffProfile', fn ($sq) => $sq->whereRaw('LOWER(COALESCE(office, \'\')) LIKE LOWER(?)', [$pattern])),
                fn ($sub, $pattern) => $sub->orWhereHas('roles', fn ($rq) => $rq->whereRaw('LOWER(COALESCE(name, \'\')) LIKE LOWER(?)', [$pattern])),
            ]);
        }

        $role = $request->input('role');
        if ($role) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $status = $request->input('status');
        if ($status === 'active' || $status === 'inactive') {
            $query->whereHas('staffProfile', fn ($sq) => $sq->where('status', $status));
        }

        $allowedSorts = [
            'name' => 'users.name',
            'email' => 'users.email',
            'office' => function ($q, $dir) {
                $q->orderBy(
                    StaffProfile::select('office')
                        ->whereColumn('staff_profiles.user_id', 'users.id')
                        ->limit(1),
                    $dir
                );
            },
            'program' => function ($q, $dir) {
                $q->orderBy(
                    StaffProfile::select('program_codes')
                        ->whereColumn('staff_profiles.user_id', 'users.id')
                        ->limit(1),
                    $dir
                );
            },
            'created_at' => 'users.created_at',
        ];

        $sortResult = ModularSortScope::apply(
            $query,
            $request->input('sort'),
            $request->input('dir'),
            $allowedSorts,
            defaultKey: 'name',
            defaultDir: 'asc',
            tieBreakColumn: 'users.id',
        );
        $users = $query->paginate(25)->withQueryString();

        $activeSetupDelegations = InstitutionalSetupDelegation::query()
            ->active()
            ->whereIn('recipient_id', $users->getCollection()->modelKeys())
            ->get()
            ->keyBy('recipient_id');
        $users->getCollection()->each(fn (User $user) => $user->setRelation(
            'activeSetupDelegation',
            $activeSetupDelegations->get($user->id),
        ));
        $display = CapabilityCatalog::personnelRoleDisplayNames();
        $officeValues = $ceiling->isFullHr($actor) || $canDelegateSetup
            ? array_keys(self::OFFICE_LABELS)
            : (array_key_exists($actorScope->office, self::OFFICE_LABELS) ? [$actorScope->office] : []);

        return Inertia::render('Staff/Personnel/Index', [
            'page_state' => $users->total() === 0 ? 'empty' : 'ready',
            'users' => $users->through(function (User $user) use ($display, $departmentCodeByProgram, $actor, $ta, $setup, $ceiling, $mfaPolicy) {
                $isProgramOffice = $user->staffProfile?->office === 'program';
                $programCodes = $isProgramOffice ? ($user->staffProfile?->program_codes ?? []) : [];
                $departmentCode = $isProgramOffice
                    ? ($user->staffProfile?->department?->code
                        ?? ($departmentCodeByProgram[$programCodes[0] ?? ''] ?? null))
                    : null;
                $authenticationState = $user->authenticationState;
                $hasConfirmedMfa = $authenticationState?->hasConfirmedTwoFactor() ?? false;
                $onboardingState = ! $authenticationState?->activation_completed_at || $authenticationState?->password_set_at === null
                    ? ($authenticationState?->attempts?->contains(fn ($attempt): bool => $attempt->completed_at === null && $attempt->revoked_at === null && ! $attempt->isExpired())
                        ? 'Setup in progress'
                        : 'Awaiting setup')
                    : (($mfaPolicy->isMfaRequired($user) && ! $hasConfirmedMfa) ? 'Security setup required' : 'Ready');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->staffProfile?->status ?? 'active',
                    'onboarding_state' => $onboardingState,
                    'office' => $user->staffProfile?->office,
                    'office_label' => self::OFFICE_LABELS[$user->staffProfile?->office ?? '']
                        ?? ($user->staffProfile?->office ?: 'Office not assigned'),
                    'department_code' => $departmentCode,
                    'program_codes' => $programCodes,
                    'avatar' => $user->staffProfile?->avatarProjection()
                        ?? ['type' => 'initials', 'preset' => null, 'url' => null],
                    'roles' => $user->roles->map(fn ($r) => [
                        'name' => $r->name,
                        'display_name' => $display[$r->name] ?? $r->name,
                    ])->values(),
                    'ta_status' => $ta->taStatus($user),
                    'can_delegate_ta' => $ta->canDelegate($actor, $user),
                    'setup_status' => $setup->setupStatus($user),
                    'setup_scopes' => $setup->delegatedScopes($user),
                    'can_delegate_setup' => $setup->canDelegate($actor, $user),
                    'can_manage_user' => $ceiling->canManageUser($actor, $user),
                    'can_issue_voucher' => $ceiling->canResetPassword($actor, $user),
                    'mfa_status' => $hasConfirmedMfa
                        ? 'enrolled'
                        : ($mfaPolicy->isMfaRequired($user) ? 'pending' : 'exempt'),
                    'can_require_mfa' => $ceiling->canManageMfa($actor, $user)
                        && (! $authenticationState?->mfa_required || (bool) $authenticationState?->mfa_exempt),
                    'can_disable_mfa' => $ceiling->canManageMfa($actor, $user)
                        && ($authenticationState?->mfa_required || $mfaPolicy->isMfaRequired($user) || $hasConfirmedMfa),
                    'can_reset_mfa' => $ceiling->canResetPassword($actor, $user)
                        && $hasConfirmedMfa
                        && ! $user->is($actor),
                    'is_self' => $user->id === $actor->id,
                ];
            }),
            'offices' => collect($officeValues)->map(fn (string $value) => [
                'value' => $value,
                'label' => self::OFFICE_LABELS[$value],
            ])->values(),
            'departments' => $departmentOptions,
            'programs' => $programOptions,

            'roles' => collect($ceiling->assignableRoleHelpers($actor))->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'office' => $office,
                'department' => $department,
                'program' => $program,
                'status' => $status,
                'sort' => $sortResult['sort'],
                'dir' => $sortResult['dir'],
            ],
            'staff_readiness' => $staffReadiness->handle($actor)['summary'],
            'can_full_hr' => $ceiling->isFullHr($actor),
            'can_manage_personnel' => $ceiling->canManagePersonnel($actor),
            'can_delegate_ta' => $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_TA_DELEGATE),
            'setup_scope_options' => $setup->scopeOptions(),
            'can_delegate_setup' => $canDelegateSetup,
            'cycles' => AcademicCycle::query()
                ->where('institution_id', $actorScope->institutionId)
                ->orderByDesc('is_active')
                ->orderByDesc('school_year')
                ->orderByDesc('semester')
                ->get(['id', 'code', 'name', 'school_year', 'semester', 'is_active']),
            'actor_office' => $actor->staffProfile?->office ?? 'admin',
        ]);
    }

    public function prepare(Request $request, StaffRoleCeiling $ceiling, StaffAccountReadiness $readiness, CapabilityAuthorizer $authorizer): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null && $ceiling->canManagePersonnel($actor), 403);
        $this->assertActivePersonnelActor($actor);

        return Inertia::render('Staff/Personnel/Prepare', [
            'readiness' => $readiness->handle($actor),
            'can_create' => $ceiling->assignableRoleHelpers($actor) !== [],
            'roles' => collect($ceiling->assignableRoleHelpers($actor))->map(fn (string $name) => [
                'name' => $name,
                'display_name' => CapabilityCatalog::personnelRoleDisplayNames()[$name] ?? $name,
            ])->values(),
            'default_office' => $actor->actorScope()->office ?? 'admin',
            'can_full_hr' => $ceiling->isFullHr($actor),
            'can_assign_department_dean' => $authorizer->decideFresh($actor, CapabilityCatalog::SETUP_PROGRAMS_MANAGE)->allowed,
            'demo_mode_available' => $ceiling->isFullHr($actor) && app()->environment(['local', 'testing']),
            'eligible_existing_heads' => $ceiling->isFullHr($actor)
                ? User::query()
                    ->whereHas('staffProfile', fn ($query) => $query->where('institution_id', $actor->actorScope()->institutionId)->where('office', 'program')->where('status', 'active'))
                    ->whereHas('roles', fn ($query) => $query->where('name', 'program_head'))
                    ->with('staffProfile:id,user_id,program_codes')
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'program_codes' => $user->staffProfile?->program_codes ?? []])
                    ->values()
                : [],
        ]);
    }

    public function previewPreparation(PreviewStaffAccountPreparationRequest $request, PrepareStaffAccountRecipients $preparation): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        return response()->json($preparation->preview($actor, $request->validated()));
    }

    public function commitPreparation(PrepareStaffAccountsRequest $request, PrepareStaffAccountRecipients $preparation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        try {
            $result = $preparation->commit($actor, $request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['preparation' => $exception->getMessage()]);
        }
        if ($result['kind'] === 'batch') {
            return back()->with('preparation_results', $result['results']);
        }

        return redirect()->route('staff.personnel.index')->with('status', $result['message']);
    }

    public function reset(ResetStaffAccountRequest $request, User $user, ResetStaffAccountForSetup $reset): RedirectResponse
    {
        $data = $request->validated();
        $data['operation_ids'] = [(string) $user->id => $data['operation_id']];
        $data['expected_generations'] = [(string) $user->id => $data['expected_generation']];
        $results = $reset->handle($request->user(), [$user], $data, $request);

        return back()->with('reset_results', $results);
    }

    public function resetBatch(ResetStaffAccountsRequest $request, ResetStaffAccountForSetup $reset): RedirectResponse
    {
        $data = $request->validated();
        $targets = User::query()->whereIn('id', $data['targets'])->get();
        $result = $reset->handle($request->user(), $targets->all(), $data, $request);

        return back()->with('reset_results', $result);
    }

    public function create(Request $request, StaffRoleCeiling $ceiling): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null && $ceiling->canManagePersonnel($actor), 403);

        $isHr = $ceiling->isFullHr($actor);
        $office = (string) ($actor->actorScope()->office ?? 'admin');
        $display = CapabilityCatalog::personnelRoleDisplayNames();
        $supportRole = StaffRoleCeiling::supportRoleForOffice($office);

        return Inertia::render('Staff/Personnel/Create', [
            'page_state' => 'ready',
            'roles' => collect($isHr ? $ceiling->permanentRoleHelpers() : [])->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'can_full_hr' => $isHr,
            'default_office' => $office,
            'support_role' => $supportRole === null ? null : [
                'name' => $supportRole,
                'display_name' => $display[$supportRole] ?? $supportRole,
            ],
            'institution_id' => (int) ($actor->actorScope()->institutionId ?? 0),
            'available_programs' => Program::query()
                ->where('is_active', true)
                ->where('institution_id', (int) ($actor->actorScope()->institutionId ?? 0))
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Program $p) => ['code' => $p->code, 'name' => $p->name])
                ->values(),
        ]);
    }

    public function store(StoreStaffAccountRequest $request, CreateStaffAccount $create, StaffRoleCeiling $ceiling): RedirectResponse
    {
        $actor = $request->user();
        $office = $ceiling->resolveCreateOffice($actor, $request->input('office'));
        $roles = $ceiling->resolveCreateRoles($actor, $request->validated(), $office);

        if ($roles === []) {
            return back()->withErrors(['roles' => 'A valid role helper is required for this office.']);
        }

        try {
            $create->handle(
                $actor,
                $request->input('name'),
                $request->input('email'),
                $roles,
                $office,
                (int) ($actor->actorScope()->institutionId ?? 0),
                $request->input('program_codes', []),
                $actor->actorScope()->activeCycleCode,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        return redirect()->route('staff.personnel.index')->with('status', 'Staff account created.');
    }

    public function edit(Request $request, User $user, StaffRoleCeiling $ceiling, StaffMfaPolicy $mfaPolicy): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null && $ceiling->canManagePersonnel($actor), 403);
        abort_unless($user->staffProfile !== null, 404);

        if (! $ceiling->canManageUser($actor, $user)) {
            abort(403);
        }

        $isHr = $ceiling->isFullHr($actor);
        $user->load(['roles:id,name', 'staffProfile', 'authenticationState']);
        $display = CapabilityCatalog::personnelRoleDisplayNames();
        $currentRoles = $user->roles->pluck('name')->values()->all();
        $isSupport = collect($currentRoles)->contains(fn (string $r) => $ceiling->isSupportRole($r));
        $authenticationState = $user->authenticationState;
        $hasConfirmedMfa = $authenticationState?->hasConfirmedTwoFactor() ?? false;

        return Inertia::render('Staff/Personnel/Edit', [
            'page_state' => 'ready',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_pending' => $user->email === null && ! $authenticationState?->activation_completed_at,
                'status' => $user->staffProfile->status,
                'office' => $user->staffProfile->office,
                'program_codes' => $user->staffProfile->program_codes ?? [],
                'roles' => $currentRoles,
                'mfa_status' => $hasConfirmedMfa
                    ? 'enrolled'
                    : ($mfaPolicy->isMfaRequired($user) ? 'pending' : 'exempt'),
                'can_require_mfa' => $ceiling->canManageMfa($actor, $user)
                    && (! $authenticationState?->mfa_required || (bool) $authenticationState?->mfa_exempt),
                'can_disable_mfa' => $ceiling->canManageMfa($actor, $user)
                    && ($authenticationState?->mfa_required || $mfaPolicy->isMfaRequired($user) || $hasConfirmedMfa),
            ],
            'roles' => collect($isHr && ! $isSupport ? $ceiling->permanentRoleHelpers() : [])->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'can_full_hr' => $isHr,
            'is_support' => $isSupport,
            'can_reset_password' => $ceiling->canResetPassword($actor, $user),
            'available_programs' => Program::query()
                ->where('is_active', true)
                ->where('institution_id', (int) ($user->staffProfile->institution_id ?? $actor->actorScope()->institutionId ?? 0))
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Program $p) => ['code' => $p->code, 'name' => $p->name])
                ->values(),
        ]);
    }

    public function update(
        UpdateStaffAccountRequest $request,
        User $user,
        UpdateStaffAssignment $update,
        SyncStaffRoles $syncRoles,
        StaffRoleCeiling $ceiling,
    ): RedirectResponse {
        $actor = $request->user();
        $office = $ceiling->resolveUpdateOffice($actor, $request->input('office'), $user->staffProfile?->office);
        [$roles, $rolesChanged] = $ceiling->resolveUpdateRoles($actor, $user, $request->validated(), $office);

        try {
            DB::transaction(function () use ($request, $actor, $user, $update, $syncRoles, $office, $roles, $rolesChanged): void {
                $update->handle(
                    $actor,
                    $user,
                    $request->input('name'),
                    $request->input('email'),
                    $office,
                    $request->input('program_codes'),
                );
                if ($request->has('roles') || $rolesChanged) {
                    $syncRoles->handle($actor, $user->fresh(), $roles);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        return redirect()->route('staff.personnel.index')->with('status', 'Staff account updated.');
    }

    public function requireMfa(
        RequireStaffMfaRequest $request,
        User $user,
        RequireStaffMfa $action,
    ): RedirectResponse {
        $action->handle($request->user(), $user, $request->input('reason'));

        return back()->with('status', 'MFA requirement has been enforced for '.$user->name.'.');
    }

    public function disableMfa(
        DisableStaffMfaRequest $request,
        User $user,
        DisableStaffMfa $action,
    ): RedirectResponse {
        $action->handle($request->user(), $user, $request->input('reason'));

        return back()->with('status', 'MFA has been turned off for '.$user->name.'.');
    }

    public function deactivate(Request $request, User $user, DeactivateStaffAccount $deactivate): RedirectResponse
    {
        try {
            $deactivate->handle($request->user(), $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['deactivate' => $e->getMessage()]);
        }

        return redirect()->route('staff.personnel.index')->with('status', 'Staff account deactivated.');
    }

    public function activate(Request $request, User $user, ActivateStaffAccount $activate): RedirectResponse
    {
        try {
            $activate->handle($request->user(), $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['activate' => $e->getMessage()]);
        }

        return redirect()->route('staff.personnel.index')->with('status', 'Staff account activated.');
    }

    private function assertActivePersonnelActor(User $actor): void
    {
        $profile = $actor->staffProfile;
        $institutionId = (int) ($actor->actorScope()->institutionId ?? 0);
        abort_unless($profile?->isActive() && (int) $profile->institution_id === $institutionId && $institutionId > 0, 403);
    }
}
