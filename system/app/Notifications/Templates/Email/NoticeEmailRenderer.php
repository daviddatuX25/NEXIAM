<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Email;

use RuntimeException;

class NoticeEmailRenderer
{
    public const VERSION = 'notice-email-1';

    public const PREVIEW_CSP = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'";

    /**
     * Only actual passed context is used. Assets must come from NoticeEmailAssetVerifier.
     *
     * @param  list<string>  $allowedTokens
     * @return array{subject:string,production_html:string,text:string,assets:array}
     */
    public function render(array $blueprint, array $context, array $allowedTokens, string $schemaVersion, string $rendererVersion, array $assets = [], string $subject = ''): array
    {
        if ($schemaVersion !== NoticeEmailSchema::SCHEMA_VERSION || $rendererVersion !== self::VERSION) {
            throw new RuntimeException('email_renderer_unavailable');
        }
        NoticeEmailSchema::assertValid($blueprint);
        if (($blueprint['schema_version'] ?? null) !== $schemaVersion) {
            throw new RuntimeException('email_schema_mismatch');
        }
        $assetMap = [];
        $cids = [];
        if (count($assets) > NoticeEmailAssetVerifier::MAX_IMAGES) {
            throw new RuntimeException('email_asset_limit');
        }
        foreach ($assets as $asset) {
            if (! isset($asset['bytes'], $asset['sha256'], $asset['mime'], $asset['cid'])
                || ! hash_equals($asset['sha256'], hash('sha256', $asset['bytes']))
                || ! in_array($asset['mime'], ['image/png', 'image/jpeg'], true)
                || ! str_contains($asset['cid'], '@') || isset($cids[$asset['cid']])
                || strlen($asset['bytes']) > NoticeEmailAssetVerifier::MAX_BYTES
                || ! preg_match('/^[a-zA-Z0-9._@-]{1,150}$/D', $asset['cid'])) {
                throw new RuntimeException('email_asset_unverified');
            }
            $image = @getimagesizefromstring($asset['bytes']);
            if ($image === false || ($image['mime'] ?? null) !== $asset['mime']
                || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > NoticeEmailAssetVerifier::MAX_PIXELS) {
                throw new RuntimeException('email_asset_raster_invalid');
            }
            $cids[$asset['cid']] = true;
            $assetMap[$asset['id']] = $asset;
        }
        $html = '';
        $text = '';
        foreach ($blueprint['blocks'] as $block) {
            [$blockHtml, $blockText] = $this->block($block, $context, $allowedTokens, $assetMap);
            $html .= $blockHtml;
            $text .= $blockText."\n\n";
        }
        $subject = $this->substitute($subject, $context, $allowedTokens);
        if (preg_match('/[\r\n\x00-\x1f\x7f]/', $subject)) {
            throw new RuntimeException('email_subject_invalid');
        }
        $production = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background-color:#ffffff;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;"><tr><td align="center">'
            .'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;border-collapse:collapse;"><tr><td style="padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#172033;overflow-wrap:anywhere;">'
            .$html.'</td></tr></table></td></tr></table></body></html>';

        return ['subject' => $subject, 'production_html' => $production, 'text' => trim($text), 'assets' => $assets];
    }

    /** @return array{preview_html:string,projection_sha256:string} */
    public function project(string $productionHtml, array $assets): array
    {
        $map = [];
        foreach ($assets as $asset) {
            if (strlen($asset['bytes']) > NoticeEmailAssetVerifier::MAX_BYTES
                || ! hash_equals($asset['sha256'], hash('sha256', $asset['bytes']))) {
                throw new RuntimeException('email_asset_checksum');
            }
            $map[$asset['cid']] = 'data:'.$asset['mime'].';base64,'.base64_encode($asset['bytes']);
        }
        if (count($map) > NoticeEmailAssetVerifier::MAX_IMAGES) {
            throw new RuntimeException('email_asset_limit');
        }
        $preview = preg_replace_callback('/src="cid:([^"<>]+)"/', static function (array $match) use ($map): string {
            if (! isset($map[$match[1]])) {
                throw new RuntimeException('email_unknown_cid');
            }

            return 'src="'.$map[$match[1]].'"';
        }, $productionHtml);
        if ($preview === null || preg_match('/(?:src|href)\s*=\s*["\']cid:/i', $preview)) {
            throw new RuntimeException('email_unknown_cid');
        }
        $preview = preg_replace('/<a href="[^"]*"([^>]*)>/', '<a aria-disabled="true"$1>', $preview);
        $preview = str_replace('<head>', '<head><meta http-equiv="Content-Security-Policy" content="'.$this->escape(self::PREVIEW_CSP).'">', $preview);

        return ['preview_html' => $preview, 'projection_sha256' => hash('sha256', $preview)];
    }

