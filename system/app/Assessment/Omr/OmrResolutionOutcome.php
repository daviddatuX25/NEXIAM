<?php

declare(strict_types=1);

namespace App\Assessment\Omr;

enum OmrResolutionOutcome: string
{
    case Resolved = 'resolved';

    case ApplicantUnresolved = 'applicant_unresolved';

    case ApplicantConflict = 'applicant_conflict';

    case ApplicantDuplicate = 'applicant_duplicate';

    case QrInvalid = 'qr_invalid';

    case QrDuplicate = 'qr_duplicate';

    case TemplateMismatch = 'template_mismatch';

    case SubmissionMissing = 'submission_missing';

    case SeatMissing = 'seat_missing';

    case KeyMissing = 'key_missing';

    case ObservedSetMismatch = 'observed_set_mismatch';

    case LifecycleInvalid = 'lifecycle_invalid';

    case PageInvalid = 'page_invalid';

    public function isFailure(): bool
    {
        return $this !== self::Resolved;
    }
}
