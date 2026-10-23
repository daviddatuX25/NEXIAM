<?php

namespace App\DocumentTemplate\Validation;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Support\MacroScanner;

/** Compares template macros with a surface's placeholder catalog. */
class DocumentTemplateAnalyzer
{
    private const SYSTEM_MACROS = ['page_i', 'page_all', 'pg_i', 'pg_all'];

    /**
     * @param  DocumentSurfaceInterface|array<string, mixed>  $surfaceOrCatalog
     * @return array{valid: bool, found: list<string>, missing: list<string>, recommended_missing: list<string>, extra: list<string>, unresolved: list<string>}
     */
    public function analyze(string $text, DocumentSurfaceInterface|array $surfaceOrCatalog): array
    {
        $tokens = MacroScanner::scan($text);
        $isCustomDocument = $surfaceOrCatalog instanceof DocumentSurfaceInterface
            && $surfaceOrCatalog->surfaceKey() === 'custom_document';
        $catalog = $surfaceOrCatalog instanceof DocumentSurfaceInterface
            ? $surfaceOrCatalog->placeholderCatalog()
            : $surfaceOrCatalog;
        $catalogKeys = $this->catalogKeys($catalog);
        $aliases = is_array($catalog['aliases'] ?? null) ? $catalog['aliases'] : [];
        $canonicalFound = array_values(array_unique(array_map(
            static fn (string $key): string => $aliases[$key] ?? $key,
            $tokens['found'],
        )));
        $missing = $isCustomDocument ? [] : array_values(array_diff($catalogKeys['required'], $canonicalFound));
        $recommendedMissing = array_values(array_diff($catalogKeys['recommended'], $canonicalFound));
        $knownKeys = array_merge($catalogKeys['all'], self::SYSTEM_MACROS);
        $extra = array_values(array_diff($tokens['found'], $knownKeys));

        return [
            'valid' => $missing === [] && $extra === [] && $tokens['unresolved'] === [],
            'found' => $tokens['found'],
            'missing' => $missing,
            'recommended_missing' => $recommendedMissing,
            'extra' => $extra,
            'unresolved' => $tokens['unresolved'],
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array{required: list<string>, recommended: list<string>, all: list<string>}
     */
    private function catalogKeys(array $catalog): array
    {
        if (isset($catalog['all']) || isset($catalog['required']) || isset($catalog['recommended'])) {
            $required = $this->stringList($catalog['required'] ?? []);
            $recommended = $this->stringList($catalog['recommended'] ?? []);
            $all = array_values(array_unique(array_merge(
                $this->stringList($catalog['all'] ?? []),
                $required,
                $recommended,
            )));

            return ['required' => $required, 'recommended' => $recommended, 'all' => $all];
        }

        $required = [];
        $recommended = [];
        $all = [];

        foreach ($catalog as $key => $definition) {
            if (! is_string($key)) {
                if (is_string($definition)) {
                    $all[] = $definition;
                }

                continue;
            }

            $all[] = $key;
            if (is_array($definition)) {
                $level = $definition['level'] ?? $definition['requirement'] ?? null;
                $isRequired = ($definition['required'] ?? false) === true || $level === 'required';
                $isRecommended = ($definition['recommended'] ?? false) === true || $level === 'recommended';
            } else {
                $isRequired = false;
                $isRecommended = false;
            }

            if ($isRequired) {
                $required[] = $key;
            } elseif ($isRecommended) {
                $recommended[] = $key;
            }
        }

        return [
            'required' => array_values(array_unique($required)),
            'recommended' => array_values(array_unique($recommended)),
            'all' => array_values(array_unique(array_merge($all, $required, $recommended))),
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value)));
    }
}
