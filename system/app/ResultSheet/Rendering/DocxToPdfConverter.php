<?php

namespace App\ResultSheet\Rendering;

/**
 * Converts a filled DOCX/ODT document to PDF bytes. Runs only inside queue
 * workers; implementations report availability so jobs fail with a typed
 * error instead of silently degrading.
 */
interface DocxToPdfConverter
{
    public function isAvailable(): bool;

    public function convert(string $documentPath, ?float $timeoutSeconds = null): string;
}
