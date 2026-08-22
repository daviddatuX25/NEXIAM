<?php

namespace App\Assessment\Omr\Canonical;

use HTMLPurifier;
use HTMLPurifier_Config;

use function preg_match;
use function preg_match_all;
use function trim;

final class OmrHtmlSanitizer
{
    /**
     * @return array{html:string,slots:list<array{name:string,tag:string}>}
     */
    public function sanitize(string $html): array
    {
        $this->rejectRemoteOrExecutableMarkup($html);
        $this->rejectUnknownDynamicTokens($html);

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', implode(',', [
            'a[href|title|target]',
            'br',
            'div[class|style|data-omr-overlay]',
            'em',
            'h1[class|style|data-omr-overlay]',
            'h2[class|style|data-omr-overlay]',
            'h3[class|style|data-omr-overlay]',
            'img[src|alt|width|height]',
            'li[class|style|data-omr-overlay]',
            'ol[class|style|data-omr-overlay]',
            'p[class|style|data-omr-overlay]',
            'small',
            'span[class|style|data-omr-overlay]',
            'strong',
            'table[class|style|data-omr-overlay]',
            'tbody',
            'td[class|style|data-omr-overlay]',
            'tfoot',
            'th[class|style|data-omr-overlay]',
            'thead',
            'tr[class|style|data-omr-overlay]',
            'ul[class|style|data-omr-overlay]',
        ]));
        $config->set('CSS.AllowedProperties', [
            'background-color',
            'border',
            'border-bottom',
            'border-collapse',
            'border-left',
            'border-right',
            'border-top',
            'color',
            'font-family',
            'font-size',
            'font-style',
            'font-weight',
            'height',
            'line-height',
            'margin',
            'margin-bottom',
            'margin-left',
            'margin-right',
            'margin-top',
            'padding',
            'padding-bottom',
            'padding-left',
            'padding-right',
            'padding-top',
            'text-align',
            'vertical-align',
            'width',
        ]);
        $config->set('URI.DisableExternalResources', true);
        $config->set('URI.AllowedSchemes', ['data' => true]);
        $config->set('HTML.SafeIframe', false);
        $config->set('Attr.EnableID', false);
        $config->set('Output.TidyFormat', false);
        $config->set('HTML.DefinitionID', 'nexiam-omr-canonical-v1');
        $config->set('HTML.DefinitionRev', 1);
        $definition = $config->maybeGetRawHTMLDefinition();
        if ($definition !== null) {
            foreach (['div', 'h1', 'h2', 'h3', 'li', 'ol', 'p', 'span', 'table', 'td', 'th', 'tr', 'ul'] as $tag) {
                $definition->addAttribute($tag, 'data-omr-overlay', 'Text');
            }
        }

        $purified = trim((new HTMLPurifier($config))->purify($html));
        $slots = [];
        if (preg_match_all('/data-omr-overlay\s*=\s*["\']([^"\']+)["\']/i', $purified, $matches) !== false) {
            foreach ($matches[1] as $name) {
                $slots[] = ['name' => trim($name), 'tag' => 'data-omr-overlay'];
            }
        }

        return ['html' => $purified, 'slots' => $slots];
    }

    private function rejectRemoteOrExecutableMarkup(string $html): void
    {
        if (preg_match('/<(?:script|iframe|object|embed|form|link|meta)\b|\bon[a-z][a-z0-9_-]*\s*=|(?:src|href)\s*=\s*["\'](?:https?:|ftp:|file:|\/\/)/i', $html) === 1) {
            throw new OmrSourcePreflightException('html_unsafe_markup', 'HTML contains executable markup or a remote resource.');
        }
    }

    private function rejectUnknownDynamicTokens(string $html): void
    {
        if (preg_match_all('/\{\{.*?\}\}|\$\{.*?\}|\[\[.*?\]\]/s', $html, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }
        foreach ($matches[0] as [$token, $offset]) {
            $before = substr($html, 0, (int) $offset);
            $stack = [];
            if (preg_match_all('/<\s*(\/?)([a-z][a-z0-9:-]*)([^>]*)>/is', $before, $tags, PREG_SET_ORDER) !== false) {
                foreach ($tags as $tag) {
                    $name = strtolower($tag[2]);
                    if ($tag[1] === '/') {
                        array_pop($stack);
                        continue;
                    }
                    $attributes = $tag[3] ?? '';
                    $selfClosing = str_ends_with(trim($attributes), '/')
                        || in_array($name, ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'], true);
                    if (! $selfClosing) {
                        $stack[] = preg_match('/\bdata-omr-overlay\s*=\s*["\'][^"\']+["\']/i', $attributes) === 1;
                    }
                }
            }
            if (! in_array(true, $stack, true)) {
                throw new OmrSourcePreflightException('dynamic_token_rejected', "Dynamic token {$token} must be inside a fixed data-omr-overlay slot.");
            }
        }
    }
}
