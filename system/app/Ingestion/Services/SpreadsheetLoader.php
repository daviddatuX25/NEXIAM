<?php

namespace App\Ingestion\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\CsvNoEscape;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

final class SpreadsheetLoader
{
    public function load(UploadedFile|string $file): Spreadsheet
    {
        $path = is_string($file) ? $file : ($file->getRealPath() ?: $file->getPathname());
        $name = is_string($file) ? basename($file) : $file->getClientOriginalName();
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $expectedReader = match ($extension) {
            'csv', 'txt' => IOFactory::READER_CSV,
            'xlsx' => IOFactory::READER_XLSX,
            'xls' => IOFactory::READER_XLS,
            default => null,
        };

        if ($expectedReader === null) {
            throw new InvalidArgumentException("The .{$extension} file format is not supported.");
        }

        try {
            IOFactory::identify($path, [$expectedReader]);

            if ($expectedReader === IOFactory::READER_CSV) {
                $reader = new CsvNoEscape;
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
                $reader->setReadEmptyCells(false);

                return $reader->load($path);
            }

            $reader = IOFactory::createReader($expectedReader);
            $reader->setReadEmptyCells(false);

            return $reader->load($path);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                "The file contents do not match the .{$extension} extension or the file is corrupt.",
                previous: $exception,
            );
        }
    }
}
