<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\SeatingArrangement;

final class SeatingArrangementsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = SeatingArrangement::class;

    protected const INSTITUTION_SCOPED = false;

    protected const KEY = 'seating_arrangements';

    protected const STAGE = 3;

    protected const STAGE_TITLE = 'Exam Logistics & Materials';

    protected const DESK = 'testing';

    protected const LABEL = 'Seating arrangements';

    protected const DESCRIPTION = 'Prepare room layouts for examination seat assignments.';

    protected const ICON = 'LayoutGrid';

    protected const HREF = '/setup/seating-arrangements';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE;
}
