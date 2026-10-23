<?php

namespace App\DocumentTemplate\Services;

use App\DocumentTemplate\DocumentFieldPolicy;
use App\DocumentTemplate\Support\MacroScanner;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ValidateDocumentExecutionContract
{
    public function __construct(private readonly DocumentFieldPolicy $fieldPolicy) {}

    /**
     * @param  list<string>  $selectedLeaves
     * @param  array<string, mixed>|null  $builderJson
     */
    public function validate(
        User $actor,
        string $surface,
        string $grain,
        array $selectedLeaves,
        ?array $builderJson,
        ?string $htmlContent,
    ): void {
        $allowedGrains = $this->fieldPolicy->allowedGrainsForDesk($actor->actorScope()->office ?? '', $surface);
        if (! in_array($grain, $allowedGrains, true)) {
            throw ValidationException::withMessages([
                'grain' => 'The selected document grain is not allowed for this office.',
            ]);
        }

        $allowedLeaves = $this->fieldPolicy->allowedFor($actor, $surface, $grain);
        if ($selectedLeaves === []) {
            $selectedLeaves = $allowedLeaves;
        }

        $invalidLeaves = array_values(array_diff(array_unique($selectedLeaves), $allowedLeaves));
        if ($invalidLeaves !== []) {
            throw ValidationException::withMessages([
                'selected_leaves' => 'One or more selected fields are not allowed for this office and document grain.',
            ]);
        }

        $references = [];
        foreach ($builderJson ?? [] as $value) {
            $this->collectReferences($value, $references);
        }
        if ($htmlContent !== null) {
            $this->collectTextReferences($htmlContent, $references);
        }

        $references = array_values(array_unique($references));
        if ($references !== [] && ! $this->fieldPolicy->validateReferences($actor, $surface, $grain, $references)) {
            throw ValidationException::withMessages([
                'document' => 'The document references fields that are not allowed by the selected field policy.',
            ]);
        }

        $referencedLeaves = array_values(array_unique(array_map(
            fn (string $reference): string => $this->canonicalReferenceLeafId($reference),
            $references,
        )));
        $automaticCustomDocumentLeaves = $surface === 'custom_document'
            ? ['document_title', 'custom_heading', 'custom_note', 'institution_name', 'generated_at', 'prepared_by_name', 'page_i', 'page_all']
            : [];
        $unselectedReferences = array_values(array_diff(
            $referencedLeaves,
            [...$selectedLeaves, ...$automaticCustomDocumentLeaves],
        ));
        if ($unselectedReferences !== []) {
            throw ValidationException::withMessages([
                'selected_leaves' => 'Every referenced document field must be selected.',
            ]);
        }
    }

    /** @param list<string> $references */
    private function collectReferences(mixed $value, array &$references): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if ($key === 'binding' && is_string($item) && trim($item) !== '') {
                    $references[] = trim($item);
                }
                $this->collectReferences($item, $references);
            }

            return;
        }

        if (is_string($value)) {
            $this->collectTextReferences($value, $references);
        }
    }

    /** @param list<string> $references */
    private function collectTextReferences(string $text, array &$references): void
    {
        $builderText = preg_replace('/\{\{\s*slot:[^}]*\}\}/', '', $text) ?? $text;
        $scan = MacroScanner::scan($builderText);
        if ($scan['unresolved'] !== []) {
            throw ValidationException::withMessages([
                'document' => 'The document contains a malformed placeholder reference.',
            ]);
        }

        array_push($references, ...$scan['found']);
    }

    private function canonicalReferenceLeafId(string $reference): string
    {
        return $this->fieldPolicy->canonicalReferenceLeafId($reference) ?? $reference;
    }
}
