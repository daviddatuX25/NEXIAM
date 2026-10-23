<?php

namespace App\DocumentTemplate\Rendering;

final class ReportHtmlContextFiller
{
    private const PAGE_NUMBER_ALIASES = ['page_i', 'pg_i'];

    private const PAGE_COUNT_ALIASES = ['page_all', 'pg_all'];

    private const PAGINATION_ALIAS_PAIRS = [
        self::PAGE_NUMBER_ALIASES,
        self::PAGE_COUNT_ALIASES,
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function fill(string $html, array $context): string
    {
        $html = preg_replace_callback(
            '/<tbody([^>]*)>(.*?)<\/tbody>/is',
            static function (array $matches) use ($context): string {
                if (! preg_match('/\{\{\s*([a-zA-Z0-9_]+)\.[a-zA-Z0-9_]+\s*\}\}/', $matches[2], $binding)) {
                    return $matches[0];
                }

                $rows = $context[$binding[1]] ?? null;
                if (! is_array($rows) || ! preg_match('/<tr\b[^>]*>.*?<\/tr>/is', $matches[2], $rowTemplate)) {
                    return $matches[0];
                }

                $renderedRows = [];
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $renderedRows[] = preg_replace_callback(
                        '/\{\{\s*'.preg_quote($binding[1], '/').'\.([a-zA-Z0-9_]+)\s*\}\}/',
                        static fn (array $cell): string => e((string) ($row[$cell[1]] ?? '')),
                        $rowTemplate[0]
                    ) ?? $rowTemplate[0];
                }

                return '<tbody'.$matches[1].'>'.implode('', $renderedRows).'</tbody>';
            },
            $html
        ) ?? $html;

        $values = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $values[$key] = (string) $value;
            }
        }

        foreach (self::PAGINATION_ALIAS_PAIRS as $aliases) {
            $providedValue = null;
            $hasProvidedValue = false;
            foreach ($aliases as $alias) {
                if (! array_key_exists($alias, $context)) {
                    continue;
                }

                $value = $context[$alias];
                if (! is_scalar($value) && $value !== null) {
                    continue;
                }

                $providedValue = (string) ($value ?? '');
                $hasProvidedValue = true;

                break;
            }

            if ($hasProvidedValue) {
                foreach ($aliases as $alias) {
                    $values[$alias] ??= $providedValue;
                }
            }
        }

        $html = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            static function (array $matches) use ($values): string {
                $key = $matches[1];
                if (array_key_exists($key, $values)) {
                    $value = $values[$key];
                    if (in_array($key, ['qr_code_svg', 'applicant_locator_qr', 'verification_qr'], true)) {
                        $normalized = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $value);
                        if (str_starts_with($normalized, '<svg')) {
                            return $normalized;
                        }
                    }

                    return e($value);
                }

                if (in_array($key, self::PAGE_NUMBER_ALIASES, true)
                    || in_array($key, self::PAGE_COUNT_ALIASES, true)) {
                    return self::paginationMarker($key);
                }

                return '';
            },
            $html,
        ) ?? $html;

        return preg_replace('/\{\{.*?\}\}/s', '', $html) ?? $html;
    }

    /**
     * Keeps a hidden width placeholder for DomPDF's page-script callback.
     */
    private static function paginationMarker(string $key): string
    {
        $field = in_array($key, self::PAGE_NUMBER_ALIASES, true) ? 'page_i' : 'page_all';

        return '<span data-nexiam-page-footer="'.$field.'">1</span>';
    }
}
