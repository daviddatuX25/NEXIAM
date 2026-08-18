<?php

namespace App\ResultSheet\Support;

use DOMDocument;
use DOMXPath;

/**
 * Repairs fragmented `{{...}}` macros in DOCX XML before placeholder
 * substitution (legacy getRepairedTemplate behavior). Unlike a string
 * run-merge, this splices only the <w:t> text nodes: when a macro spans
 * several runs, the first text node receives the whole macro and the middle
 * nodes are emptied — document structure (tables, text boxes, drawings) is
 * never touched, so LibreOffice always loads the result.
 */
final class DocxMacroRepairer
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function repairXml(string $xml): string
    {
        $xml = $this->mergeMacroRuns($xml);

        return $this->stripXmlFromMacros($xml);
    }

    private function mergeMacroRuns(string $xml): string
    {
        libxml_use_internal_errors(true);

        $doc = new DOMDocument;

        if (! $doc->loadXML($xml)) {
            libxml_clear_errors();

            return $xml;
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('w', self::W_NS);

        $changed = false;

        foreach ($xpath->query('//w:p') as $paragraph) {
            $textNodes = [];
            $offsets = [];
            $combined = '';

            foreach ($xpath->query('./w:r/w:t', $paragraph) as $textNode) {
                $offsets[] = strlen($combined);
                $textNodes[] = $textNode;
                $combined .= $textNode->nodeValue;
            }

            if (count($textNodes) < 2) {
                continue;
            }

            if (! preg_match_all('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', $combined, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [$macro, $start]) {
                $end = $start + strlen($macro);
                $startNode = $this->nodeIndexAt($offsets, $start);
                $endNode = $this->nodeIndexAt($offsets, $end - 1);

                if ($startNode === $endNode) {
                    continue; // macro already intact within one run
                }

                $localStart = $start - $offsets[$startNode];
                $localEnd = $end - $offsets[$endNode];

                $first = $textNodes[$startNode];
                $first->nodeValue = substr((string) $first->nodeValue, 0, $localStart).$macro;

                for ($k = $startNode + 1; $k < $endNode; $k++) {
                    $textNodes[$k]->nodeValue = '';
                }

                $last = $textNodes[$endNode];
                $last->nodeValue = substr((string) $last->nodeValue, $localEnd);

                $changed = true;
            }
        }

        if (! $changed) {
            libxml_clear_errors();

            return $xml;
        }

        libxml_clear_errors();

        $result = $doc->saveXML();

        return $result === false ? $xml : $result;
    }

    /**
     * @param  list<int>  $offsets
     */
    private function nodeIndexAt(array $offsets, int $position): int
    {
        $index = 0;

        foreach ($offsets as $i => $offset) {
            if ($offset <= $position) {
                $index = $i;
            } else {
                break;
            }
        }

        return $index;
    }

    /**
     * Collapse XML tags that still sit inside a macro after run merging
     * (formatting differences between fragments).
     */
    private function stripXmlFromMacros(string $xml): string
    {
        return preg_replace_callback(
            '#(\{\{)(.*?)(\}\})#s',
            fn (array $m): string => $m[1].preg_replace('#<[^>]+>#', '', $m[2]).$m[3],
            $xml,
        ) ?? $xml;
    }
}
