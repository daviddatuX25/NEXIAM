<?php

namespace App\Authorization;

/**
 * Stable GF-04/GF-21 capability keys. Role labels are not listed here.
 */
final class CapabilityCatalog
{
    public const COMMUNICATIONS_ANNOUNCEMENTS_MANAGE = 'communications.announcements.manage';

    public const COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH = 'communications.announcements.publish';

    public const COMMUNICATIONS_ANNOUNCEMENTS_CAMPUS = 'communications.announcements.campus';

    public const PROGRAM_PUBLIC_PROFILE_MANAGE = 'program.public_profile.manage';

    public const PROGRAM_PUBLIC_PROFILE_PUBLISH = 'program.public_profile.publish';

    public const COMMUNICATIONS_POLICIES_MANAGE = 'communications.policies.manage';

    public const COMMUNICATIONS_POLICIES_PUBLISH = 'communications.policies.publish';

    public const FOUNDATION_PROBE = 'foundation.probe';

    public const REGISTRAR_APPLICATIONS_VIEW = 'registrar.applications.view';

    public const REGISTRAR_APPLICATIONS_ACCEPT = 'registrar.applications.accept';

    public const REGISTRAR_APPLICATIONS_DISMISS = 'registrar.applications.dismiss';

    public const REGISTRAR_APPLICATIONS_CORRECT = 'registrar.applications.correct';

    public const REGISTRAR_APPLICATIONS_IMPORT = 'registrar.applications.import';

    /**
     * @deprecated Use SETUP_INSTITUTION_MANAGE or SETUP_PROGRAMS_MANAGE.
     */
    public const REGISTRAR_SETUP_MANAGE = 'registrar.setup.manage';

    public const SETUP_INSTITUTION_MANAGE = 'setup.institution.manage';

    public const SETUP_PROGRAMS_MANAGE = 'setup.programs.manage';

    public const SETUP_CYCLES_MANAGE = 'setup.cycles.manage';

    public const SETUP_ROOMS_MANAGE = 'setup.rooms.manage';

    public const REGISTRAR_REQUIREMENTS_MANAGE = 'registrar.requirements.manage';

    public const COMMUNICATIONS_HOMEPAGE_MANAGE = 'communications.homepage.manage';

    public const SETUP_INSTITUTION_DELEGATE = 'setup.institution.delegate';

    public const EXECUTIVE_DASHBOARD_VIEW = 'executive.dashboard.view';

    public const AI_GOVERNANCE_INSTITUTION_MANAGE = 'ai.governance.institution.manage';

    public const AI_GOVERNANCE_OFFICE_MANAGE = 'ai.governance.office.manage';

    public const AUDIT_EXPORT = 'audit.export';

    /** Program quota write — Program Head (PROGRAM scope) + Guidance Counselor TA path (D-087). */
    public const PROGRAM_QUOTA_MANAGE = 'program.quota.manage';

    public const PROGRAM_QUOTA_READ = 'program.quota.read';

    public const ADMISSION_BENCHMARK_GOVERNANCE_VIEW = 'admission.benchmark.governance.view';

    public const ADMISSION_BENCHMARK_DRAFT_EDIT = 'admission.benchmark.draft.edit';

    public const ADMISSION_BENCHMARK_PREVIEW = 'admission.benchmark.preview';

    public const ADMISSION_BENCHMARK_PUBLISH = 'admission.benchmark.publish';

    public const ADMISSION_BENCHMARK_ACTIVATE = 'admission.benchmark.activate';

    public const ADMISSION_ADVISING_NOTE_VIEW = 'admission.advising-note.view';

    public const ADMISSION_ADVISING_NOTE_DRAFT_EDIT = 'admission.advising-note.draft.edit';

    public const ADMISSION_ADVISING_NOTE_PUBLISH = 'admission.advising-note.publish';

    public const ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ = 'admission.governance-discussion.benchmark.read';

    public const ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND = 'admission.governance-discussion.benchmark.send';

    public const ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE = 'admission.governance-discussion.benchmark.manage-state';

    public const ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ = 'admission.governance-discussion.quota.read';

    public const ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND = 'admission.governance-discussion.quota.send';

    public const ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE = 'admission.governance-discussion.quota.manage-state';

    public const ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ = 'admission.governance-discussion.advising.read';

    public const ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND = 'admission.governance-discussion.advising.send';

    public const ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE = 'admission.governance-discussion.advising.manage-state';

    /**
     * Test-sensitive assessment catalog (aptitude areas; later rating scales / result templates).
     * Guidance Counselor (inherent TA) only by default — not Registrar setup.
     */
    public const GUIDANCE_ASSESSMENT_CATALOG = 'guidance.assessment.catalog';

