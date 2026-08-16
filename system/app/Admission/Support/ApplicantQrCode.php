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
