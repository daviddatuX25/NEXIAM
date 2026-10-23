<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Email;

final class NoticeEmailLinkPolicy
{
    /** No resolution, fetching, URL rewriting or caller-supplied origin configuration. */
    public static function allows(string $url): bool
    {
        $origin = self::origin($url);
        if ($origin === null) {
            return false;
        }
        if ($origin['scheme'] === 'https') {
            return true;
        }
        if ($origin['scheme'] !== 'http' || ! app()->environment('local', 'testing')) {
            return false;
        }
        $configured = config('app.url');
        $localOrigin = is_string($configured) ? self::origin($configured) : null;

        return $localOrigin !== null && $localOrigin['scheme'] === 'http'
            && in_array($localOrigin['host'], ['localhost', '127.0.0.1', '[::1]'], true)
            && $origin === $localOrigin;
    }

    /** @return array{scheme:string,host:string,port:int}|null */
    private static function origin(string $url): ?array
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return ['scheme' => $scheme, 'host' => strtolower($parts['host']),
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80)];
    }
}
