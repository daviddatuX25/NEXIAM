<?php

namespace App\Ai\Support;

use Illuminate\Support\Facades\App;
use InvalidArgumentException;

final class EndpointSecurityGuard
{
    public const MAX_DISCOVERY_RESPONSE_BYTES = 2_097_152;

    /**
     * @var array<string, string>
     */
    private const DEFAULT_BASE_URLS = [
        'openrouter' => 'https://openrouter.ai/api/v1',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
        'anthropic' => 'https://api.anthropic.com/v1',
        'openai' => 'https://api.openai.com/v1',
        'deepseek' => 'https://api.deepseek.com/v1',
        'groq' => 'https://api.groq.com/openai/v1',
        'ollama' => 'http://localhost:11434/v1',
    ];

    public function normalizeVersionedBaseUrl(string $driver, ?string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl ?? ''), '/');

        if ($baseUrl === '') {
            return self::DEFAULT_BASE_URLS[$driver] ?? throw new InvalidArgumentException("No default base URL is configured for driver [{$driver}].");
        }

        if ($driver === 'gemini' && ! str_ends_with(strtolower($baseUrl), '/v1beta')) {
            return $baseUrl.'/v1beta';
        }

        if (in_array($driver, ['anthropic', 'openai', 'deepseek'], true) && ! str_ends_with(strtolower($baseUrl), '/v1')) {
            return $baseUrl.'/v1';
        }

        return $baseUrl;
    }

    /**
     * @param  callable(string): array<mixed>|null  $dnsResolver
     * @return array{url: string, host: string, port: int, pinned_ip: string, curl_options: array<int, mixed>}
     */
    public function assertAndResolveSafeEndpoint(string $url, string $driver, ?callable $dnsResolver = null): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Discovery endpoint must include a valid scheme and host.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Discovery endpoint must not include URL credentials.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(trim((string) $parts['host'], '[]'));
        $isLocalOllama = App::environment('local', 'testing') && $driver === 'ollama';
        if ($scheme !== 'https' && ! ($scheme === 'http' && $isLocalOllama)) {
            throw new InvalidArgumentException('Discovery endpoint must use HTTPS outside local Ollama development.');
        }

        $normalizedHost = rtrim($host, '.');
        if (in_array($normalizedHost, ['169.254.169.254', 'metadata.google.internal', 'instance-data', 'fd00:ec2::254'], true)
            || str_ends_with($normalizedHost, '.metadata.google.internal')) {
            throw new InvalidArgumentException('Discovery endpoint targets a forbidden metadata host.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Discovery endpoint has an invalid port.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ips = [$host];
        } else {
            $resolved = ($dnsResolver ?? [$this, 'resolveHostIps'])($host);
            $ips = $this->extractIps($resolved);
        }

        if ($ips === []) {
            throw new InvalidArgumentException('Discovery endpoint host did not resolve to an IP address.');
        }

        $validatedIps = [];
        foreach ($ips as $ip) {
            if (! $this->isPublicRoutableIp($ip, $driver, $host)) {
                throw new InvalidArgumentException('Discovery endpoint resolved to a non-public or forbidden IP address.');
            }

            $validatedIps[] = $ip;
        }

        $pinnedIp = $validatedIps[0];
        $resolveIp = str_contains($pinnedIp, ':') ? '['.$pinnedIp.']' : $pinnedIp;

        return [
            'url' => $url,
            'host' => $host,
            'port' => $port,
            'pinned_ip' => $pinnedIp,
            'curl_options' => defined('CURLOPT_RESOLVE')
                ? [CURLOPT_RESOLVE => ["{$host}:{$port}:{$resolveIp}"]]
                : [],
        ];
    }

    /**
     * @param  array<mixed>  $discoveryResponse
     * @return list<string>
     */
    private function extractIps(array $discoveryResponse): array
    {
        $ips = [];
        foreach ($discoveryResponse as $record) {
            if (is_string($record)) {
                if (filter_var($record, FILTER_VALIDATE_IP) !== false) {
                    $ips[] = $record;
                }

                continue;
            }

            if (! is_array($record)) {
                continue;
            }

            foreach (['ip', 'ipv6', 'address'] as $key) {
                if (isset($record[$key]) && is_string($record[$key]) && filter_var($record[$key], FILTER_VALIDATE_IP) !== false) {
                    $ips[] = $record[$key];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @return list<string>|array<mixed>
     */
    public function resolveHostIps(string $host): array
    {
        $records = dns_get_record($host, DNS_A + DNS_AAAA);
        $ips = [];

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $fallback = gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        return array_values(array_unique($ips));
    }

    public function isPublicRoutableIp(string $ip, string $driver, string $host): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $isLocalOllamaHost = App::environment('local', 'testing')
            && $driver === 'ollama'
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($isLocalOllamaHost && in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $mappedIpv4 = inet_ntop(substr($packed, 12));

            return is_string($mappedIpv4)
                && filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                && ! $this->isInCidr($mappedIpv4, '100.64.0.0/10');
        }

        if (strlen($packed) === 4) {
            if ($this->isInCidr($ip, '127.0.0.0/8')
                || $this->isInCidr($ip, '10.0.0.0/8')
                || $this->isInCidr($ip, '172.16.0.0/12')
                || $this->isInCidr($ip, '192.168.0.0/16')
                || $this->isInCidr($ip, '169.254.0.0/16')
                || $this->isInCidr($ip, '100.64.0.0/10')) {
                return false;
            }
        } else {
            if ($this->isInCidr($ip, '::1/128')
                || $this->isInCidr($ip, 'fc00::/7')
                || $this->isInCidr($ip, 'fe80::/10')) {
                return false;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function isInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefixLength] = explode('/', $cidr, 2);
        $packedIp = inet_pton($ip);
        $packedNetwork = inet_pton($network);
        if ($packedIp === false || $packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
            return false;
        }

        $remainingBits = (int) $prefixLength;
        for ($index = 0, $length = strlen($packedIp); $index < $length; $index++) {
            if ($remainingBits <= 0) {
                break;
            }

            $bitsInByte = min(8, $remainingBits);
            $mask = (0xFF << (8 - $bitsInByte)) & 0xFF;
            if ((ord($packedIp[$index]) & $mask) !== (ord($packedNetwork[$index]) & $mask)) {
                return false;
            }

            $remainingBits -= $bitsInByte;
        }

        return true;
    }

    public function enforceResponseSizeCeiling(string $body, ?int $contentLengthHeader = null): void
    {
        if (($contentLengthHeader !== null && $contentLengthHeader > self::MAX_DISCOVERY_RESPONSE_BYTES)
            || strlen($body) > self::MAX_DISCOVERY_RESPONSE_BYTES) {
            throw new InvalidArgumentException('Discovery response exceeded the 2 MB maximum size ceiling.');
        }
    }
}
