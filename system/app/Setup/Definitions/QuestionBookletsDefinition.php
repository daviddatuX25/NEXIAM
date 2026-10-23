<?php

namespace App\Setup\Definitions;

use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrQuestionBookletVersion;

final class QuestionBookletsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = OmrQuestionBookletVersion::class;

    protected const INSTITUTION_SCOPED = false;

    protected const KEY = 'booklets';

    protected const STAGE = 3;

    protected const STAGE_TITLE = 'Exam Logistics & Materials';

    protected const DESK = 'testing';

    protected const LABEL = 'Question booklets';

    protected const DESCRIPTION = 'Manage examination booklet keys and versioned booklet materials.';

    protected const ICON = 'BookOpen';

    protected const HREF = '/setup/exam-materials/booklets';

    protected const CAPABILITY = CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE;
}
