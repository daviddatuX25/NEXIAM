<?php

namespace App\Assessment\Omr;

use function in_array;
use function strtolower;
use function trim;

/**
 * Declares the deterministic synthetic evidence case vocabulary and canonical
 * definitions for FakeOmrDetection, test fixtures, and demo seeders.
 *
 * Covers all eight required evidence cases without leaking expected answers:
 * - definite: single marked bubble
 * - blank: untouched/empty row
 * - ambiguous: multiple candidates above threshold
 * - unreadable: damaged/smudged/unreadable item
 * - erased: an erased mark with residual gray (and optional second mark)
 * - crossed: a cross or strikethrough in the bubble
 * - partial: an incomplete/small mark
 * - out_of_region: a mark displaced outside or between bubbles
 */
final class OmrSyntheticEvidenceCase
{
    public const CASE_DEFINITE = 'definite';

    public const CASE_BLANK = 'blank';

    public const CASE_AMBIGUOUS = 'ambiguous';

    public const CASE_UNREADABLE = 'unreadable';

    public const CASE_ERASED = 'erased';

    public const CASE_CROSSED = 'crossed';

    public const CASE_PARTIAL = 'partial';

    public const CASE_OUT_OF_REGION = 'out_of_region';

    /**
     * @var list<string>
     */
    public const ALL_CASES = [
        self::CASE_DEFINITE,
        self::CASE_BLANK,
        self::CASE_AMBIGUOUS,
        self::CASE_UNREADABLE,
        self::CASE_ERASED,
        self::CASE_CROSSED,
        self::CASE_PARTIAL,
        self::CASE_OUT_OF_REGION,
    ];

    public static function isKnownCase(string $case): bool
    {
        return in_array(self::normalizeCaseName($case), self::ALL_CASES, true);
    }

    public static function normalizeCaseName(string $case): string
    {
        $normalized = strtolower(trim($case));
        if ($normalized === 'out-of-region') {
            return self::CASE_OUT_OF_REGION;
        }

        return $normalized;
    }
}
