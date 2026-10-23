<?php

namespace App\Support\Navigation;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Communications\AnnouncementSourceAuthorizer;
use App\Communications\PublicPolicySourceAuthorizer;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\Models\Foundation\Department;
use App\Models\User;

/**
 * Server-authored staff sidebar contract (GF-22).
 *
 * Sections group capability-filtered items and mirror the staff's operational
 * lifecycle. Sections with no authorized items are omitted.
 */
final class StaffNavigationBuilder
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AnnouncementSourceAuthorizer $announcementSources,
        private readonly PublicPolicySourceAuthorizer $policySources,
        private readonly KnowledgeSourceAccess $knowledgeAccess,
    ) {}

    /**
     * @return list<array{label: string|null, key?: string, collapsible?: bool, default_collapsed?: bool, items: list<array{key: string, href: string, label: string, icon: string, match: string, badge_key?: string}>}>
     */
    public function for(?User $user): array
    {
        $sections = [];

        $sections[] = [
            'label' => null,
            'items' => [
                [
                    'key' => 'home',
                    'href' => '/staff',
                    'label' => 'Dashboard',
                    'icon' => 'LayoutDashboard',
                    'match' => 'exact',
                ],
            ],
        ];

        if ($user === null) {
            return $sections;
        }
        $hasManagedAnnouncementSource = $this->announcementSources->publishers($user) !== [];
        $hasManagedPolicySource = $this->policySources->publishers($user) !== [];

        $staffProfile = $user->staffProfile()->first();
        $isAdminOffice = $staffProfile !== null && $staffProfile->office === 'admin';
        $canViewExecDashboard = $this->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW);
        $canManageSetup = $this->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE)
            || $this->allows($user, CapabilityCatalog::SETUP_CYCLES_MANAGE)
            || $this->allows($user, CapabilityCatalog::SETUP_ROOMS_MANAGE)
            || $this->allows($user, CapabilityCatalog::SETUP_PROGRAMS_MANAGE)
            || $this->allows($user, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE);
        $canAuthorDocumentTemplates = collect(DocumentSurfaceRegistry::definitions())->keys()->contains(fn (string $surfaceKey): bool => DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, $surfaceKey));

        if ($this->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW)) {
            $scope = $user->actorScope();
            $isDean = Department::query()->where('institution_id', $scope->institutionId)
                ->where('dean_user_id', $user->id)->where('is_active', true)->exists();

            if ($isDean) {
                $sections[] = [
                    'label' => 'My Department',
                    'items' => [[
                        'key' => 'my_programs',
                        'badge_key' => 'my_programs',
                        'href' => '/staff/programs',
                        'label' => 'My Programs',
                        'icon' => 'GraduationCap',
                        'match' => 'prefix',
                    ]],
                ];
            } else {
                $programCodes = array_values(array_filter($scope->programCodes, 'is_string'));
                $items = $programCodes !== []
                    ? array_map(static fn (string $code): array => [
                        'key' => 'my_program_'.strtolower($code),
                        'badge_key' => 'my_program_'.strtolower($code),
                        'href' => '/staff/programs/'.$code,
                        'label' => $code,
                        'icon' => 'GraduationCap',
                        'match' => 'prefix',
                    ], $programCodes)
                    : [[
                        'key' => 'my_program',
                        'href' => '/staff/programs',
                        'label' => 'My Program',
                        'icon' => 'GraduationCap',
                        'match' => 'prefix',
                    ]];

                $sections[] = [
                    'label' => 'My Program',
                    'items' => $items,
                ];
            }
        }

        $applications = $this->when($this->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW), [
            'key' => 'admissions',
            'badge_key' => 'admissions',
            'href' => '/admissions',
            'label' => 'Applications',
            'icon' => 'ClipboardList',
            'match' => 'prefix',
        ]);

        if ($applications !== []) {
            $sections[] = ['label' => 'Application', 'items' => $applications];
        }

        $examination = array_merge(
            $this->when($this->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities()), [
                'key' => 'exam_scheduling',
                'badge_key' => 'exam_scheduling',
                'href' => '/exams/sessions',
                'label' => 'Exam Scheduling',
                'icon' => 'Calendar',
                'match' => 'prefix',
            ]),
            [
                [
                    'key' => 'exam_monitoring',
                    'badge_key' => 'exam_monitoring',
                    'href' => '/exams/monitoring',
                    'label' => 'Exam Monitoring',
                    'icon' => 'Activity',
                    'match' => 'prefix',
                ],
                [
                    'key' => 'scoring',
                    'badge_key' => 'scoring',
                    'href' => route('scoring.index', absolute: false),
                    'label' => 'Scoring',
                    'icon' => 'FileCheck',
                    'match' => 'prefix',
                ],
            ],
        );

        $examination = array_values(array_filter($examination, fn (array $item) => $this->canSeeAssessment($user, $item)));

        if ($examination !== []) {
            $sections[] = ['label' => 'Examination', 'items' => $examination];
        }

        $releaseResults = [
            [
                'key' => 'result_documents',
                'badge_key' => 'result_documents',
                'href' => '/release',
                'label' => 'Printing of Results',
                'icon' => 'Printer',
                'match' => 'prefix',
            ],
            [
                'key' => 'consultation_scheduling',
                'badge_key' => 'consultation_scheduling',
                'href' => '/consultation/sessions',
                'label' => 'Release Scheduling',
                'icon' => 'Calendar',
                'match' => 'prefix',
            ],
            [
                'key' => 'consultation_release',
                'badge_key' => 'consultation_release',
                'href' => '/consultation',
                'label' => 'Release Day',
                'icon' => 'MessagesSquare',
                'match' => 'prefix',
            ],
        ];

        $releaseResults = array_values(array_filter(
            $releaseResults,
            fn (array $item) => $item['href'] === '/release'
                ? $this->canSeeAssessment($user, $item)
                : $this->canSeeConsultation($user, $item),
        ));

        if ($releaseResults !== []) {
            $sections[] = ['label' => 'Release of Results', 'items' => $releaseResults];
        }

        $registrar = $this->when($this->allows($user, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY), [
            'key' => 'admission_clearance',
            'badge_key' => 'admission_clearance',
            'href' => '/registrar/enrollment-eligibility',
            'label' => 'Admission Clearance',
            'icon' => 'BadgeCheck',
            'match' => 'prefix',
        ]);

        if ($registrar !== []) {
            $sections[] = ['label' => 'Admission Clearance', 'items' => $registrar];
        }

        $institutionalGovernance = array_values(array_merge(
            $this->when(($isAdminOffice || $canViewExecDashboard) && $this->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE), [
                'key' => 'institution_profile',
                'href' => '/setup/institution',
                'label' => 'Institution Profile',
                'icon' => 'Building2',
                'match' => 'prefix',
            ]),
            $this->when(($isAdminOffice || $canViewExecDashboard)
                && ($this->allows($user, CapabilityCatalog::SETUP_CYCLES_MANAGE)
                    || $this->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE)), [
                        'key' => 'academic_cycles',
                        'href' => '/setup/cycles',
                        'label' => 'Cycles',
                        'icon' => 'Calendar',
                        'match' => 'prefix',
                    ]),
            [[
                'key' => 'staff_personnel',
                'href' => '/staff/personnel',
                'label' => (($isAdminOffice || $canViewExecDashboard)
                    && ($this->allows($user, CapabilityCatalog::HR_STAFF_MANAGE)
                        || $this->allows($user, CapabilityCatalog::SETUP_INSTITUTION_DELEGATE)))
                    ? 'Personnel & Staff'
                    : 'Staff',
                'icon' => 'Users',
                'match' => 'prefix',
            ]],
            $this->when(($isAdminOffice || $canViewExecDashboard) && $this->allowsAny($user, [
                CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE,
                CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE,
            ]), [
                'key' => 'ai_governance',
                'href' => '/setup/ai-center',
                'label' => 'AI Governance',
                'icon' => 'Settings',
                'match' => 'prefix',
            ]),
            $this->when($this->allows($user, CapabilityCatalog::COMMUNICATIONS_HOMEPAGE_MANAGE), [
                'key' => 'homepage_studio',
                'href' => '/communications/homepage',
                'label' => 'Homepage Studio',
                'icon' => 'BookOpen',
                'match' => 'prefix',
            ]),
            $this->when($this->allows($user, CapabilityCatalog::AUDIT_LOGS_VIEW) || $this->allows($user, CapabilityCatalog::AUDIT_LOGS_VIEW_OWN), [
                'key' => 'audit_logs',
                'href' => '/staff/logs',
                'label' => ($isAdminOffice || $canViewExecDashboard)
                    ? 'Global Audit Logs'
                    : ($this->allows($user, CapabilityCatalog::AUDIT_LOGS_VIEW) ? 'Office Audit Logs' : 'My Audit Trail'),
                'icon' => 'Shield',
                'match' => 'prefix',
            ]),
        ));

        if ($institutionalGovernance !== []) {
            $sections[] = [
                'key' => 'governance',
                'label' => 'Governance',
                'collapsible' => true,
                'default_collapsed' => ! $isAdminOffice,
                'items' => $institutionalGovernance,
            ];
        }

        $hasOfficeInquiryAccess = $this->allows($user, CapabilityCatalog::OFFICE_INQUIRY_HANDLE)
            && $staffProfile !== null
            && app(ApplicantOfficeInquiryAccess::class)->isAvailable($user);
        $hasCaseThreadAccess = $this->allows($user, CapabilityCatalog::RECORD_THREAD_PARTICIPATE)
            && $staffProfile !== null
            && $staffProfile->isActive()
            && $staffProfile->deactivated_at === null
            && in_array($staffProfile->office, ['guidance', 'program'], true);
        $communications = array_values(array_merge(
            $this->when($hasOfficeInquiryAccess || $hasCaseThreadAccess, [
                'key' => 'messages',
                'badge_key' => 'messaging',
                'href' => route('staff.inquiries.index', absolute: false),
                'label' => 'Messages',
                'icon' => 'MessagesSquare',
                'match' => 'prefix',
            ]),
            $this->when($hasManagedAnnouncementSource, [
                'key' => 'announcements',
                'href' => route('communications.index', absolute: false),
                'label' => 'Announcements',
                'icon' => 'Megaphone',
                'match' => 'prefix',
            ]),
            $this->when($hasManagedPolicySource, [
                'key' => 'public_policies',
                'href' => route('communications.policies.index', absolute: false),
                'label' => 'Public policies',
                'icon' => 'Shield',
                'match' => 'prefix',
            ]),
            $this->when($this->knowledgeAccess->canViewCatalog($user), [
                'key' => 'knowledge_faqs',
                'href' => route('knowledge.index', absolute: false),
                'label' => 'FAQs',
                'icon' => 'CircleHelp',
                'match' => 'prefix',
            ]),
        ));

        if ($communications !== []) {
            $sections[] = [
                'key' => 'communications_support',
                'label' => 'Communications & Support',
                'collapsible' => true,
                'default_collapsed' => true,
                'items' => $communications,
            ];
        }
        $moreFunctions = array_values(array_merge(
            $this->when($this->allows($user, CapabilityCatalog::REGISTRAR_REPORTS_VIEW)
                || $this->allows($user, CapabilityCatalog::GUIDANCE_REPORTS_VIEW)
                || $this->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW)
                || $this->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW), [
                    'key' => 'reports',
                    'href' => '/reports',
                    'label' => 'Analytics & Reports',
                    'icon' => 'Activity',
                    'match' => 'prefix',
                ]),
            $this->when($canManageSetup
                || $this->allows($user, CapabilityCatalog::REGISTRAR_SETUP_MANAGE)
                || $this->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE)
                || $this->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
                || $this->allows($user, CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE)
                || $this->allows($user, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE)
                || $this->allows($user, CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE)
                || $this->allowsAny($user, [
                    CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
                    CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
                    CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
                    CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
                    CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
                ])
                || $hasManagedAnnouncementSource
                || $hasManagedPolicySource, [
                    'key' => 'setup',
                    'href' => '/setup',
                    'label' => ($isAdminOffice || $canViewExecDashboard) ? 'Institutional Setup' : 'Setup',
                    'icon' => 'Settings',
                    'match' => 'prefix',
                ]),
            $this->when($canAuthorDocumentTemplates, [
                'key' => 'document_templates',
                'href' => '/setup/document-templates',
                'label' => 'Document Templates',
                'icon' => 'Printer',
                'match' => 'prefix',
            ]),
            $this->when($this->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT)
                || $this->allows($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT), [
                    'key' => 'imports',
                    'href' => '/imports',
                    'label' => 'Imports',
                    'icon' => 'FileSpreadsheet',
                    'match' => 'prefix',
                ]),
        ));

        if ($moreFunctions !== []) {
            $sections[] = [
                'key' => 'more_functions',
                'label' => 'More Functions',
                'collapsible' => true,
                'default_collapsed' => true,
                'items' => $moreFunctions,
            ];
        }

        return $sections;
    }

    /**
     * Examination and result-document items retain their individual capability gates.
     *
     * @param  array{key: string, href: string, label: string, icon: string, match: string, badge_key?: string}  $item
     */
    private function canSeeAssessment(User $user, array $item): bool
    {
        return match ($item['href']) {
            '/exams/sessions' => $this->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities()),
            '/exams/monitoring' => $this->canMonitor($user),
            '/scoring', '/grading' => $this->allowsAny($user, [
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
                CapabilityCatalog::GUIDANCE_SCORING_OMR,
            ]),
            '/release' => $this->allowsAny($user, [
                CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
                CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
            ]),
            default => false,
        };
    }

    /**
     * Consultation links are independently gated by their lifecycle capability.
     *
     * @param  array{key: string, href: string, label: string, icon: string, match: string, badge_key?: string}  $item
     */
    private function canSeeConsultation(User $user, array $item): bool
    {
        return match ($item['href']) {
            '/consultation' => $this->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE),
            '/consultation/sessions' => $this->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN),
            default => false,
        };
    }

    private function canMonitor(User $user): bool
    {
        return $this->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities());
    }

    /**
     * @return list<array{key: string, href: string, label: string, icon: string, match: string, badge_key?: string}>
     */
    private function when(bool $condition, array $item): array
    {
        return $condition ? [$item] : [];
    }

    private function allows(User $user, string $capability): bool
    {
        return $this->authorizer->allows($user, $capability);
    }

    private function allowsAny(User $user, array $capabilities): bool
    {
        return $this->authorizer->allowsAny($user, $capabilities);
    }
}
