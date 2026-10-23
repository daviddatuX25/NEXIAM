<?php

declare(strict_types=1);

namespace App\Assessment\Omr;

use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Examination\ExamSession;

final readonly class OmrResolutionRequest
{
    public function __construct(
        public GradingSession|ExamSession $session,
        public ?string $applicantNumber,
        public int $pageNo = 1,
        public ?string $qrPayload = null,
        public ?OmrBlankInstance $blankInstance = null,
        public ?OmrBlankInstancePage $blankPage = null,
        public ?string $observedSetCode = null,
        public ?OmrCaptureSheet $sheet = null,
    ) {}

    public function normalizedApplicantNumber(): ?string
    {
        $value = trim((string) $this->applicantNumber);

        return $value === '' ? null : $value;
    }

    public function normalizedObservedSetCode(): ?string
    {
        $value = strtoupper(trim((string) $this->observedSetCode));

        return $value === '' ? null : $value;
    }
}
