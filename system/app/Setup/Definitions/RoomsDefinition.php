<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Room;

final class RoomsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = Room::class;

    protected const KEY = 'rooms';

    protected const STAGE = 3;

    protected const STAGE_TITLE = 'Exam Logistics & Materials';

    protected const DESK = 'admin';

    protected const LABEL = 'Exam rooms';

    protected const DESCRIPTION = 'Configure examination rooms and their usable capacities.';

    protected const ICON = 'DoorOpen';

    protected const HREF = '/setup/rooms';

    protected const CAPABILITY = CapabilityCatalog::SETUP_ROOMS_MANAGE;
}
