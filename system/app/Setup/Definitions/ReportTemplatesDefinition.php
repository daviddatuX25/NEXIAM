<?php

namespace App\Setup\Definitions;

use App\Models\Foundation\Institution;
use App\Models\Reports\ReportTemplate;

final class ReportTemplatesDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = ReportTemplate::class;

    protected const KEY = 'report_templates';

    protected const STAGE = 1;

    protected const STAGE_TITLE = 'Institutional Information & Academic Core';

    protected const DESK = 'guidance';

    protected const LABEL = 'Document templates';

    protected const DESCRIPTION = 'Configure official admission slips, clearance slips, institutional baselines, and report templates.';

    protected const ICON = 'FileBarChart2';

    protected const HREF = '/setup/document-templates';

    protected const CAPABILITY = 'report_templates';

    protected const CONTRIBUTES_TO_READINESS = false;

    protected function configuredCount(Institution $institution): int
    {
        return ReportTemplate::query()->count();
    }
}
