<?php

namespace App\ResultSheet\Rendering;

use App\ResultSheet\Support\DocxMacroRepairer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

/**
 * Fills a Word/ODT template with one applicant's replacement values, returning
 * a temporary filled document ready for PDF conversion. DOCX goes through
 * PhpWord's TemplateProcessor after macro repair; ODT replaces placeholders
 * inside content.xml (legacy parity).
 *
 * @param  array<string, string>  $replacements
 */
final class DocxFiller
{
    public function __construct(
        private readonly DocxMacroRepairer $repairer,
        private readonly ResultSheetPlaceholderService $placeholders,
    ) {
    }

    public function fill(string $sourcePath, array $replacements): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'odt' => $this->fillOdt($sourcePath, $replacements),
            default => $this->fillDocx($sourcePath, $replacements),
        };
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function fillDocx(string $sourcePath, array $replacements): string
    {
        $tempDir = config('result-sheet.temp_dir', storage_path('app/temp/libreoffice'));

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = tempnam($tempDir, 'docx_filled_').'.docx';

        if ($tempFile === false) {
            throw new RuntimeException('Unable to allocate a filled document path.');
        }

        // Repair fragmented macros on a copy of the source.
        $repairedSource = $this->repairSource($sourcePath, $tempDir);

        try {
            $processor = new TemplateProcessor($repairedSource);
            $processor->setMacroChars('{{', '}}');

            foreach ($replacements as $key => $value) {
                $processor->setValue($key, $value);
            }

            $processor->saveAs($tempFile);
        } finally {
            if ($repairedSource !== $sourcePath && is_file($repairedSource)) {
                @unlink($repairedSource);
            }
        }

        return $tempFile;
    }

    private function repairSource(string $sourcePath, string $tempDir): string
    {
        $zip = new ZipArchive;

        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('Unable to open document archive.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return $sourcePath;
        }

        $repaired = $this->repairer->repairXml($xml);

        if ($repaired === $xml) {
            return $sourcePath;
        }

        $copy = tempnam($tempDir, 'docx_repaired_').'.docx';

        if ($copy === false) {
            return $sourcePath;
        }

        copy($sourcePath, $copy);

        $zip = new ZipArchive;

        if ($zip->open($copy) === true) {
            $zip->addFromString('word/document.xml', $repaired);
            $zip->close();

            return $copy;
        }

        @unlink($copy);

        return $sourcePath;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function fillOdt(string $sourcePath, array $replacements): string
    {
        $tempDir = config('result-sheet.temp_dir', storage_path('app/temp/libreoffice'));

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = tempnam($tempDir, 'odt_filled_').'.odt';

        if ($tempFile === false) {
            throw new RuntimeException('Unable to allocate a filled document path.');
        }

        copy($sourcePath, $tempFile);

        $zip = new ZipArchive;

        if ($zip->open($tempFile) !== true) {
            throw new RuntimeException('Unable to open filled ODT archive.');
        }

        $content = $zip->getFromName('content.xml');

        if ($content === false) {
            $zip->close();

            throw new RuntimeException('ODT content.xml is missing.');
        }

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        $zip->addFromString('content.xml', $content);
        $zip->close();

        return $tempFile;
    }
}
