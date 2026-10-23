<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestInstrument;

final class TestInstrumentsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = TestInstrument::class;

    protected const KEY = 'test_instruments';

    protected const STAGE = 4;

    protected const STAGE_TITLE = 'Psychometrics & Scoring Architecture';

    protected const DESK = 'guidance';

    protected const LABEL = 'Test instruments';

    protected const DESCRIPTION = 'Maintain versioned assessment instruments and their subtests.';

    protected const ICON = 'Binary';

    protected const HREF = '/setup/test-instruments';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG;
}
