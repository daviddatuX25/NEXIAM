<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\Foundation\ProgramQuota;
use Illuminate\Database\Eloquent\Builder;

final class ProgramQuotasDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = ProgramQuota::class;

    protected const KEY = 'quotas';

    protected const STAGE = 5;

    protected const STAGE_TITLE = 'Admissions Decision & Systems';

    protected const DESK = 'guidance';

    protected const LABEL = 'Program quotas';

    protected const DESCRIPTION = 'Set program admission capacity for an academic cycle.';

    protected const ICON = 'Users';

    protected const HREF = '/setup/quotas';

    protected const CAPABILITY = CapabilityCatalog::PROGRAM_QUOTA_MANAGE;

    protected function configuredCount(Institution $institution): int
    {
        return ProgramQuota::query()
            ->whereHas('academicCycle', static function (Builder $query) use ($institution): void {
                $query->where('institution_id', $institution->getKey());
            })
            ->count();
    }
}
