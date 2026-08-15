<?php

namespace App\Printing;

/**
 * The single visual truth for sheets: renders any bundle template (stored
 * version or unsaved builder draft) into PDF bytes through the shared
 * BundleRenderer + DomPDF path. Both the print pipeline and the setup
 * previews call this — preview and print cannot drift.
 */
final class RenderBundlePdf
{
    public function __construct(
        private readonly BundleRenderer $bundleRenderer,
        private readonly DomPdfPrintRenderer $pdfRenderer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $bundleTemplate  native template.json content
     * @param  array<string, mixed>|null  $overlay  print overlay (title, header_lines, footer_lines, name_box_label, per_page)
     * @return string  PDF bytes
     */
    public function renderTemplate(array $bundleTemplate, ?array $overlay = null): string
    {
        $html = $this->bundleRenderer->renderHtml($bundleTemplate, ['overlay' => $overlay]);
        $paper = $this->bundleRenderer->paper();

        return $this->pdfRenderer->render($html, [
            'format' => 'custom',
            'orientation' => 'portrait',
            'width_mm' => $paper['width'],
            'height_mm' => $paper['height'],
        ]);
    }
}
