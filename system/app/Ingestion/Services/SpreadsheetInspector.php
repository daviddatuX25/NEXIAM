<?php

namespace App\Ingestion\Services;

use Illuminate\Http\UploadedFile;

final class SpreadsheetInspector
{
    public function __construct(
        private readonly SpreadsheetLoader $loader,
        private readonly SpreadsheetParser $parser,
    ) {}

    /**
     * Inspect workbook structure without loading heavy cell memory where possible.
     *
     * @return array{
     *     file_name: string,
     *     file_size_bytes: int,
     *     sheets: list<array{
     *         name: string,
     *         index: int,
     *         row_count: int,
     *         raw_headers: list<string>,
     *         normalized_headers: list<string>,
     *         populated_headers: list<string>,
     *         has_demographics: bool,
     *         has_scores: bool
     *     }>,
     *     total_examinee_rows: int
     * }
     */
    public function inspect(UploadedFile|string $file): array
    {
        $path = is_string($file) ? $file : ($file->getRealPath() ?: $file->getPathname());
        $name = is_string($file) ? basename($file) : $file->getClientOriginalName();
        $size = is_string($file) ? (filesize($path) ?: 0) : $file->getSize();

        $spreadsheet = $this->loader->load($file);
        $sheetNames = $spreadsheet->getSheetNames();

        $sheets = [];
        $totalRows = 0;

        foreach ($sheetNames as $idx => $sheetName) {
            $worksheet = $spreadsheet->getSheetByName($sheetName);
            if ($worksheet === null) {
                continue;
            }

            $rows = $worksheet->toArray(
                nullValue: null,
                calculateFormulas: true,
                formatData: true,
                returnCellRef: true,
            );

            $headerInfo = $this->parser->locateHeaderRow($rows, $sheetName);
            if ($headerInfo === null) {
                $sheets[] = [
                    'name' => $sheetName,
                    'index' => $idx,
                    'row_count' => 0,
                    'raw_headers' => [],
                    'normalized_headers' => [],
                    'populated_headers' => [],
                    'has_demographics' => false,
                    'has_scores' => false,
                ];

                continue;
            }

            [$headerRowNumber, $headers] = $headerInfo;

            $dataRowCount = 0;
            $populatedHeaders = [];
            foreach ($rows as $rowNum => $row) {
                if ((int) $rowNum > $headerRowNumber && ! $this->parser->rowIsEmpty($row)) {
                    $dataRowCount++;
                    foreach ($headers as $col => $normalizedHeader) {
                        $value = $row[$col] ?? null;
                        if ($value !== null && (! is_string($value) || SpreadsheetParser::trimUnicode($value) !== '')) {
                            $populatedHeaders[$normalizedHeader] = true;
                        }
                    }
                }
            }
            $totalRows += $dataRowCount;

            $rawHeaders = [];
            $headerRow = $rows[$headerRowNumber] ?? [];
            foreach ($headers as $col => $normHeader) {
                $rawHeaders[] = (string) ($headerRow[$col] ?? $normHeader);
            }

            $normalized = array_values($headers);

            $hasDemographics = count(array_intersect(
                ['first_name', 'last_name', 'middle_name', 'birthdate', 'sex'],
                array_keys($populatedHeaders),
            )) > 0;

            $hasScores = false;

            $sheets[] = [
                'name' => $sheetName,
                'index' => $idx,
                'row_count' => $dataRowCount,
                'raw_headers' => $rawHeaders,
                'normalized_headers' => $normalized,
                'populated_headers' => array_keys($populatedHeaders),
                'has_demographics' => $hasDemographics,
                'has_scores' => $hasScores,
            ];
        }

        return [
            'file_name' => $name,
            'file_size_bytes' => (int) $size,
            'sheets' => $sheets,
            'total_examinee_rows' => $totalRows,
        ];
    }
}
