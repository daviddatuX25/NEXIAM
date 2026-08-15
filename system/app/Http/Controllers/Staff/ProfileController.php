<?php

namespace App\Http\Controllers\Staff;

use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service staff profile. Read-only for now; self password change and
 * self account management land here in a follow-up cut (founder lock 2026-08-10).
 */
class ProfileController extends Controller
{
    public function show(): Response
    {
        $actor = request()->user();

        abort_unless($actor !== null, 403);

        $actor->load(['roles:id,name', 'staffProfile']);

        $display = CapabilityCatalog::personnelRoleDisplayNames();

        return Inertia::render('Staff/Profile/Show', [
            'page_state' => 'ready',
            'user' => [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
                'status' => $actor->staffProfile?->status ?? 'active',
                'office' => $actor->staffProfile?->office,
                'roles' => $actor->roles->map(fn ($r) => [
                    'name' => $r->name,
                    'display_name' => $display[$r->name] ?? $r->name,
                ])->values(),
            ],
        ]);
    }
}