    /** Staff may author Booklet keys and manage Booklet lifecycle. */
    public const GUIDANCE_BOOKLET_MANAGE = 'guidance.assessment.booklet.manage';

    /** Dedicated least-privilege grant for plaintext stored question content. */
    public const GUIDANCE_BOOKLET_CONTENT = 'guidance.assessment.booklet.content';

    /** Descriptive alias retained for callers that name the protected seam explicitly. */
    public const GUIDANCE_ASSESSMENT_BOOKLET_CONTENT = self::GUIDANCE_BOOKLET_CONTENT;

    /**
     * Single gate for exam session management pages (`/exams/sessions*`) AND Xiam
     * `schedule.exam_session` Kernel rows. Not a separate AI permission.
     * Part of the Test Administrator capability set (D-016) — not a Spatie role.
     */
    public const GUIDANCE_EXAM_SCHEDULE = 'guidance.exam.schedule';

    public const GUIDANCE_SCORING_MANUAL = 'guidance.scoring.manual';

    public const GUIDANCE_SCORING_OMR = 'guidance.scoring.omr';

    public const GUIDANCE_SCORING_IMPORT = 'guidance.scoring.import';

    public const GUIDANCE_RESULTS_PREPARE = 'guidance.results.prepare';

    public const GUIDANCE_RESULTS_RELEASE = 'guidance.results.release';

    public const GUIDANCE_CONSULTATION_DECIDE = 'guidance.consultation.decide';

    public const GUIDANCE_CONSULTATION_PLAN = 'guidance.consultation.plan';

    public const REGISTRAR_ENROLLMENT_ELIGIBILITY = 'registrar.enrollment.eligibility';

    public const REGISTRAR_ADMISSION_CLEARANCE_CONFIRM = 'registrar.admission.clearance.confirm';

    public const REGISTRAR_ADMISSION_CLEARANCE_REOPEN = 'registrar.admission.clearance.reopen';

    public const REGISTRAR_DOCUMENTS_VERIFY = 'registrar.documents.verify';

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

    /**
     * @deprecated Retired legacy stamp capability (D-091 cutover).
     * Use clearance capabilities instead:
     * - REGISTRAR_DOCUMENTS_VERIFY
     * - REGISTRAR_ADMISSION_CLEARANCE_CONFIRM
     * - REGISTRAR_ADMISSION_CLEARANCE_REOPEN
     */
    public const REGISTRAR_ENROLLMENT_STAMP = 'registrar.enrollment.stamp';

    /** Authenticated staff QR resolver gate; never grants business authority (D-092). */
    public const APPLICANT_QR_RESOLVE = 'applicant.qr.resolve';

    /** Grant/revoke the Test Administrator set on eligible Guidance clerk/support (D-016). */
    public const GUIDANCE_TA_DELEGATE = 'guidance.ta.delegate';

    /** Local/testing-only operator access to the interactive demo stage deck. */
    public const DEMO_STAGE_CONTROL = 'demo.stage.control';

    public const HR_STAFF_MANAGE = 'hr.staff.manage';

    public const OFFICE_SUPPORT_STAFF_MANAGE = 'office.support_staff.manage';

    /**
     * Applicant-owned exam slot capabilities (Slice 9 — applicant.choose_slot skill).
     * Not role-granted: granted directly to applicant portal users at login
     * (see Admission\Support\ApplicantCapabilityGrants).
     */
    public const APPLICANT_EXAM_SLOTS_VIEW = 'applicant.exam.slots.view';

    public const APPLICANT_EXAM_SLOT_RESERVE = 'applicant.exam.slot.reserve';

    public const APPLICANT_OFFICE_INQUIRY_USE = 'applicant.office-inquiry.use';

    public const OFFICE_INQUIRY_HANDLE = 'admission.office-inquiry.handle';

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

    /** Knowledge corpus governance — disposition review, retention, and reconciliation. */
    public const KNOWLEDGE_GOVERNANCE = 'knowledge.governance';

    public const REGISTRAR_REPORTS_VIEW = 'registrar.reports.view';

    public const GUIDANCE_REPORTS_VIEW = 'guidance.reports.view';

    public const AUDIT_LOGS_VIEW = 'audit.logs.view';

    public const AUDIT_LOGS_VIEW_OWN = 'audit.logs.view.own';

