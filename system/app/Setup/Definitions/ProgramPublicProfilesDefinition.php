<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Communications\ProgramPublicProfile;

final class ProgramPublicProfilesDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = ProgramPublicProfile::class;

    protected const KEY = 'program_public_profiles';

    protected const STAGE = 1;

    protected const STAGE_TITLE = 'Institutional Information & Academic Core';

    protected const LABEL = 'Program public profiles';

    protected const DESCRIPTION = 'Manage the public-facing information for each academic program.';

    protected const ICON = 'Newspaper';

    protected const HREF = '/staff/programs';

    protected const CAPABILITY = CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE;

    protected const DESK = 'guidance';

    protected const CONTRIBUTES_TO_READINESS = false;
}
