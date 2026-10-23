<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

enum OmrWorkbenchCommand: string
{
    case CaptureStart = 'capture_start';

    case CaptureRetry = 'capture_retry';

    case ReviewSave = 'review_save';

    case ResolveApplicant = 'resolve_applicant';

    case ResolveDisplayCode = 'resolve_display_code';

    case ResolveDuplicate = 'resolve_duplicate';

    case QuarantineCapture = 'quarantine_capture';

    case SelectSession = 'select_session';

    case ProductionComplete = 'production_complete';

    case DiagnosticCompare = 'diagnostic_compare';

    case DiagnosticComplete = 'diagnostic_complete';

    case DiagnosticCancel = 'diagnostic_cancel';
}
