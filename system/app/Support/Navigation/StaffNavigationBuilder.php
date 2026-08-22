<?php

namespace App\Support\Navigation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Examination\ProctorDuty;
use App\Models\User;

/**
 * Server-authored staff sidebar contract (GF-22).
 *
 * Sections group capability-filtered items; a section with no authorized item is
 * omitted. Grouping follows the legacy pipeline feel: Admissions (intake +
 * scheduling plan), Assessment (conduct + scoring + release + consultation),
 * and System (setup / knowledge / staff). Exam scheduling and exam monitoring
 * stay distinct items — scheduling owns the session plan (create/edit/assign/
 * publish), monitoring owns conduct (roster/attendance/submission) for the
 * session's later phase.
 */
final class StaffNavigationBuilder
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /**
     * @return list<array{label: string|null, items: list<array{href: string, label: string, icon: string, match: string}>}>
     */
    public function for(?User $user): array
    {
        $sections = [];

        $sections[] = [
            'label' => null,
            'items' => [
                [
                    'href' => '/staff',
                    'label' => 'Home',
                    'icon' => 'LayoutDashboard',
                    'match' => 'exact',
                ],
            ],
        ];

        if ($user === null) {
            return $sections;
        }


        $admissions = array_merge(
            $this->when($this->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW), [
                'href' => '/admissions',
                'label' => 'Admissions',
                'icon' => 'ClipboardList',
                'match' => 'prefix',
            ]),
            $this->when($this->allows($user, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY), [
                'href' => '/registrar/enrollment-eligibility',
                'label' => 'Enrollment Eligibility',
                'icon' => 'BadgeCheck',
                'match' => 'prefix',
            ]),
            $this->when($this->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities()), [
                'href' => '/exams/sessions',
                'label' => 'Exam Scheduling',
                'icon' => 'Calendar',
                'match' => 'prefix',
            ]),
        );

        if ($admissions !== []) {
            $sections[] = ['label' => 'Admissions', 'items' => $admissions];
        }

        $assessment = [];
        $assessment[] = [
            'href' => '/exams/monitoring',
            'label' => 'Exam Monitoring',
            'icon' => 'Activity',
            'match' => 'prefix',
        ];
        $assessment[] = [
            'href' => '/grading',
            'label' => 'Scoring',
            'icon' => 'FileCheck',
            'match' => 'prefix',
        ];
        $assessment[] = [
            'href' => '/setup/result-templates',
            'label' => 'Result Templates',
            'icon' => 'Printer',
            'match' => 'prefix',
        ];
        $assessment[] = [
            'href' => '/release',
            'label' => 'Release',
            'icon' => 'Send',
            'match' => 'prefix',
        ];
        $assessment[] = [
            'href' => '/consultation',
            'label' => 'Consultation',
            'icon' => 'MessagesSquare',
            'match' => 'prefix',
        ];

        $assessment = array_values(array_filter($assessment, fn (array $item) => $this->canSeeAssessment($user, $item)));

        if ($assessment !== []) {
            $sections[] = ['label' => 'Assessment', 'items' => $assessment];
        }

        $system = array_merge(
            $this->when($this->allows($user, CapabilityCatalog::REGISTRAR_SETUP_MANAGE)
                || $this->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE), [
                    'href' => '/setup',
                    'label' => 'Setup',
                    'icon' => 'Settings',
                    'match' => 'prefix',
                ]),
            $this->when($this->allows($user, CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE)
                || $this->allows($user, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE), [
                    'href' => '/knowledge',
                    'label' => 'Knowledge',
                    'icon' => 'BookOpen',
                    'match' => 'prefix',
                ]),
            [[
                'href' => '/staff/personnel',
                'label' => 'Staff',
                'icon' => 'Users',
                'match' => 'prefix',
            ]],
        );

        $sections[] = ['label' => 'System', 'items' => $system];

        if ($this->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW)) {
            $sections[] = [
                'label' => 'Program Head',
                'items' => [[
                    'href' => '/staff/programs',
                    'label' => 'My Programs',
                    'icon' => 'GraduationCap',
                    'match' => 'prefix',
                ]],
            ];
        }

        return $sections;
    }

    /**
     * Assessment items are individually capability-gated (D-015/D-016/GRE).
     *
     * @param  array{href: string, label: string, icon: string, match: string}  $item
     */
    private function canSeeAssessment(User $user, array $item): bool
    {
        return match ($item['href']) {
            '/exams/monitoring' => $this->canMonitor($user),
            '/grading' => $this->allows($user, CapabilityCatalog::GUIDANCE_SCORING_MANUAL),
            '/setup/result-templates' => $this->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
            '/release' => $this->allows($user, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE),
            default => false,
        };
    }

    /**
     * Monitoring is visible to Test Administrators (schedule capability) and to
     * staff holding an active proctor duty on any session (D-015 duty overlay).
     */
    private function canMonitor(User $user): bool
    {
        if ($this->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities())) {
            return true;
        }

        return ProctorDuty::query()
            ->where('user_id', $user->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * @return list<array{href: string, label: string, icon: string, match: string}>
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
