<?php

namespace App\Http\Controllers\Staff;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Staff\Queries\StaffDashboardProjection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdminDashboardController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer, StaffDashboardProjection $projection): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $authorizer->decideFresh($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW, ['institution_id' => $user->actorScope()->institutionId])->allowed, 403);

        return Inertia::render('Staff/Home', [
            'dashboard' => $projection->for($user),
            'staff' => [
                'name' => $user->name,
                'email' => $user->email,
                'capabilities' => $user->getAllPermissions()->pluck('name')->values()->all(),
                'scope' => $user->actorScope()->toArray(),
            ],
        ]);
    }
}
