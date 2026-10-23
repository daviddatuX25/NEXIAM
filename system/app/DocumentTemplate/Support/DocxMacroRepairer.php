<?php

namespace App\DocumentTemplate\Support;

use DOMDocument;
use DOMXPath;

/** Repairs placeholders fragmented across WordprocessingML runs. */
class DocxMacroRepairer
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function repairXml(string $xml): string
    {
        $xml = $this->mergeMacroRuns($xml);
        $xml = $this->stripXmlFromMacros($xml);

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            static fn (array $matches): string => '{{'.$matches[1].'}}',
            $xml,
        ) ?? $xml;
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

            if (count($textNodes) < 2 || ! preg_match_all('/\{\{\s*[a-zA-Z0-9_.]+\s*\}\}/', $combined, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach (array_reverse($matches[0]) as [$macro, $start]) {
                $end = $start + strlen($macro);
                $startNode = $this->nodeIndexAt($offsets, $start);
                $endNode = $this->nodeIndexAt($offsets, $end - 1);

                if ($startNode === $endNode) {
                    continue;
                }

                $localStart = $start - $offsets[$startNode];
                $localEnd = $end - $offsets[$endNode];
                $first = $textNodes[$startNode];
                $first->nodeValue = substr((string) $first->nodeValue, 0, $localStart).$macro;

                for ($index = $startNode + 1; $index < $endNode; $index++) {
                    $textNodes[$index]->nodeValue = '';
                }

                $last = $textNodes[$endNode];
                $last->nodeValue = substr((string) $last->nodeValue, $localEnd);
                $changed = true;
            }
        }

        libxml_clear_errors();

        if (! $changed) {
            return $xml;
        }

        $result = $doc->saveXML();

        return $result === false ? $xml : $result;
    }

    /**
     * @param  list<int>  $offsets
     */
    private function nodeIndexAt(array $offsets, int $position): int
    {
        $index = 0;

        foreach ($offsets as $candidate => $offset) {
            if ($offset <= $position) {
                $index = $candidate;
            } else {
                break;
            }
        }

        return $index;
    }

    private function stripXmlFromMacros(string $xml): string
    {
        return preg_replace_callback(
            '#(\{\{)(.*?)(\}\})#s',
            static fn (array $matches): string => $matches[1].preg_replace('#<[^>]+>#', '', $matches[2]).$matches[3],
            $xml,
        ) ?? $xml;
    }
}
