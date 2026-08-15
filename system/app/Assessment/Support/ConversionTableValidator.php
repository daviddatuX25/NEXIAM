<?php

namespace App\Assessment\Support;

use RuntimeException;

/**
 * Validates a conversion table is a non-overlapping, contiguous, fully
 * covering partition of `0..max_items` (SCV-2). Throws a RuntimeException the
 * staff-facing controller surfaces as a validation flash.
 */
class ConversionTableValidator
{
    /**
     * @param  array<int, array{min_score: int, max_score: int, output_value: string}>  $rows
     */
    public function validate(array $rows, int $maxItems): void
    {
        if ($rows === []) {
            throw new RuntimeException('A conversion table needs at least one range row.');
        }

        $sorted = $rows;
        usort($sorted, fn (array $a, array $b): int => $a['min_score'] <=> $b['min_score']);

        $previousMax = -1;

        foreach ($sorted as $index => $row) {
            $min = (int) $row['min_score'];
            $max = (int) $row['max_score'];

            if ($min < 0 || $max < $min) {
                throw new RuntimeException('Row '.($index + 1).' has an invalid score range.');
            }

            if ($max > $maxItems) {
                throw new RuntimeException('Row '.($index + 1)." exceeds the maximum score of {$maxItems}.");
            }

            if ($index === 0) {
                if ($min !== 0) {
                    throw new RuntimeException('The conversion table must start at score 0.');
                }
            } else {
                if ($min <= $previousMax) {
                    throw new RuntimeException('Conversion table ranges must not overlap.');
                }

                if ($min !== $previousMax + 1) {
                    throw new RuntimeException("Conversion table has a gap between {$previousMax} and {$min}.");
                }
            }

            $previousMax = $max;
        }

        if ($previousMax < $maxItems) {
            throw new RuntimeException("The conversion table must cover scores up to {$maxItems}.");
        }
    }
}
