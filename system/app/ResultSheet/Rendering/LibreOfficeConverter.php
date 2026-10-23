<?php

namespace App\ResultSheet\Rendering;

/**
 * Result-sheet adapter for the shared LibreOffice PDF conversion service.
 */
final class LibreOfficeConverter implements DocxToPdfConverter
{
    public function __construct(private readonly \App\DocumentTemplate\Rendering\LibreOfficeConverter $converter) {}

    public function isAvailable(): bool
    {
        return $this->converter->isAvailable();
    }

    public function convert(string $documentPath, ?float $timeoutSeconds = null): string
    {
        return $this->converter->convertToPdf($documentPath, $timeoutSeconds);
    }
}
