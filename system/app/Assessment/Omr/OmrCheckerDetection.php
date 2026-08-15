<?php

namespace App\Assessment\Omr;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function array_map;
use function hash;
use function hash_equals;
use function hash_hmac;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function str_contains;
use function strlen;
use function time;

/**
 * Stateless authenticated transport to the isolated OMRChecker supervisor.
 * It receives no model, storage, roster, answer-key, or scoring dependency.
 */
final class OmrCheckerDetection implements OmrDetectionPort
{
    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        try {
            $manifest = $this->manifest($request);
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = (string) time();
            $signature = hash_hmac('sha256', implode("\n", [
                'POST',
                '/v1/detections',
                $timestamp,
                $request->runId,
                hash('sha256', $manifestJson),
                $request->inputHash,
            ]), $this->secret());

            $response = Http::baseUrl($this->endpoint())
                ->connectTimeout((float) config('omr.omrchecker.connect_timeout_seconds', 2))
                ->timeout($request->providerDeadline)
                ->withoutRedirecting()
                ->withOptions(['stream' => true])
                ->withHeaders([
                    'Accept' => 'multipart/form-data',
                    'X-Nexiam-Contract' => $request->envelopeVersion,
                    'X-Nexiam-Run-Id' => $request->runId,
                    'X-Nexiam-Timestamp' => $timestamp,
                    'X-Nexiam-Signature' => $signature,
                ])
                ->attach('manifest', $manifestJson, 'manifest.json', ['Content-Type' => 'application/json'])
                ->attach('image', $request->imageBytes, 'sheet', ['Content-Type' => $request->imageMime])
                ->post('/v1/detections');
        } catch (ConnectionException) {
            return OmrDetectionResult::failed(OmrDetectionOutcome::RetryableFailure, 'worker_unavailable');
        } catch (JsonException|RuntimeException) {
            return OmrDetectionResult::failed(OmrDetectionOutcome::TerminalFailure, 'worker_configuration_invalid');
        }

        if ($response->status() !== 200) {
            return $this->failedResponse($response);
        }

