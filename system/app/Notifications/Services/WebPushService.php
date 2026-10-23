<?php

declare(strict_types=1);

namespace App\Notifications\Services;

use App\Models\Notifications\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class WebPushService
{
    private const ASN1_HEADER = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * @return array{public_key: string, private_key: string} Base64url encoded VAPID keys
     */
    public static function generateVapidKeys(): array
    {
        $res = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($res === false) {
            throw new RuntimeException('Failed to generate EC P-256 key pair.');
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false) {
            throw new RuntimeException('Failed to get EC key details.');
        }

        $pubRaw = chr(4).str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        openssl_pkey_export($res, $privatePem);

        return [
            'public_key' => self::base64UrlEncode($pubRaw),
            'private_key' => self::base64UrlEncode(str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT)),
            'private_pem' => $privatePem,
        ];
    }


    public function vapidPublicKey(): string
    {
        $publicKey = config('services.webpush.vapid_public_key');
        if (! is_string($publicKey) || trim($publicKey) === '') {
            throw new RuntimeException('vapid_configuration_unavailable');
        }

        $rawPublicKey = self::base64UrlDecode(trim($publicKey));
        if (strlen($rawPublicKey) !== 65 || ord($rawPublicKey[0]) !== 4) {
            throw new RuntimeException('vapid_configuration_invalid');
        }

        $publicPem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::ASN1_HEADER).$rawPublicKey), 64, "\n")."-----END PUBLIC KEY-----\n";
        $publicHandle = openssl_pkey_get_public($publicPem);
        $publicDetails = $publicHandle === false ? false : openssl_pkey_get_details($publicHandle);
        if (! is_array($publicDetails)
            || ($publicDetails['type'] ?? null) !== OPENSSL_KEYTYPE_EC
            || ($publicDetails['ec']['curve_name'] ?? null) !== 'prime256v1'
            || self::base64UrlEncode(chr(4).str_pad($publicDetails['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($publicDetails['ec']['y'], 32, "\0", STR_PAD_LEFT)) !== trim($publicKey)) {
            throw new RuntimeException('vapid_configuration_invalid');
        }

        $privatePem = config('services.webpush.vapid_private_pem');
        if (! is_string($privatePem) || trim($privatePem) === '') {
            $privateKey = config('services.webpush.vapid_private_key');
            if (! is_string($privateKey) || trim($privateKey) === '') {
                throw new RuntimeException('vapid_configuration_unavailable');
            }

            $rawPrivateKey = self::base64UrlDecode(trim($privateKey));
            if (strlen($rawPrivateKey) !== 32) {
                throw new RuntimeException('vapid_configuration_invalid');
            }

            $privatePem = self::rawPrivateDToPem($rawPrivateKey);
        }

        $privateHandle = openssl_pkey_get_private($privatePem);
        $privateDetails = $privateHandle === false ? false : openssl_pkey_get_details($privateHandle);
        if (! is_array($privateDetails)
            || ($privateDetails['type'] ?? null) !== OPENSSL_KEYTYPE_EC
            || ($privateDetails['ec']['curve_name'] ?? null) !== 'prime256v1'
            || self::base64UrlEncode(chr(4).str_pad($privateDetails['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($privateDetails['ec']['y'], 32, "\0", STR_PAD_LEFT)) !== trim($publicKey)) {
            throw new RuntimeException('vapid_configuration_invalid');
        }

        return trim($publicKey);
    }

    /**
     * Encrypt message payload using RFC 8291 (aes128gcm).
     */
    public function encryptPayload(string $plaintext, string $clientP256dhBase64, string $clientAuthTokenBase64): string
    {
        $receiverPubRaw = self::base64UrlDecode($clientP256dhBase64);
        $receiverAuth = self::base64UrlDecode($clientAuthTokenBase64);

        if (strlen($receiverPubRaw) !== 65 || ord($receiverPubRaw[0]) !== 4) {
            throw new RuntimeException('Invalid receiver P-256 public key (must be 65-byte uncompressed point).');
        }

        // 1. Generate sender ephemeral EC P-256 key pair
        $senderKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($senderKey === false) {
            throw new RuntimeException('Failed to generate ephemeral EC key.');
        }
        $senderDetails = openssl_pkey_get_details($senderKey);
        $senderPubRaw = chr(4).str_pad($senderDetails['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($senderDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);

        // 2. Wrap receiver raw public key in DER/PEM to derive shared secret via ECDH
        $receiverDer = hex2bin(self::ASN1_HEADER).$receiverPubRaw;
        $receiverPem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($receiverDer), 64, "\n")."-----END PUBLIC KEY-----\n";
        $receiverKey = openssl_pkey_get_public($receiverPem);
        if ($receiverKey === false) {
            throw new RuntimeException('Failed to parse receiver public key PEM.');
        }

        $sharedSecret = openssl_pkey_derive($receiverKey, $senderKey);
        if ($sharedSecret === false || strlen($sharedSecret) === 0) {
            throw new RuntimeException('Failed to derive ECDH shared secret.');
        }

        // 3. RFC 8291 Key Derivation
        $salt = random_bytes(16);
        $keyInfo = "WebPush: info\0".$receiverPubRaw.$senderPubRaw;
        $prkAuth = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $receiverAuth);
        $prk = hash_hkdf('sha256', $prkAuth, 32, 'Content-Encoding: aes128gcm\0', $salt);
        $cek = substr($prk, 0, 16);
        $nonce = hash_hkdf('sha256', $prkAuth, 12, 'Content-Encoding: nonce\0', $salt);

        // 4. AES-128-GCM encryption with 0x02 record delimiter
        $padded = $plaintext."\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('AES-128-GCM encryption failed.');
        }

        // 5. Binary record header: salt (16) + record_size (4) + key_id_len (1) + sender_pub (65) + ciphertext + tag (16)
        $recordSize = pack('N', 4096);
        $keyIdLen = chr(65);

        return $salt.$recordSize.$keyIdLen.$senderPubRaw.$ciphertext.$tag;
    }

    /**
     * Create RFC 8292 VAPID Authorization header token.
     */
    public function createVapidToken(string $audience): string
    {
        $publicKey = $this->vapidPublicKey();
        $subject = (string) (config('services.webpush.vapid_subject') ?? 'mailto:admin@nexiam.edu.ph');
        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $claims = self::base64UrlEncode((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 86400,
            'sub' => $subject,
        ], JSON_THROW_ON_ERROR));

        $privatePem = config('services.webpush.vapid_private_pem');
        if (! is_string($privatePem) || trim($privatePem) === '') {
            $rawPrivateKey = self::base64UrlDecode((string) config('services.webpush.vapid_private_key', ''));
            $privatePem = self::rawPrivateDToPem($rawPrivateKey);
        }

        $privateHandle = openssl_pkey_get_private($privatePem);
        if ($privateHandle === false) {
            throw new RuntimeException('vapid_configuration_invalid');
        }

        $dataToSign = $header.'.'.$claims;
        $signatureDer = '';
        if (! openssl_sign($dataToSign, $signatureDer, $privateHandle, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('vapid_configuration_invalid');
        }

        return $dataToSign.'.'.self::base64UrlEncode(self::derToP1363($signatureDer));
    }

    /**
     * Dispatch encrypted push message to the given subscription endpoint.
     *
     * @param  array{title: string, body: string, url?: string, tag?: string}  $payload
     * @return array{status: int, success: bool, reason?: string}
     */
    public function send(PushSubscription $subscription, array $payload): array
    {
        $endpoint = $subscription->endpoint;
        $parsed = parse_url($endpoint);
        $origin = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? 'localhost');

        $jsonPayload = (string) json_encode($payload, JSON_THROW_ON_ERROR);

        try {
            $encryptedBody = $this->encryptPayload($jsonPayload, $subscription->p256dh, $subscription->auth_token);
        } catch (\Throwable $e) {
            Log::error('Web push encryption failed', ['exception' => $e->getMessage()]);

            return ['status' => 0, 'success' => false, 'reason' => 'encryption_failed: '.$e->getMessage()];
        }

        try {
            $vapidToken = $this->createVapidToken($origin);
            $vapidKey = $this->vapidPublicKey();
        } catch (RuntimeException $exception) {
            return ['status' => 0, 'success' => false, 'reason' => $exception->getMessage()];
        }

        try {
            $response = Http::withBody($encryptedBody, 'application/octet-stream')
                ->withHeaders([
                    'Content-Encoding' => 'aes128gcm',
                    'TTL' => '86400',
                    'Urgency' => 'normal',
                    'Authorization' => "vapid t={$vapidToken}, k={$vapidKey}",
                ])
                ->timeout(10)
                ->post($endpoint);

            $status = $response->status();

            if ($status === 404 || $status === 410) {
                // Subscription has expired or been unsubscribed on device
                $subscription->revoke();

                return ['status' => $status, 'success' => false, 'reason' => 'endpoint_deactivated'];
            }

            return [
                'status' => $status,
                'success' => $status >= 200 && $status < 300,
            ];
        } catch (\Throwable $e) {
            Log::warning('Web push network delivery failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            return ['status' => 0, 'success' => false, 'reason' => 'network_failed: '.$e->getMessage()];
        }
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }

    private static function derToP1363(string $der): string
    {
        if (strlen($der) < 8) {
            return str_repeat("\0", 64);
        }
        $pos = 3;
        $rLen = ord($der[$pos]);
        $r = substr($der, $pos + 1, $rLen);
        $pos += 1 + $rLen + 1;
        if (! isset($der[$pos])) {
            return str_repeat("\0", 64);
        }
        $sLen = ord($der[$pos]);
        $s = substr($der, $pos + 1, $sLen);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    private static function rawPrivateDToPem(string $rawD): string
    {
        // SEC1 with curve parameters only: OpenSSL derives the public point from the private scalar.
        $der = hex2bin('30310201010420').$rawD.hex2bin('a00a06082a8648ce3d030107');

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }
}
