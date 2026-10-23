<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Communications\AnnouncementSourceAuthorizer;
use App\Communications\PublicPolicySourceAuthorizer;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Models\Foundation\Institution;
use App\Notifications\Queries\NotificationOccurrenceQuery;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use App\Staffing\Support\StaffTargetManagementScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Domain 2: Institutional Setup, Readiness & Governance
 *
 * Acts as the institutional readiness gateway before an academic admission cycle can launch.
 *
 * The Four Foundational Readiness Pillars:
 * 1. Academic Cycle: Year, terms, application windows, and intake capacity.
 * 2. Degree Programs: Strands, prerequisites, and authorized departmental quotas.
 * 3. Examination Architecture: Test rooms, building capacities, and calibrated OMR master keys.
 * 4. Scoring Policies: Aptitude area weights, percentile cut-offs, and neural compatibility models.
 *
 * Pedagogical / Interactive Walkthrough Pairing:
 * - Paired with 'setup-readiness-hub' tour via Xiam NPC (staff-copilot persona).
 */
class SetupDashboardController extends Controller
{
    use HasSetupCapabilities;

    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        AnnouncementSourceAuthorizer $announcementSources,
        PublicPolicySourceAuthorizer $policySources,
        ComputeSetupReadinessQuery $readinessQuery,
    ): Response|RedirectResponse {
        $user = $request->user();
        $caps = $this->capabilities($authorizer, $request);
        $personnelManagement = $user !== null && (
            $authorizer->decideFresh($user, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => (int) Institution::current()->id])->allowed
            || $authorizer->decideFresh($user, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, [
                'institution_id' => (int) Institution::current()->id,
                'office' => (string) ($user->staffProfile?->office ?? ''),
            ])->allowed
            || app(StaffTargetManagementScope::class)->ownedActiveDepartmentIds($user, (int) Institution::current()->id) !== []
        );
        $canManageApplicationSuggestions = $authorizer->allows($user, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, [
            'office' => 'registrar',
            'institution_id' => (int) Institution::current()->getKey(),
        ]);
        $canDecisionSupport = $authorizer->allowsAny($user, [
            CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
            CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
        ]);
        $hasKnowledge = $authorizer->allows($request->user(), CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE)
            || $authorizer->allows($request->user(), CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE);
        $canPlanConsultation = $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN);
        $canManageAnnouncements = $announcementSources->publishers($user) !== [];
        $canManagePolicies = $policySources->publishers($user) !== [];
        $canManageProgramProfiles = $user !== null
            && $user->actorScope()->office === 'program'
            && $user->actorScope()->programCodes !== []
            && $authorizer->allows($user, CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE);
        $canAuthorReportTemplates = collect(DocumentSurfaceRegistry::definitions())
            ->keys()
            ->contains(fn (string $surfaceKey): bool => DocumentSurfaceRegistry::canAuthorSurface($authorizer, $user, $surfaceKey));
        $canAccessNotifications = in_array(
            true,
            app(NotificationOccurrenceQuery::class)->accessibleDomains($user),
            true,
        );
        $canManageCommunications = $canManageAnnouncements || $canManagePolicies;
        if (
            ! $caps['setup_institution']
            && ! $caps['setup_cycles']
            && ! $caps['setup_rooms']
            && ! $caps['setup_programs']
            && ! $caps['requirements']
            && ! $canManageApplicationSuggestions
            && ! $caps['schedule']
            && ! $caps['homepage_manage']
            && ! $canManageProgramProfiles
            && ! $caps['assessment']
            && ! $caps['scoring_omr']
            && ! $caps['booklet']
            && ! $caps['ai_governance']
            && ! $hasKnowledge
            && ! $canPlanConsultation
            && ! $canAuthorReportTemplates
            && ! $canAccessNotifications
            && ! $canManageCommunications
        ) {
            if ($canDecisionSupport) {
                return redirect()->route('setup.decision-support.index');
            }
            abort(403);
        }

        if (
            $caps['setup_institution']
            || $caps['setup_cycles']
            || $caps['setup_rooms']
            || $caps['setup_programs']
            || $caps['homepage_manage']
        ) {
            $userDesk = 'admin';
        } elseif (($caps['requirements'] || $canManageApplicationSuggestions) && ! $caps['assessment']) {
            $userDesk = 'registrar';
        } elseif ($caps['assessment'] || $caps['quota'] || $canPlanConsultation || $canManageProgramProfiles) {
            $userDesk = 'guidance';
        } elseif ($caps['schedule'] || $caps['booklet'] || $caps['scoring_omr']) {
            $userDesk = 'testing';
        } elseif ($caps['ai_governance']) {
            $userDesk = 'system';
        } else {
            $userDesk = 'all';
        }

        return Inertia::render('Setup/Index', [
            'page_state' => 'ready',
            'user_desk' => $userDesk,
            'readiness' => $readinessQuery->handle($request->user()),
            'capabilities' => [
                ...$caps,
                'decision_support' => $canDecisionSupport,
                'application_suggestions' => $canManageApplicationSuggestions,
                'staff_accounts' => $personnelManagement,
                'knowledge' => $hasKnowledge,
                'consultation_policy' => $canPlanConsultation,
                'report_templates' => $canAuthorReportTemplates,
                'program_public_profiles' => $canManageProgramProfiles,
                'notifications' => $canAccessNotifications,
                'examination_notifications' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, ['office' => 'guidance']),
                'results_notifications' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, ['office' => 'guidance']) || $authorizer->allows($user, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, ['office' => 'guidance']),
                'admissions_notifications' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_SETUP_MANAGE, ['office' => 'registrar']) || $caps['requirements'],
                'communications' => $canManageCommunications,
                'announcements' => $canManageAnnouncements,
                'public_policies' => $canManagePolicies,
            ],
        ]);
    }
}
