<?php

declare(strict_types=1);

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Communications\PublicHomepageSection;

final class HomepageDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = PublicHomepageSection::class;

    protected const KEY = 'homepage';

    protected const STAGE = 10;

    protected const STAGE_TITLE = 'Communications & Public Information';

    protected const DESK = 'admin';

    protected const LABEL = 'Public homepage';

    protected const DESCRIPTION = 'Edit the public hero banner and campus stories.';

    protected const ICON = 'PanelsTopLeft';

    protected const HREF = '/communications/homepage';

    protected const CAPABILITY = CapabilityCatalog::COMMUNICATIONS_HOMEPAGE_MANAGE;
}
