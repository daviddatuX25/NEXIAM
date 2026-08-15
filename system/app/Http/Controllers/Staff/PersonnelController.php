<?php

namespace App\Http\Controllers\Staff;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffAccountRequest;
use App\Http\Requests\Staff\UpdateStaffAccountRequest;
use App\Models\User;
use App\Staffing\Actions\ActivateStaffAccount;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Actions\DeactivateStaffAccount;
use App\Staffing\Actions\SyncStaffRoles;
use App\Staffing\Actions\UpdateStaffAssignment;
use App\Staffing\Support\EligibleTaRecipients;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request, StaffRoleCeiling $ceiling, EligibleTaRecipients $ta, CapabilityAuthorizer $authorizer): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $users = User::query()
            ->whereHas('staffProfile')
            ->with(['roles:id,name', 'staffProfile'])
            ->when(! $ceiling->isFullHr($actor), fn ($q) => $q->whereHas(
                'staffProfile',
                fn ($q) => $q->where('office', $actor->actorScope()->office)
            ))
            ->when($request->input('search'), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->input('role'), fn ($q, $role) => $q->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $display = CapabilityCatalog::personnelRoleDisplayNames();

        return Inertia::render('Staff/Personnel/Index', [
            'page_state' => $users->total() === 0 ? 'empty' : 'ready',
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->staffProfile?->status ?? 'active',
                'office' => $user->staffProfile?->office,
                'roles' => $user->roles->map(fn ($r) => [
                    'name' => $r->name,
                    'display_name' => $display[$r->name] ?? $r->name,
                ])->values(),
                'ta_status' => $ta->taStatus($user),
                'can_delegate_ta' => $ta->canDelegate($actor, $user),
                'can_manage_user' => $ceiling->canManageUser($actor, $user),
                'is_self' => $user->id === $actor->id,
            ]),
            'roles' => collect($ceiling->assignableRoleHelpers($actor))->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'filters' => $request->only(['search', 'role']),
            'can_full_hr' => $ceiling->isFullHr($actor),
            'can_manage_personnel' => $ceiling->canManagePersonnel($actor),
            'can_delegate_ta' => $authorizer->allows($actor, CapabilityCatalog::GUIDANCE_TA_DELEGATE),
        ]);
    }

    public function create(Request $request, StaffRoleCeiling $ceiling): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null && $ceiling->canManagePersonnel($actor), 403);

        $isHr = $ceiling->isFullHr($actor);
        $office = (string) ($actor->actorScope()->office ?? 'hr');
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
            'institution_id' => $actor->actorScope()->institutionId,
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
                $request->input('password'),
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

    public function edit(Request $request, User $user, StaffRoleCeiling $ceiling): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null && $ceiling->canManagePersonnel($actor), 403);
        abort_unless($user->staffProfile !== null, 404);

        if (! $ceiling->canManageUser($actor, $user)) {
            abort(403);
        }

        $isHr = $ceiling->isFullHr($actor);
        $user->load(['roles:id,name', 'staffProfile']);
        $display = CapabilityCatalog::personnelRoleDisplayNames();
        $currentRoles = $user->roles->pluck('name')->values()->all();
        $isSupport = collect($currentRoles)->contains(fn (string $r) => $ceiling->isSupportRole($r));

        return Inertia::render('Staff/Personnel/Edit', [
            'page_state' => 'ready',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->staffProfile->status,
                'office' => $user->staffProfile->office,
                'program_codes' => $user->staffProfile->program_codes ?? [],
                'roles' => $currentRoles,
            ],
            'roles' => collect($isHr && ! $isSupport ? $ceiling->permanentRoleHelpers() : [])->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'can_full_hr' => $isHr,
            'is_support' => $isSupport,
            'can_reset_password' => $ceiling->canResetPassword($actor, $user),
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
        [$roles, $rolesChanged] = $ceiling->resolveUpdateRoles($actor, $user, $request->validated());

        try {
            $update->handle(
                $actor,
                $user,
                $request->input('name'),
                $request->input('email'),
                $request->input('password'),
                $office,
                $request->input('program_codes'),
            );
            if ($rolesChanged) {
                $syncRoles->handle($actor, $user->fresh(), $roles);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        return redirect()->route('staff.personnel.index')->with('status', 'Staff account updated.');
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
}
