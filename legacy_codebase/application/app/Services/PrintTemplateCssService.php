<?php

namespace App\Services;

use Illuminate\Support\Facades\Vite;

/**
 * Injects sandboxed, self-contained Tailwind CSS into print template HTML.
 *
 * The print-template.css is a separate Vite entry that bundles all Tailwind
 * utilities used by print templates. This service loads the built CSS and wraps
 * it in a CSS @scope rule so styles only apply inside .print-template containers,
 * preventing global app styles from bleeding in.
 *
 * Two render paths:
 *  - wrap() / wrapDual()         → browser preview & window.print() (uses @scope)
 *  - wrapForPdf() / wrapDualForPdf() → Headless Chromium PDF (full HTML doc, no @scope)
 */
class PrintTemplateCssService
{
    private ?string $cachedCss = null;

    private ?string $cachedRawCss = null;

    /**
     * Wrap rendered template HTML in a scoped container with injected CSS.
     */
    public function wrap(string $html): string
    {
        $css = $this->getScopedCss();

        return "<style>{$css}</style>\n<div class=\"print-template\">{$html}</div>";
    }

    /**
     * Wrap two applicant HTML blocks in a single dual-layout container with shared CSS.
     * Uses CSS Grid for accurate 50/50 vertical split.
     */
    public function wrapDual(string $html1, string $html2): string
    {
        $css = $this->getScopedCss();

        return "<style>{$css}</style>\n"
            ."<div class=\"print-template print-template--dual\">\n"
            ."  <div class=\"print-template--half\">{$html1}</div>\n"
            ."  <div class=\"print-template--half\">{$html2}</div>\n"
            .'</div>';
    }

    /**
     * Get the scoped CSS string (cached per request).
     *
     * Wraps the built print-template CSS in @scope(.print-template) { ... }
     * so all Tailwind utilities are isolated to .print-template containers.
     */
    public function getScopedCss(): string
    {
        if ($this->cachedCss !== null) {
            return $this->cachedCss;
        }

        $rawCss = $this->getRawCss();
        $scoped = "@scope (.print-template) {\n{$rawCss}\n}";

        $this->cachedCss = $scoped;

        return $scoped;
    }

    /**
     * Get the raw (unscoped) built CSS string (cached per request).
     */
    public function getRawCss(): string
    {
        if ($this->cachedRawCss !== null) {
            return $this->cachedRawCss;
        }

        $this->cachedRawCss = $this->loadBuiltCss();

        return $this->cachedRawCss;
    }

    /**
     * Produce a full <!DOCTYPE html> document for PDF rendering (Headless Chromium).
     *
     * No @scope is used — the isolated Chromium context has no global CSS to protect
     * against. Styles are applied directly to .print-template. An @page rule sets the
     * paper size and removes default margins. A Google Fonts import loads Montserrat
     * on Chromium's cold start.
     *
     * @param  string  $paperSize  e.g. 'a4', 'letter', 'legal'
     * @param  string  $orientation  'portrait' | 'landscape'
     */
    public function wrapForPdf(string $html, string $paperSize = 'a4', string $orientation = 'portrait'): string
    {
        $css = $this->getRawCss();
        $pageRule = $this->buildPageRule($paperSize, $orientation);

        return $this->buildHtmlDocument(
            "<div class=\"print-template\">{$html}</div>",
            $pageRule.$css
        );
    }

    /**
     * Produce a full <!DOCTYPE html> document for a dual-layout PDF (Headless Chromium).
     */
    public function wrapDualForPdf(string $html1, string $html2, string $paperSize = 'a4', string $orientation = 'portrait'): string
    {
        $css = $this->getRawCss();
        $pageRule = $this->buildPageRule($paperSize, $orientation);

        $body = "<div class=\"print-template print-template--dual\">\n"
            ."  <div class=\"print-template--half\">{$html1}</div>\n"
            ."  <div class=\"print-template--half\">{$html2}</div>\n"
            .'</div>';

        return $this->buildHtmlDocument($body, $pageRule.$css);
    }

    /**
     * Wrap arbitrary bulk HTML (joined fragments) into a full document.
     */
    public function wrapBulkForPdf(string $html, string $paperSize = 'a4', string $orientation = 'portrait'): string
    {
        $css = $this->getRawCss();
        $pageRule = $this->buildPageRule($paperSize, $orientation);

        return $this->buildHtmlDocument($html, $pageRule.$css);
    }

    /**
     * Build the @page CSS rule for the given paper size and orientation.
     */
    private function buildPageRule(string $paperSize, string $orientation): string
    {
        $size = match (strtolower($paperSize)) {
            'letter' => '8.5in 11in',
            'legal' => '8.5in 14in',
            default => 'A4',
        };

        if (strtolower($orientation) === 'landscape') {
            $size .= ' landscape';
        }

        return "@page { size: {$size}; margin: 0; }\n";
    }

    /**
     * Wrap body HTML and CSS into a complete HTML document ready for Chromium.
     */
    private function buildHtmlDocument(string $body, string $css): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; padding: 0; background: white; }
            {$css}
            </style>
            </head>
            <body>
            {$body}
            </body>
            </html>
            HTML;
    }

    /**
     * Load the built print-template CSS asset.
     */
    protected function loadBuiltCss(): string
    {
        $manifestPath = public_path('build/manifest.json');

        if (! file_exists($manifestPath)) {
            // Fallback: try asset() helper (works in dev with Vite dev server)
            return $this->loadFromAssetHelper();
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $key = 'resources/css/print-template.css';

        if (isset($manifest[$key]['file'])) {
            $cssPath = public_path("build/{$manifest[$key]['file']}");
            if (file_exists($cssPath)) {
                return file_get_contents($cssPath);
            }
        }

        return $this->loadFromAssetHelper();
    }

    /**
     * Fallback: load CSS using Vite facade (works in dev mode).
     */
    protected function loadFromAssetHelper(): string
    {
        try {
            $url = Vite::asset('resources/css/print-template.css');

            // In dev mode, the asset is served by Vite dev server.
            // We can't inline it, so return a link tag instead.
            if (str_contains($url, '://')) {
                return $this->linkTagCss($url);
            }

            $cssPath = public_path(parse_url($url, PHP_URL_PATH));
            if (file_exists($cssPath)) {
                return file_get_contents($cssPath);
            }

            return $this->linkTagCss($url);
        } catch (\Throwable $e) {
            // Vite asset not available — return empty string gracefully.
            // Templates will fall back to the app's global Tailwind.
            return '';
        }
    }

    /**
     * Fallback for Vite dev mode.
     *
     * Note: @import inside @scope is invalid CSS, and we cannot inline a dynamically
     * served Vite CSS file cleanly. In dev mode, print templates will be largely unstyled.
     * To test accurate print template styling, you must run `npm run build`.
     */
    protected function linkTagCss(string $url): string
    {
        return "/* DEV MODE: run 'npm run build' to see scoped print styles. */";
    }
}
