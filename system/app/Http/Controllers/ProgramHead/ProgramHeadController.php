<?php

namespace App\Http\Controllers\ProgramHead;

use App\Admission\Queries\CaseConversationQuery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadCandidateListsQuery;
use App\ProgramHead\Queries\ProgramHeadDashboardQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Domain 6: Academic Department Quota & Decision Verdicts
 *
 * Empowers departmental academic leaders (Program Heads and Deans) to oversee their
 * candidate pools, monitor approved quota ceilings, and record official admissions verdicts.
 *
 * Architectural Flow & Governance:
 * - Scope Gate: Strictly scoped to the academic programs assigned to the authenticated user.
 * - Candidate Pool Review: Evaluates applicants based on entrance scores, high school strand, and interview notes.
 * - Verdict Decisions: Endorses candidates as Accepted, Waitlisted, or Referred to an alternative degree track.
 *
 * Pedagogical / Interactive Walkthrough Pairing:
 * - Paired with 'program-head-overview' tour via Xiam NPC (staff-copilot persona).
 */
class ProgramHeadController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer): Response|RedirectResponse
    {
        $user = User::query()->findOrFail($request->user()->id);
        $scope = $user->actorScope();
        $cycle = $this->activeCycleFor($user);

        if ($cycle === null || ! $authorizer->decideFresh($user, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
            'institution_id' => $scope->institutionId,
            'cycle' => $cycle->code,
        ])->allowed) {
            return Inertia::render('ProgramHead/Index', ['page_state' => 'denied', 'programs' => []]);
        }

        $programCodes = $scope->programCodes;
        $canManagePublicProfile = $authorizer->decideFresh($user, CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE, [
            'institution_id' => $scope->institutionId,
            'office' => 'program',
        ])->allowed;
        $programs = Program::query()
            ->where('institution_id', $scope->institutionId)
            ->whereIn('code', $programCodes)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Program $program): array => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'public_details_href' => $canManagePublicProfile ? '/setup/programs/'.$program->id.'/public-details' : null,
            ]);

        $isDean = $this->isDean($user);
        if (! $isDean && $programs->isNotEmpty()) {
            return redirect()->route('program-head.show', $programs->first()['code']);
        }

        $department = $this->resolveDepartment($user);

        return Inertia::render('ProgramHead/Index', [
            'page_state' => $programs->isEmpty() ? 'empty' : 'ready',
            'programs' => $programs,
            'cycle' => $cycle->code,
            'is_dean' => $isDean,
            'department' => $department ? [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ] : null,
        ]);
    }

    public function show(
        Request $request,
        Program $program,
        CapabilityAuthorizer $authorizer,
        ProgramHeadDashboardQuery $dashboard,
        ProgramHeadCandidateListsQuery $lists,
        GovernanceAuthorizer $governance,
        CaseConversationQuery $conversations,
    ): Response|\Symfony\Component\HttpFoundation\Response {
        $user = User::query()->findOrFail($request->user()->id);
        $cycle = $this->activeCycleFor($user);

        if ($cycle === null || ! $this->canViewProgram($user, $program, $cycle, $authorizer)) {
            $cycleCode = $cycle?->code ?? $user->actorScope()->activeCycleCode;

            return Inertia::render('ProgramHead/ProgramShow', [
                'page_state' => 'denied',
                'program' => ['code' => $program->code, 'name' => $program->name],
                'can_manage_quotas' => false,
                'active_cycle_code' => $cycleCode,
            ])->toResponse($request)->setStatusCode(403);
        }

        $links = [];
        if ($governance->allows($user, CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW, $program, $cycle)) {
            $links['studio'] = route('setup.decision-support.index', ['program_id' => $program->id, 'cycle_id' => $cycle->id]);
        }
        if ($governance->allows($user, CapabilityCatalog::PROGRAM_QUOTA_READ, $program, $cycle)
            || $governance->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle)) {
            $links['quotas'] = route('setup.quotas', ['program_id' => $program->id, 'cycle_id' => $cycle->id]);
        }
        if ($authorizer->decideFresh($user, CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE, [
            'institution_id' => $user->actorScope()->institutionId,
            'office' => 'program',
        ])->allowed) {
            $links['public_details'] = '/setup/programs/'.$program->id.'/public-details';
        }

        $search = $request->query('conversation_search', $request->query('search'));
        $cursor = $request->query('cursor');
        $applicantConversations = $conversations->summariesForProgram(
            $program,
            $cycle->code,
            $user,
            is_string($search) ? $search : null,
            is_string($cursor) ? $cursor : null,
        );

        $isDean = $this->isDean($user);
        $department = $this->resolveDepartment($user);

        return Inertia::render('ProgramHead/ProgramShow', [
            'page_state' => 'ready',
            'dashboard' => $dashboard->for($program, $cycle->code),
            'lists' => $lists->for($program, $cycle->code),
            'setup_links' => $links,
            'applicant_conversations' => $applicantConversations,
            'is_dean' => $isDean,
            'department' => $department ? [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ] : null,
        ]);
    }

    public function applicantShow(
        Request $request,
        Program $program,
        AdmissionAttempt $attempt,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $user = User::query()->findOrFail($request->user()->id);
        $cycle = $this->activeCycleFor($user);
        if ($cycle === null || ! $this->canViewProgram($user, $program, $cycle, $authorizer)) {
            return redirect()->route('program-head.show', $program->code);
        }

        return redirect()->route('admission-support.show', [
            'attempt' => $attempt->id,
            'program_code' => $program->code,
            'cycle_code' => $cycle->code,
            'tab' => 'record',
        ]);
    }

    private function activeCycleFor(User $actor): ?AcademicCycle
    {
        $scope = $actor->actorScope();

        return AcademicCycle::query()
            ->where('institution_id', $scope->institutionId)
            ->where('code', $scope->activeCycleCode)
            ->where('is_active', true)
            ->first();
    }

    private function canViewProgram(User $actor, Program $program, AcademicCycle $cycle, CapabilityAuthorizer $authorizer): bool
    {
        $scope = $actor->actorScope();

        return $program->is_active
            && $scope->institutionId !== null
            && (int) $program->institution_id === (int) $scope->institutionId
            && (int) $cycle->institution_id === (int) $scope->institutionId
            && in_array($program->code, $scope->programCodes, true)
            && $authorizer->decideFresh($actor, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
                'institution_id' => $program->institution_id,
                'program' => $program->code,
                'cycle' => $cycle->code,
            ])->allowed;
    }

    private function isDean(User $actor): bool
    {
        return Department::query()->where('institution_id', $actor->actorScope()->institutionId)
            ->where('dean_user_id', $actor->id)->where('is_active', true)->exists();
    }

    private function resolveDepartment(User $actor): ?Department
    {
        return Department::query()
            ->where('institution_id', $actor->actorScope()->institutionId)
            ->where('dean_user_id', $actor->id)->where('is_active', true)
            ->select(['id', 'code', 'name'])->first();
    }
}
