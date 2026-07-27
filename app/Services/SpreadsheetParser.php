<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Shared spreadsheet parsing service used by all import features.
 * Handles CSV, XLSX, and XLS files with robust header normalization,
 * row padding, and structured validation feedback.
 */
class SpreadsheetParser
{
    public const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB

    public const SUPPORTED_EXTENSIONS = ['csv', 'xlsx', 'xls', 'txt'];

    /**
     * Well-known column alias map: normalized alias → internal field name.
     * Allows spreadsheets from external systems (e.g. enrolment sheets) to be
     * imported without renaming columns manually.
     */
    public const COLUMN_ALIASES = [
        // Name variants
        'family_name'            => 'last_name',
        'lastname'               => 'last_name',
        'surname'                => 'last_name',
        'firstname'              => 'first_name',
        'given_name'             => 'first_name',
        'middle_name'            => 'middle_name',
        'middle_initial'         => 'middle_name',
        'middlename'             => 'middle_name',
        'mi'                     => 'middle_name',
        // Sex / gender
        'gender'                 => 'sex',
        // GWA
        'general_weighted_average' => 'gwa',
        'weighted_average'       => 'gwa',
        'grade_point_average'    => 'gwa',
        'gpa'                    => 'gwa',
        // Course applied / preferences
        'course_applied'         => 'course_preference_1',
        'course'                 => 'course_preference_1',
        'program_applied'        => 'course_preference_1',
        'course_preference'      => 'course_preference_1',
        '1st_choice'             => 'course_preference_1',
        '2nd_choice'             => 'course_preference_2',
        '3rd_choice'             => 'course_preference_3',
        // Strand / previous course
        'strand_prev_course'     => 'strand',
        'prev_course'            => 'strand',
        'previous_course'        => 'strand',
        'shs_strand'             => 'strand',
        // School
        'school'                 => 'last_school_enrolled',
        'last_school'            => 'last_school_enrolled',
        'school_name'            => 'last_school_enrolled',
        'school_origin'          => 'last_school_enrolled',
        // Applicant type
        'classification'         => 'applicant_type',
        'applicant_classification' => 'applicant_type',
        'type'                   => 'applicant_type',
        // Reference / Applicant Number mapping
        'applicant_no'           => 'applicant_number',
        'applicant_number'       => 'applicant_number',
        'reference_no'           => 'applicant_number',
        'reference_number'       => 'applicant_number',
        'ref_no'                 => 'applicant_number',
        // Columns to silently ignore (map to special marker)
        'no'                     => '__ignore__',
        'seq'                    => '__ignore__',
        'sequence'               => '__ignore__',
        '#'                      => '__ignore__',
    ];

    /**
     * Normalize a header string: lowercase, replace spaces/dashes/dots with underscores,
     * strip non-alphanumeric/underscore characters, and collapse multiple underscores.
     * Then resolve any known aliases to their canonical field names.
     */
    public static function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[\s\-\.]+/', '_', $header);
        $header = preg_replace('/[^a-z0-9_]/', '', $header);
        $header = preg_replace('/_+/', '_', $header);
        $header = trim($header, '_');

