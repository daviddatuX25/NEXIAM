<?php

namespace App\Assessment\Omr\Canonical;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;
use function hash_equals;
use function hash_hmac;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;
use function preg_match;
use function str_contains;
use function time;

final readonly class OmrQuadRectificationResult
{
    /** @param array<string, mixed> $metrics */
    public function __construct(public string $bytes, public string $mime, public array $metrics, public string $resultHash) {}
}

final class OmrQuadRectifier
{
    /**
     * @param  list<array{0:int|float,1:int|float}>  $points
     */
    public function rectify(string $imageBytes, string $imageMime, array $points, int $targetWidth, int $targetHeight): OmrQuadRectificationResult
    {
        if (count($points) !== 4) {
            throw new RuntimeException('quad_invalid');
        }
        $runId = (string) Str::uuid();
        $manifest = [
            'contract_version' => (string) config('omr.omrchecker.contract_version'),
            'run_id' => $runId,
            'correlation_id' => 'omr:canonical:rectify:'.$runId,
            'input_hash' => hash('sha256', $imageBytes),
            'image_mime' => $imageMime,
            'points' => $points,
            'target_width' => $targetWidth,
            'target_height' => $targetHeight,
        ];
        $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', implode("\n", [
            'POST',
            '/v1/source-rectification',
            $timestamp,
            $runId,
            hash('sha256', $manifestJson),
            $manifest['input_hash'],
        ]), (string) config('omr.omrchecker.signing_secret'));
        try {
            $response = Http::baseUrl((string) config('omr.omrchecker.endpoint'))
                ->connectTimeout((float) config('omr.omrchecker.connect_timeout_seconds', 2))
                ->timeout((float) config('omr.canonical.limits.conversion_timeout_seconds_per_page', 300))
                ->withoutRedirecting()
                ->withHeaders([
                    'Accept' => 'multipart/form-data',
                    'X-Nexiam-Contract' => $manifest['contract_version'],
                    'X-Nexiam-Run-Id' => $runId,
                    'X-Nexiam-Timestamp' => $timestamp,
                    'X-Nexiam-Signature' => $signature,
                ])
                ->attach('manifest', $manifestJson, 'manifest.json', ['Content-Type' => 'application/json'])
                ->attach('image', $imageBytes, 'source', ['Content-Type' => $imageMime])
                ->post('/v1/source-rectification');
        } catch (ConnectionException $exception) {
            throw new RuntimeException('worker_unavailable', 0, $exception);
        }
        if ($response->status() !== 200) {
            $body = (array) $response->json();
            throw new RuntimeException((string) ($body['code'] ?? 'source_rectification_failed'));
        }
        $resultHash = (string) $response->header('X-Nexiam-Result-SHA256');
        $rectifiedHash = (string) $response->header('X-Nexiam-Rectified-SHA256');
        $responseTimestamp = (string) $response->header('X-Nexiam-Timestamp');
        $responseRunId = (string) $response->header('X-Nexiam-Run-Id');
        $expected = hash_hmac('sha256', implode("\n", [$responseTimestamp, $runId, $resultHash, $rectifiedHash]), (string) config('omr.omrchecker.signing_secret'));
        if ($responseRunId !== $runId || ! hash_equals($expected, (string) $response->header('X-Nexiam-Signature'))) {
            throw new RuntimeException('worker_response_signature_invalid');
        }
        [$resultBytes, $rectifiedBytes] = $this->parts($response->body(), (string) $response->header('Content-Type'));
        if (hash('sha256', $resultBytes) !== $resultHash || hash('sha256', $rectifiedBytes) !== $rectifiedHash) {
            throw new RuntimeException('worker_response_hash_invalid');
        }
        $result = json_decode($resultBytes, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result) || ($result['run_id'] ?? null) !== $runId) {
            throw new RuntimeException('worker_response_invalid');
        }

        return new OmrQuadRectificationResult($rectifiedBytes, 'image/png', (array) ($result['quality'] ?? []), $rectifiedHash);
    }

    /** @return array{0:string,1:string} */
    private function parts(string $body, string $contentType): array
    {
        if (preg_match('/boundary=([^;]+)/', $contentType, $matches) !== 1) {
            throw new RuntimeException('worker_response_invalid');
        }
        $boundary = trim($matches[1], '" ');
        $parts = [];
        foreach (explode('--'.$boundary, $body) as $part) {
            if ($part === '' || $part === "--\r\n" || $part === '--') {
                continue;
            }
            $segments = explode("\r\n\r\n", ltrim($part, "\r\n"), 2);
            if (count($segments) !== 2) {
                continue;
            }
            [$headers, $content] = $segments;
            $content = preg_replace('/\r\n$/', '', $content) ?? $content;
            if (str_contains($headers, 'name="result"')) {
                $parts['result'] = $content;
            } elseif (str_contains($headers, 'name="rectified_image"')) {
                $parts['rectified'] = $content;
            }
        }
        if (! isset($parts['result'], $parts['rectified'])) {
            throw new RuntimeException('worker_response_invalid');
        }

        return [$parts['result'], $parts['rectified']];
    }
}
