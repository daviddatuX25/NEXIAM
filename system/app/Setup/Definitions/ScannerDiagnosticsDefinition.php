<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Foundation\Institution;

final class ScannerDiagnosticsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = OmrDiagnosticRun::class;

    protected const KEY = 'omr_test';

    protected const STAGE = 3;

    protected const STAGE_TITLE = 'Exam Logistics & Materials';

    protected const DESK = 'testing';

    protected const LABEL = 'Scanner diagnostics';

    protected const DESCRIPTION = 'Run diagnostic capture and review to validate OMR scanning.';

    protected const ICON = 'ScanLine';

    protected const HREF = '/setup/exam-materials/omr-test';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_SCORING_OMR;

    protected function configuredCount(Institution $institution): int
    {
        return OmrDiagnosticRun::query()
            ->where('institution_id', $institution->getKey())
            ->where('execution_kind', OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC)
            ->count();
    }
}
