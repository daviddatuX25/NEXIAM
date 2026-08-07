<?php

namespace App\Support\Navigation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\User;

/**
 * Server-authored staff sidebar contract (GF-22).
 * Capability filters which items appear; match mode keeps one active highlight.
 */
final class StaffNavigationBuilder
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer)
    {
    }

    /**
     * @return list<array{href: string, label: string, icon: string, match: string, group: string}>
     */
    public function for(?User $user): array
    {
        $items = [
            [
                'href' => '/staff',
                'label' => 'Home',
                'icon' => 'LayoutDashboard',
                'match' => 'exact',
                'group' => 'workspace',
            ],
        ];

        if ($user === null) {
            return $items;
        }

        if ($this->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)) {
            $items[] = [
                'href' => '/admissions',
                'label' => 'Admissions',
                'icon' => 'ClipboardList',
                'match' => 'prefix',
                'group' => 'registrar',
            ];
        }

        if ($this->allows($user, CapabilityCatalog::REGISTRAR_SETUP_MANAGE)
            || $this->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
            $items[] = [
                'href' => '/setup',
                'label' => 'Setup',
                'icon' => 'Settings',
                'match' => 'prefix',
                'group' => 'institution',
            ];
        }

        if ($this->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            $items[] = [
                'href' => '/exams/sessions',
                'label' => 'Exams',
                'icon' => 'Calendar',
                'match' => 'prefix',
                'group' => 'guidance',
            ];
        }

        if ($this->allows($user, CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)) {
            $items[] = [
                'href' => '/proctor',
                'label' => 'Proctor',
                'icon' => 'Shield',
                'match' => 'prefix',
                'group' => 'guidance',
            ];
        }

        if ($this->allows($user, CapabilityCatalog::GUIDANCE_SCORING_MANUAL)) {
            $items[] = [
                'href' => '/grading',
                'label' => 'Grading',
                'icon' => 'FileCheck',
                'match' => 'prefix',
                'group' => 'guidance',
            ];
        }

        if ($this->allows($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            $items[] = [
                'href' => '/consultation',
                'label' => 'Consultation',
                'icon' => 'MessagesSquare',
                'match' => 'prefix',
                'group' => 'guidance',
            ];
        }

        if ($this->allows($user, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE)) {
            $items[] = [
                'href' => '/release',
                'label' => 'Release',
                'icon' => 'Send',
                'match' => 'prefix',
                'group' => 'guidance',
            ];
        }

        if ($this->allows($user, CapabilityCatalog::HR_STAFF_MANAGE)
            || $this->allows($user, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE)) {
            $items[] = [
                'href' => '/staff/personnel',
                'label' => 'Personnel',
                'icon' => 'Users',
                'match' => 'prefix',
                'group' => 'staffing',
            ];
        }

        $items[] = [
            'href' => '/ui-kit',
            'label' => 'UI Kit',
            'icon' => 'Blocks',
            'match' => 'prefix',
            'group' => 'system',
        ];

        return $items;
    }

    private function allows(User $user, string $capability): bool
    {
        return $this->authorizer->allows($user, $capability);
    }
}
