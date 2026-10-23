<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Program;

final class ProgramsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = Program::class;

    protected const KEY = 'programs';

    protected const STAGE = 1;

    protected const STAGE_TITLE = 'Institutional Information & Academic Core';

    protected const LABEL = 'Programs';

    protected const DESCRIPTION = 'Maintain the institution’s academic programs and course offerings.';

    protected const ICON = 'GraduationCap';

    protected const HREF = '/setup/programs';

    protected const CAPABILITY = CapabilityCatalog::SETUP_PROGRAMS_MANAGE;

    protected const DESK = 'admin';
}
