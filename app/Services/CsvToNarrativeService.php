<?php

namespace App\Services;

use Illuminate\Support\Str;

class CsvToNarrativeService
{
    public const MAX_ROWS = 5000;

    public const MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024; // 2MB

    /**
     * Parse CSV content and convert rows to narrative sentences (rule-based, generic).
     * Metadata is provided by the admin and defines the document; it is not inferred from CSV.
     *
     * @return array{content: string, row_count: int, headers: array}
     * @throws \InvalidArgumentException on invalid/empty CSV
     */
    public function convert(string $csvContent): array
    {
        $lines = $this->parseLines($csvContent);
        if (empty($lines)) {
            throw new \InvalidArgumentException('No data rows. CSV is empty or header-only.');
        }

        $headers = array_shift($lines);
        $headers = array_map('trim', $headers);

        $sentences = [];
        $rowNum = 1;
        foreach ($lines as $row) {
            if ($rowNum > self::MAX_ROWS) {
                break;
            }
            $sentence = $this->rowToSentence($row, $headers, $rowNum);
            if ($sentence !== '') {
                $sentences[] = $sentence;
            }
            $rowNum++;
        }

        if ($sentences === []) {
            throw new \InvalidArgumentException('No data rows. CSV has no valid content rows.');
        }

        return [
            'content' => implode("\n\n", $sentences),
            'row_count' => count($sentences),
            'headers' => $headers,
        ];
    }

    /**
     * Convert a single row to a factual sentence. Generic: "{col1}: {val1}; {col2}: {val2}."
     */
    private function rowToSentence(array $row, array $headers, int $rowNum): string
    {
        $pairs = [];
        foreach ($headers as $i => $header) {
            $val = isset($row[$i]) ? trim((string) $row[$i]) : '';
            if ($val === '') {
                continue;
            }
            $label = $header !== '' ? $header : "Column " . ($i + 1);
            $pairs[] = "{$label}: {$val}";
        }
        if ($pairs === []) {
            return '';
        }
        return "Row {$rowNum}: " . implode('; ', $pairs) . '.';
    }

    /**
     * Parse CSV into array of rows. Handles UTF-8; attempts to detect delimiter.
     */
    private function parseLines(string $content): array
    {
        $content = Str::replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $lines = explode("\n", $content);
        $rows = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $rows[] = $row;
        }

        $headerRow = $rows[0] ?? [];
        if (empty(array_filter($headerRow))) {
            return [];
        }

        return $rows;
    }

    /**
     * Validate file: size and basic encoding.
     */
    public function validateFile(\Illuminate\Http\UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException('CSV file too large. Maximum size is 2MB.');
        }

        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            throw new \InvalidArgumentException('Could not read file.');
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $converted = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
            if ($converted === false || $converted !== $content) {
                throw new \InvalidArgumentException('Invalid encoding. File must be UTF-8.');
            }
        }
    }
}
