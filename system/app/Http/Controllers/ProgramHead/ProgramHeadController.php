<?php

namespace App\Http\Controllers\ProgramHead;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\Program;
use App\ProgramHead\Queries\ProgramHeadCandidateListsQuery;
use App\ProgramHead\Queries\ProgramHeadDashboardQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramHeadController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $user = $request->user();

        if (! $authorizer->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW)) {
            return Inertia::render('ProgramHead/Index', ['page_state' => 'denied', 'programs' => []]);
        }

        $programCodes = $user->actorScope()->programCodes;
        $programs = Program::query()
            ->whereIn('code', $programCodes)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name']);

        return Inertia::render('ProgramHead/Index', [
            'page_state' => $programs->isEmpty() ? 'empty' : 'ready',
            'programs' => $programs,
            'cycle' => $user->actorScope()->activeCycleCode,
        ]);
    }

    public function show(
        Request $request,
        Program $program,
        CapabilityAuthorizer $authorizer,
        ProgramHeadDashboardQuery $dashboard,
        ProgramHeadCandidateListsQuery $lists,
    ): Response {
        $user = $request->user();

        if (! $authorizer->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW, ['program' => $program->code])) {
            return Inertia::render('ProgramHead/ProgramShow', ['page_state' => 'denied', 'program' => ['code' => $program->code]]);
        }

        $cycle = $user->actorScope()->activeCycleCode;

        return Inertia::render('ProgramHead/ProgramShow', [
            'page_state' => 'ready',
            'dashboard' => $dashboard->for($program, $cycle),
            'lists' => $lists->for($program, $cycle),
        ]);
    }

    public function applicantShow(
        Request $request,
        Program $program,
        \App\Models\Admission\AdmissionAttempt $attempt,
        CapabilityAuthorizer $authorizer,
    ): \Illuminate\Http\RedirectResponse {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::PROGRAM_HEAD_VIEW, ['program' => $program->code])) {
            return redirect()->route('program-head.show', $program->code);
        }

        // Founder 2026-08-15: PH applicant detail lives on the shared
        // admission-support workspace (one inner page per applicant).
        return redirect()->route('admission-support.show', $attempt);
    }
}
