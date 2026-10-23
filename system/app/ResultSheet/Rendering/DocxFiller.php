<?php

namespace App\ResultSheet\Rendering;

use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\ResultSheet\Support\DocxMacroRepairer;
use App\ResultSheet\Support\ResultSheetPlaceholderService;

/** Backward-compatible result-sheet adapter for the shared document filler. */
final class DocxFiller
{
    private readonly DocxTemplateFiller $filler;

    public function __construct(DocxMacroRepairer $repairer, ResultSheetPlaceholderService $placeholders)
    {
        $this->filler = new DocxTemplateFiller($repairer);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function fill(string $sourcePath, array $replacements): string
    {
        return $this->filler->fill($sourcePath, $replacements);
    }
}