    /**
     * D-016 Test Administrator capability set (NOT a base role).
     * Guidance Counselor holds this inherently; clerk/support may receive subsets or the
     * full set only via approved Guidance delegation — never via HR role naming.
     *
     * Exam session pages + Xiam exam Assistant both require {@see GUIDANCE_EXAM_SCHEDULE}
     * from this set (same GF-21 key — one implementation).
     *
     * @return list<string>
     */
    /**
     * Original 9-key Test Administrator operational subset retained for backward-compatible
     * legacy designation detection and backfill.
     *
     * @return list<string>
     */
    public static function legacyTestAdministratorCapabilities(): array
    {
        return [
            self::GUIDANCE_EXAM_SCHEDULE,
            self::GUIDANCE_SCORING_MANUAL,
            self::GUIDANCE_SCORING_OMR,
            self::GUIDANCE_RESULTS_PREPARE,
            self::GUIDANCE_RESULTS_RELEASE,
            self::GUIDANCE_CONSULTATION_DECIDE,
            self::GUIDANCE_CONSULTATION_PLAN,
            self::GUIDANCE_SCORING_IMPORT,
        ];
    }

    public static function testAdministratorCapabilities(): array
    {
        return [
            ...self::legacyTestAdministratorCapabilities(),
            self::GUIDANCE_ASSESSMENT_CATALOG,
            self::GUIDANCE_BOOKLET_MANAGE,
            self::GUIDANCE_BOOKLET_CONTENT,
            self::GUIDANCE_SELECTION_MANAGE,
            self::GUIDANCE_FOLLOW_UP_MANAGE,
            self::PROGRAM_HEAD_MESSAGE,
            self::RECORD_THREAD_PARTICIPATE,
            self::GUIDANCE_REPORTS_VIEW,
        ];
    }

    /**
     * Bounded admission governance granted only with an explicit full TA designation.
     *
     * @return list<string>
     */
    public static function testAdministratorGovernanceCapabilities(): array
    {
        return [
            self::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
            self::ADMISSION_BENCHMARK_DRAFT_EDIT,
            self::ADMISSION_BENCHMARK_PREVIEW,
            self::ADMISSION_BENCHMARK_PUBLISH,
            self::ADMISSION_BENCHMARK_ACTIVATE,
            self::ADMISSION_ADVISING_NOTE_VIEW,
            self::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
            self::ADMISSION_ADVISING_NOTE_PUBLISH,
            self::PROGRAM_QUOTA_READ,
            self::PROGRAM_QUOTA_MANAGE,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
        ];
    }

    /**
     * Capabilities granted to an eligible Guidance designee holding the complete TA set.
     *
     * @return list<string>
     */
    public static function delegatedTestAdministratorCapabilities(): array
    {
        return array_values(array_unique([
            ...self::testAdministratorCapabilities(),
            ...self::testAdministratorGovernanceCapabilities(),
        ]));
    }

