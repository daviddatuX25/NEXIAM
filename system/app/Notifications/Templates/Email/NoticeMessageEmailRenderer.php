<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Email;

use RuntimeException;

final class NoticeMessageEmailRenderer
{
    public const VERSION = 'notice-message-email-2';

    public function __construct(private readonly NoticeEmailRenderer $bodyRenderer) {}

    /**
     * @param  list<string>  $allowedTokens
     * @param  list<array<string, mixed>>  $assets
     * @param  array{enabled:bool,label:?string,route:?string,url:?string}|null  $action
     * @return array{subject:string,production_html:string,text:string,assets:array}
     */
    public function render(
        array $blueprint,
        array $context,
        array $allowedTokens,
        string $schemaVersion,
        string $rendererVersion,
        array $assets = [],
        string $subject = '',
        ?array $action = null,
    ): array {
        if (! in_array($rendererVersion, [NoticeEmailRenderer::VERSION, self::VERSION], true)) {
            throw new RuntimeException('email_renderer_unavailable');
        }

        $rendered = $this->bodyRenderer->render(
            $blueprint,
            $context,
            $allowedTokens,
            $schemaVersion,
            NoticeEmailRenderer::VERSION,
            $assets,
            $subject,
        );

        if ($rendererVersion === NoticeEmailRenderer::VERSION) {
            return $rendered;
        }

        if ($action === null) {
            throw new RuntimeException('email_action_invalid');
        }
        $this->assertAction($action);
        if (! $action['enabled']) {
            return $rendered;
        }

        $url = $action['url'];
        $label = $action['label'];
        $ctaHtml = '<p style="margin:24px 0 0;"><a href="'.$this->escape($url).'"><strong>'.$this->escape($label).'</strong></a></p>';
        $closing = '</td></tr></table></td></tr></table></body></html>';
        if (! str_ends_with($rendered['production_html'], $closing)) {
            throw new RuntimeException('email_renderer_output_invalid');
        }

        $rendered['production_html'] = substr($rendered['production_html'], 0, -strlen($closing)).$ctaHtml.$closing;
        $rendered['text'] = trim($rendered['text'])."\n\n".$label.': '.$url;

        return $rendered;
    }

    /** @return array{preview_html:string,projection_sha256:string} */
    public function project(string $productionHtml, array $assets): array
    {
        return $this->bodyRenderer->project($productionHtml, $assets);
    }

    /** @param list<string> $allowedTokens */
    public function substitute(string $text, array $context, array $allowedTokens): string
    {
        return $this->bodyRenderer->substitute($text, $context, $allowedTokens);
    }

    /** @param array{enabled:bool,label:?string,route:?string,url:?string} $action */
    private function assertAction(array $action): void
    {
        if (count($action) !== 4 || array_diff(array_keys($action), ['enabled', 'label', 'route', 'url']) !== []
            || ! is_bool($action['enabled'] ?? null)) {
            throw new RuntimeException('email_action_invalid');
        }

        if (! $action['enabled']) {
            if ($action['label'] !== null || $action['route'] !== null || $action['url'] !== null) {
                throw new RuntimeException('email_action_invalid');
            }

            return;
        }

        if (! is_string($action['label']) || trim($action['label']) === '' || mb_strlen($action['label']) > 64
            || ! is_string($action['route']) || $action['route'] === '' || ! is_string($action['url'])
            || ! NoticeEmailLinkPolicy::allows($action['url'])) {
            throw new RuntimeException('email_action_invalid');
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
