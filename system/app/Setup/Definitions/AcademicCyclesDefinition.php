<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AcademicCycle;

final class AcademicCyclesDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = AcademicCycle::class;

    protected const KEY = 'cycles';

    protected const STAGE = 1;

    protected const STAGE_TITLE = 'Institutional Information & Academic Core';

    protected const LABEL = 'Academic cycles';

    protected const DESCRIPTION = 'Configure academic years, semesters, and application windows.';

    protected const ICON = 'CalendarRange';

    protected const HREF = '/setup/cycles';

    protected const CAPABILITY = CapabilityCatalog::SETUP_CYCLES_MANAGE;

    protected const DESK = 'admin';
}
