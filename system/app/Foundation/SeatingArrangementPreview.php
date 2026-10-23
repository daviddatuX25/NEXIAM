<?php

namespace App\Foundation;

use App\Models\Foundation\SeatingArrangement;
use InvalidArgumentException;

final class SeatingArrangementPreview
{
    /**
     * @return list<array{index: int, row: int, column: int, label: string, booklet: string}>
     */
    public function forArrangement(SeatingArrangement $arrangement): array
    {
        return $this->forGeometry(
            (int) $arrangement->rows,
            (int) $arrangement->columns,
            (string) $arrangement->booklet_pattern,
        );
    }

    /**
     * @return list<array{index: int, row: int, column: int, label: string, booklet: string}>
     */
    public function forGeometry(int $rows, int $columns, string $pattern): array
    {
        if ($rows < 1 || $columns < 1) {
            throw new InvalidArgumentException('seating_arrangement_geometry_invalid');
        }

        if (! in_array($pattern, SeatingArrangement::PATTERNS, true)) {
            throw new InvalidArgumentException('seating_arrangement_pattern_invalid');
        }

        $desks = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $index = ($row * $columns) + $column;
                $desks[] = [
                    'index' => $index + 1,
                    'row' => $row + 1,
                    'column' => $column + 1,
                    'label' => $this->rowLabel($row).($column + 1),
                    'booklet' => $this->bookletCode($pattern, $row, $column, $index),
                ];
            }
        }

        return $desks;
    }

    /**
     * @return list<string>
     */
    public function requiredSetCodes(SeatingArrangement $arrangement): array
    {
        return array_values(array_unique(array_column($this->forArrangement($arrangement), 'booklet')));
    }

    private function bookletCode(string $pattern, int $row, int $column, int $index): string
    {
        return match ($pattern) {
            SeatingArrangement::PATTERN_UNIFORM => 'A',
            SeatingArrangement::PATTERN_CHECKERBOARD => ($row + $column) % 2 === 0 ? 'A' : 'B',
            SeatingArrangement::PATTERN_LATIN_FOUR => ['A', 'B', 'C', 'D'][($row + $column) % 4],
            SeatingArrangement::PATTERN_LINEAR_ALTERNATING => $index % 2 === 0 ? 'A' : 'B',
            default => throw new InvalidArgumentException('seating_arrangement_pattern_invalid'),
        };
    }

    private function rowLabel(int $zeroBasedRow): string
    {
        $label = '';

        for ($value = $zeroBasedRow + 1; $value > 0; $value = intdiv($value - 1, 26)) {
            $label = chr(65 + (($value - 1) % 26)).$label;
        }

        return $label;
    }
}
