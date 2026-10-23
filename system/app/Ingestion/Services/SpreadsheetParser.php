<?php

namespace App\Ingestion\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

final class SpreadsheetParser
{
    public const MAX_FILE_SIZE_BYTES = 25 * 1024 * 1024;

    public const SUPPORTED_EXTENSIONS = ['csv', 'xlsx', 'xls', 'txt'];

    public const COLUMN_ALIASES = [
        // Names
        'family_name' => 'last_name',
        'lastname' => 'last_name',
        'surname' => 'last_name',
        'last_name' => 'last_name',
        'firstname' => 'first_name',
        'first_name' => 'first_name',
        'given_name' => 'first_name',
        'givenname' => 'first_name',
        'middle_name' => 'middle_name',
        'middlename' => 'middle_name',
        'middle_initial' => 'middle_name',
        'mi' => 'middle_name',
        'suffix' => 'suffix',
        'ext' => 'suffix',
        // Sex / Gender
        'gender' => 'sex',
        'sex' => 'sex',
        // Date of birth
        'birthdate' => 'birthdate',
        'birth_date' => 'birthdate',
        'dob' => 'birthdate',
        'date_of_birth' => 'birthdate',
        'birthday' => 'birthdate',
        // Contact
        'email' => 'contact_email',
        'contact_email' => 'contact_email',
        'email_address' => 'contact_email',
        'phone' => 'contact_phone',
        'contact_phone' => 'contact_phone',
        'mobile' => 'contact_phone',
        'cellphone' => 'contact_phone',
        'contact_number' => 'contact_phone',
        'tel_no' => 'contact_phone',
        'address' => 'address_line',
        'address_line' => 'address_line',
        'street' => 'address_line',
        'city' => 'city',
        'municipality' => 'city',
        'province' => 'province',
        'state' => 'province',
        'zip' => 'zip_code',
        'zip_code' => 'zip_code',
        'postal_code' => 'zip_code',
        // Academic
        'strand' => 'strand',
        'shs_strand' => 'strand',
        'track' => 'strand',
        'shs_track' => 'strand',
        'prev_course' => 'strand',
        'previous_course' => 'strand',
        'school' => 'last_school_enrolled',
        'last_school' => 'last_school_enrolled',
        'school_name' => 'last_school_enrolled',
        'last_school_enrolled' => 'last_school_enrolled',
        'classification' => 'applicant_type',
        'applicant_classification' => 'applicant_type',
        'applicant_type' => 'applicant_type',
        'type' => 'applicant_type',
        'gwa' => 'gwa',
        'general_weighted_average' => 'gwa',
        'gpa' => 'gwa',
        // Program preferences
        'course' => 'course_preference_1',
        'course_applied' => 'course_preference_1',
        'program_applied' => 'course_preference_1',
        'course_preference_1' => 'course_preference_1',
        '1st_choice' => 'course_preference_1',
        'first_choice' => 'course_preference_1',
        'course_preference_2' => 'course_preference_2',
        '2nd_choice' => 'course_preference_2',
        'second_choice' => 'course_preference_2',
        'course_preference_3' => 'course_preference_3',
        '3rd_choice' => 'course_preference_3',
        'third_choice' => 'course_preference_3',
        // Identifiers
        'applicant_no' => 'applicant_number',
        'applicant_number' => 'applicant_number',
        'reference_no' => 'applicant_number',
        'reference_number' => 'applicant_number',
        'ref_no' => 'applicant_number',
        'lrn' => 'applicant_number',
        // Institutional Aptitude Areas
        'verbal' => 'VERBAL',
        'verbal_aptitude' => 'VERBAL',
        'verbal_score' => 'VERBAL',
        'verbal_raw' => 'VERBAL',
        'quant' => 'QUANT',
        'quantitative' => 'QUANT',
        'quantitative_aptitude' => 'QUANT',
        'numerical' => 'QUANT',
        'numerical_aptitude' => 'QUANT',
        'quant_score' => 'QUANT',
        'quant_raw' => 'QUANT',
        'profile_verbal' => 'PROFILE_VERBAL',
        'profile_verbal_reasoning' => 'PROFILE_VERBAL',
        'profile_quant' => 'PROFILE_QUANT',
        'profile_quantitative_reasoning' => 'PROFILE_QUANT',
        'logic' => 'LOGIC',
        'general_ability' => 'LOGIC',
        'logic_score' => 'LOGIC',
        'logic_raw' => 'LOGIC',
        'spatial' => 'SPATIAL',
        'spatial_aptitude' => 'SPATIAL',
        'spatial_score' => 'SPATIAL',
        'spatial_raw' => 'SPATIAL',
        'memory' => 'MEMORY',
        'perceptual_aptitude' => 'MEMORY',
        'memory_score' => 'MEMORY',
        'memory_raw' => 'MEMORY',
        'language' => 'LANGUAGE',
        'manual_dexterity' => 'LANGUAGE',
        'language_score' => 'LANGUAGE',
        'language_raw' => 'LANGUAGE',
        // Common fallback aliases
        'math' => 'MATH',
        'math_score' => 'MATH',
        'math_raw' => 'MATH',
        'mathematics' => 'MATH',
        'science' => 'SCIENCE',
        'science_score' => 'SCIENCE',
        'science_raw' => 'SCIENCE',
        'english' => 'ENGLISH',
        'english_score' => 'ENGLISH',
        'english_raw' => 'ENGLISH',
        'abstract' => 'ABSTRACT',
        'abstract_score' => 'ABSTRACT',
        'abstract_raw' => 'ABSTRACT',
        'abstract_reasoning' => 'ABSTRACT',
        // Ignored line noise
        'no' => '__ignore__',
        'seq' => '__ignore__',
        'sequence' => '__ignore__',
        '#' => '__ignore__',
    ];

