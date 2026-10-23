<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

enum OmrExecutionKind: string
{
    case Production = 'production';

    case Diagnostic = 'diagnostic';
}
