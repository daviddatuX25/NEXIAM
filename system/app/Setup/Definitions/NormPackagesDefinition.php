<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AssessmentNormPackage;

final class NormPackagesDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = AssessmentNormPackage::class;

    protected const KEY = 'norm_packages';

    protected const STAGE = 4;

    protected const STAGE_TITLE = 'Psychometrics & Scoring Architecture';

    protected const DESK = 'guidance';

    protected const LABEL = 'Norm packages';

    protected const DESCRIPTION = 'Manage versioned norm packages used to interpret assessment scores.';

    protected const ICON = 'TrendingUp';

    protected const HREF = '/setup/norm-packages';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG;
}
