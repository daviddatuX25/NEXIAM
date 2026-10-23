<?php

namespace App\DocumentTemplate\Validation;

use App\DocumentTemplate\Enums\LayoutParadigm;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;

/** Validates normalized document blueprints for their layout paradigm. */
final class DocumentBlueprintValidator
{
    /**
     * @param  array<string, mixed>  $document
     * @param  list<string>|null  $allowedBindings
     * @return list<array{code: string, message: string, component_id?: string}>
     */
    public function validate(array $document, ?array $allowedBindings = null): array
    {
        $layoutParadigm = LayoutParadigm::tryFrom((string) ($document['layout_paradigm'] ?? 'absolute'))
            ?? LayoutParadigm::Absolute;
        $normalizedDocument = [
            ...$document,
            'layout_paradigm' => $layoutParadigm->value,
        ];
        $diagnostics = ResultSheetBuilderSchema::diagnostics($normalizedDocument, null, $allowedBindings);
        $normalizedDocument = ResultSheetBuilderSchema::normalize($normalizedDocument);

        foreach ($normalizedDocument['components'] as $index => $component) {
            if (($component['type'] ?? null) !== 'data_table') {
                continue;
            }

            $rawComponents = is_array($document['components'] ?? null) ? $document['components'] : [];
            $rawProps = is_array($rawComponents[$index]['props'] ?? null) ? $rawComponents[$index]['props'] : [];
            $columns = $rawProps['columns'] ?? null;
            if (! is_array($columns) || ! array_is_list($columns) || $columns === []) {
                $componentId = (string) ($component['id'] ?? 'component-'.($index + 1));
                $diagnostics[] = [
                    'code' => 'data_table_columns',
                    'message' => "Data table [{$componentId}] must define a non-empty list of columns.",
                    'component_id' => $componentId,
                ];
            }
        }

        return $diagnostics;
    }
}