    /**
     * Application capabilities that authorize exam-session management (page + Kernel/Xiam).
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
            self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
            self::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
            self::COMMUNICATIONS_ANNOUNCEMENTS_CAMPUS,
            self::PROGRAM_PUBLIC_PROFILE_MANAGE,
            self::PROGRAM_PUBLIC_PROFILE_PUBLISH,
            self::COMMUNICATIONS_POLICIES_MANAGE,
            self::COMMUNICATIONS_POLICIES_PUBLISH,
            self::APPLICANT_OFFICE_INQUIRY_USE,
            self::OFFICE_INQUIRY_HANDLE,
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
            self::REGISTRAR_APPLICATIONS_IMPORT,
            self::GUIDANCE_SCORING_IMPORT,
            self::RECORD_THREAD_PARTICIPATE,
            self::APPLICANT_PROGRAM_SELECT,
            self::REGISTRAR_ENROLLMENT_STAMP,
            self::APPLICANT_QR_RESOLVE,
            self::SETUP_INSTITUTION_MANAGE,
            self::SETUP_CYCLES_MANAGE,
            self::SETUP_ROOMS_MANAGE,
            self::REGISTRAR_REQUIREMENTS_MANAGE,
            self::COMMUNICATIONS_HOMEPAGE_MANAGE,
            self::SETUP_PROGRAMS_MANAGE,
            self::SETUP_INSTITUTION_DELEGATE,
            self::EXECUTIVE_DASHBOARD_VIEW,
            self::AI_GOVERNANCE_INSTITUTION_MANAGE,
            self::AI_GOVERNANCE_OFFICE_MANAGE,
            self::AUDIT_EXPORT,
            self::REGISTRAR_SETUP_MANAGE,
            self::PROGRAM_QUOTA_MANAGE,
            self::PROGRAM_QUOTA_READ,
            self::GUIDANCE_ASSESSMENT_CATALOG,
            self::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
            self::ADMISSION_BENCHMARK_DRAFT_EDIT,
            self::ADMISSION_BENCHMARK_PREVIEW,
            self::ADMISSION_BENCHMARK_PUBLISH,
            self::ADMISSION_BENCHMARK_ACTIVATE,
            self::ADMISSION_ADVISING_NOTE_VIEW,
            self::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
            self::ADMISSION_ADVISING_NOTE_PUBLISH,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
            self::GUIDANCE_BOOKLET_MANAGE,
            self::GUIDANCE_BOOKLET_CONTENT,
            self::GUIDANCE_EXAM_SCHEDULE,
            self::GUIDANCE_SCORING_MANUAL,
            self::GUIDANCE_SCORING_OMR,
            self::GUIDANCE_RESULTS_PREPARE,
            self::GUIDANCE_RESULTS_RELEASE,
            self::GUIDANCE_CONSULTATION_DECIDE,
            self::GUIDANCE_CONSULTATION_PLAN,
            self::GUIDANCE_TA_DELEGATE,
            self::HR_STAFF_MANAGE,
            self::OFFICE_SUPPORT_STAFF_MANAGE,
            self::KNOWLEDGE_SOURCES_MANAGE,
            self::KNOWLEDGE_SOURCES_APPROVE,
            self::KNOWLEDGE_GOVERNANCE,
            self::BREAK_GLASS_SUPPORT,
            self::APPLICANT_EXAM_SLOTS_VIEW,
            self::APPLICANT_EXAM_SLOT_RESERVE,
            self::NOTIFY_READ_OWN,
            self::REGISTRAR_REPORTS_VIEW,
            self::GUIDANCE_REPORTS_VIEW,
            self::AUDIT_LOGS_VIEW,
            self::AUDIT_LOGS_VIEW_OWN,
            self::DEMO_STAGE_CONTROL,
            self::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM,
            self::REGISTRAR_ADMISSION_CLEARANCE_REOPEN,
            self::REGISTRAR_DOCUMENTS_VERIFY,
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
            'institutional_admin',
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
            'institutional_admin' => 'Institutional Administrator',
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
        $registrarSupportCounterOps = [
            self::FOUNDATION_PROBE,
            self::REGISTRAR_APPLICATIONS_VIEW,
            self::REGISTRAR_APPLICATIONS_ACCEPT,
            self::REGISTRAR_APPLICATIONS_CORRECT,
            self::REGISTRAR_ENROLLMENT_ELIGIBILITY,
            self::REGISTRAR_ENROLLMENT_STAMP,
            self::APPLICANT_QR_RESOLVE,
            self::NOTIFY_READ_OWN,
            self::REGISTRAR_ADMISSION_CLEARANCE_CONFIRM,
            self::REGISTRAR_DOCUMENTS_VERIFY,
            self::OFFICE_INQUIRY_HANDLE,
        ];

        $registrarOps = [
            ...$registrarSupportCounterOps,
            self::REGISTRAR_APPLICATIONS_DISMISS,
            self::REGISTRAR_APPLICATIONS_IMPORT,
        ];

        // Inherent Test Administrator (D-016) — single source; do not re-list TA keys ad hoc.
        $inherentTestAdministrator = [
            self::FOUNDATION_PROBE,
            self::NOTIFY_READ_OWN,
            ...self::delegatedTestAdministratorCapabilities(),
            self::APPLICANT_QR_RESOLVE,
        ];

        // Ordinary Guidance operations; scoring and result preparation require explicit TA delegation.
        $guidanceOfficeOps = [
            self::FOUNDATION_PROBE,
            self::NOTIFY_READ_OWN,
            self::GUIDANCE_EXAM_SCHEDULE,
            self::APPLICANT_QR_RESOLVE,
        ];
        $governanceParticipation = [
            self::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
            self::ADMISSION_BENCHMARK_DRAFT_EDIT,
            self::ADMISSION_BENCHMARK_PREVIEW,
            self::ADMISSION_ADVISING_NOTE_VIEW,
            self::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
            self::PROGRAM_QUOTA_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
            self::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
        ];

        $guidanceGovernance = [
            ...$governanceParticipation,
            self::ADMISSION_BENCHMARK_PUBLISH,
            self::ADMISSION_BENCHMARK_ACTIVATE,
            self::ADMISSION_ADVISING_NOTE_PUBLISH,
        ];

        return [
            'hr_administrator' => [
                self::FOUNDATION_PROBE,
                self::NOTIFY_READ_OWN,
                self::HR_STAFF_MANAGE,
                self::EXECUTIVE_DASHBOARD_VIEW,
                self::AUDIT_LOGS_VIEW,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'institutional_admin' => [
                self::FOUNDATION_PROBE,
                self::NOTIFY_READ_OWN,
                self::SETUP_INSTITUTION_MANAGE,
                self::SETUP_CYCLES_MANAGE,
                self::SETUP_ROOMS_MANAGE,
                self::SETUP_PROGRAMS_MANAGE,
                self::REGISTRAR_REQUIREMENTS_MANAGE,
                self::COMMUNICATIONS_HOMEPAGE_MANAGE,
                self::SETUP_INSTITUTION_DELEGATE,
                self::EXECUTIVE_DASHBOARD_VIEW,
                self::AI_GOVERNANCE_INSTITUTION_MANAGE,
                self::AI_GOVERNANCE_OFFICE_MANAGE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
                self::COMMUNICATIONS_ANNOUNCEMENTS_CAMPUS,
                self::COMMUNICATIONS_POLICIES_MANAGE,
                self::COMMUNICATIONS_POLICIES_PUBLISH,
                self::PROGRAM_QUOTA_READ,
                self::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::KNOWLEDGE_SOURCES_APPROVE,
                self::KNOWLEDGE_GOVERNANCE,
                self::AUDIT_LOGS_VIEW,
                self::AUDIT_EXPORT,
                self::DEMO_STAGE_CONTROL,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'registrar_admin' => [
                ...$registrarOps,
                self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
                self::COMMUNICATIONS_POLICIES_MANAGE,
                self::REGISTRAR_REQUIREMENTS_MANAGE,
                self::COMMUNICATIONS_POLICIES_PUBLISH,
                self::AI_GOVERNANCE_OFFICE_MANAGE,
                self::KNOWLEDGE_SOURCES_APPROVE,
                self::KNOWLEDGE_GOVERNANCE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::REGISTRAR_REPORTS_VIEW,
                self::REGISTRAR_ADMISSION_CLEARANCE_REOPEN,
                self::AUDIT_LOGS_VIEW,
                self::DEMO_STAGE_CONTROL,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'registrar_clerk' => [
                ...$registrarOps,
                self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                self::REGISTRAR_REQUIREMENTS_MANAGE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
                self::COMMUNICATIONS_POLICIES_MANAGE,
                self::COMMUNICATIONS_POLICIES_PUBLISH,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::REGISTRAR_REPORTS_VIEW,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'registrar_support_staff' => [
                ...$registrarSupportCounterOps,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'guidance_counselor' => [
                ...$inherentTestAdministrator,
                ...$guidanceGovernance,
                self::OFFICE_INQUIRY_HANDLE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
                self::COMMUNICATIONS_POLICIES_MANAGE,
                self::COMMUNICATIONS_POLICIES_PUBLISH,
                self::GUIDANCE_TA_DELEGATE,
                self::AI_GOVERNANCE_OFFICE_MANAGE,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::KNOWLEDGE_SOURCES_APPROVE,
                self::KNOWLEDGE_GOVERNANCE,
                self::AUDIT_LOGS_VIEW,
                self::DEMO_STAGE_CONTROL,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'guidance_clerk' => [
                ...$guidanceOfficeOps,
                self::OFFICE_INQUIRY_HANDLE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
                self::COMMUNICATIONS_POLICIES_MANAGE,
                self::COMMUNICATIONS_POLICIES_PUBLISH,
                self::OFFICE_SUPPORT_STAFF_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::GUIDANCE_REPORTS_VIEW,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'guidance_support_staff' => [
                ...$guidanceOfficeOps,
                self::OFFICE_INQUIRY_HANDLE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                self::KNOWLEDGE_SOURCES_MANAGE,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
            'program_head' => [
                ...$governanceParticipation,
                self::PROGRAM_QUOTA_MANAGE,
                self::ADMISSION_BENCHMARK_ACTIVATE,
                self::ADMISSION_ADVISING_NOTE_PUBLISH,
                self::NOTIFY_READ_OWN,
                self::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                self::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
                self::PROGRAM_PUBLIC_PROFILE_MANAGE,
                self::PROGRAM_PUBLIC_PROFILE_PUBLISH,
                self::COMMUNICATIONS_POLICIES_MANAGE,
                self::COMMUNICATIONS_POLICIES_PUBLISH,
                self::PROGRAM_HEAD_VIEW,
                self::PROGRAM_HEAD_FOLLOW_UP_RESOLVE,
                self::PROGRAM_HEAD_SELECTION_DECLINE,
                self::PROGRAM_HEAD_MESSAGE,
                self::RECORD_THREAD_PARTICIPATE,
                self::APPLICANT_QR_RESOLVE,
                self::AUDIT_LOGS_VIEW_OWN,
            ],
        ];
    }
}
