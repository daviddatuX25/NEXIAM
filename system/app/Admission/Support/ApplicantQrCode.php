<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * One permanent universal applicant-attempt locator (D-092, founder 2026-08-15).
 *
 * The token is DETERMINISTIC per attempt (HMAC over the attempt id), so the
 * QR never changes, regenerates, or expires — printed and portal copies are
 * the same code and stay valid for the applicant's admission window. Payload
 * carries no name, scores, notes, action, destination, capability, or
 * permission. Copying grants nothing: the scanner must authenticate and
 * CapabilityAuthorizer still evaluates every destination/action.
 */
final class ApplicantQrCode
{
    private const VERSION = 1;

    public static function issue(AdmissionAttempt $attempt): string
    {
        $payload = self::VERSION.':'.$attempt->id;
        $signature = hash_hmac('sha256', $payload, self::secret());

        return self::base64UrlEncode($payload.':'.$signature);
    }

    public static function entryUrl(AdmissionAttempt $attempt): string
    {
        $origin = parse_url((string) config('app.url'));

        if (! is_array($origin)
            || ! self::isPermittedOrigin($origin)
            || (isset($origin['path']) && $origin['path'] !== '/')
            || isset($origin['query'])
            || isset($origin['fragment'])
            || isset($origin['user'])
            || isset($origin['pass'])) {
            throw new RuntimeException('Applicant QR entry requires an HTTPS origin or a local loopback origin.');
        }

        $scheme = strtolower((string) $origin['scheme']);
        $host = strtolower((string) $origin['host']);
        $host = str_contains($host, ':') ? '['.$host.']' : $host;
        $port = isset($origin['port']) ? ':'.$origin['port'] : '';

        return $scheme.'://'.$host.$port.'/scan/applicant/'.self::issue($attempt);
    }

    private static function isPermittedOrigin(array $origin): bool
    {
        $scheme = strtolower((string) ($origin['scheme'] ?? ''));
        $host = strtolower((string) ($origin['host'] ?? ''));

        return isset($origin['host'])
            && (! isset($origin['path']) || $origin['path'] === '/')
            && ! array_key_exists('query', $origin)
            && ! array_key_exists('fragment', $origin)
            && ! array_key_exists('user', $origin)
            && ! array_key_exists('pass', $origin)
            && ($scheme === 'https'
                || ($scheme === 'http'
                    && app()->environment(['local', 'testing'])
                    && in_array($host, ['localhost', '127.0.0.1', '::1'], true)));
    }

    /**
     * Return a verified permanent locator from its historical raw form or canonical
     * same-origin HTTPS entry URL (local loopback HTTP is allowed for local runtimes).
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);
        $token = $value;

        if (str_contains($value, '://')) {
            $parts = parse_url($value);
            $application = parse_url((string) config('app.url'));

            if (! is_array($parts)
                || ! is_array($application)
                || ! self::isPermittedOrigin($application)
                || strtolower((string) ($parts['scheme'] ?? '')) !== strtolower((string) ($application['scheme'] ?? ''))
                || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) ($application['host'] ?? ''))
                || self::effectivePort($parts) !== self::effectivePort($application)
                || array_key_exists('user', $parts)
                || array_key_exists('pass', $parts)
                || array_key_exists('query', $parts)
                || array_key_exists('fragment', $parts)
                || preg_match('#^/scan/applicant/([A-Za-z0-9_-]+)$#D', (string) ($parts['path'] ?? ''), $matches) !== 1) {
                throw new RuntimeException('Code could not be resolved.');
            }

            $token = $matches[1];
        }

        self::verify($token);

        return $token;
    }

    private static function effectivePort(array $parts): int
    {
        $defaultPort = strtolower((string) ($parts['scheme'] ?? 'https')) === 'http' ? 80 : 443;

        return (int) ($parts['port'] ?? $defaultPort);
    }

    /**
     * Verify the locator and return the attempt id, or throw a typed error.
     * Denied results reveal no applicant data to the caller.
     */
    public static function verify(string $token): int
    {
        $decoded = self::base64UrlDecode(trim($token));

        if ($decoded === '' || ! str_contains($decoded, ':')) {
            throw new RuntimeException('Code could not be resolved.');
        }

        [$version, $attemptId, $signature] = array_pad(explode(':', $decoded, 3), 3, null);

        if ((int) $version !== self::VERSION || ! ctype_digit((string) $attemptId) || ! is_string($signature)) {
            throw new RuntimeException('Code could not be resolved.');
        }

        $expected = hash_hmac('sha256', self::VERSION.':'.$attemptId, self::secret());

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Code could not be resolved.');
        }

        return (int) $attemptId;
    }

    private static function secret(): string
    {
        return (string) App::make('encrypter')->getKey();
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');

        return base64_decode($value, true) ?: '';
    }
}
