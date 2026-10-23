<?php

namespace App\DocumentTemplate\Support;

use RuntimeException;
use ZipArchive;

/** Extracts placeholder text from a DOCX body, headers, and footers. */
class DocxTextExtractor
{
    /**
     * @return list<string>
     */
    public function placeholders(string $docxPath): array
    {
        return $this->inspect($docxPath)['found'];
    }

    /**
     * @return list<string>
     */
    public function unresolvedMacros(string $docxPath): array
    {
        return $this->inspect($docxPath)['unresolved'];
    }

    /**
     * @return array{found: list<string>, unresolved: list<string>}
     */
    public function inspect(string $docxPath): array
    {
        return MacroScanner::scan($this->text($docxPath));
    }

    public function text(string $docxPath): string
    {
        return implode("\n", array_map(
            fn (string $xml): string => $this->textFromXml($xml),
            $this->documentXmlParts($docxPath),
        ));
    }

    public function documentXml(string $docxPath): string
    {
        return $this->documentXmlParts($docxPath)['word/document.xml'];
    }

    private function textFromXml(string $xml): string
    {
        $xml = preg_replace('#</w:r><w:r(?:[^>]*)>#', '', $xml) ?? $xml;
        $text = preg_replace('#<w:tab[^>]*/>#', "\t", $xml) ?? $xml;
        $text = preg_replace('#<w:br[^>]*/>#', "\n", $text) ?? $text;
        $text = preg_replace('#<w:p[^>]*>#', "\n", $text) ?? $text;
        $text = preg_replace('#</w:p>#', '', $text) ?? $text;
        $text = preg_replace('#<w:tr[^>]*>#', "\n", $text) ?? $text;
        $text = preg_replace('#</w:tr>#', '', $text) ?? $text;
        $text = preg_replace('#<w:tc[^>]*>#', ' | ', $text) ?? $text;
        $text = strip_tags($text);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    private function documentXmlParts(string $docxPath): array
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("Document not found: {$docxPath}");
        }

        $zip = new ZipArchive;

        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('Unable to open document archive.');
        }

        try {
            $parts = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name)
                    || ($name !== 'word/document.xml'
                        && preg_match('#^word/(?:header|footer)\d+\.xml$#', $name) !== 1)) {
                    continue;
                }

                $xml = $zip->getFromIndex($index);
                if (is_string($xml)) {
                    $parts[$name] = $xml;
                }
            }

            if (! isset($parts['word/document.xml'])) {
                throw new RuntimeException('Document body (word/document.xml) is missing.');
            }

            return ['word/document.xml' => $parts['word/document.xml']]
                + array_diff_key($parts, ['word/document.xml' => true]);
        } finally {
            $zip->close();
        }
    }
}
