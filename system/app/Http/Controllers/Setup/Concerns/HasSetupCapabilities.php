<?php

namespace App\Http\Controllers\Setup\Concerns;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use Illuminate\Http\Request;

/**
 * Shared Setup-surface gates (GF-21). The Setup section spans three capability
 * scopes with different owners:
 *
 * - institutional catalogs → `setup.institution.manage` or `setup.programs.manage`
 * - public policy authoring → the existing scoped communications policy capabilities
 * - aptitude catalog + scoring → `guidance.assessment.catalog` (Test Administrator)
 * - program quotas → `program.quota.manage` (Program Head / Guidance TA path)
 *
 * Route middleware never uses Spatie role labels; every page gate delegates to
 * the CapabilityAuthorizer decision authority.
 */
trait HasSetupCapabilities
{
    /**
     * @return array{setup: bool, setup_institution: bool, setup_cycles: bool, setup_rooms: bool, setup_programs: bool, requirements: bool, schedule: bool, homepage_manage: bool, assessment: bool, scoring_omr: bool, booklet: bool, quota: bool, ai_governance: bool}
     */
    private function capabilities(CapabilityAuthorizer $authorizer, Request $request): array
    {
        $user = $request->user();
        $setupInstitution = $authorizer->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE);
        $setupCycles = $setupInstitution || $authorizer->allows($user, CapabilityCatalog::SETUP_CYCLES_MANAGE);
        $setupRooms = $setupInstitution || $authorizer->allows($user, CapabilityCatalog::SETUP_ROOMS_MANAGE);
        $setupPrograms = $authorizer->allows($user, CapabilityCatalog::SETUP_PROGRAMS_MANAGE);
        $requirements = $setupInstitution || $authorizer->allows($user, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE);
        $schedule = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
        $homepageManage = $authorizer->allows($user, CapabilityCatalog::COMMUNICATIONS_HOMEPAGE_MANAGE);
        $scoringOmr = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
            'office' => 'guidance',
            'institution_id' => (int) Institution::current()->id,
        ]);
        $aiGovernance = $authorizer->allowsAny($user, [
            CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE,
            CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE,
        ]);

        return [
            'setup' => $setupInstitution || $setupCycles || $setupRooms || $setupPrograms,
            'setup_institution' => $setupInstitution,
            'setup_cycles' => $setupCycles,
            'setup_rooms' => $setupRooms,
            'setup_programs' => $setupPrograms,
            'requirements' => $requirements,
            'schedule' => $schedule,
            'homepage_manage' => $homepageManage,
            'assessment' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
            'scoring_omr' => $scoringOmr,
            'booklet' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE),
            'quota' => $authorizer->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE),
            'ai_governance' => $aiGovernance,
        ];
    }

    private function requireSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::SETUP_INSTITUTION_MANAGE), 403);
    }

    private function requireInstitutionSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::SETUP_INSTITUTION_MANAGE), 403);
    }

    private function requireProgramSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::SETUP_PROGRAMS_MANAGE), 403);
    }

    private function requireCycleSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless(
            $authorizer->allowsAny($request->user(), [
                CapabilityCatalog::SETUP_CYCLES_MANAGE,
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            ]),
            403,
        );
    }

    private function requireRoomSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless(
            $authorizer->allowsAny($request->user(), [
                CapabilityCatalog::SETUP_ROOMS_MANAGE,
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            ]),
            403,
        );
    }

    private function requireRegistrarRequirements(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless(
            $authorizer->allowsAny($request->user(), [
                CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE,
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            ]),
            403,
        );
    }

    private function requireAssessment(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), 403);
    }
}
