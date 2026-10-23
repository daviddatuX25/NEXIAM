<?php

namespace App\DocumentTemplate\Support;

/** Scans document text for valid and malformed placeholder macros. */
class MacroScanner
{
    /**
     * @return array{found: list<string>, unresolved: list<string>}
     */
    public static function scan(string $text): array
    {
        $pattern = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';
        preg_match_all($pattern, $text, $matches);
        $withoutPlaceholders = preg_replace($pattern, '', $text) ?? $text;
        preg_match_all('/\{\{\{|\}\}\}/', $text, $nestedMatches);
        $unresolved = $nestedMatches[0] ?? [];
        preg_match_all('/\{\{[^\r\n<]{0,160}|\}\}/', strip_tags($withoutPlaceholders), $unresolvedMatches);
        $unresolved = array_merge($unresolved, $unresolvedMatches[0] ?? []);

        return [
            'found' => array_values(array_unique($matches[1] ?? [])),
            'unresolved' => array_values(array_unique(array_map(
                static fn (string $fragment): string => trim($fragment),
                $unresolved,
            ))),
        ];
    }
}
