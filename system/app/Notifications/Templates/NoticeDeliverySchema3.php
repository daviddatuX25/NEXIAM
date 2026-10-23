<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Notifications\NotificationIntent;
use Illuminate\Support\Str;

final class NoticeDeliverySchema3
{
    private const PDF_TUPLES = [
        ['admissions', 'accepted', 'admission.accepted.v1', 'admission.accepted'],
        ['examination', 'session-published', 'exam.session_published.v1', 'exam.session_published'],
        ['results', 'released-online', 'result.released.v1', 'result.released.online'],
    ];

    public static function valid(array $snapshot): bool
    {
        $tuple = [$snapshot['domain'] ?? null, $snapshot['canonical_slug'] ?? null,
            $snapshot['event_type'] ?? null, $snapshot['template_key'] ?? null];
        [$domain, $slug, $eventType, $templateKey] = $tuple;
        if (! is_string($domain) || ! is_string($slug) || ! is_string($eventType) || ! is_string($templateKey)
            || NotificationTemplateResolver::mapping(NotificationIntent::v1($eventType, $templateKey, [])) !== [$domain, $slug]
            || CommunicationTemplateRegistry::authoringMode($domain, $slug) === 'builtin') {
            return false;
        }

        $printEligible = in_array($tuple, self::PDF_TUPLES, true);
        $print = $snapshot['print'] ?? null;
        $template = $snapshot['template'] ?? null;
        $context = $snapshot['context'] ?? null;
        $email = $snapshot['email'] ?? null;

        if (! is_array($template)) {
            return false;
        }
        $rendererVersion = $template['email_renderer_version'] ?? null;

        return Str::isUuid($template['id'] ?? '')
            && (int) ($template['version'] ?? 0) > 0
            && is_string($template['content_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $template['content_sha256']) === 1
            && ($template['content_schema_version'] ?? null) === 'notice-content-3'
            && ($template['email_schema_version'] ?? null) === 'notice-email-1'
            && in_array($rendererVersion, ['notice-email-1', 'notice-message-email-2'], true)
            && is_array($context)
            && is_array($context['values'] ?? null)
            && is_array($context['missing_required_keys'] ?? null)
            && is_array($email)
            && is_array($email['blueprint'] ?? null)
            && is_array($email['assets'] ?? null)
            && is_array($snapshot['recipient'] ?? null)
            && is_string($snapshot['recipient']['type'] ?? null)
            && (int) ($snapshot['recipient']['id'] ?? 0) > 0
            && is_array($snapshot['event_resource'] ?? null)
            && self::resourceMatches($domain, $slug, $snapshot['event_resource'])
            && (int) ($snapshot['institution_id'] ?? 0) > 0
            && is_array($snapshot['portal'] ?? null)
            && ($rendererVersion !== 'notice-message-email-2' || self::validAction($email['action'] ?? null))
            && ! self::containsScoreData(['context' => $context, 'email' => $email, 'portal' => $snapshot['portal']])
            && ($print === null || ($printEligible && self::validPrint($print, $slug, $snapshot)));
    }

    private static function validAction(mixed $action): bool
    {
        if (! is_array($action)
            || array_diff(array_keys($action), ['enabled', 'label', 'route', 'url']) !== []
            || count($action) !== 4
            || ! is_bool($action['enabled'] ?? null)) {
            return false;
        }

        if ($action['enabled'] === false) {
            return $action['label'] === null && $action['route'] === null && $action['url'] === null;
        }

        return is_string($action['label'])
            && trim($action['label']) !== ''
            && mb_strlen($action['label']) <= 64
            && is_string($action['route'])
            && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/D', $action['route']) === 1
            && is_string($action['url'])
            && strlen($action['url']) <= 2048
            && self::isSafeFrozenUrl($action['url']);
    }

    private static function isSafeFrozenUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && is_string($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    private static function resourceMatches(string $domain, string $slug, array $resource): bool
    {
        return match ($domain) {
            'admissions' => (int) ($resource['attempt_id'] ?? 0) > 0
                && (int) ($resource['identity_id'] ?? 0) > 0,
            'examination' => $slug === 'reminder'
                ? (int) ($resource['id'] ?? 0) > 0
                    && (int) ($resource['assignment_id'] ?? 0) > 0
                    && (int) ($resource['assignment_version'] ?? 0) > 0
                    && is_string($resource['starts_at'] ?? null)
                : (int) ($resource['session_id'] ?? 0) > 0
                    && (int) ($resource['attempt_id'] ?? 0) > 0
                    && (int) ($resource['identity_id'] ?? 0) > 0,
            'results' => $slug === 'consultation-published'
                ? (int) ($resource['consultation_session_id'] ?? 0) > 0
                    && (int) ($resource['attempt_id'] ?? 0) > 0
                    && (int) ($resource['identity_id'] ?? 0) > 0
                : (int) ($resource['release_id'] ?? 0) > 0
                    && (int) ($resource['release_version'] ?? 0) > 0
                    && (int) ($resource['attempt_id'] ?? 0) > 0
                    && (int) ($resource['identity_id'] ?? 0) > 0,
            default => false,
        };
    }

    private static function validPrint(mixed $print, string $slug, array $snapshot): bool
    {
        $allowedKeys = ['mode', 'document_revision_id', 'version', 'definition_sha256', 'render_sha256', 'state', 'assets'];
        if ($slug === 'released-online') {
            $allowedKeys[] = 'canonical_released_result';
        }
        if (! is_array($print)
            || array_diff(array_keys($print), $allowedKeys) !== []
            || ($print['mode'] ?? null) !== 'notice_document'
            || ! is_int($print['document_revision_id'] ?? null) || $print['document_revision_id'] < 1
            || ! is_int($print['version'] ?? null) || $print['version'] < 1
            || ! is_array($print['state'] ?? null) || ! is_array($print['assets'] ?? null)
            || ! is_string($print['definition_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $print['definition_sha256']) !== 1
            || ! is_string($print['render_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $print['render_sha256']) !== 1) {
            return false;
        }

        if ($slug !== 'released-online') {
            return ! array_key_exists('canonical_released_result', $print);
        }

        $binding = $print['canonical_released_result'] ?? null;
        $resource = $snapshot['event_resource'];
        $recipient = $snapshot['recipient'];

        return is_array($binding)
            && ($binding['binding_schema_version'] ?? null) === 'released-result-1'
            && ($binding['data_provider'] ?? null) === 'result-document.snapshot_payload_json'
            && (int) ($binding['institution_id'] ?? 0) === (int) $snapshot['institution_id']
            && (int) ($binding['recipient']['id'] ?? 0) === (int) ($recipient['id'] ?? 0)
            && ($binding['recipient']['type'] ?? null) === ($recipient['type'] ?? null)
            && (int) ($binding['attempt_id'] ?? 0) === (int) ($resource['attempt_id'] ?? 0)
            && ($resource['mode'] ?? null) === 'online'
            && ($binding['mode'] ?? null) === 'online';
    }

    private static function containsScoreData(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if (str_contains(strtolower((string) $key), 'score')
                || (is_array($value) && self::containsScoreData($value))) {
                return true;
            }
        }

        return false;
    }
}
