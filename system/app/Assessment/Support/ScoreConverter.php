<?php

namespace App\Assessment\Support;

use App\Models\Assessment\AssessmentNormTable;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\ScoreConversionRow;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;

/**
 * Single conversion core for the guidance-locked conversion-table scoring
 * (SCV-1/SCV-8). Manual entry, future OMR import, and Xiam grading all resolve
 * through here; the normalized/formula basis is rejected.
 */
class ScoreConverter
{
    public function convert(AptitudeArea $area, int $rawScore): ?string
    {
        if ($area->scoring_method !== AptitudeArea::SCORING_METHOD_CONVERSION_TABLE) {
            return null;
        }

        $row = $area->conversionRows()
            ->where('min_score', '<=', $rawScore)
            ->where('max_score', '>=', $rawScore)
            ->first();

        return $row?->output_value;
    }

    /**
     * Resolve many rows at once for projection/preview without N+1.
     *
     * @param  array<int, ScoreConversionRow>  $rows
     */
    public function resolve(array $rows, int $rawScore): ?string
    {
        foreach ($rows as $row) {
            if ($row->min_score <= $rawScore && $row->max_score >= $rawScore) {
                return $row->output_value;
            }
        }

        return null;
    }

    /**
     * Look up a raw metric value in an AssessmentNormTable interval table.
     */
    public function convertViaNormTable(AssessmentNormTable $table, string|float|int $rawValue): ?string
    {
        $intervals = is_array($table->intervals_json)
            ? $table->intervals_json
            : json_decode($table->intervals_json ?? '[]', true);

        if (! is_array($intervals)) {
            return null;
        }

        $rawText = (string) $rawValue;
        $numericValue = $this->decimal($rawText);

        foreach ($intervals as $interval) {
            if (! is_array($interval)) {
                continue;
            }

            $output = $interval['output_value'] ?? $interval['output'] ?? $interval['value'] ?? null;
            if ($output === null) {
                continue;
            }

            if (isset($interval['raw_value'])) {
                $exactValue = $this->decimal((string) $interval['raw_value']);
                if (($numericValue !== null && $exactValue !== null && $numericValue->compareTo($exactValue) === 0)
                    || (string) $interval['raw_value'] === $rawText) {
                    return (string) $output;
                }
            }

            $min = $interval['min_score'] ?? $interval['raw_min'] ?? $interval['min'] ?? null;
            $max = $interval['max_score'] ?? $interval['raw_max'] ?? $interval['max'] ?? null;
            if (isset($interval['raw_value']) && $min === null && $max === null) {
                continue;
            }
            if ($numericValue === null) {
                continue;
            }

            $minimum = $min === null ? null : $this->decimal((string) $min);
            $maximum = $max === null ? null : $this->decimal((string) $max);
            if (($min !== null && $minimum === null) || ($max !== null && $maximum === null)) {
                continue;
            }
            if (($minimum === null || $numericValue->compareTo($minimum) >= 0)
                && ($maximum === null || $numericValue->compareTo($maximum) <= 0)) {
                return (string) $output;
            }
        }

        return null;
    }

    private function decimal(string $value): ?BigDecimal
    {
        if (! preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value)) {
            return null;
        }

        try {
            return BigDecimal::of($value);
        } catch (NumberFormatException) {
            return null;
        }
    }
}
