<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\ResultSheet\ResultSheetTemplate;

final class ResultTemplatesDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = ResultSheetTemplate::class;

    protected const INSTITUTION_SCOPED = false;

    protected const KEY = 'result_templates';

    protected const STAGE = 4;

    protected const STAGE_TITLE = 'Psychometrics & Scoring Architecture';

    protected const DESK = 'guidance';

    protected const LABEL = 'Result templates';

    protected const DESCRIPTION = 'Configure the result-sheet template used to present assessment outcomes.';

    protected const ICON = 'ReceiptText';

    protected const HREF = '/setup/result-templates';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG;
}
