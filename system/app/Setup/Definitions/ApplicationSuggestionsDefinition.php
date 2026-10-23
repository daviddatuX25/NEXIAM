<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\ApplicationSuggestionList;
use App\Models\Foundation\Institution;

final class ApplicationSuggestionsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = ApplicationSuggestionList::class;

    protected const KEY = 'application_suggestions';

    protected const STAGE = 2;

    protected const STAGE_TITLE = 'Applicant Admission & Governance';

    protected const DESK = 'registrar';

    protected const LABEL = 'Application suggestions';

    protected const DESCRIPTION = 'Paste or upload optional school, strand, and previous-course suggestions.';

    protected const ICON = 'SlidersHorizontal';

    protected const HREF = '/setup/application-suggestions';

    protected const CAPABILITY = CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE;

    protected const CONTRIBUTES_TO_READINESS = false;

    public function checks(Institution $institution): array
    {
        return [];
    }

    public function summaryMetric(Institution $institution): string
    {
        return 'Optional suggestions';
    }
}
