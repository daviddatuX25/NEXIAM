<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;

final class AdmissionSupportDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = AdmissionBenchmarkActivation::class;

    protected const KEY = 'admission_support';

    protected const STAGE = 5;

    protected const STAGE_TITLE = 'Admissions Decision & Systems';

    protected const DESK = 'guidance';

    protected const LABEL = 'Decision Support';

    protected const DESCRIPTION = 'Configure active benchmark support for admission decisions.';

    protected const ICON = 'Cpu';

    protected const HREF = '/setup/decision-support';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG;
}
