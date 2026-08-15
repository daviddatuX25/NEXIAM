<?php

namespace App\Assessment\Support;

use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\ScoreConversionRow;

/**
 * Single conversion core for the guidance-locked conversion-table scoring
 * (SCV-1/SCV-8). Manual entry, future OMR import, and Nexi grading all resolve
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
}
