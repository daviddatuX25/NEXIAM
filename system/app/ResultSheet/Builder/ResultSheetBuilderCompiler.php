<?php

namespace App\ResultSheet\Builder;

use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\ResultSheet\Support\ResultSheetPlaceholderService;

/** Compiles versioned result-sheet components into print-ready HTML. */
final class ResultSheetBuilderCompiler
{
    private readonly DocumentBlueprintCompiler $blueprintCompiler;

    public function __construct(private readonly ResultSheetPlaceholderService $placeholders)
    {
        $this->blueprintCompiler = new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults());
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{html: string, document: array<string, mixed>, diagnostics: list<array{code: string, message: string, component_id?: string}>}
     */
    public function compile(array $document): array
    {
        $normalized = ResultSheetBuilderSchema::normalize($document);
        $diagnostics = ResultSheetBuilderSchema::diagnostics(
            $document,
            max(3, count($this->placeholders->sampleData()['scores'] ?? [])),
        );

        return [
            'html' => $this->blueprintCompiler->compile($normalized),
            'document' => $normalized,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string, mixed> $document @return list<string> */
    public function usedBindings(array $document): array
    {
        return $this->blueprintCompiler->usedBindings($document);
    }
}
