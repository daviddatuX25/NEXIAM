<?php

namespace App\Assessment\Omr;

enum OmrRegistrationMode: string
{
    case FiducialAnchored = 'fiducial_anchored';
    case DocumentBound = 'document_bound';
}
