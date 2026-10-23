<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AptitudeArea;

final class AptitudeDomainsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = AptitudeArea::class;

    protected const INSTITUTION_SCOPED = false;

    protected const KEY = 'aptitude';

    protected const STAGE = 4;

    protected const STAGE_TITLE = 'Psychometrics & Scoring Architecture';

    protected const DESK = 'guidance';

    protected const LABEL = 'Aptitude domains';

    protected const DESCRIPTION = 'Configure the aptitude domains used to organize assessment scoring.';

    protected const ICON = 'Brain';

    protected const HREF = '/setup/aptitude';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG;
}
