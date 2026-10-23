<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Ai\XiamUsageReservation;
use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Builder;

final class AiCenterDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = XiamUsageReservation::class;

    protected const KEY = 'ai_center';

    protected const STAGE = 5;

    protected const STAGE_TITLE = 'Admissions Decision & Systems';

    protected const DESK = 'system';

    protected const LABEL = 'AI center';

    protected const DESCRIPTION = 'Review AI usage controls, reservations, and operational safeguards.';

    protected const ICON = 'Bot';

    protected const HREF = '/setup/ai-center';

    protected const CAPABILITY = CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE;

    protected function configuredCount(Institution $institution): int
    {
        return XiamUsageReservation::query()
            ->where(function (Builder $query) use ($institution): void {
                $query->where('institution_id', $institution->getKey())
                    ->orWhereNull('institution_id');
            })
            ->count();
    }
}
