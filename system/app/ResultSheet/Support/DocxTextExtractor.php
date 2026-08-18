<?php

namespace App\ResultSheet\Support;

use RuntimeException;
use ZipArchive;

/**
 * Reads a .docx template's body text for placeholder analysis and preview.
 * Extracts word/document.xml, merges split runs so fragmented {{...}} macros
 * reassemble, and strips markup (legacy behavior).
 */
final class DocxTextExtractor
{
    /**
     * @return list<string> placeholder names found in the document
     */
    public function placeholders(string $docxPath): array
    {
        $text = $this->text($docxPath);

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Reassembled body text with macros merged but XML tags stripped.
     */
    public function text(string $docxPath): string
    {
        $xml = $this->documentXml($docxPath);

        // Merge adjacent runs so macros split by Word are reassembled.
        $xml = preg_replace('#</w:r><w:r(?:[^>]*)>#', '', $xml) ?? $xml;
        // Drop all remaining markup, keep table cell / row boundaries as text.
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

    public function documentXml(string $docxPath): string
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("Document not found: {$docxPath}");
        }

        $zip = new ZipArchive;

        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('Unable to open document archive.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml === false) {
                throw new RuntimeException('Document body (word/document.xml) is missing.');
            }

            return $xml;
        } finally {
            $zip->close();
        }
    }
}
