<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\IntakeFieldPolicy;

final class IntakeFieldsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = IntakeFieldPolicy::class;

    protected const KEY = 'intake_fields';

    protected const STAGE = 2;

    protected const STAGE_TITLE = 'Applicant Admission & Governance';

    protected const LABEL = 'Application Form Fields';

    protected const DESCRIPTION = 'Control application field requirements and import mappings by academic cycle.';

    protected const ICON = 'SlidersHorizontal';

    protected const HREF = '/setup/intake-fields';

    protected const CAPABILITY = CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE;

    protected const DESK = 'registrar';
}