    public function __construct(private readonly SpreadsheetLoader $loader) {}

    public static function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', self::trimUnicode($header)) ?? self::trimUnicode($header);
        $header = mb_strtolower($header, 'UTF-8');
        $header = preg_replace('/[\s\-\.]+/u', '_', $header);
        $header = preg_replace('/[^\pL\pN_]/u', '', (string) $header);
        $header = preg_replace('/_+/', '_', (string) $header);
        $header = trim((string) $header, '_');

        return self::COLUMN_ALIASES[$header] ?? $header;
    }

    /**
     * @return list<array{row_number: int, data: array<string, mixed>}>
     */
    public function parseSheetRows(UploadedFile|string $file, ?string $sheetName = null): array
    {
        $spreadsheet = $this->loader->load($file);
        $worksheet = $sheetName !== null
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        if ($worksheet === null) {
            return [];
        }

        $rows = $worksheet->toArray(
            nullValue: null,
            calculateFormulas: true,
            formatData: true,
            returnCellRef: true,
        );

        $sheetTitle = $worksheet->getTitle();
        $headerInfo = $this->locateHeaderRow($rows, $sheetTitle);
        if ($headerInfo === null) {
            return [];
        }

        [$headerRowNumber, $headers] = $headerInfo;

        $records = [];
        foreach ($rows as $rowNumber => $row) {
            if ((int) $rowNumber <= $headerRowNumber || $this->rowIsEmpty($row)) {
                continue;
            }

            $record = [];
            foreach ($headers as $column => $header) {
                $rawVal = $row[$column] ?? null;
                $cleaned = is_string($rawVal)
                    ? self::cleanCellValue($rawVal)
                    : ($rawVal ?? '');
                $record[$header] = $cleaned;
            }

            $records[] = [
                'row_number' => (int) $rowNumber,
                'data' => $record,
            ];
        }

        return $records;
    }

    /**
     * Parse a worksheet and return only records.
     *
     * @return list<array<string, mixed>>
     */
    public function parseSheet(UploadedFile|string $file, ?string $sheetName = null): array
    {
        return array_map(fn (array $item): array => $item['data'], $this->parseSheetRows($file, $sheetName));
    }

    /**
     * Locate the true column header row, skipping banners and leading empty lines.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @return array{0: int, 1: array<string, string>}|null
     */
    public function locateHeaderRow(array $rows, string $sheetTitle): ?array
    {
        foreach ($rows as $rowNumber => $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $nonEmptyCells = array_filter(
                $row,
                fn (mixed $v): bool => $v !== null && (! is_string($v) || self::trimUnicode($v) !== ''),
            );

            if ($nonEmptyCells === []) {
                continue;
            }

            $hasKnownHeader = false;
            foreach ($nonEmptyCells as $cellValue) {
                $normalized = self::normalizeHeader((string) $cellValue);
                if (isset(self::COLUMN_ALIASES[$normalized]) || in_array($normalized, self::COLUMN_ALIASES, true)) {
                    $hasKnownHeader = true;
                    break;
                }
            }

            // A single non-empty cell that is not a known alias is treated as a banner row
            if (count($nonEmptyCells) === 1 && ! $hasKnownHeader) {
                continue;
            }

            // Found header row candidate — validate and map columns
            $headers = [];
            $seenHeaders = [];

            // Determine maximum column with content
            $columnsWithContent = array_keys($nonEmptyCells);
            $lastColumn = end($columnsWithContent);

            foreach ($row as $colLetter => $rawVal) {
                $cellStr = is_string($rawVal) ? self::trimUnicode($rawVal) : ($rawVal !== null ? (string) $rawVal : '');
                if ($cellStr === '') {
                    // If this empty header is before the last populated column, it's an ambiguous gap
                    if ($this->columnPrecedesOrEquals($colLetter, $lastColumn)) {
                        throw new InvalidArgumentException(
                            "Sheet \"{$sheetTitle}\" has an empty column header in column {$colLetter} (Row {$rowNumber}).",
                        );
                    }

                    continue;
                }

                $header = self::normalizeHeader($cellStr);
                if ($header === '') {
                    throw new InvalidArgumentException(
                        "Sheet \"{$sheetTitle}\" has an unparseable column header in column {$colLetter} (Row {$rowNumber}).",
                    );
                }

                if ($header === '__ignore__') {
                    $header = "__ignore_col_{$colLetter}__";
                } elseif (isset($seenHeaders[$header])) {
                    $firstCol = $seenHeaders[$header];
                    throw new InvalidArgumentException(
                        "Sheet \"{$sheetTitle}\" has duplicate columns ({$firstCol} and {$colLetter}) both mapping to \"{$header}\".",
                    );
                }

                $seenHeaders[$header] = $colLetter;
                $headers[$colLetter] = $header;
            }

            return [(int) $rowNumber, $headers];
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    public function rowIsEmpty(array $row): bool
    {
        return ! collect($row)->contains(
            fn (mixed $value): bool => $value !== null && (! is_string($value) || self::trimUnicode($value) !== ''),
        );
    }

    public static function trimUnicode(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/^[\s\p{Z}\p{C}]+|[\s\p{Z}\p{C}]+$/u', '', $value) ?? trim($value);

        return $value;
    }

    public static function cleanCellValue(string $value): string
    {
        $value = self::trimUnicode($value);

        // Strip non-printable control characters, but preserve newlines (\n) and tabs (\t)
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
    }

    private function columnPrecedesOrEquals(string $colA, string $colB): bool
    {
        return Coordinate::columnIndexFromString($colA)
            <= Coordinate::columnIndexFromString($colB);
    }
}
