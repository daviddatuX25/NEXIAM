<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

use function array_values;
use function config;
use function hash;
use function hash_equals;
use function json_encode;
use function preg_match;

/**
 * Signs and verifies opaque blank-instance QR payloads.
 *
 * The payload contains no applicant information. Key ids remain in the
 * payload so historical prints can be verified after key rotation.
 */
final class OmrQrPayload
{
    public const PREFIX = 'nexiam:blank';

    public const VERSION = 'v1';

    public const SIGNATURE_VERSION = 1;

    /** @param array<string, string> $keys */
    public function __construct(
        private readonly array $keys = [],
        private readonly ?string $currentKeyId = null,
    ) {}

    public function sign(string $instanceCode, int $pageNo, int $pageCount, ?string $keyId = null): string
    {
        $this->assertPage($pageNo, $pageCount);
        $keyId ??= $this->currentKeyId ?? (string) config('omr.qr-keyring.current', 'dev-v1');
        $key = $this->key($keyId);
        $this->assertInstanceCode($instanceCode);

        $message = $this->message($instanceCode, $pageNo, $pageCount, $keyId);
        $signature = hash_hmac('sha256', $message, $key);

        return sprintf(
            '%s:%s:%s:p%d:p%d:k%s:%s',
            self::PREFIX,
            self::VERSION,
            $instanceCode,
            $pageNo,
            $pageCount,
            $keyId,
            $signature,
        );
    }

    /**
     * @return array{version:string,instance_code:string,page_no:int,page_count:int,signing_key_id:string,signature_version:int,payload:string,payload_hash:string}
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
            $this->message($parsed['instance_code'], $parsed['page_no'], $parsed['page_count'], $parsed['signing_key_id']),
            $key,
        );
        if (! hash_equals($expected, $parsed['signature'])) {
            throw new OmrQrPayloadException('qr_signature_invalid', 'QR payload signature is invalid.');
        }

        return [
            'version' => self::VERSION,
            'instance_code' => $parsed['instance_code'],
            'page_no' => $parsed['page_no'],
            'page_count' => $parsed['page_count'],
            'signing_key_id' => $parsed['signing_key_id'],
            'signature_version' => self::SIGNATURE_VERSION,
            'payload' => $payload,
            'payload_hash' => hash('sha256', $payload),
        ];
    }

    /**
     * @return array{version:string,instance_code:string,page_no:int,page_count:int,signing_key_id:string,signature:string}
     */
    public function parse(string $payload): array
    {
        $matches = [];
        if (preg_match(
            '/^'.preg_quote(self::PREFIX, '/').'\\:'.preg_quote(self::VERSION, '/').'\\:(NI-[0-9A-HJKMNP-TV-Z]{26})\\:p([1-9][0-9]*)\\:p([1-9][0-9]*)\\:k([A-Za-z0-9._-]{1,32})\\:([a-f0-9]{64})$/',
            $payload,
            $matches,
        ) !== 1) {
            throw new OmrQrPayloadException('qr_signature_invalid', 'QR payload format is invalid.');
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
            'instance_code' => $matches[1],
            'page_no' => $pageNo,
            'page_count' => $pageCount,
            'signing_key_id' => $matches[4],
            'signature' => $matches[5],
        ];
    }

    /** @param list<string> $payloadHashes */
    public function manifestHash(array $payloadHashes): string
    {
        if ($payloadHashes === []) {
            throw new OmrQrPayloadException('qr_manifest_invalid', 'QR manifest must contain at least one page hash.');
        }
        foreach ($payloadHashes as $hashValue) {
            if (preg_match('/^[a-f0-9]{64}$/', $hashValue) !== 1) {
                throw new OmrQrPayloadException('qr_manifest_invalid', 'QR manifest contains an invalid page hash.');
            }
        }

        return hash('sha256', json_encode(array_values($payloadHashes), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
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
            throw new OmrQrPayloadException('qr_key_unknown', "QR signing key {$keyId} is not configured.");
        }

        return $key;
    }

    private function message(string $instanceCode, int $pageNo, int $pageCount, string $keyId): string
    {
        return sprintf('%s|%s|p%d|p%d|k%s', self::VERSION, $instanceCode, $pageNo, $pageCount, $keyId);
    }

    private function assertInstanceCode(string $instanceCode): void
    {
        if (preg_match('/^NI-[0-9A-HJKMNP-TV-Z]{26}$/', $instanceCode) !== 1) {
            throw new OmrQrPayloadException('instance_code_invalid', 'Instance code must be NI- plus 26 Crockford base32 characters.');
        }
    }

    private function assertPage(int $pageNo, int $pageCount): void
    {
        if ($pageNo < 1 || $pageCount < 1 || $pageNo > $pageCount) {
            throw new RuntimeException('QR page number must be between 1 and page count.');
        }
    }
}