        // Resolve alias → canonical field name
        return self::COLUMN_ALIASES[$header] ?? $header;
    }

    /**
     * Parse a spreadsheet file into an array of records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(UploadedFile|string $file): array
    {
        $extension = is_string($file)
            ? strtolower(pathinfo($file, PATHINFO_EXTENSION))
            : strtolower($file->getClientOriginalExtension());

        $realPath = is_string($file) ? $file : ($file->getRealPath() ?: $file->getPathname());

        if (is_string($file)) {
            if (! file_exists($file)) {
                throw new \InvalidArgumentException('Import file not found. Please upload again.');
            }
        } else {
            $this->validateFileSize($file);
            if (empty($realPath) || ! file_exists($realPath)) {
                throw new \InvalidArgumentException('Uploaded file could not be read. Please try again.');
            }
        }

        if ($extension === 'txt') {
            $extension = 'csv'; // treat .txt as CSV
        }

        return match ($extension) {
            'xlsx', 'xls' => $this->parseExcel($realPath),
            'csv' => $this->parseCsv($realPath),
            default => throw new \InvalidArgumentException(
                'Unsupported file format. Please upload a CSV or Excel file (.csv, .xlsx, .xls).'
            ),
        };
    }

    /**
     * Analyze a spreadsheet file without full parsing — returns header info and row count
     * for client-side pre-validation feedback.
     *
     * @param  array<int, string>  $requiredColumns
     * @param  array<int, string>  $optionalColumns
     * @return array{
     *     headers: array<int, string>,
     *     raw_headers: array<int, string>,
     *     row_count: int,
     *     column_analysis: array<int, array{raw: string, normalized: string, status: string, matched_to: ?string}>,
     *     missing_required: array<int, string>,
     *     file_size: int,
     *     file_name: string,
     *     checks: array<int, array{label: string, status: string, detail: string}>
     * }
     */
    public function analyze(
        UploadedFile $file,
        array $requiredColumns = [],
        array $optionalColumns = []
    ): array {
        $records = $this->parse($file);
        $headers = ! empty($records) ? array_keys($records[0]) : [];

        // Build the raw headers for display
        $rawHeaders = $this->extractRawHeaders($file);

        // Analyze each column
        $allExpected = array_merge($requiredColumns, $optionalColumns);
        $allExpectedSet = array_flip(array_map('strtolower', $allExpected));
        $matchedHeaders = [];

        $columnAnalysis = [];
        foreach ($rawHeaders as $i => $raw) {
            $normalized = self::normalizeHeader($raw);
            $status = 'unknown'; // not recognized
            $matchedTo = null;

            if (in_array($normalized, array_map('strtolower', $requiredColumns), true)) {
                $status = 'required';
                $matchedTo = $normalized;
                $matchedHeaders[] = $normalized;
            } elseif (in_array($normalized, array_map('strtolower', $optionalColumns), true)) {
                $status = 'optional';
                $matchedTo = $normalized;
                $matchedHeaders[] = $normalized;
            } else {
                // Try fuzzy match
                $fuzzy = $this->fuzzyMatch($normalized, $allExpected);
                if ($fuzzy) {
                    $status = 'fuzzy';
                    $matchedTo = $fuzzy;
                }
            }

            $columnAnalysis[] = [
                'raw' => $raw,
                'normalized' => $normalized,
                'status' => $status,
                'matched_to' => $matchedTo,
            ];
        }

        // Find missing required columns
        $missingRequired = array_values(array_diff(
            array_map('strtolower', $requiredColumns),
            array_map('strtolower', $matchedHeaders)
        ));

        // Build checks
        $checks = $this->buildChecks($file, $records, $requiredColumns, $missingRequired, $columnAnalysis);

        return [
            'headers' => $headers,
            'raw_headers' => $rawHeaders,
            'row_count' => count($records),
            'column_analysis' => $columnAnalysis,
            'missing_required' => $missingRequired,
            'file_size' => $file->getSize(),
            'file_name' => $file->getClientOriginalName(),
            'checks' => $checks,
        ];
    }

    /**
     * Try to fuzzy-match a normalized header to expected columns.
     * Returns the match or null.
     */
    private function fuzzyMatch(string $normalized, array $expectedColumns): ?string
    {
        foreach ($expectedColumns as $expected) {
            $expectedLower = strtolower($expected);

            // Check if one contains the other
            if (str_contains($normalized, $expectedLower) || str_contains($expectedLower, $normalized)) {
                return $expectedLower;
            }

            // Levenshtein distance for close matches
            if (strlen($normalized) > 2 && strlen($expectedLower) > 2) {
                $distance = levenshtein($normalized, $expectedLower);
                if ($distance <= 2) {
                    return $expectedLower;
                }
            }
        }

        return null;
    }

    /**
     * Build structured pre-upload checks with pass/fail/warn statuses.
     *
     * @return array<int, array{label: string, status: string, detail: string}>
     */
    private function buildChecks(
        UploadedFile $file,
        array $records,
        array $requiredColumns,
        array $missingRequired,
        array $columnAnalysis
    ): array {
        $checks = [];

        // 1. File type check
        $ext = strtolower($file->getClientOriginalExtension());
        $checks[] = [
            'label' => 'File format',
            'status' => in_array($ext, self::SUPPORTED_EXTENSIONS, true) ? 'pass' : 'fail',
            'detail' => in_array($ext, self::SUPPORTED_EXTENSIONS, true)
                ? strtoupper($ext).' file detected'
                : 'Unsupported format. Use CSV, XLSX, or XLS.',
        ];

        // 2. File size check
        $sizeMB = round($file->getSize() / 1024 / 1024, 2);
        $checks[] = [
            'label' => 'File size',
            'status' => $file->getSize() <= self::MAX_FILE_SIZE_BYTES ? 'pass' : 'fail',
            'detail' => $file->getSize() <= self::MAX_FILE_SIZE_BYTES
                ? "{$sizeMB} MB (max 10 MB)"
                : "{$sizeMB} MB exceeds 10 MB limit",
        ];

        // 3. Has data rows
        $checks[] = [
            'label' => 'Data rows',
            'status' => count($records) > 0 ? 'pass' : 'fail',
            'detail' => count($records) > 0
                ? count($records).' row(s) found'
                : 'No data rows found. Ensure data starts on row 2.',
        ];

        // 4. Required columns
        $checks[] = [
            'label' => 'Required columns',
            'status' => empty($missingRequired) ? 'pass' : 'fail',
            'detail' => empty($missingRequired)
                ? 'All required columns present'
                : 'Missing: '.implode(', ', $missingRequired),
        ];

        // 5. Unrecognized columns
        $unknowns = array_filter($columnAnalysis, fn ($c) => $c['status'] === 'unknown');
        $fuzzy = array_filter($columnAnalysis, fn ($c) => $c['status'] === 'fuzzy');

        if (! empty($fuzzy)) {
            $fuzzyNames = array_map(fn ($c) => "\"{$c['raw']}\" → {$c['matched_to']}", $fuzzy);
            $checks[] = [
                'label' => 'Fuzzy-matched columns',
                'status' => 'warn',
                'detail' => implode(', ', $fuzzyNames),
            ];
        }

        if (! empty($unknowns)) {
            $unknownNames = array_map(fn ($c) => "\"{$c['raw']}\"", $unknowns);
            $checks[] = [
                'label' => 'Unrecognized columns',
                'status' => 'warn',
                'detail' => implode(', ', $unknownNames).' (will be ignored)',
            ];
        }

        return $checks;
    }

    /**
     * Extract raw (un-normalized) headers from a file for display.
     *
     * @return array<int, string>
     */
    private function extractRawHeaders(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath() ?: $file->getPathname();

        if (empty($path) || ! file_exists($path)) {
            return [];
        }

        if ($ext === 'csv' || $ext === 'txt') {
            $handle = fopen($path, 'r');
            if ($handle === false) {
                return [];
            }
            $row = fgetcsv($handle);
            fclose($handle);

            return $row !== false ? array_map('trim', $row) : [];
        }

        try {
            $spreadsheet = IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            return ! empty($rows) ? array_map('trim', $rows[0]) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Validate file size.
     */
    private function validateFileSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException('File too large. Maximum size is 10MB.');
        }
    }

    /**
     * Parse Excel file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseExcel(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                throw new \InvalidArgumentException('Excel file is empty.');
            }

            $rawHeaders = array_shift($rows);
            $headers = array_map(fn ($h) => self::normalizeHeader((string) $h), $rawHeaders);
            // Drop __ignore__ columns by replacing with a unique placeholder
            foreach ($headers as $i => $h) {
                if ($h === '__ignore__') {
                    $headers[$i] = '__ignore_col_'.$i.'__';
                }
            }

            $records = [];
            foreach ($rows as $row) {
                if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                    continue;
                }

                $row = array_pad($row, count($headers), null);
                $row = array_slice($row, 0, count($headers));
                $record = array_combine($headers, $row);

                if ($record !== false) {
                    $records[] = array_map(fn ($v) => is_string($v) ? trim($v) : ($v ?? ''), $record);
                }
            }

            return $records;
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new \InvalidArgumentException('Unable to parse Excel file: '.$e->getMessage());
        }
    }

    /**
     * Parse CSV file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false || empty($rawHeaders)) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV file is empty or has no headers.');
        }

        $headers = array_map(fn ($h) => self::normalizeHeader((string) $h), $rawHeaders);
        // Drop __ignore__ columns by replacing with a unique placeholder
        foreach ($headers as $i => $h) {
            if ($h === '__ignore__') {
                $headers[$i] = '__ignore_col_'.$i.'__';
            }
        }
        $headerCount = count($headers);

        $records = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $row = array_pad($row, $headerCount, null);
            $row = array_slice($row, 0, $headerCount);

            $record = array_combine($headers, $row);
            if ($record !== false) {
                $records[] = array_map(fn ($v) => is_string($v) ? trim($v) : ($v ?? ''), $record);
            }
        }

        fclose($handle);

        return $records;
    }
}
