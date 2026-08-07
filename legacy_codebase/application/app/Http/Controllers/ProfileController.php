<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\UserCredential;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the profile edit form.
     */
    public function edit(): Response
    {
        $user = Auth::user();

        return Inertia::render('Profile/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'googleLinked' => $user->hasGoogleLinked(),
        ]);
    }

    /**
     * Update the user's profile information (name and email).
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $changes = [];

        if ($validated['name'] !== $user->name) {
            $changes['name'] = ['from' => $user->name, 'to' => $validated['name']];
            $user->name = $validated['name'];
        }

        if ($validated['email'] !== $user->email) {
            $changes['email'] = ['from' => $user->email, 'to' => $validated['email']];

            // Unlink Google credential when email changes
            $user->credentials()
                ->where('provider', UserCredential::PROVIDER_GOOGLE)
                ->delete();

            $user->email = $validated['email'];
            $user->email_verified_at = null;
        }

        if (empty($changes)) {
            return back()->with('success', 'No changes to save.');
        }

        $user->save();

        Log::info('Profile updated', ['user_id' => $user->id, 'changes' => array_keys($changes)]);
        app(AuditService::class)->log('profile.updated', $user::class, $user->id, [], [
            'changes' => $changes,
        ], 'Profile updated');

        return back()->with('success', 'Profile updated.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->password = Hash::make($validated['password']);
        $user->save();

        Log::info('Password changed', ['user_id' => $user->id]);
        app(AuditService::class)->log('profile.password_changed', $user::class, $user->id, [], [], 'Password changed');

        return back()->with('success', 'Password changed.');
    }
}
