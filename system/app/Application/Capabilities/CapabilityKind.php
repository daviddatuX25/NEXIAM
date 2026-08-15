<?php

namespace App\Application\Capabilities;

enum CapabilityKind: string
{
    case Read = 'read';
    case Propose = 'propose';
    case Command = 'command';
}
