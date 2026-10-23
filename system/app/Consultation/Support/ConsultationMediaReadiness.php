<?php

declare(strict_types=1);

namespace App\Consultation\Support;

use Illuminate\Support\Facades\Http;

final class ConsultationMediaReadiness
{
    /** @return array{ready: bool, reason: ?string} */
    public function report(): array
    {
        $origin = (string) config('jitsi.origin', '');
        $parsedOrigin = parse_url($origin);
        $originReady = is_array($parsedOrigin)
            && ($parsedOrigin['scheme'] ?? null) === 'https'
            && isset($parsedOrigin['host'])
            && ! array_key_exists('user', $parsedOrigin)
            && ! array_key_exists('pass', $parsedOrigin)
            && in_array($parsedOrigin['path'] ?? '', ['', '/'], true)
            && ! isset($parsedOrigin['query'])
            && ! isset($parsedOrigin['fragment']);

        $signingSecret = (string) config('jitsi.app_secret', '');
        $admissionSecret = (string) config('jitsi.admission_secret', '');
        $controlToken = (string) config('jitsi.control_token', '');
        $controlUrl = (string) config('jitsi.control_url', '');
        $control = parse_url($controlUrl);
        $controlReady = is_array($control)
            && ($control['scheme'] ?? null) === 'http'
            && ($control['host'] ?? null) === 'prosody'
            && (int) ($control['port'] ?? 0) === 5280
            && ($control['path'] ?? '') === '/nexiam_admission'
            && ! isset($control['user'])
            && ! isset($control['pass'])
            && ! isset($control['query'])
            && ! isset($control['fragment'])
            && strlen($controlToken) >= 32;

        $reason = match (true) {
            strlen($signingSecret) < 32 => 'Online consultation is unavailable: configure a media signing secret of at least 32 bytes.',
            strlen($admissionSecret) < 32 => 'Online consultation is unavailable: configure a separate admission secret of at least 32 bytes.',
            ! $originReady => 'Online consultation is unavailable: configure an approved HTTPS media origin.',
            hash_equals($signingSecret, $admissionSecret) => 'Online consultation is unavailable: media signing and admission secrets must be independently provisioned.',
            hash_equals($signingSecret, $controlToken) || hash_equals($admissionSecret, $controlToken) => 'Online consultation is unavailable: media, admission, and control secrets must be independently provisioned.',
            ! $controlReady => 'Online consultation is unavailable: configure authenticated internal control at the fixed Prosody service endpoint.',
            ! is_string(config('jitsi.domain')) || config('jitsi.domain') === '' => 'Online consultation is unavailable: configure the Jitsi media domain.',
            ! is_string(config('jitsi.app_id')) || config('jitsi.app_id') === '' => 'Online consultation is unavailable: configure the Jitsi application ID.',
            ! is_string(config('jitsi.audience')) || config('jitsi.audience') === '' => 'Online consultation is unavailable: configure the accepted media-token audience.',
            default => $this->verifyLiveControl($controlUrl, $controlToken),
        };

        if (is_array($reason)) {
            return $reason;
        }

        return ['ready' => false, 'reason' => $reason];
    }

    /** @return array{ready: bool, reason: ?string} */
    private function verifyLiveControl(string $controlUrl, string $controlToken): array
    {
        try {
            $nonce = bin2hex(random_bytes(24));
            $response = Http::connectTimeout(2)
                ->timeout(3)
                ->acceptJson()
                ->withToken($controlToken)
                ->post(rtrim($controlUrl, '/').'/health', ['nonce' => $nonce]);
        } catch (\Throwable) {
            return ['ready' => false, 'reason' => 'Online consultation is unavailable: authenticated Jitsi health verification failed.'];
        }

        $body = $response->json();
        $now = now()->timestamp;
        $callbacksVerifiedAt = is_array($body) ? ($body['callbacks_verified_at'] ?? null) : null;
        $callbacksAge = is_array($body) ? ($body['callbacks_age_seconds'] ?? null) : null;
        $observedCallbacksAge = is_int($callbacksVerifiedAt) ? $now - $callbacksVerifiedAt : null;
        $healthy = $response->status() === 200
            && is_array($body)
            && ($body['nonce'] ?? null) === $nonce
            && ($body['protocol_version'] ?? null) === 1
            && ($body['admission_hook_installed'] ?? null) === true
            && ($body['admission_callback_reachable'] ?? null) === true
            && ($body['admission_callback_status'] ?? null) === 200
            && ($body['presence_callback_reachable'] ?? null) === true
            && ($body['mandatory_lobby_installed'] ?? null) === true
            && is_int($callbacksVerifiedAt)
            && $callbacksVerifiedAt > 0
            && is_int($callbacksAge)
            && $callbacksAge >= 0
            && $callbacksAge <= 10
            && is_int($observedCallbacksAge)
            && $observedCallbacksAge >= 0
            && $observedCallbacksAge <= 10
            && abs($callbacksAge - $observedCallbacksAge) <= 1;

        return $healthy
            ? ['ready' => true, 'reason' => null]
            : ['ready' => false, 'reason' => 'Online consultation is unavailable: live Jitsi admission/control health was not positively verified.'];
    }
}
