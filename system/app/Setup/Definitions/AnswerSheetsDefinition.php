<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;

final class AnswerSheetsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = OmrAnswerSheetTemplateVersion::class;

    protected const INSTITUTION_SCOPED = false;

    protected const KEY = 'answer_sheets';

    protected const STAGE = 3;

    protected const STAGE_TITLE = 'Exam Logistics & Materials';

    protected const DESK = 'testing';

    protected const LABEL = 'Answer sheets';

    protected const DESCRIPTION = 'Register and manage answer-sheet templates for examination scoring.';

    protected const ICON = 'FileSpreadsheet';

    protected const HREF = '/setup/exam-materials/answer-sheets';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_SCORING_OMR;
}
