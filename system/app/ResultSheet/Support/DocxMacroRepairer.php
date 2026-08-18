<?php

namespace App\ResultSheet\Support;

/**
 * Repairs fragmented `{{...}}` macros in DOCX XML before placeholder
 * substitution (legacy getRepairedTemplate behavior): adjacent runs sharing
 * identical formatting are merged so a macro split across runs reassembles,
 * then any leftover markup inside a macro is stripped as a fallback.
 */
final class DocxMacroRepairer
{
    public function repairXml(string $xml): string
    {
        $xml = $this->mergeAdjacentRuns($xml);

        return $this->stripXmlFromMacros($xml);
    }

    /**
     * Merge consecutive <w:r> runs inside each paragraph that share the same
     * run properties, concatenating their <w:t> contents into the first run.
     */
    private function mergeAdjacentRuns(string $xml): string
    {
        return preg_replace_callback(
            '#<w:p(?:\s[^>]*)?>.*?</w:p>#s',
            function (array $paragraph): string {
                $body = $paragraph[0];

                if (! preg_match_all('#<w:r>.*?</w:r>#s', $body, $matches)) {
                    return $body;
                }

                $runs = $matches[0];
                $out = '';
                $cursor = 0;
                $buffer = null; // ['rpr' => string, 'text' => string]

                foreach ($runs as $run) {
                    $pos = strpos($body, $run, $cursor);
                    $out .= substr($body, $cursor, $pos - $cursor);
                    $cursor = $pos + strlen($run);

                    preg_match('#<w:rPr>.*?</w:rPr>#s', $run, $rpr);
                    preg_match('#<w:t[^>]*>(.*?)</w:t>#s', $run, $text);

                    $rprKey = $rpr[0] ?? '';
                    $textValue = $text[1] ?? '';

                    if ($buffer !== null && $buffer['rpr'] === $rprKey) {
                        $buffer['text'] .= $textValue;

                        continue;
                    }

                    if ($buffer !== null) {
                        $out .= $this->buildRun($buffer);
                    }

                    $buffer = ['rpr' => $rprKey, 'text' => $textValue];
                }

                if ($buffer !== null) {
                    $out .= $this->buildRun($buffer);
                }

                return $out.substr($body, $cursor);
            },
            $xml,
        ) ?? $xml;
    }

    /**
     * @param  array{rpr: string, text: string}  $buffer
     */
    private function buildRun(array $buffer): string
    {
        return '<w:r>'.$buffer['rpr'].'<w:t xml:space="preserve">'.$buffer['text'].'</w:t></w:r>';
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
