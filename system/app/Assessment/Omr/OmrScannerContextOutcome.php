<?php

namespace App\Assessment\Omr;

enum OmrScannerContextOutcome: string
{
    case RouteBound = 'route_bound';
    case AutoResolved = 'auto_resolved';
    case SelectionRequired = 'selection_required';
}
