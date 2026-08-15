<?php

namespace App\Printing;

/**
 * Renders prepared HTML into a PDF document. Implementations are swappable
 * behind this interface (DomPDF now; headless Chromium as the upgrade path);
 * the pipeline, storage, and UI never change when the renderer swaps.
 */
interface PrintRenderer
{
    /**
     * @param  array{format?: string, orientation?: string, margins?: array{top: float, right: float, bottom: float, left: float}}  $options
     */
    public function render(string $html, array $options = []): string;
}
