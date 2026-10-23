<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Department;

final class DepartmentsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = Department::class;

    protected const KEY = 'departments';

    protected const STAGE = 1;

    protected const STAGE_TITLE = 'Institutional Information & Academic Core';

    protected const LABEL = 'Departments';

    protected const DESCRIPTION = 'Organize programs under the institution’s academic departments.';

    protected const ICON = 'Building2';

    protected const HREF = '/setup/departments';

    protected const CAPABILITY = CapabilityCatalog::SETUP_PROGRAMS_MANAGE;

    protected const DESK = 'admin';
}
