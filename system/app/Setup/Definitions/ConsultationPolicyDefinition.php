<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\ConsultationPolicyResolver;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;

final class ConsultationPolicyDefinition extends AbstractSetupDomainDefinition
{
    protected const KEY = 'consultation_policy';

    protected const STAGE = 5;

    protected const STAGE_TITLE = 'Admissions Decision & Systems';

    protected const DESK = 'guidance';

    protected const LABEL = 'Consultation policy';

    protected const DESCRIPTION = 'Define the consultation methods and online exception policy.';

    protected const ICON = 'MessagesSquare';

    protected const HREF = '/setup/consultation-policy';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN;

    public function summaryMetric(Institution $institution): string
    {
        $policy = $this->effectivePolicy($institution);

        if ($policy === null || ! in_array($policy->normal_method, [ConsultationPolicy::METHOD_F2F, ConsultationPolicy::METHOD_ONLINE], true)) {
            return 'No valid active-cycle policy';
        }

        return $policy->exists ? 'Saved policy' : 'Package default';
    }

    public function checks(Institution $institution): array
    {
        $policy = $this->effectivePolicy($institution);
        $configured = $policy !== null
            && in_array($policy->normal_method, [ConsultationPolicy::METHOD_F2F, ConsultationPolicy::METHOD_ONLINE], true);

        return [[
            'label' => 'Consultation policy is configured',
            'passed' => $configured,
            'severity' => 'critical',
            'detail' => $configured
                ? ($policy->exists ? 'Saved policy' : 'Package default').'. The selected method and exceptions are available for the active academic cycle.'
                : 'An active academic cycle with a supported consultation method is required.',
            'action_href' => $configured ? null : self::HREF,
            'action_label' => $configured ? null : 'Configure '.self::LABEL,
        ]];
    }

    private function effectivePolicy(Institution $institution): ?ConsultationPolicy
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->getKey())
            ->where('is_active', true)
            ->first();

        return $cycle === null
            ? null
            : app(ConsultationPolicyResolver::class)->forCycle($cycle);
    }
}
