<?php

declare(strict_types=1);

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Staffing\Queries\StaffAccountReadiness;

final class StaffAccountsDefinition extends AbstractSetupDomainDefinition
{
    protected const KEY = 'staff_accounts';

    protected const STAGE = 1;

    protected const STAGE_TITLE = 'Institutional Information & Academic Core';

    protected const LABEL = 'Staff account preparation';

    protected const DESCRIPTION = 'Review staff coverage and prepare identified recipients for secure account setup.';

    protected const ICON = 'Users';

    protected const HREF = '/staff/personnel';

    protected const CAPABILITY = CapabilityCatalog::HR_STAFF_MANAGE;

    protected const DESK = 'admin';

    public function summaryMetric(Institution $institution): string
    {
        $readiness = app(StaffAccountReadiness::class)->handle(null, $institution);

        return $readiness['cycle_summary']['summary_metric'];
    }

    public function status(Institution $institution): string
    {
        return app(StaffAccountReadiness::class)->handle(null, $institution)['cycle_summary']['status'] === 'ready'
            ? 'ready'
            : 'attention';
    }

    public function checks(Institution $institution): array
    {
        $cycle = app(StaffAccountReadiness::class)->handle(null, $institution)['cycle_summary'];

        return [[
            'label' => 'Academic staff coverage reviewed',
            'passed' => $cycle['status'] === 'ready',
            'severity' => 'important',
            'detail' => $cycle['detail'],
            'action_href' => '/staff/personnel',
            'action_label' => 'Review personnel',
        ]];
    }
}
