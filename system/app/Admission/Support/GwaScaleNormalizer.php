<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use InvalidArgumentException;

final class GwaScaleNormalizer
{
    public const SCALE_PERCENTAGE_100 = 'percentage_100';

    public const SCALE_PH_1_HIGHEST = 'ph_1_highest';

    public const SCALE_GPA_4_HIGHEST = 'gpa_4_highest';

    public const SCALE_GPA_5_HIGHEST = 'gpa_5_highest';

    /**
     * @return list<string>
     */
    public static function allowedScales(): array
    {
        return [
            self::SCALE_PERCENTAGE_100,
            self::SCALE_PH_1_HIGHEST,
            self::SCALE_GPA_4_HIGHEST,
            self::SCALE_GPA_5_HIGHEST,
        ];
    }

    public static function scaleLabel(?string $scale): string
    {
        return match ($scale) {
            self::SCALE_PH_1_HIGHEST => '1–5 (1.00 best)',
            self::SCALE_GPA_4_HIGHEST => '1–4 (4.00 best)',
            self::SCALE_GPA_5_HIGHEST => '1–5 (5.00 best)',
            default => '0–100 (100 best)',
        };
    }

    public static function shortScaleLabel(?string $scale): string
    {
        return match ($scale) {
            self::SCALE_PH_1_HIGHEST => '1.00 best',
            self::SCALE_GPA_4_HIGHEST => '4.00 best',
            self::SCALE_GPA_5_HIGHEST => '5.00 best',
            default => '0–100',
        };
    }

    public static function isValidValueForScale(float $value, string $scale): bool
    {
        if (! is_finite($value)) {
            return false;
        }

        return match ($scale) {
            self::SCALE_PH_1_HIGHEST, self::SCALE_GPA_5_HIGHEST => $value >= 1.0 && $value <= 5.0,
            self::SCALE_GPA_4_HIGHEST => $value >= 1.0 && $value <= 4.0,
            default => $value >= 50.0 && $value <= 100.0,
        };
    }

    /**
     * Normalize a raw GWA value and scale into the canonical 0-100 institutional scale.
     *
     * @return array{normalized: float, raw_value: float, scale: string, scale_label: string}
     */
    public static function normalize(float $rawValue, ?string $scale = null): array
    {
        $resolvedScale = in_array($scale, self::allowedScales(), true)
            ? (string) $scale
            : self::inferScale($rawValue);

        if (! self::isValidValueForScale($rawValue, $resolvedScale)) {
            throw new InvalidArgumentException('The provided GWA is outside the valid range for the selected grading scale.');
        }

        $normalized = match ($resolvedScale) {
            self::SCALE_PH_1_HIGHEST => self::fromPhOneHighest($rawValue),
            self::SCALE_GPA_5_HIGHEST => self::fromPhOneHighest(6.0 - $rawValue),
            self::SCALE_GPA_4_HIGHEST => round(75.0 + (($rawValue - 1.0) / 3.0) * 23.0, 2),
            default => round($rawValue, 2),
        };

        return [
            'normalized' => $normalized,
            'raw_value' => round($rawValue, 2),
            'scale' => $resolvedScale,
            'scale_label' => self::shortScaleLabel($resolvedScale),
        ];
    }

    public static function inferScale(float $rawValue): string
    {
        if ($rawValue >= 1.0 && $rawValue <= 5.0) {
            return self::SCALE_PH_1_HIGHEST;
        }

        return self::SCALE_PERCENTAGE_100;
    }

    /**
     * Resolve canonical 0-100 GWA and original reported metadata for an AdmissionAttempt.
     *
     * @return array{gwa: ?float, gwa_raw: ?float, gwa_scale: string, gwa_scale_label: string, is_converted: bool, formatted_normalized: string, formatted_original: string, formatted_combined: string}
     */
    public static function fromAttempt(AdmissionAttempt $attempt): array
    {
        $payload = is_array($attempt->payload) ? $attempt->payload : [];
        $supplemental = is_array($payload['supplemental_evidence'] ?? null) ? $payload['supplemental_evidence'] : [];
        $meta = is_array($supplemental['gwa_meta'] ?? null)
            ? $supplemental['gwa_meta']
            : (is_array($payload['gwa_meta'] ?? null) ? $payload['gwa_meta'] : []);

        $storedGwa = is_numeric($attempt->gwa)
            ? (float) $attempt->gwa
            : (is_numeric($supplemental['gwa'] ?? null) ? (float) $supplemental['gwa'] : null);

        if ($storedGwa === null && ! is_numeric($meta['raw_value'] ?? null)) {
            return [
                'gwa' => null,
                'gwa_raw' => null,
                'gwa_scale' => self::SCALE_PERCENTAGE_100,
                'gwa_scale_label' => self::shortScaleLabel(self::SCALE_PERCENTAGE_100),
                'is_converted' => false,
                'formatted_normalized' => '',
                'formatted_original' => '',
                'formatted_combined' => '',
            ];
        }

        $scale = is_string($meta['scale'] ?? null) && in_array($meta['scale'], self::allowedScales(), true)
            ? $meta['scale']
            : ($storedGwa !== null ? self::inferScale($storedGwa) : self::SCALE_PERCENTAGE_100);

        $rawValue = is_numeric($meta['raw_value'] ?? null)
            ? (float) $meta['raw_value']
            : $storedGwa;

        if ($storedGwa !== null && $storedGwa >= 1.0 && $storedGwa <= 5.0 && $scale !== self::SCALE_PERCENTAGE_100) {
            $normalized = self::normalize($storedGwa, $scale)['normalized'];
        } elseif ($storedGwa !== null && $storedGwa > 5.0) {
            $normalized = round($storedGwa, 2);
        } elseif ($rawValue !== null) {
            $normalized = self::normalize($rawValue, $scale)['normalized'];
        } else {
            $normalized = null;
        }

        $isConverted = $scale !== self::SCALE_PERCENTAGE_100 && $rawValue !== null && $normalized !== null;
        $formattedNormalized = $normalized !== null ? number_format($normalized, 2) : '';
        $formattedOriginal = $rawValue !== null ? number_format($rawValue, 2) : $formattedNormalized;
        $shortLabel = self::shortScaleLabel($scale);
        $formattedCombined = $isConverted
            ? "{$formattedNormalized} ({$formattedOriginal} · {$shortLabel})"
            : $formattedNormalized;

        return [
            'gwa' => $normalized,
            'gwa_raw' => $rawValue !== null ? round($rawValue, 2) : null,
            'gwa_scale' => $scale,
            'gwa_scale_label' => $shortLabel,
            'is_converted' => $isConverted,
            'formatted_normalized' => $formattedNormalized,
            'formatted_original' => $formattedOriginal,
            'formatted_combined' => $formattedCombined,
        ];
    }

    private static function fromPhOneHighest(float $raw): float
    {
        if ($raw <= 3.0) {
            return round(98.0 - (($raw - 1.0) / 2.0) * 23.0, 2);
        }

        return round(75.0 - (($raw - 3.0) / 2.0) * 15.0, 2);
    }
}
