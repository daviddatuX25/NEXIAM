<?php

namespace App\Admission\Support;

use App\Models\Foundation\AcademicCycle;

/**
 * Pure value and formatting seam for canonical Applicant No. (`AY<YY>-<05d>`)
 * and compact Examinee No. (`<05d>` / `<d>`) representations.
 */
final class ApplicantReference
{
    public const DEFAULT_DIGITS = 5;

    public static function cycleYearFourDigits(?AcademicCycle $cycle = null): string
    {
        if ($cycle !== null) {
            $opensOnYear = substr(trim((string) ($cycle->opens_on ?? '')), 0, 4);
            if (preg_match('/^\d{4}$/', $opensOnYear) === 1 && $opensOnYear !== '0000') {
                return $opensOnYear;
            }

            if (preg_match('/\b(\d{4})\b/', trim((string) ($cycle->school_year ?? '')), $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('/\b(\d{4})\b/', trim((string) ($cycle->code ?? '')).' '.trim((string) ($cycle->name ?? '')), $matches) === 1) {
                return $matches[1];
            }
        }

        return (string) now()->year;
    }

    public static function cycleYearTwoDigits(?AcademicCycle $cycle = null): string
    {
        return substr(self::cycleYearFourDigits($cycle), -2);
    }

    public static function format(AcademicCycle $cycle, int $sequence, int $digits = self::DEFAULT_DIGITS): string
    {
        $yy = self::cycleYearTwoDigits($cycle);

        return sprintf('AY%s-%0'.$digits.'d', $yy, max(1, $sequence));
    }

    public static function formatLegacy(AcademicCycle $cycle, int $sequence, int $digits = self::DEFAULT_DIGITS): string
    {
        $yyyy = self::cycleYearFourDigits($cycle);

        return sprintf('APP-%s-%0'.$digits.'d', $yyyy, max(1, $sequence));
    }

    /**
     * Extract the positive integer sequence from a canonical code (`AY26-00001`),
     * legacy code (`APP-2026-00001`), 7-digit year+sequence (`2600001`), or
     * numeric shorthand (`00001` / `1`).
     */
    public static function extractSequence(?string $rawInput, ?AcademicCycle $cycle = null): ?int
    {
        $normalized = strtoupper(trim((string) $rawInput));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^AY\d{2}-0*([1-9]\d*)$/', $normalized, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^(?:APP|NOA)-\d{4}-0*([1-9]\d*)$/', $normalized, $matches) === 1) {
            return (int) $matches[1];
        }

        if (ctype_digit($normalized)) {
            if ($cycle !== null && strlen($normalized) === 7) {
                $yy = self::cycleYearTwoDigits($cycle);
                if (str_starts_with($normalized, $yy)) {
                    $suffix = ltrim(substr($normalized, 2), '0');
                    if ($suffix !== '' && ctype_digit($suffix)) {
                        return (int) $suffix;
                    }
                }
            }

            $stripped = ltrim($normalized, '0');
            if ($stripped !== '' && ctype_digit($stripped)) {
                return (int) $stripped;
            }
        }

        return null;
    }

    /**
     * Return the 5-digit zero-padded examinee number (`00001`) for OMR bubble grids
     * and permits, or null if the reference does not carry a numeric sequence.
     */
    public static function paddedDigits(?string $referenceCode, ?AcademicCycle $cycle = null, int $digits = self::DEFAULT_DIGITS): ?string
    {
        $sequence = self::extractSequence($referenceCode, $cycle);
        if ($sequence === null) {
            return null;
        }

        return sprintf('%0'.$digits.'d', $sequence);
    }

    /**
     * Return the unpadded positive integer sequence string (`1`) for compact permits,
     * or null if the reference does not carry a numeric sequence.
     */
    public static function compactNumber(?string $referenceCode, ?AcademicCycle $cycle = null): ?string
    {
        $sequence = self::extractSequence($referenceCode, $cycle);
        if ($sequence === null) {
            return null;
        }

        return (string) $sequence;
    }

    /**
     * Build the deterministic list of uppercase candidate `reference_code` values
     * that represent `$rawInput` within `$cycle`. Never uses substring wildcards.
     *
     * @return list<string>
     */
    public static function candidateCodes(?string $rawInput, ?AcademicCycle $cycle = null): array
    {
        $trimmed = strtoupper(trim((string) $rawInput));
        if ($trimmed === '') {
            return [];
        }

        $candidates = [$trimmed];
        $sequence = self::extractSequence($trimmed, $cycle);

        if ($sequence !== null) {
            if ($cycle !== null) {
                $candidates[] = self::format($cycle, $sequence, self::DEFAULT_DIGITS);
                $candidates[] = self::format($cycle, $sequence, 4);
                $candidates[] = self::format($cycle, $sequence, 3);
                $candidates[] = self::formatLegacy($cycle, $sequence, self::DEFAULT_DIGITS);
                $candidates[] = self::formatLegacy($cycle, $sequence, 4);
                $candidates[] = self::formatLegacy($cycle, $sequence, 3);
            }

            $candidates[] = sprintf('%05d', $sequence);
            $candidates[] = sprintf('%04d', $sequence);
            $candidates[] = sprintf('%03d', $sequence);
            $candidates[] = (string) $sequence;
        }

        return array_values(array_unique($candidates));
    }
}
