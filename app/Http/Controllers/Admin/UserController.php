<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCredential;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()->with('roles:id,name,display_name');

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

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name', 'display_name']),
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('name')->get(['id', 'name', 'display_name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $roleIds = Role::whereIn('name', $validated['roles'])->pluck('id');
        $user->roles()->sync($roleIds);

        Log::info('User created', ['user_id' => $user->id, 'actor_id' => $request->user()->id]);
        app(AuditService::class)->log('user.created', User::class, $user->id, [], [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $validated['roles'],
        ], "User {$user->email} created");

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        $user->load('roles:id,name,display_name');

        return Inertia::render('Admin/Users/Edit', [
            'user'         => $user,
            'roles'        => Role::orderBy('name')->get(['id', 'name', 'display_name']),
            'googleLinked' => $user->hasGoogleLinked(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            if ($validated['email'] !== $user->email) {
                $user->credentials()
                    ->where('provider', UserCredential::PROVIDER_GOOGLE)
                    ->delete();
            }
            $user->email = $validated['email'];
        }
        if (! empty($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        if (isset($validated['roles'])) {
            $newRoles = $validated['roles'];
            $currentUser = $request->user();
            if ($user->id === $currentUser->id && $user->hasRole('super_admin')) {
                if (! in_array('super_admin', $newRoles)) {
                    $newRoles[] = 'super_admin';
                }
            }
            $roleIds = Role::whereIn('name', $newRoles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        Log::info('User updated', ['user_id' => $user->id, 'actor_id' => $request->user()->id]);
        app(AuditService::class)->log('user.updated', User::class, $user->id, [], [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $validated['roles'] ?? null,
        ], "User {$user->email} updated");

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            abort(403, 'Cannot delete your own account.');
        }

        $email = $user->email;
        $user->roles()->detach();
        $user->delete();

        Log::info('User deleted', ['user_id' => $user->id, 'actor_id' => $request->user()->id]);
        app(AuditService::class)->log('user.deleted', User::class, $user->id, [
            'email' => $email,
        ], [], "User {$email} deleted");

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
