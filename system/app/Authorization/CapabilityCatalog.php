<?php

namespace App\Authorization;

/**
 * Stable GF-04/GF-21 capability keys. Role labels are not listed here.
 */
final class CapabilityCatalog
{
    public const FOUNDATION_PROBE = 'foundation.probe';

    public const REGISTRAR_APPLICATIONS_VIEW = 'registrar.applications.view';

    public const REGISTRAR_APPLICATIONS_ACCEPT = 'registrar.applications.accept';

    public const REGISTRAR_APPLICATIONS_DISMISS = 'registrar.applications.dismiss';

    public const REGISTRAR_APPLICATIONS_CORRECT = 'registrar.applications.correct';

    /**
     * Shared institutional catalog setup (D-087).
     * Historical key name — granted to Registrar Admin/Clerk and Guidance Counselor/Clerk.
     */
    public const REGISTRAR_SETUP_MANAGE = 'registrar.setup.manage';

    /** Program quota write — Program Head (PROGRAM scope) + Guidance Counselor TA path (D-087). */
    public const PROGRAM_QUOTA_MANAGE = 'program.quota.manage';

    /**
     * Test-sensitive assessment catalog (aptitude areas; later rating scales / result templates).
     * Guidance Counselor (inherent TA) only by default — not Registrar setup.
     */
    public const GUIDANCE_ASSESSMENT_CATALOG = 'guidance.assessment.catalog';

    public const GUIDANCE_EXAM_SCHEDULE = 'guidance.exam.schedule';

    public const GUIDANCE_PROCTOR_ASSIGN = 'guidance.exam.proctor.assign';

    public const GUIDANCE_SCORING_MANUAL = 'guidance.scoring.manual';

    public const GUIDANCE_RESULTS_PREPARE = 'guidance.results.prepare';

    public const GUIDANCE_RESULTS_RELEASE = 'guidance.results.release';

    public const GUIDANCE_CONSULTATION_DECIDE = 'guidance.consultation.decide';

    public const HR_STAFF_MANAGE = 'hr.staff.manage';

    public const OFFICE_SUPPORT_STAFF_MANAGE = 'office.support_staff.manage';

    public const BREAK_GLASS_SUPPORT = 'break_glass.support';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::FOUNDATION_PROBE,
            self::REGISTRAR_APPLICATIONS_VIEW,
            self::REGISTRAR_APPLICATIONS_ACCEPT,
            self::REGISTRAR_APPLICATIONS_DISMISS,
            self::REGISTRAR_APPLICATIONS_CORRECT,
            self::REGISTRAR_SETUP_MANAGE,
            self::PROGRAM_QUOTA_MANAGE,
            self::GUIDANCE_ASSESSMENT_CATALOG,
            self::GUIDANCE_EXAM_SCHEDULE,
            self::GUIDANCE_PROCTOR_ASSIGN,
            self::GUIDANCE_SCORING_MANUAL,
            self::GUIDANCE_RESULTS_PREPARE,
            self::GUIDANCE_RESULTS_RELEASE,
            self::GUIDANCE_CONSULTATION_DECIDE,
            self::HR_STAFF_MANAGE,
            self::OFFICE_SUPPORT_STAFF_MANAGE,
            self::BREAK_GLASS_SUPPORT,
        ];
    }

    /**
     * D-014 permanent + support role helper names (never Proctor/TA as roles).
     *
     * @return list<string>
     */
    public static function personnelRoleHelpers(): array
    {
        return [
            'hr_administrator',
            'registrar_admin',
            'registrar_clerk',
            'registrar_support_staff',
            'guidance_counselor',
            'guidance_clerk',
            'guidance_support_staff',
            'program_head',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function personnelRoleDisplayNames(): array
    {
        return [
            'hr_administrator' => 'Staffing / HR Administrator',
            'registrar_admin' => 'Registrar Administrator',
            'registrar_clerk' => 'Registrar Clerk',
            'registrar_support_staff' => 'Registrar Support Staff',
            'guidance_counselor' => 'Guidance Counselor',
            'guidance_clerk' => 'Guidance Clerk',
            'guidance_support_staff' => 'Guidance Support Staff',
            'program_head' => 'Program Head',
        ];
    }

    /**
     * Spatie role helpers → capability grants. Roles are assignment helpers only.
     *
     * @return array<string, list<string>>
     */
    public static function roleHelperGrants(): array
    {
        $registrarOps = [
            self::FOUNDATION_PROBE,
            self::REGISTRAR_APPLICATIONS_VIEW,
            self::REGISTRAR_APPLICATIONS_ACCEPT,
            self::REGISTRAR_APPLICATIONS_DISMISS,
            self::REGISTRAR_APPLICATIONS_CORRECT,
        ];

        $guidanceOps = [
            self::FOUNDATION_PROBE,
            self::GUIDANCE_EXAM_SCHEDULE,
            self::GUIDANCE_PROCTOR_ASSIGN,
            self::GUIDANCE_SCORING_MANUAL,
            self::GUIDANCE_RESULTS_PREPARE,
            self::GUIDANCE_RESULTS_RELEASE,
            self::GUIDANCE_CONSULTATION_DECIDE,
        ];

        return [
            'hr_administrator' => [
                self::FOUNDATION_PROBE,
                self::HR_STAFF_MANAGE,
            ],
            'registrar_admin' => [
                ...$registrarOps,
                self::REGISTRAR_SETUP_MANAGE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
            ],
            'registrar_clerk' => [
                ...$registrarOps,
                self::REGISTRAR_SETUP_MANAGE,
            ],
            'registrar_support_staff' => $registrarOps,
            'guidance_counselor' => [
                ...$guidanceOps,
                self::REGISTRAR_SETUP_MANAGE,
                self::GUIDANCE_ASSESSMENT_CATALOG,
                self::PROGRAM_QUOTA_MANAGE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
            ],
            'guidance_clerk' => [
                self::FOUNDATION_PROBE,
                self::REGISTRAR_SETUP_MANAGE,
                self::GUIDANCE_EXAM_SCHEDULE,
                self::GUIDANCE_SCORING_MANUAL,
                self::GUIDANCE_RESULTS_PREPARE,
            ],
            'guidance_support_staff' => [
                self::FOUNDATION_PROBE,
                self::GUIDANCE_EXAM_SCHEDULE,
                self::GUIDANCE_SCORING_MANUAL,
            ],
            'program_head' => [
                self::FOUNDATION_PROBE,
                self::PROGRAM_QUOTA_MANAGE,
            ],
        ];
    }
}
