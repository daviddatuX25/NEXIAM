<?php

namespace App\Http\Controllers\Staff;

use App\Authorization\CapabilityAuthorizer;
use App\Http\Controllers\Controller;
use App\Staff\Queries\StaffDashboardProjection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Domain 8: Executive Leadership & Cross-Department Operations
 *
 * Central operations cockpit for university leadership, deans, and admissions directors.
 *
 * Telemetry & Governance:
 * - Consolidates the real-time institutional intake funnel across all operational stages:
 *   Applicants Ingested -> Exam Sessions Booked -> OMR Scored -> Guidance Consulted -> Matriculated.
 * - Flags bottleneck stages (e.g. unadvised qualifiers, unreleased score batches) for rapid triage.
 *
 * Pedagogical / Interactive Walkthrough Pairing:
 * - Paired with 'staff-operations-dashboard' tour via Xiam NPC (staff-copilot persona).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, StaffDashboardProjection $projection, CapabilityAuthorizer $authorizer): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->applicantAccount()->exists() && ! $user->staffProfile()->exists()) {
            return redirect()->route('applicant.schedule');
        }

        return Inertia::render('Staff/Home', [
            'staff' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'capabilities' => $user?->getAllPermissions()->pluck('name')->values() ?? [],
                'scope' => $user?->actorScope()->toArray() ?? null,
            ],
            'dashboard' => $user ? $projection->for($user) : null,
        ]);
    }
}
