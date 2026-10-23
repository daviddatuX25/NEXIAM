<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Email;

use App\Notifications\Mail\NotificationMailable;
use HTMLPurifier;
use HTMLPurifier_Config;
use RuntimeException;

class NoticeLegacyEmailRenderer
{
    public const SCHEMA = 'notice-email-legacy-1';

    public const VERSION = 'legacy';

    /** @return array{subject:string,production_html:string,text:string,assets:array} */
    public function render(array $snapshot, string $templateKey, int $templateVersion): array
    {
        $template = $snapshot['template'];
        $email = $snapshot['email'];
        $blueprint = $email['blueprint'];
        if ($template['content_schema_version'] !== 'notice-content-1'
            || $template['email_schema_version'] !== self::SCHEMA || $template['email_renderer_version'] !== self::VERSION
            || ($blueprint['schema_version'] ?? null) !== self::SCHEMA) {
            throw new RuntimeException('email_renderer_unavailable');
        }
        if (array_diff(array_keys($blueprint), ['schema_version', 'body_template', 'action_label', 'action_url']) !== []
            || ! is_string($blueprint['body_template'] ?? null) || ! is_string($blueprint['action_url'] ?? null)
            || ($email['assets'] ?? []) !== []) {
            throw new RuntimeException('notice_snapshot_integrity');
        }
        $values = $snapshot['context']['values'];
        $subject = html_entity_decode($this->substitute($email['subject_template'], $values), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/[\r\n\x00-\x1f\x7f]/', $subject)) {
            throw new RuntimeException('email_subject_invalid');
        }
        $body = $this->substitute($blueprint['body_template'], $values);
        $body = preg_replace('#<(script|iframe)\b[^>]*>.*?</\1\s*>#is', '', $body) ?? $body;
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', 'p,h1,h2,h3,h4,strong,em,u,ul,ol,li,a[href|title],br,hr,blockquote,table,tr,td,th,thead,tbody,span[class|style]');
        $config->set('CSS.AllowedProperties', ['color', 'font-size', 'margin', 'padding', 'border']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('URI.DisableExternalResources', true);
        $config->set('HTML.SafeIframe', false);
        $config->set('Attr.EnableID', false);
        $body = (new HTMLPurifier($config))->purify($body);
        $payload = ['subject' => $subject, 'body' => $body, 'body_is_html' => true,
            'action_label' => $blueprint['action_label'], 'action_url' => $blueprint['action_url']];
        $html = (new NotificationMailable($templateKey, $templateVersion, $payload))->content()->htmlString;
        $plain = preg_replace('#</(?:p|h[1-4]|li|tr|blockquote)>|<br\s*/?>#i', "\n", $body) ?? $body;
        $plain = trim(html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($blueprint['action_url'] !== '#') {
            $plain .= "\n\n".(string) ($blueprint['action_label'] ?? 'View in NEXIAM').': '.$blueprint['action_url'];
        }

        return ['subject' => $subject, 'production_html' => $html, 'text' => $plain, 'assets' => []];
    }

    private function substitute(string $template, array $values): string
    {
        $result = preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', static function (array $match) use ($values): string {
            $key = trim($match[1]);
            if (! array_key_exists($key, $values) || ! is_string($values[$key]) || trim($values[$key]) === '') {
                throw new RuntimeException('email_context_missing');
            }

            return htmlspecialchars($values[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $template);
        if ($result === null || str_contains($result, '{{') || str_contains($result, '}}')) {
            throw new RuntimeException('email_token_invalid');
        }

        return $result;
    }
}
