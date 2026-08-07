<?php

namespace App\Http\Controllers\Staff;

use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Staffing\Actions\ActivateStaffAccount;
use App\Staffing\Actions\CreateStaffAccount;
use App\Staffing\Actions\DeactivateStaffAccount;
use App\Staffing\Actions\SyncStaffRoles;
use App\Staffing\Actions\UpdateStaffAssignment;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PersonnelController extends Controller
{
    public function index(Request $request, StaffRoleCeiling $ceiling): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null && $ceiling->canManagePersonnel($actor), 403);

        $query = User::query()
            ->whereHas('staffProfile')
            ->with(['roles:id,name', 'staffProfile']);

        if (! $ceiling->isFullHr($actor)) {
            $query->whereHas('staffProfile', fn ($q) => $q->where('office', $actor->actorScope()->office));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        $assignable = $ceiling->assignableRoleHelpers($actor);
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
            ]),
            'roles' => collect($assignable)->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'filters' => $request->only(['search', 'role']),
            'can_full_hr' => $ceiling->isFullHr($actor),
        ]);
    }

    public function create(Request $request, StaffRoleCeiling $ceiling): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null && $ceiling->canManagePersonnel($actor), 403);

        $assignable = $ceiling->assignableRoleHelpers($actor);
        $display = CapabilityCatalog::personnelRoleDisplayNames();

        return Inertia::render('Staff/Personnel/Create', [
            'page_state' => 'ready',
            'roles' => collect($assignable)->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'can_full_hr' => $ceiling->isFullHr($actor),
            'default_office' => $actor->actorScope()->office ?? 'hr',
            'institution_id' => $actor->actorScope()->institutionId,
        ]);
    }

    public function store(Request $request, CreateStaffAccount $create, StaffRoleCeiling $ceiling): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $assignable = $ceiling->assignableRoleHelpers($actor);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($assignable)],
            'office' => ['required', 'string', 'max:64'],
            'program_codes' => ['sometimes', 'array'],
            'program_codes.*' => ['string', 'max:32'],
        ]);

        try {
            $create->handle(
                $actor,
                $validated['name'],
                $validated['email'],
                $validated['password'],
                $validated['roles'],
                $validated['office'],
                (int) ($actor->actorScope()->institutionId ?? 0),
                $validated['program_codes'] ?? [],
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

        if (! $ceiling->isFullHr($actor) && $user->staffProfile->office !== $actor->actorScope()->office) {
            abort(403);
        }

        $user->load(['roles:id,name', 'staffProfile']);
        $assignable = $ceiling->assignableRoleHelpers($actor);
        $display = CapabilityCatalog::personnelRoleDisplayNames();

        return Inertia::render('Staff/Personnel/Edit', [
            'page_state' => 'ready',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->staffProfile->status,
                'office' => $user->staffProfile->office,
                'program_codes' => $user->staffProfile->program_codes ?? [],
                'roles' => $user->roles->pluck('name')->values(),
            ],
            'roles' => collect($assignable)->map(fn (string $name) => [
                'name' => $name,
                'display_name' => $display[$name] ?? $name,
            ])->values(),
            'can_full_hr' => $ceiling->isFullHr($actor),
        ]);
    }

    public function update(
        Request $request,
        User $user,
        UpdateStaffAssignment $update,
        SyncStaffRoles $syncRoles,
        StaffRoleCeiling $ceiling,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $assignable = $ceiling->assignableRoleHelpers($actor);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($assignable)],
            'office' => ['required', 'string', 'max:64'],
            'program_codes' => ['sometimes', 'array'],
            'program_codes.*' => ['string', 'max:32'],
        ]);

        try {
            $update->handle(
                $actor,
                $user,
                $validated['name'],
                $validated['email'],
                $validated['password'] ?? null,
                $validated['office'],
                $validated['program_codes'] ?? null,
            );
            $syncRoles->handle($actor, $user->fresh(), $validated['roles']);
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
