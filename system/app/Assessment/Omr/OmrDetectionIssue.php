<?php

namespace App\Assessment\Omr;

/**
 * A normalized, human-meaningful detection issue. Providers must only emit
 * codes from the known registry; NEXIAM persists them to
 * `omr_capture_sheet_issues` and drives review from them.
 */
final readonly class OmrDetectionIssue
{
    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_REQUIRED = 'required';

    public const SEVERITY_TERMINAL = 'terminal';

    public const CODE_AMBIGUOUS_MARK = 'ambiguous_mark';

    public const CODE_UNREADABLE = 'unreadable';

    public const CODE_BLANK_MARK = 'blank_mark';

    public const CODE_LOW_CONFIDENCE = 'low_confidence';

    public const CODE_IDENTITY_UNRESOLVED = 'identity_unresolved';

    public const CODE_IDENTITY_CONFLICT = 'identity_conflict';

    public const CODE_NO_GEOMETRY = 'no_geometry';

    public const CODE_SHEET_MISALIGNED = 'sheet_misaligned';

    public const KNOWN_CODES = [
        self::CODE_AMBIGUOUS_MARK,
        self::CODE_UNREADABLE,
        self::CODE_BLANK_MARK,
        self::CODE_LOW_CONFIDENCE,
        self::CODE_IDENTITY_UNRESOLVED,
        self::CODE_IDENTITY_CONFLICT,
        self::CODE_NO_GEOMETRY,
        self::CODE_SHEET_MISALIGNED,
    ];

    public function __construct(
        public string $code,
        public string $severity,
        public ?string $areaCode = null,
        public ?int $questionNo = null,
    ) {}

    public static function isKnownCode(string $code): bool
    {
        return in_array($code, self::KNOWN_CODES, true);
    }

    public static function isKnownSeverity(string $severity): bool
    {
        return in_array($severity, [self::SEVERITY_WARNING, self::SEVERITY_REQUIRED, self::SEVERITY_TERMINAL], true);
    }
}
