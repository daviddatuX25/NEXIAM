<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestKit;

final class ExamMaterialsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = TestKit::class;

    protected const INSTITUTION_SCOPED = true;

    protected const KEY = 'exam_materials';

    protected const STAGE = 3;

    protected const STAGE_TITLE = 'Exam Logistics & Materials';

    protected const DESK = 'testing';

    protected const LABEL = 'Test Kits';

    protected const DESCRIPTION = 'Manage exam packages that group test profiles, answer sheets, booklet keys, and norm tables.';

    protected const ICON = 'BookOpen';

    protected const HREF = '/setup/exam-materials/test-kits';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG;
}
