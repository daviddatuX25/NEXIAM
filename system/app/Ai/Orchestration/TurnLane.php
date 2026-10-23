<?php

declare(strict_types=1);

namespace App\Ai\Orchestration;

enum TurnLane: string
{
    case Conversational = 'conversational';

    case AmbientBriefing = 'ambient_briefing';

    case PolicyInquiry = 'policy_inquiry';

    case ActionProposal = 'action_proposal';
}
