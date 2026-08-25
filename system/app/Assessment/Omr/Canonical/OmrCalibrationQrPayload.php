<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

use function array_values;
use function config;
use function hash;
use function hash_equals;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Signs and verifies calibration-purpose QR payloads for physical challenge
 * sheets (OpenSpec 35.19/35.27).
 *
 * The payload is opaque and signed/versioned — it carries only the challenge
 * code, page numbers, and signing key id; it never contains applicant
 * identity. It is deliberately distinct from the blank-instance payload
 * (`OmrQrPayload`, `nexiam:blank:*`) so capture registration can tell a
 * calibration challenge sheet from a real blank instance (AD-7, 35.31).
 *
 * Payload: `nexiam:calibration:v1:<challenge_code>:p<page_no>:p<page_count>:k<key_id>:<hmac>`
 * with `hmac = HMAC-SHA256(key[signing_key_id], "v1|<challenge_code>|p<page_no>|p<page_count>|k<key_id>")`.
 */
final class OmrCalibrationQrPayload
{
    public const PREFIX = 'nexiam:calibration';
    public const VERSION = 'v1';
    public const SIGNATURE_VERSION = 1;

    /** @param array<string, string> $keys */
    public function __construct(
        private readonly array $keys = [],
        private readonly ?string $currentKeyId = null,
    ) {
    }

    public function sign(string $challengeCode, int $pageNo, int $pageCount, ?string $keyId = null): string
    {
        $this->assertPage($pageNo, $pageCount);
        $this->assertChallengeCode($challengeCode);
        $keyId ??= $this->currentKeyId ?? (string) config('omr.qr-keyring.current', 'dev-v1');
        $key = $this->key($keyId);

        $message = $this->message($challengeCode, $pageNo, $pageCount, $keyId);
        $signature = hash_hmac('sha256', $message, $key);

        return sprintf(
            '%s:%s:%s:p%d:p%d:k%s:%s',
            self::PREFIX,
            self::VERSION,
            $challengeCode,
            $pageNo,
            $pageCount,
            $keyId,
            $signature,
        );
    }

    /**
     * @return array{version:string,challenge_code:string,page_no:int,page_count:int,signing_key_id:string,signature_version:int,payload:string,payload_hash:string}
     */
    public function verify(string $payload): array
    {
        $parsed = $this->parse($payload);
        $key = $this->keyring()[$parsed['signing_key_id']] ?? null;
        if (! is_string($key) || $key === '') {
            throw new OmrQrPayloadException(
                'qr_key_unknown',
                sprintf('QR signing key %s is not present in the configured keyring.', $parsed['signing_key_id']),
            );
        }

        $expected = hash_hmac(
            'sha256',
            $this->message($parsed['challenge_code'], $parsed['page_no'], $parsed['page_count'], $parsed['signing_key_id']),
            $key,
        );
        if (! hash_equals($expected, $parsed['signature'])) {
            throw new OmrQrPayloadException('qr_signature_invalid', 'Calibration QR payload signature is invalid.');
        }

        return [
            'version' => self::VERSION,
            'challenge_code' => $parsed['challenge_code'],
            'page_no' => $parsed['page_no'],
            'page_count' => $parsed['page_count'],
            'signing_key_id' => $parsed['signing_key_id'],
            'signature_version' => self::SIGNATURE_VERSION,
            'payload' => $payload,
            'payload_hash' => hash('sha256', $payload),
        ];
    }

    /**
     * @return array{version:string,challenge_code:string,page_no:int,page_count:int,signing_key_id:string,signature:string}
     */
    public function parse(string $payload): array
    {
        $matches = [];
        if (preg_match(
            '/^'.preg_quote(self::PREFIX, '/').'\\:'.preg_quote(self::VERSION, '/').'\\:(CHAL-[0-9A-F]{10})\\:p([1-9][0-9]*)\\:p([1-9][0-9]*)\\:k([A-Za-z0-9._-]{1,32})\\:([a-f0-9]{64})$/',
            $payload,
            $matches,
        ) !== 1) {
            throw new OmrQrPayloadException('qr_signature_invalid', 'Calibration QR payload format is invalid.');
        }

        $pageNo = (int) $matches[2];
        $pageCount = (int) $matches[3];
        try {
            $this->assertPage($pageNo, $pageCount);
        } catch (RuntimeException $exception) {
            throw new OmrQrPayloadException('qr_signature_invalid', $exception->getMessage());
        }

        return [
            'version' => self::VERSION,
            'challenge_code' => $matches[1],
            'page_no' => $pageNo,
            'page_count' => $pageCount,
            'signing_key_id' => $matches[4],
            'signature' => $matches[5],
        ];
    }

    public function payloadHash(string $payload): string
    {
        return hash('sha256', $payload);
    }

    /** @return list<string> */
    public function keyIds(): array
    {
        return array_values(array_keys($this->keyring()));
    }

    /** @return array<string, string> */
    private function keyring(): array
    {
        return $this->keys !== []
            ? $this->keys
            : (array) config('omr.qr-keyring.keys', []);
    }

    private function key(string $keyId): string
    {
        $key = $this->keyring()[$keyId] ?? null;
        if (! is_string($key) || $key === '') {
            throw new OmrQrPayloadException('qr_key_unknown', sprintf('QR signing key %s is not configured.', $keyId));
        }

        return $key;
    }

    private function message(string $challengeCode, int $pageNo, int $pageCount, string $keyId): string
    {
        return sprintf('%s|%s|p%d|p%d|k%s', self::VERSION, $challengeCode, $pageNo, $pageCount, $keyId);
    }

    private function assertChallengeCode(string $challengeCode): void
    {
        if (preg_match('/^CHAL-[0-9A-F]{10}$/', $challengeCode) !== 1) {
            throw new OmrQrPayloadException('challenge_code_invalid', 'Challenge code must be CHAL- plus ten uppercase hex characters.');
        }
    }

    private function assertPage(int $pageNo, int $pageCount): void
    {
        if ($pageNo < 1 || $pageCount < 1 || $pageNo > $pageCount) {
            throw new RuntimeException('QR page number must be between 1 and page count.');
        }
    }
}
