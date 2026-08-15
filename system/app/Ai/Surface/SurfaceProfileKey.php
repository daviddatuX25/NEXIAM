<?php

namespace App\Ai\Surface;

/**
 * Trust boundary for one shell/actor class (OpenSpec ai-assistant-foundation).
 * Not a visual theme and not a Spatie role string.
 */
enum SurfaceProfileKey: string
{
    case Staff = 'staff';
    case Applicant = 'applicant';
    case ProctorSession = 'proctor_session';
    case KnowledgeAdmin = 'knowledge_admin';
    case Operations = 'operations';
    case BreakGlass = 'break_glass';
}
