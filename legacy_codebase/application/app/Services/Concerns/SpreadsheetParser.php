<?php

namespace App\Services\Concerns;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Trait for parsing spreadsheet files (CSV, XLSX, XLS).
 */
trait SpreadsheetParser
{
    /**
     * Parse spreadsheet file into array of records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseSpreadsheet(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'xlsx', 'xls' => $this->parseExcel($file),
            'csv' => $this->parseCsv($file),
            default => throw new \InvalidArgumentException(
                'Unsupported file format. Please upload CSV or Excel file (XLSX/XLS).'
            ),
        };
    }

    /**
     * Parse Excel file (XLSX/XLS) into array of records.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseExcel(UploadedFile $file): array
    {
        $this->validateFile($file);

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                throw new \InvalidArgumentException('Excel file is empty.');
            }

            // First row is headers
            $headers = array_map('strtolower', array_map('trim', array_shift($rows)));

            $records = [];
            foreach ($rows as $rowIndex => $row) {
                // Skip completely empty rows
                if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                    continue;
                }

                // Pad row to match header count
                $row = array_pad($row, count($headers), null);
                $record = array_combine($headers, $row);

                if ($record !== false) {
                    $records[] = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $record);
                }
            }

            return $records;
        } catch (Exception $e) {
            throw new \InvalidArgumentException('Unable to parse Excel file: '.$e->getMessage());
        }
    }

    /**
     * Parse CSV file into array of records.
     * Override this in implementing classes if needed.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseCsv(UploadedFile $file): array
    {
        $this->validateFile($file);

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        $headers = array_map('strtolower', array_map('trim', fgetcsv($handle)));

        $records = [];
        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($headers, $row);
            if ($record !== false) {
                $records[] = array_map('trim', $record);
            }
        }

        fclose($handle);

        return $records;
    }

    /**
     * Validate file size.
     * Override this in implementing classes.
     */
    abstract protected function validateFile(UploadedFile $file): void;
}
