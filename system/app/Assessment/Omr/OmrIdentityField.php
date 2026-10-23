<?php

namespace App\Assessment\Omr;

/**
 * A proposed identity field value read from the sheet. Detection is advisory
 * only; staff binding against the roster is the authority (OMR-5).
 */
final readonly class OmrIdentityField
{
    public const CODE_APPLICANT_NUMBER = 'applicant_number';

    public const CODE_NAME = 'name';

    public const CODE_BIRTHDATE = 'birthdate';

    public function __construct(
        public string $code,
        public OmrRegion $region,
        public ?float $confidence,
        public ?string $value = null,
    ) {}
}
