<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\IntakeRequirement;

final class IntakeRequirementsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = IntakeRequirement::class;

    protected const KEY = 'requirements';

    protected const STAGE = 2;

    protected const STAGE_TITLE = 'Applicant Admission & Governance';

    protected const LABEL = 'Application Requirements';

    protected const DESCRIPTION = 'Configure required and optional physical documents and supporting items for Registrar review.';

    protected const ICON = 'ClipboardCheck';

    protected const HREF = '/setup/requirements';

    protected const CAPABILITY = CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE;

    protected const DESK = 'registrar';
}
