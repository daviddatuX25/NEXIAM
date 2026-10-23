<?php

namespace App\Assessment\Omr;

enum OmrQrMode: string
{
    case Signed = 'qr_signed';
    case Disabled = 'qr_disabled';
}