    public function substitute(string $text, array $context, array $allowedTokens): string
    {
        $result = preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', static function (array $match) use ($context, $allowedTokens): string {
            $key = trim($match[1]);
            if (! in_array($key, $allowedTokens, true)) {
                throw new RuntimeException('email_token_unknown');
            }
            if (! array_key_exists($key, $context) || ! is_scalar($context[$key]) || trim((string) $context[$key]) === '') {
                throw new RuntimeException('email_context_missing');
            }
            $value = (string) $context[$key];
            if (str_contains($value, '{{') || str_contains($value, '}}')) {
                throw new RuntimeException('email_context_invalid');
            }

            return $value;
        }, $text);
        if ($result === null || str_contains($result, '{{') || str_contains($result, '}}')) {
            throw new RuntimeException('email_token_malformed');
        }

        return $result;
    }

    /** @return array{string,string} */
    private function block(array $block, array $context, array $tokens, array $assets): array
    {
        $type = $block['type'];
        $style = $this->style($block);
        if ($type === 'paragraph' || $type === 'heading') {
            [$html, $text] = $this->inline($block, $context, $tokens);
            $tag = $type === 'heading' ? 'h'.($block['level'] ?? 1) : 'p';

            return ['<'.$tag.' style="margin:0 0 16px;'.$style.'">'.$html.'</'.$tag.'>', $text];
        }
        if ($type === 'list') {
            $tag = ($block['ordered'] ?? false) ? 'ol' : 'ul';
            $html = '<'.$tag.' style="margin:0 0 16px;padding-left:24px;'.$style.'">';
            $text = '';
            foreach ($block['items'] as $index => $item) {
                [$itemHtml, $itemText] = $this->content($item, $context, $tokens, $assets);
                $html .= '<li>'.$itemHtml.'</li>';
                $text .= (($block['ordered'] ?? false) ? ($index + 1).'. ' : '- ').$itemText."\n";
            }

            return [$html.'</'.$tag.'>', trim($text)];
        }
        if ($type === 'table') {
            $html = '<table width="100%" cellpadding="8" cellspacing="0" style="width:100%;table-layout:fixed;border-collapse:collapse;'.$style.'">';
            $text = '';
            $rows = $block['rows'];
            if (! empty($block['headers'])) {
                array_unshift($rows, ['cells' => $block['headers'], '_header' => true]);
            }
            foreach ($rows as $row) {
                $html .= '<tr>';
                $plainCells = [];
                foreach ($row['cells'] ?? $row as $cell) {
                    [$cellHtml, $cellText] = $this->content($cell, $context, $tokens, $assets);
                    $tag = ($row['_header'] ?? false) ? 'th' : 'td';
                    $html .= '<'.$tag.(($row['_header'] ?? false) ? ' scope="col"' : '').' style="border:1px solid #d1d5db;vertical-align:top;overflow-wrap:anywhere;text-align:left;">'.$cellHtml.'</'.$tag.'>';
                    $plainCells[] = trim($cellText);
                }
                $html .= '</tr>';
                $text .= implode(' | ', $plainCells)."\n";
            }

            return [$html.'</table>', trim($text)];
        }
        if ($type === 'image') {
            $asset = $assets[$block['asset_id']] ?? throw new RuntimeException('email_asset_missing');
            $alt = $this->substitute($block['alt'], $context, $tokens);
            $width = $block['width'];
            $widthAttr = is_numeric($width) ? (string) min(600, (int) $width) : '600';

            return ['<img src="cid:'.$asset['cid'].'" alt="'.$this->escape($alt).'" width="'.$widthAttr.'" style="display:block;border:0;width:'.$this->dimension($width).';max-width:100%;height:auto;'.$style.'">', '['.$alt.']'];
        }
        if ($type === 'divider') {
            return ['<hr style="border:0;border-top:1px solid '.($block['color'] ?? '#d1d5db').';'.$style.'">', '---'];
        }
        if ($type === 'spacer') {
            return ['<div aria-hidden="true" style="height:'.$this->dimension($block['height'] ?? 16).';line-height:1px;'.$style.'">&#160;</div>', ''];
        }

        throw new RuntimeException('email_block_unknown');
    }

    /** @return array{string,string} */
    private function content(array|string $content, array $context, array $tokens, array $assets): array
    {
        if (is_array($content) && isset($content['type'])) {
            return $this->block($content, $context, $tokens, $assets);
        }
        if (is_array($content) && isset($content['blocks'])) {
            $html = '';
            $text = '';
            foreach ($content['blocks'] as $child) {
                [$childHtml, $childText] = $this->block($child, $context, $tokens, $assets);
                $html .= $childHtml;
                $text .= $childText."\n";
            }

            return [$html, trim($text)];
        }

        return $this->inline($content, $context, $tokens);
    }

    /** @return array{string,string} */
    private function inline(array|string $content, array $context, array $tokens): array
    {
        if (is_string($content)) {
            $text = $this->substitute($content, $context, $tokens);

            return [nl2br($this->escape($text), false), $text];
        }
        $runs = $content['runs'] ?? (array_is_list($content) ? $content : [['text' => $content['text'] ?? '']]);
        $html = '';
        $plain = '';
        foreach ($runs as $run) {
            $run = is_string($run) ? ['text' => $run] : $run;
            $value = isset($run['token']) ? '{{'.$run['token'].'}}' : ($run['text'] ?? '');
            $text = $this->substitute($value, $context, $tokens);
            $markup = nl2br($this->escape($text), false);
            foreach (['bold' => 'strong', 'italic' => 'em', 'underline' => 'u'] as $flag => $tag) {
                if ($run[$flag] ?? false) {
                    $markup = '<'.$tag.'>'.$markup.'</'.$tag.'>';
                }
            }
            if (isset($run['color'])) {
                $markup = '<span style="color:'.$run['color'].';">'.$markup.'</span>';
            }
            $link = $run['link'] ?? $run['href'] ?? null;
            if ($link !== null) {
                $link = $this->substitute($link, $context, $tokens);
                $this->assertUrl($link);
                $markup = '<a href="'.$this->escape($link).'" style="color:#1d4ed8;text-decoration:underline;">'.$markup.'</a>';
                $text .= ' ('.$link.')';
            }
            $html .= $markup;
            $plain .= $text;
        }

        return [$html, $plain];
    }

    private function assertUrl(string $url): void
    {
        if (! NoticeEmailLinkPolicy::allows($url)) {
            throw new RuntimeException('email_link_invalid');
        }
    }

    private function style(array $block): string
    {
        $style = '';
        $values = array_merge($block, $block['style'] ?? []);
        foreach (['color' => 'color', 'background_color' => 'background-color', 'align' => 'text-align', 'font_family' => 'font-family'] as $key => $css) {
            if (isset($values[$key])) {
                $style .= $css.':'.$values[$key].';';
            }
        }
        foreach (['font_size' => 'font-size', 'spacing_before' => 'margin-top', 'spacing_after' => 'margin-bottom', 'padding' => 'padding', 'width' => 'width'] as $key => $css) {
            if (isset($values[$key])) {
                $style .= $css.':'.$this->dimension($values[$key]).';';
            }
        }

        return $style;
    }

    private function dimension(int|float|string $value): string
    {
        if (is_numeric($value)) {
            return $value.'px';
        }
        if (! preg_match('/^\d+(?:\.\d+)?(?:px|%)$/D', $value)) {
            throw new RuntimeException('email_dimension_invalid');
        }

        return $value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
