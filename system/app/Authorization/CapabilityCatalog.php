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

    /**
     * Single gate for exam session management pages (`/exams/sessions*`) AND Nexi
     * `schedule.exam_session` Kernel rows. Not a separate AI permission.
     * Part of the Test Administrator capability set (D-016) — not a Spatie role.
     */
    public const GUIDANCE_EXAM_SCHEDULE = 'guidance.exam.schedule';

    public const GUIDANCE_PROCTOR_ASSIGN = 'guidance.exam.proctor.assign';

    public const GUIDANCE_SCORING_MANUAL = 'guidance.scoring.manual';

    public const GUIDANCE_SCORING_OMR = 'guidance.scoring.omr';

    public const GUIDANCE_RESULTS_PREPARE = 'guidance.results.prepare';

    public const GUIDANCE_RESULTS_RELEASE = 'guidance.results.release';

    public const GUIDANCE_CONSULTATION_DECIDE = 'guidance.consultation.decide';

    public const GUIDANCE_CONSULTATION_PLAN = 'guidance.consultation.plan';

    public const REGISTRAR_ENROLLMENT_ELIGIBILITY = 'registrar.enrollment.eligibility';

    /**
     * D-089–D-093 admission-support capability set (GF-04/GF-21).
     * Scope: PROGRAM for PH keys, OWN for applicant keys; office duty for Registrar stamp.
     */
    public const PROGRAM_HEAD_VIEW = 'program.head.view';

    public const PROGRAM_HEAD_FOLLOW_UP_RESOLVE = 'program.head.follow_up.resolve';

    public const PROGRAM_HEAD_SELECTION_DECLINE = 'program.head.selection.decline';

    public const PROGRAM_HEAD_MESSAGE = 'program.head.message';

    public const GUIDANCE_SELECTION_MANAGE = 'guidance.selection.manage';

    public const GUIDANCE_FOLLOW_UP_MANAGE = 'guidance.follow_up.manage';

    /** Server-owned case relationship decides participants (PH<->Guidance internal, or current selection/follow-up applicant thread). */
    public const RECORD_THREAD_PARTICIPATE = 'record.thread.participate';

    /** Applicant-owned program choice (OWN scope) — granted directly at login, like exam slots. */
    public const APPLICANT_PROGRAM_SELECT = 'applicant.program.select';

    /** Registrar-only enrolling/enrolled stamps + reasoned correction (OFFICE duty). */
    public const REGISTRAR_ENROLLMENT_STAMP = 'registrar.enrollment.stamp';

    /** Authenticated staff QR resolver gate; never grants business authority (D-092). */
    public const APPLICANT_QR_RESOLVE = 'applicant.qr.resolve';

    /**
     * Grant/revoke the Test Administrator set on eligible Guidance clerk/support (D-016).
     * Intentionally outside {@see testAdministratorCapabilities()} so delegates cannot chain-grant.
     */
    public const GUIDANCE_TA_DELEGATE = 'guidance.ta.delegate';

    public const HR_STAFF_MANAGE = 'hr.staff.manage';

    public const OFFICE_SUPPORT_STAFF_MANAGE = 'office.support_staff.manage';

    /**
     * Applicant-owned exam slot capabilities (Slice 9 — applicant.choose_slot skill).
     * Not role-granted: granted directly to applicant portal users at login
     * (see Admission\Support\ApplicantCapabilityGrants).
     */
    public const APPLICANT_EXAM_SLOTS_VIEW = 'applicant.exam.slots.view';

    public const APPLICANT_EXAM_SLOT_RESERVE = 'applicant.exam.slot.reserve';

    /**
     * Own-data notification bell (list / read / unread-count). Granted to every
     * staff role AND applicant portal users — the bell is own-data, not a
     * role-scoped surface (GF-21; GF-22 §5.5).
     */
    public const NOTIFY_READ_OWN = 'notify.read.own';

    public const BREAK_GLASS_SUPPORT = 'break_glass.support';

    /** Knowledge corpus manage (create/edit drafts, quarantine upload). */
    public const KNOWLEDGE_SOURCES_MANAGE = 'knowledge.sources.manage';

    /** Knowledge corpus approve/publish/revoke — admin/counselor only (clerks manage drafts). */
    public const KNOWLEDGE_SOURCES_APPROVE = 'knowledge.sources.approve';

    /**
     * D-016 Test Administrator capability set (NOT a base role).
     * Guidance Counselor holds this inherently; clerk/support may receive subsets or the
     * full set only via approved Guidance delegation — never via HR role naming.
     *
     * Exam session pages + Nexi exam Assistant both require {@see GUIDANCE_EXAM_SCHEDULE}
     * from this set (same GF-21 key — one implementation).
     *
     * @return list<string>
     */
    public static function testAdministratorCapabilities(): array
    {
        return [
            self::GUIDANCE_EXAM_SCHEDULE,
            self::GUIDANCE_PROCTOR_ASSIGN,
            self::GUIDANCE_SCORING_MANUAL,
            self::GUIDANCE_SCORING_OMR,
            self::GUIDANCE_RESULTS_PREPARE,
            self::GUIDANCE_RESULTS_RELEASE,
            self::GUIDANCE_CONSULTATION_DECIDE,
            self::GUIDANCE_CONSULTATION_PLAN,
        ];
    }

    /**
     * Application capabilities that authorize exam-session management (page + Kernel/Nexi).
     *
     * @return list<string>
     */
    public static function examSessionManagementCapabilities(): array
    {
        return [self::GUIDANCE_EXAM_SCHEDULE];
    }

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
            self::REGISTRAR_ENROLLMENT_ELIGIBILITY,
            self::PROGRAM_HEAD_VIEW,
            self::PROGRAM_HEAD_FOLLOW_UP_RESOLVE,
            self::PROGRAM_HEAD_SELECTION_DECLINE,
            self::PROGRAM_HEAD_MESSAGE,
            self::GUIDANCE_SELECTION_MANAGE,
            self::GUIDANCE_FOLLOW_UP_MANAGE,
            self::RECORD_THREAD_PARTICIPATE,
            self::APPLICANT_PROGRAM_SELECT,
            self::REGISTRAR_ENROLLMENT_STAMP,
            self::APPLICANT_QR_RESOLVE,
            self::REGISTRAR_SETUP_MANAGE,
            self::PROGRAM_QUOTA_MANAGE,
            self::GUIDANCE_ASSESSMENT_CATALOG,
            self::GUIDANCE_EXAM_SCHEDULE,
            self::GUIDANCE_PROCTOR_ASSIGN,
            self::GUIDANCE_SCORING_MANUAL,
            self::GUIDANCE_SCORING_OMR,
            self::GUIDANCE_RESULTS_PREPARE,
            self::GUIDANCE_RESULTS_RELEASE,
            self::GUIDANCE_CONSULTATION_DECIDE,
            self::GUIDANCE_CONSULTATION_PLAN,
            self::GUIDANCE_TA_DELEGATE,
            self::HR_STAFF_MANAGE,
            self::OFFICE_SUPPORT_STAFF_MANAGE,
            self::BREAK_GLASS_SUPPORT,
            self::KNOWLEDGE_SOURCES_MANAGE,
            self::KNOWLEDGE_SOURCES_APPROVE,
            self::APPLICANT_EXAM_SLOTS_VIEW,
            self::APPLICANT_EXAM_SLOT_RESERVE,
            self::NOTIFY_READ_OWN,
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
            self::REGISTRAR_ENROLLMENT_ELIGIBILITY,
            self::REGISTRAR_ENROLLMENT_STAMP,
            self::APPLICANT_QR_RESOLVE,
            self::NOTIFY_READ_OWN,
        ];

        // Inherent Test Administrator (D-016) — single source; do not re-list TA keys ad hoc.
        $inherentTestAdministrator = [
            self::FOUNDATION_PROBE,
            self::NOTIFY_READ_OWN,
            ...self::testAdministratorCapabilities(),
            self::GUIDANCE_SELECTION_MANAGE,
            self::GUIDANCE_FOLLOW_UP_MANAGE,
            self::RECORD_THREAD_PARTICIPATE,
            self::APPLICANT_QR_RESOLVE,
        ];

        // Delegated exam-ops subset (not full TA): may use exam session pages + Nexi schedule
        // skill when GUIDANCE_EXAM_SCHEDULE is present — same key as counselor, no AI bypass.
        $delegatedExamScheduleOps = [
            self::FOUNDATION_PROBE,
            self::NOTIFY_READ_OWN,
            self::GUIDANCE_EXAM_SCHEDULE,
            self::GUIDANCE_SCORING_MANUAL,
            self::GUIDANCE_RESULTS_PREPARE,
            self::APPLICANT_QR_RESOLVE,
        ];

        return [
            'hr_administrator' => [
                self::FOUNDATION_PROBE,
                self::NOTIFY_READ_OWN,
                self::HR_STAFF_MANAGE,
            ],
            'registrar_admin' => [
                ...$registrarOps,
                self::REGISTRAR_SETUP_MANAGE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::KNOWLEDGE_SOURCES_APPROVE,
            ],
            'registrar_clerk' => [
                ...$registrarOps,
                self::REGISTRAR_SETUP_MANAGE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
            ],
            'registrar_support_staff' => [
                ...$registrarOps,
                self::KNOWLEDGE_SOURCES_MANAGE,
            ],
            'guidance_counselor' => [
                ...$inherentTestAdministrator,
                self::GUIDANCE_TA_DELEGATE,
                self::REGISTRAR_SETUP_MANAGE,
                self::GUIDANCE_ASSESSMENT_CATALOG,
                self::PROGRAM_QUOTA_MANAGE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::KNOWLEDGE_SOURCES_APPROVE,
            ],
            'guidance_clerk' => [
                ...$delegatedExamScheduleOps,
                self::REGISTRAR_SETUP_MANAGE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
            ],
            'guidance_support_staff' => [
                self::FOUNDATION_PROBE,
                self::NOTIFY_READ_OWN,
                self::GUIDANCE_EXAM_SCHEDULE,
                self::GUIDANCE_SCORING_MANUAL,
                self::APPLICANT_QR_RESOLVE,
                self::KNOWLEDGE_SOURCES_MANAGE,
            ],
            'program_head' => [
                self::FOUNDATION_PROBE,
                self::NOTIFY_READ_OWN,
                self::PROGRAM_QUOTA_MANAGE,
                self::PROGRAM_HEAD_VIEW,
                self::PROGRAM_HEAD_FOLLOW_UP_RESOLVE,
                self::PROGRAM_HEAD_SELECTION_DECLINE,
                self::PROGRAM_HEAD_MESSAGE,
                self::RECORD_THREAD_PARTICIPATE,
                self::APPLICANT_QR_RESOLVE,
            ],
        ];
    }
}
