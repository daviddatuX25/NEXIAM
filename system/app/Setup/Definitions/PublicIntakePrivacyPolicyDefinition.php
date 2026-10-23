<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\Institution;

final class PublicIntakePrivacyPolicyDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = PublicPolicy::class;

    protected const INSTITUTION_SCOPED = true;

    protected const KEY = 'privacy';

    protected const STAGE = 2;

    protected const STAGE_TITLE = 'Applicant Admission & Governance';

    protected const LABEL = 'Application Privacy Policy';

    protected const DESCRIPTION = 'New public applications require the current institution’s designated, published PublicPolicy revision. Legacy PrivacyPolicy records remain historical provenance.';

    protected const ICON = 'ShieldCheck';

    protected const HREF = '/communications/policies';

    protected const CAPABILITY = CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE;

    protected const DESK = 'admin';

    protected function configuredCount(Institution $institution): int
    {
        return PublicPolicy::designatedIntake((int) $institution->getKey()) === null ? 0 : 1;
    }
}