        try {
            return $this->successfulResponse($request, $response);
        } catch (JsonException|RuntimeException) {
            return OmrDetectionResult::failed(OmrDetectionOutcome::TerminalFailure, 'worker_output_invalid');
        }
    }

    /** @return array<string, mixed> */
    private function manifest(OmrDetectionRequest $request): array
    {
        return [
            'contract_version' => $request->envelopeVersion,
            'run_id' => $request->runId,
            'correlation_id' => $request->correlationId,
            'input_hash' => $request->inputHash,
            'image_mime' => $request->imageMime,
            'image_width' => $request->imageWidth,
            'image_height' => $request->imageHeight,
            'native_bundle_hash' => $request->nativeBundleHash,
            'manifest_hash' => $request->manifestHash,
            'config_hash' => $request->configHash,
            'booklet_hash' => $request->bookletHash,
            'engine' => [
                'name' => $request->engineName,
                'commit' => $request->engineCommit,
                'patch_identity' => $request->patchIdentity,
            ],
            'native_bundle_metadata' => $request->nativeBundleMetadata,
            'native_template' => $request->nativeTemplate,
            'native_config' => $request->nativeConfig,
            'review_manifest' => $request->reviewManifest,
            'items' => array_map(static fn (OmrItemGeometry $item): array => [
                'position' => $item->position,
                'area_code' => $item->areaCode,
                'question_no' => $item->questionNo,
                'native_label' => $item->nativeLabel,
                'region' => $item->region->toArray(),
                'options' => array_map(static fn (OmrOptionGeometry $option): array => [
                    'code' => $option->code,
                    'display_order' => $option->displayOrder,
                    'region' => $option->region->toArray(),
                ], $item->options),
            ], $request->items),
            'identity_fields' => $this->identityFields($request->reviewManifest, $request->pageNo),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function identityFields(array $manifest, int $pageNo): array
    {
        $byLabel = [];
        foreach ((array) ($manifest['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null)
                || (int) ($field['page'] ?? 1) !== $pageNo
                || ! str_starts_with($field['label'], 'APPLICANT_NUMBER_') || ! is_array($field['region'] ?? null)) {
                continue;
            }
            $byLabel[$field['label']][] = $field['region'];
        }

        $identity = [];
        foreach ($byLabel as $label => $regions) {
            if (preg_match('/_(\d+)$/', $label, $matches) !== 1) {
                throw new RuntimeException('Applicant-number label is malformed.');
            }
            $identity[] = [
                'code' => OmrIdentityField::CODE_APPLICANT_NUMBER,
                'native_label' => $label,
                'position' => (int) $matches[1],
                'region' => $this->union($regions),
            ];
        }
        usort($identity, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        if ($identity === []) {
            throw new RuntimeException('Applicant-number geometry is missing.');
        }

        return $identity;
    }

    /** @param list<array<string, mixed>> $regions @return array{x: float, y: float, w: float, h: float} */
    private function union(array $regions): array
    {
        $left = min(array_map(static fn (array $region): float => (float) $region['x'], $regions));
        $top = min(array_map(static fn (array $region): float => (float) $region['y'], $regions));
        $right = max(array_map(static fn (array $region): float => (float) $region['x'] + (float) $region['w'], $regions));
        $bottom = max(array_map(static fn (array $region): float => (float) $region['y'] + (float) $region['h'], $regions));

        return ['x' => $left, 'y' => $top, 'w' => $right - $left, 'h' => $bottom - $top];
    }

    private function failedResponse(Response $response): OmrDetectionResult
    {
        $status = $response->status();
        $code = 'worker_http_'.$status;
        try {
            $body = $this->readBounded($response->toPsrResponse()->getBody(), 4096);
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && is_string($decoded['code'] ?? null) && preg_match('/^[a-z0-9_]{1,64}$/', $decoded['code']) === 1) {
                $code = $decoded['code'];
            }
        } catch (JsonException|RuntimeException) {
        }

        if ($status === 429 || $status >= 500) {
            $terminal = in_array($code, ['child_output_invalid', 'contract_mismatch', 'engine_mismatch'], true);

            return OmrDetectionResult::failed($terminal ? OmrDetectionOutcome::TerminalFailure : OmrDetectionOutcome::RetryableFailure, $code);
        }
        if ($status === 422 && in_array($code, ['image_type_invalid', 'image_decode_failed', 'image_metadata_mismatch', 'input_hash_mismatch'], true)) {
            return OmrDetectionResult::failed(OmrDetectionOutcome::InvalidInput, $code);
        }

        return OmrDetectionResult::failed(OmrDetectionOutcome::TerminalFailure, $code);
    }

    private function successfulResponse(OmrDetectionRequest $request, Response $response): OmrDetectionResult
    {
        $headers = [
            'contract' => $response->header('X-Nexiam-Contract'),
            'run_id' => $response->header('X-Nexiam-Run-Id'),
            'timestamp' => $response->header('X-Nexiam-Timestamp'),
            'result_hash' => $response->header('X-Nexiam-Result-SHA256'),
            'aligned_hash' => $response->header('X-Nexiam-Aligned-SHA256'),
            'signature' => $response->header('X-Nexiam-Signature'),
        ];
        if ($headers['contract'] !== $request->envelopeVersion || $headers['run_id'] !== $request->runId
            || ! is_string($headers['timestamp']) || ! ctype_digit($headers['timestamp'])
            || abs(time() - (int) $headers['timestamp']) > (int) config('omr.omrchecker.max_clock_skew_seconds', 30)) {
            throw new RuntimeException('Worker response identity is invalid.');
        }

        $body = $this->readBounded(
            $response->toPsrResponse()->getBody(),
            (int) config('omr.omrchecker.max_response_bytes', 18 * 1024 * 1024),
        );
        $parts = $this->parseMultipart((string) $response->header('Content-Type'), $body);
        $resultBytes = $parts['result']['body'] ?? null;
        $alignedBytes = $parts['aligned_image']['body'] ?? null;
        if (! is_string($resultBytes) || ! is_string($alignedBytes)
            || strlen($resultBytes) > (int) config('omr.omrchecker.max_result_bytes', 2 * 1024 * 1024)
            || strlen($alignedBytes) > (int) config('omr.omrchecker.max_aligned_bytes', 15 * 1024 * 1024)
            || hash('sha256', $resultBytes) !== $headers['result_hash']
            || hash('sha256', $alignedBytes) !== $headers['aligned_hash']) {
            throw new RuntimeException('Worker response hashes are invalid.');
        }

        $expectedSignature = hash_hmac('sha256', implode("\n", [
            $headers['timestamp'],
            $request->runId,
            $headers['result_hash'],
            $headers['aligned_hash'],
        ]), $this->secret());
        if (! is_string($headers['signature']) || ! hash_equals($expectedSignature, $headers['signature'])) {
            throw new RuntimeException('Worker response signature is invalid.');
        }

        $payload = json_decode($resultBytes, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || $this->containsForbiddenKeys($payload)) {
            throw new RuntimeException('Worker output contains forbidden scoring data.');
        }
        $aligned = (array) ($payload['aligned_image'] ?? []);
        if (($parts['aligned_image']['mime'] ?? null) !== ($aligned['mime'] ?? null)) {
            throw new RuntimeException('Aligned image MIME is inconsistent.');
        }
        $aligned['bytes'] = $alignedBytes;
        $aligned['hash'] = $headers['aligned_hash'];

        $success = new OmrDetectionSuccess(
            engineName: (string) ($payload['engine']['name'] ?? ''),
            engineVersion: (string) ($payload['engine']['version'] ?? ''),
            engineCommit: isset($payload['engine']['commit']) ? (string) $payload['engine']['commit'] : null,
            inputHash: (string) ($payload['input_hash'] ?? ''),
            configHash: (string) ($payload['config_hash'] ?? ''),
            applicantNumberProposal: isset($payload['applicant_number_proposal']) ? (string) $payload['applicant_number_proposal'] : null,
            applicantNumberConfidence: isset($payload['applicant_number_confidence']) ? (float) $payload['applicant_number_confidence'] : null,
            identityFields: array_map(static fn (array $field): OmrIdentityField => new OmrIdentityField(
                code: (string) ($field['code'] ?? ''),
                region: OmrRegion::fromArray((array) ($field['region'] ?? [])),
                confidence: isset($field['confidence']) ? (float) $field['confidence'] : null,
                value: isset($field['value']) ? (string) $field['value'] : null,
            ), (array) ($payload['identity_fields'] ?? [])),
            items: array_map(static fn (array $item): OmrDetectedItem => new OmrDetectedItem(
                position: (int) ($item['position'] ?? 0),
                areaCode: (string) ($item['area_code'] ?? ''),
                questionNo: (int) ($item['question_no'] ?? 0),
                selectedOption: isset($item['selected_option']) ? (string) $item['selected_option'] : null,
                state: (string) ($item['state'] ?? ''),
                confidence: isset($item['confidence']) ? (float) $item['confidence'] : null,
                region: OmrRegion::fromArray((array) ($item['region'] ?? [])),
            ), (array) ($payload['items'] ?? [])),
            issues: array_map(static fn (array $issue): OmrDetectionIssue => new OmrDetectionIssue(
                code: (string) ($issue['code'] ?? ''),
                severity: (string) ($issue['severity'] ?? ''),
                areaCode: isset($issue['area_code']) ? (string) $issue['area_code'] : null,
                questionNo: isset($issue['question_no']) ? (int) $issue['question_no'] : null,
            ), (array) ($payload['issues'] ?? [])),
            sheetConfidence: isset($payload['sheet_confidence']) ? (float) $payload['sheet_confidence'] : null,
            responseEnvelopeVersion: (string) ($payload['contract_version'] ?? ''),
            runId: isset($payload['run_id']) ? (string) $payload['run_id'] : null,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
            bookletHash: isset($payload['booklet_hash']) ? (string) $payload['booklet_hash'] : null,
            nativeBundleHash: isset($payload['native_bundle_hash']) ? (string) $payload['native_bundle_hash'] : null,
            manifestHash: isset($payload['manifest_hash']) ? (string) $payload['manifest_hash'] : null,
            patchIdentity: isset($payload['engine']['patch_identity']) ? (string) $payload['engine']['patch_identity'] : null,
            calibrationStatus: (string) ($payload['calibration_status'] ?? ''),
            fieldBlockShifts: (array) ($payload['field_block_shifts'] ?? []),
            alignedImage: $aligned,
            imageMime: isset($payload['image']['mime']) ? (string) $payload['image']['mime'] : null,
            imageWidth: isset($payload['image']['width']) ? (int) $payload['image']['width'] : null,
            imageHeight: isset($payload['image']['height']) ? (int) $payload['image']['height'] : null,
        );
        $timings = (array) ($payload['timings'] ?? []);

        return OmrDetectionResult::succeeded($success, new OmrDetectionTimings(
            detectionMs: (float) ($timings['detection_ms'] ?? 0),
            preprocessMs: isset($timings['preprocess_ms']) ? (float) $timings['preprocess_ms'] : null,
        ));
    }

    private function readBounded(StreamInterface $stream, int $limit): string
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(min(65536, $limit + 1 - strlen($body)));
            if (strlen($body) > $limit) {
                throw new RuntimeException('Worker response exceeds its byte limit.');
            }
        }

        return $body;
    }

    /** @return array<string, array{mime: string, body: string}> */
    private function parseMultipart(string $contentType, string $body): array
    {
        if (preg_match('/^multipart\/form-data;\s*boundary=(?:"([A-Za-z0-9._-]{1,70})"|([A-Za-z0-9._-]{1,70}))$/i', $contentType, $matches) !== 1) {
            throw new RuntimeException('Worker response is not bounded multipart.');
        }
        $boundary = $matches[1] !== '' ? $matches[1] : $matches[2];
        $segments = explode('--'.$boundary, $body);
        if (count($segments) !== 4 || $segments[0] !== '' || $segments[3] !== "--\r\n") {
            throw new RuntimeException('Worker multipart framing is invalid.');
        }

        $parts = [];
        foreach ([$segments[1], $segments[2]] as $segment) {
            if (! str_starts_with($segment, "\r\n") || ! str_ends_with($segment, "\r\n")) {
                throw new RuntimeException('Worker multipart part framing is invalid.');
            }
            $segment = substr($segment, 2, -2);
            [$rawHeaders, $partBody] = array_pad(explode("\r\n\r\n", $segment, 2), 2, null);
            if (! is_string($partBody)) {
                throw new RuntimeException('Worker multipart headers are invalid.');
            }
            $headers = [];
            foreach (explode("\r\n", $rawHeaders) as $line) {
                if (! str_contains($line, ':')) {
                    throw new RuntimeException('Worker multipart header is malformed.');
                }
                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                if (isset($headers[$name])) {
                    throw new RuntimeException('Worker multipart header is duplicated.');
                }
                $headers[$name] = trim($value);
            }
            if (preg_match('/^form-data;\s*name="(result|aligned_image)"(?:;\s*filename="[^"]+")?$/', $headers['content-disposition'] ?? '', $nameMatch) !== 1
                || ! isset($headers['content-type']) || isset($parts[$nameMatch[1]])) {
                throw new RuntimeException('Worker multipart disposition is invalid.');
            }
            $parts[$nameMatch[1]] = ['mime' => $headers['content-type'], 'body' => $partBody];
        }
        if (array_keys($parts) !== ['result', 'aligned_image']) {
            throw new RuntimeException('Worker multipart parts are incomplete or out of order.');
        }

        return $parts;
    }

    private function containsForbiddenKeys(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, ['answer_key', 'correct_option', 'correctness', 'score', 'scores', 'area_scores', 'per_area_scores', 'converted_score'], true)) {
                return true;
            }
            if (is_array($value) && $this->containsForbiddenKeys($value)) {
                return true;
            }
        }

        return false;
    }

    private function endpoint(): string
    {
        $endpoint = rtrim((string) config('omr.omrchecker.endpoint'), '/');
        if ($endpoint === '' || ! str_starts_with($endpoint, 'http://') && ! str_starts_with($endpoint, 'https://')) {
            throw new RuntimeException('OMRChecker endpoint is invalid.');
        }

        return $endpoint;
    }

    private function secret(): string
    {
        $secret = (string) config('omr.omrchecker.signing_secret');
        if (strlen($secret) < 32) {
            throw new RuntimeException('OMRChecker signing secret is invalid.');
        }

        return $secret;
    }
}
