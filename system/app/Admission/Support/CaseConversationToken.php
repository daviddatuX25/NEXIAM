<?php

namespace App\Admission\Support;

use App\Models\User;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class CaseConversationToken
{
    private const int TTL_SECONDS = 300;

    private const int MAX_TOKEN_LENGTH = 1024;

    public static function presentationContext(User $actor): string
    {
        return self::presentationContextForPrincipal((string) $actor->getKey());
    }

    public static function presentationContextForPrincipal(string $principalKey): string
    {
        $key = (string) config('app.key');
        $sessionHash = self::resolveSessionBinding($principalKey, true);
        if ($sessionHash === null || $key === '') {
            throw new RuntimeException('A started session and application key are required.');
        }

        $message = json_encode([
            'domain' => 'case-conversation-presentation-v1',
            'user' => $principalKey,
            'session' => $sessionHash,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return 'messaging-client-v1.'.hash_hmac('sha256', $message, $key);
    }

    public static function issue(User $actor, int|string $threadId, string $highestSequence): string
    {
        return self::issueForPrincipal((string) $actor->getKey(), $threadId, $highestSequence);
    }

    public static function issueForPrincipal(string $principalKey, int|string $threadId, string $highestSequence): string
    {
        $key = (string) config('app.key');
        $sessionHash = self::resolveSessionBinding($principalKey, true);

        if ($sessionHash === null || $key === '') {
            throw new RuntimeException('A started session and application key are required.');
        }

        $thread = (string) $threadId;
        if (strlen($thread) > 128 || strlen($highestSequence) > 128) {
            throw new InvalidArgumentException('Conversation token binding exceeds the supported size.');
        }

        $payload = json_encode([
            'v' => 1,
            'user' => $principalKey,
            'session' => $sessionHash,
            'thread' => $thread,
            'sequence' => $highestSequence,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encodedPayload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $encodedPayload, $key);
        $token = $encodedPayload.'.'.$signature;

        if (strlen($token) > self::MAX_TOKEN_LENGTH) {
            throw new InvalidArgumentException('Conversation token exceeds the supported size.');
        }

        return $token;
    }

    public static function verify(User $actor, int|string $threadId, string $highestSequence, ?string $token): bool
    {
        return self::verifyForPrincipal((string) $actor->getKey(), $threadId, $highestSequence, $token);
    }

    public static function verifyForPrincipal(string $principalKey, int|string $threadId, string $highestSequence, ?string $token): bool
    {
        $key = (string) config('app.key');
        $sessionHash = self::resolveSessionBinding($principalKey, false);
        if (
            $token === null
            || strlen($token) > self::MAX_TOKEN_LENGTH
            || $sessionHash === null
            || $key === ''
        ) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2 || preg_match('/^[a-f0-9]{64}$/D', $parts[1]) !== 1) {
            return false;
        }

        [$encodedPayload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $key);
        if (! hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $payloadJson = self::base64UrlDecode($encodedPayload);
        if ($payloadJson === null) {
            return false;
        }

        try {
            $payload = json_decode($payloadJson, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (
            ! is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ! is_string($payload['user'] ?? null)
            || ! is_string($payload['session'] ?? null)
            || ! is_string($payload['thread'] ?? null)
            || ! is_string($payload['sequence'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] <= now()->getTimestamp()
            || $payload['user'] !== $principalKey
            || $payload['thread'] !== (string) $threadId
            || $payload['sequence'] !== $highestSequence
        ) {
            return false;
        }

        return hash_equals($payload['session'], $sessionHash);
    }

    private static function resolveSessionBinding(string $principalKey, bool $throwIfMissing): ?string
    {
        if (str_starts_with($principalKey, 'guest:') && strlen($principalKey) > 6) {
            return hash('sha256', $principalKey);
        }

        $session = session();
        $sessionId = (string) $session->getId();
        if (! $session->isStarted() || $sessionId === '') {
            if ($throwIfMissing) {
                throw new RuntimeException('A started session and application key are required.');
            }

            return null;
        }

        return hash('sha256', $sessionId);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
