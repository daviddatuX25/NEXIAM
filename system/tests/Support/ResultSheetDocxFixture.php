<?php

namespace Tests\Support;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use ZipArchive;

final class ResultSheetDocxFixture
{
    /**
     * Create a genuine Word document containing every advertised catalog key,
     * together with structures that exercise Word's run and part boundaries.
     *
     * @param  list<string>  $catalogKeys
     */
    public static function createValid(array $catalogKeys): string
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $header = $section->addHeader();
        $footer = $section->addFooter();

        $section->addText('Generated result-sheet fixture');

        $boundaryKeys = ['applicant_name', 'institution_name', 'counselor_name'];
        foreach (array_values(array_unique(array_diff($catalogKeys, $boundaryKeys))) as $key) {
            $section->addText('Catalog: {{'.$key.'}}');
        }

        $section->addText('Outer whitespace: {{ applicant_name }}');
        $section->addText('Adjacent: {{applicant_reference}}{{applicant_name}}');

        $bodySplit = $section->addTextRun();
        $bodySplit->addText('Body styled split: {{appli', ['bold' => true]);
        $bodySplit->addText('cant_name}}', ['italic' => true]);

        $table = $section->addTable();
        $table->addRow()->addCell()->addText('Table cell: {{applicant_reference}}');

        $headerSplit = $header->addTextRun();
        $headerSplit->addText('Header styled split: {{institution_', ['bold' => true]);
        $headerSplit->addText('name}}', ['italic' => true]);

        $footerSplit = $footer->addTextRun();
        $footerSplit->addText('Footer styled split: {{counselor_', ['bold' => true]);
        $footerSplit->addText('name}}', ['italic' => true]);

        return self::save($word);
    }

    /**
     * Create a genuine Word document containing one required macro, one
     * unknown macro, and one malformed unclosed macro.
     */
    public static function createInvalid(): string
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addHeader()->addText('Generated invalid result-sheet fixture');
        $section->addFooter()->addText('Validation boundary');

        $section->addText('{{applicant_reference}}');
        $section->addText("{{xyz}}''");

        $malformed = $section->addTextRun();
        $malformed->addText('{{unclosed_');
        $malformed->addText('macro');

        return self::save($word);
    }

    /**
     * @return array<string, string>
     */
    public static function readWordXmlParts(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open generated DOCX archive.');
        }

        try {
            $parts = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name)
                    || preg_match('#^word/(?:document|header\d+|footer\d+)\.xml$#', $name) !== 1) {
                    continue;
                }

                $xml = $zip->getFromName($name);

                if ($xml !== false) {
                    $parts[$name] = $xml;
                }
            }

            if (! isset($parts['word/document.xml'])) {
                throw new RuntimeException('Generated DOCX body (word/document.xml) is missing.');
            }

            ksort($parts);

            return $parts;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, string>
     */
    public static function decodeWordXmlParts(string $path): array
    {
        $parts = [];

        foreach (self::readWordXmlParts($path) as $name => $xml) {
            $text = preg_replace('#<w:tab\b[^>]*/>#', "\t", $xml) ?? $xml;
            $text = preg_replace('#<w:br\b[^>]*/>#', "\n", $text) ?? $text;
            $parts[$name] = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $parts;
    }

    public static function delete(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function save(PhpWord $word): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'result-sheet-docx-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a generated DOCX path.');
        }

        @unlink($temporaryPath);
        $path = $temporaryPath.'.docx';

        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
        } catch (\Throwable $exception) {
            self::delete($path);
            throw $exception;
        }

        return $path;
    }
}
