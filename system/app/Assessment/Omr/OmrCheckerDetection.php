<?php

namespace App\Assessment\Omr;

use App\Assessment\Omr\Canonical\OmrQrObservation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function abs;
use function array_is_list;
use function array_keys;
use function array_map;
use function count;
use function feof;
use function fread;
use function fwrite;
use function hash;
use function hash_equals;
use function hash_hmac;
use function is_array;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function rewind;
use function str_contains;
use function stream_get_contents;
use function strlen;
use function strpos;
use function substr;
use function time;
use function tmpfile;

/**
 * Stateless authenticated transport to the isolated OMRChecker supervisor.
 * It receives no model, storage, roster, answer-key, or scoring dependency.
 */
final class OmrCheckerDetection implements OmrDetectionPort, OmrLayoutVerificationPort
{
    private const MAX_MULTIPART_HEADER_LINE_BYTES = 8192;

    private const MAX_MULTIPART_HEADER_BYTES = 16 * 1024;

    private const MAX_MULTIPART_HEADER_COUNT = 2;

    private const AUXILIARY_ENVELOPE_VERSION = 'nexiam.omr.detection.v4';

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
        } catch (ConnectionException $exception) {
            logger()->error('OMR detection worker unreachable', ['exception' => $exception]);

            return OmrDetectionResult::failed(OmrDetectionOutcome::RetryableFailure, 'worker_unavailable');
        } catch (JsonException|RuntimeException $exception) {
            logger()->error('OMR detection request could not be built', ['exception' => $exception]);

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
        $this->assertRequestSourceBinding($request);
        try {
            $decodedConfig = json_decode($request->nativeConfigJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The request config JSON is invalid.', previous: $exception);
        }
        if (! is_array($decodedConfig)
            || array_is_list($decodedConfig)
            || $decodedConfig !== $request->nativeConfig
            || ! hash_equals($request->configHash, hash('sha256', $request->nativeConfigJson))
            || ($request->nativeBundleMetadata['files']['config.json'] ?? null) !== $request->configHash) {
            throw new RuntimeException('The request config bytes do not match their immutable identity.');
        }

        return [
            'contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
            'registration_mode' => $request->registrationMode->value,
            'crop_policy' => $request->cropPolicy ?? 'auto_document_quad',
            ...($request->templateVersionId !== null
                ? ['template_version_id' => $request->templateVersionId]
                : ['candidate_target_id' => $request->candidateTargetId]),
            'expected_page_no' => $request->pageNo,
            'source_image' => $request->sourceImage->toArray(),
            ...($request->clientQuad !== null ? ['client_quad' => $request->clientQuad->toArray()] : []),
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
            'mapping_hash' => $request->nativeBundleMetadata['mapping_hash'] ?? $request->manifestHash,
            'engine' => [
                'name' => $request->engineName,
                'commit' => $request->engineCommit,
                'patch_identity' => $request->patchIdentity,
            ],
            'native_bundle_metadata' => $request->nativeBundleMetadata,
            'native_template' => $request->nativeTemplate,
            'native_config_json' => $request->nativeConfigJson,
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
            'native_assets' => $request->nativeAssets === []
                ? new \stdClass
                : array_map(static fn (string $bytes): string => base64_encode($bytes), $request->nativeAssets),
        ];
    }

    /** @return array<string, mixed> */
    private function preflightManifest(OmrDetectionRequest $request): array
    {
        $manifest = $this->manifest($request);
        unset(
            $manifest['registration_mode'],
            $manifest['template_version_id'],
            $manifest['candidate_target_id'],
            $manifest['expected_page_no'],
            $manifest['source_image'],
            $manifest['client_quad'],
        );
        $manifest['contract_version'] = self::AUXILIARY_ENVELOPE_VERSION;

        return $manifest;
    }

    private function assertRequestSourceBinding(OmrDetectionRequest $request): void
    {
        if ($request->envelopeVersion !== OmrDetectionRequest::ENVELOPE_VERSION
            || $request->inputHash !== hash('sha256', $request->imageBytes)) {
            throw new RuntimeException('The v5 request envelope or source-image hash is invalid.');
        }
        $sourceImage = OmrSourceImage::fromBytes($request->imageBytes);
        if ($sourceImage->toArray() !== $request->sourceImage->toArray()
            || ($request->clientQuad !== null && ! $request->clientQuad->isBoundTo($sourceImage, $request->pageNo))) {
            throw new RuntimeException('The v5 source-image or advisory quad binding is invalid.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertResponseIdentity(OmrDetectionRequest $request, array $payload): void
    {
        if (($payload['contract_version'] ?? null) !== OmrDetectionRequest::ENVELOPE_VERSION
            || ($payload['registration_mode'] ?? null) !== $request->registrationMode->value
            || ($payload['expected_page_no'] ?? null) !== $request->pageNo) {
            throw new RuntimeException('Worker response mode, page, or contract identity is invalid.');
        }

        if ($request->templateVersionId !== null) {
            if (($payload['template_version_id'] ?? null) !== $request->templateVersionId
                || array_key_exists('candidate_target_id', $payload)) {
                throw new RuntimeException('Worker response immutable template identity is invalid.');
            }
        } elseif (($payload['candidate_target_id'] ?? null) !== $request->candidateTargetId
            || array_key_exists('template_version_id', $payload)) {
            throw new RuntimeException('Worker response candidate target identity is invalid.');
        }

        $sourceImage = $payload['source_image'] ?? null;
        if (! is_array($sourceImage)
            || ($sourceImage['width'] ?? null) !== $request->sourceImage->width
            || ($sourceImage['height'] ?? null) !== $request->sourceImage->height
            || ($sourceImage['orientation'] ?? null) !== $request->sourceImage->orientation
            || ($sourceImage['input_hash'] ?? null) !== $request->sourceImage->inputHash) {
            throw new RuntimeException('Worker response source-image identity is invalid.');
        }
    }

    /** @param array<string, mixed> $rectification */
    private function assertDocumentRectification(OmrDetectionRequest $request, array $rectification): void
    {
        if ($request->registrationMode === OmrRegistrationMode::DocumentBound
            && ! OmrDocumentRectification::isAccepted($rectification)) {
            throw new RuntimeException('Document-bound worker output has no accepted single server rectification.');
        }
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
        $rectification = null;
        try {
            $body = $this->readBounded($response->toPsrResponse()->getBody(), 16384);
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && is_string($decoded['code'] ?? null) && preg_match('/^[a-z0-9_]{1,64}$/', $decoded['code']) === 1) {
                $code = $decoded['code'];
            }
            if (is_array($decoded) && is_array($decoded['rectification'] ?? null)
                && ! $this->containsForbiddenKeys($decoded['rectification'])) {
                $rectification = $decoded['rectification'];
            }
        } catch (JsonException|RuntimeException) {
        }

        if ($status === 429 || $status >= 500) {
            $terminal = in_array($code, ['child_output_invalid', 'contract_mismatch', 'engine_mismatch'], true);

            return OmrDetectionResult::failed($terminal ? OmrDetectionOutcome::TerminalFailure : OmrDetectionOutcome::RetryableFailure, $code, rectification: $rectification);
        }
        if ($status === 422 && in_array($code, ['image_type_invalid', 'image_decode_failed', 'image_metadata_mismatch', 'input_hash_mismatch'], true)) {
            return OmrDetectionResult::failed(OmrDetectionOutcome::InvalidInput, $code, rectification: $rectification);
        }

        return OmrDetectionResult::failed(OmrDetectionOutcome::TerminalFailure, $code, rectification: $rectification);
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

        $parts = $this->parseMultipart(
            (string) $response->header('Content-Type'),
            $response->toPsrResponse()->getBody(),
            (int) config('omr.omrchecker.max_response_bytes', 25 * 1024 * 1024),
        );
        $resultBytes = $parts['result']['body'] ?? null;
        $alignedBytes = $parts['aligned_image']['body'] ?? null;
        if (! is_string($resultBytes) || ! is_string($alignedBytes)
            || strlen($resultBytes) > (int) config('omr.omrchecker.max_result_bytes', 8 * 1024 * 1024)
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
        $this->assertResponseIdentity($request, $payload);
        $rectification = is_array($payload['rectification'] ?? null)
            ? $payload['rectification']
            : (is_array($payload['provider_diagnostics']['rectification'] ?? null) ? $payload['provider_diagnostics']['rectification'] : []);
        $this->assertDocumentRectification($request, $rectification);
        $aligned = (array) ($payload['aligned_image'] ?? []);
        if (($parts['aligned_image']['mime'] ?? null) !== ($aligned['mime'] ?? null)) {
            throw new RuntimeException('Aligned image MIME is inconsistent.');
        }
        unset($parts, $resultBytes);
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
            items: $this->parseDetectedItems($payload['items'] ?? null),
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
            providerDiagnostics: (array) ($payload['provider_diagnostics'] ?? []),
            fieldBlockShifts: (array) ($payload['field_block_shifts'] ?? []),
            alignedImage: $aligned,
            imageMime: isset($payload['image']['mime']) ? (string) $payload['image']['mime'] : null,
            imageWidth: isset($payload['image']['width']) ? (int) $payload['image']['width'] : null,
            imageHeight: isset($payload['image']['height']) ? (int) $payload['image']['height'] : null,
            birthdateProposal: null,
            rectification: isset($payload['rectification']) && is_array($payload['rectification']) ? $payload['rectification'] : (isset($payload['provider_diagnostics']['rectification']) && is_array($payload['provider_diagnostics']['rectification']) ? $payload['provider_diagnostics']['rectification'] : null),
            registrationMode: is_string($payload['registration_mode'] ?? null) ? $payload['registration_mode'] : null,
            templateVersionId: is_int($payload['template_version_id'] ?? null) ? $payload['template_version_id'] : null,
            candidateTargetId: is_string($payload['candidate_target_id'] ?? null) ? $payload['candidate_target_id'] : null,
            expectedPageNo: is_int($payload['expected_page_no'] ?? null) ? $payload['expected_page_no'] : null,
            sourceImage: is_array($payload['source_image'] ?? null) ? $payload['source_image'] : null,
        );
        $timings = (array) ($payload['timings'] ?? []);

        return OmrDetectionResult::succeeded($success, new OmrDetectionTimings(
            detectionMs: (float) ($timings['detection_ms'] ?? 0),
            preprocessMs: isset($timings['preprocess_ms']) ? (float) $timings['preprocess_ms'] : null,
        ));
    }

    /**
     * @return list<OmrDetectedItem>
     *
     * @throws OmrInvalidDetectionOutput
     */
    private function parseDetectedItems(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new OmrInvalidDetectionOutput('Worker item evidence is not an ordered list.');
        }

        $items = [];
        foreach ($value as $entry) {
            if (! is_array($entry)) {
                throw new OmrInvalidDetectionOutput('Worker item evidence is malformed.');
            }
            $items[] = OmrDetectedItem::fromArray($entry);
        }

        return $items;
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

    /**
     * @param  list<string>  $expected  ordered part names; the detection contract is result + aligned_image
     * @return array<string, array{mime: string, body: string}>
     */
    private function parseMultipart(
        string $contentType,
        StreamInterface $stream,
        int $limit,
        array $expected = ['result', 'aligned_image'],
    ): array {
        if (preg_match('/^multipart\/form-data;\s*boundary=(?:"([A-Za-z0-9._-]{1,70})"|([A-Za-z0-9._-]{1,70}))$/i', $contentType, $matches) !== 1) {
            throw new RuntimeException('Worker response is not bounded multipart.');
        }
        $boundary = $matches[1] !== '' ? $matches[1] : $matches[2];
        $response = tmpfile();
        if (! is_resource($response)) {
            throw new RuntimeException('Worker response temporary storage is unavailable.');
        }
        $partStreams = [];

        try {
            $this->spoolResponse($stream, $response, $limit);
            $buffer = '';
            $opening = '--'.$boundary."\r\n";
            if ($this->readMultipartBytes($response, $buffer, strlen($opening)) !== $opening) {
                throw new RuntimeException('Worker multipart framing is invalid.');
            }

            /** @var array<string, array{mime: string, stream: resource}> $parts */
            $parts = [];
            foreach ($expected as $index => $_expectedPart) {
                $headers = $this->readMultipartHeaders($response, $buffer);
                if (preg_match('/^form-data;\s*name="(result|aligned_image|layout_overlay)"(?:;\s*filename="[^"]+")?$/', $headers['content-disposition'] ?? '', $nameMatch) !== 1
                    || ! isset($headers['content-type'])) {
                    throw new RuntimeException('Worker multipart disposition is invalid.');
                }
                $name = $nameMatch[1];
                $expectedMime = match ($name) {
                    'result' => 'application/json',
                    'aligned_image', 'layout_overlay' => 'image/png',
                    default => null,
                };
                if (($headers['content-type'] ?? null) !== $expectedMime) {
                    throw new RuntimeException('Worker multipart content type is invalid.');
                }
                if (isset($parts[$name])) {
                    throw new RuntimeException('Worker multipart disposition is invalid.');
                }

                $part = tmpfile();
                if (! is_resource($part)) {
                    throw new RuntimeException('Worker response temporary storage is unavailable.');
                }
                $partStreams[] = $part;
                $this->copyMultipartBody(
                    $response,
                    $buffer,
                    $part,
                    "\r\n--".$boundary,
                    $limit,
                );
                $parts[$name] = ['mime' => $headers['content-type'], 'stream' => $part];

                $suffix = $index === count($expected) - 1 ? "--\r\n" : "\r\n";
                if ($this->readMultipartBytes($response, $buffer, strlen($suffix)) !== $suffix) {
                    throw new RuntimeException('Worker multipart framing is invalid.');
                }
            }

            $trailing = $buffer;
            if ($trailing === '') {
                $trailing = fread($response, 1);
                if ($trailing === false) {
                    throw new RuntimeException('Worker multipart framing is invalid.');
                }
            }
            if ($trailing !== '' || array_keys($parts) !== $expected) {
                throw new RuntimeException('Worker multipart parts are incomplete or out of order.');
            }

            $parsed = [];
            foreach ($expected as $name) {
                $part = $parts[$name]['stream'] ?? null;
                if (! is_resource($part)) {
                    throw new RuntimeException('Worker multipart parts are incomplete or out of order.');
                }
                rewind($part);
                $body = stream_get_contents($part);
                if (! is_string($body)) {
                    throw new RuntimeException('Worker multipart body could not be read.');
                }
                $parsed[$name] = ['mime' => $parts[$name]['mime'], 'body' => $body];
            }

            return $parsed;
        } finally {
            foreach ($partStreams as $part) {
                if (is_resource($part)) {
                    fclose($part);
                }
            }
            fclose($response);
        }
    }

    /**
     * @param  resource  $output
     */
    private function spoolResponse(StreamInterface $stream, $output, int $limit): void
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $written = 0;
        while (! $stream->eof()) {
            $chunk = $stream->read(min(65536, $limit - $written + 1));
            if (! is_string($chunk)) {
                throw new RuntimeException('Worker response could not be read.');
            }
            if ($chunk === '') {
                if ($stream->eof()) {
                    break;
                }

                continue;
            }
            $this->writeMultipartBytes($output, $chunk, $written, $limit);
        }
        rewind($output);
    }

    /**
     * @param  resource  $input
     * @return array<string, string>
     */
    private function readMultipartHeaders($input, string &$buffer): array
    {
        $headers = [];
        $headerBytes = 0;
        while (true) {
            $line = $this->readMultipartLine($input, $buffer);
            if ($line === '') {
                if (count($headers) !== self::MAX_MULTIPART_HEADER_COUNT
                    || ! isset($headers['content-disposition'], $headers['content-type'])) {
                    throw new RuntimeException('Worker multipart headers are incomplete.');
                }

                return $headers;
            }
            $headerBytes += strlen($line) + 2;
            if ($headerBytes > self::MAX_MULTIPART_HEADER_BYTES
                || count($headers) >= self::MAX_MULTIPART_HEADER_COUNT) {
                throw new RuntimeException('Worker multipart headers are too large.');
            }
            if ($line[0] === ' ' || $line[0] === "\t"
                || preg_match('/[\x00-\x1F\x7F]/', $line) === 1
                || ! str_contains($line, ':')) {
                throw new RuntimeException('Worker multipart header is malformed.');
            }
            [$rawName, $value] = explode(':', $line, 2);
            if (preg_match('/^[A-Za-z0-9-]+$/', $rawName) !== 1) {
                throw new RuntimeException('Worker multipart header is malformed.');
            }
            $name = strtolower($rawName);
            if (! in_array($name, ['content-disposition', 'content-type'], true)
                || isset($headers[$name])) {
                throw new RuntimeException('Worker multipart header is invalid.');
            }
            $headers[$name] = trim($value);
        }
    }

    /**
     * @param  resource  $input
     */
    private function readMultipartLine($input, string &$buffer): string
    {
        while (($position = strpos($buffer, "\r\n")) === false) {
            if (strlen($buffer) > self::MAX_MULTIPART_HEADER_LINE_BYTES) {
                throw new RuntimeException('Worker multipart header is too large.');
            }
            $chunk = fread($input, 65536);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Worker multipart headers are invalid.');
            }
            $buffer .= $chunk;
        }
        if ($position > self::MAX_MULTIPART_HEADER_LINE_BYTES) {
            throw new RuntimeException('Worker multipart header is too large.');
        }
        $line = substr($buffer, 0, $position);
        $buffer = substr($buffer, $position + 2);

        return $line;
    }

    /**
     * @param  resource  $input
     */
    private function readMultipartBytes($input, string &$buffer, int $length): string
    {
        while (strlen($buffer) < $length && ! feof($input)) {
            $chunk = fread($input, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }
        if (strlen($buffer) < $length) {
            $value = $buffer;
            $buffer = '';

            return $value;
        }
        $value = substr($buffer, 0, $length);
        $buffer = substr($buffer, $length);

        return $value;
    }

    /**
     * @param  resource  $input
     * @param  resource  $output
     */
    private function copyMultipartBody($input, string &$buffer, $output, string $delimiter, int $limit): int
    {
        $written = 0;
        $keep = strlen($delimiter) - 1;
        while (true) {
            $position = strpos($buffer, $delimiter);
            if ($position !== false) {
                $this->writeMultipartBytes($output, substr($buffer, 0, $position), $written, $limit);
                $buffer = substr($buffer, $position + strlen($delimiter));

                return $written;
            }

            $flushLength = strlen($buffer) - $keep;
            if ($flushLength > 0) {
                $this->writeMultipartBytes($output, substr($buffer, 0, $flushLength), $written, $limit);
                $buffer = substr($buffer, $flushLength);
            }
            if (feof($input)) {
                throw new RuntimeException('Worker multipart part framing is invalid.');
            }
            $chunk = fread($input, 65536);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Worker multipart part framing is invalid.');
            }
            $buffer .= $chunk;
        }
    }

    /**
     * @param  resource  $output
     */
    private function writeMultipartBytes($output, string $bytes, int &$written, int $limit): void
    {
        $length = strlen($bytes);
        if ($length === 0) {
            return;
        }
        if ($length > $limit - $written) {
            throw new RuntimeException('Worker response exceeds its byte limit.');
        }
        $offset = 0;
        while ($offset < $length) {
            $count = fwrite($output, $offset === 0 ? $bytes : substr($bytes, $offset));
            if ($count === false || $count === 0) {
                throw new RuntimeException('Worker response temporary storage failed.');
            }
            $offset += $count;
        }
        $written += $length;
    }

    /**
     * Read-only layout verification (builder Test capture, task 10.5).
     *
     * Same authenticated envelope as detection, but POSTs to the
     * /v1/layout-verification endpoint and expects the three-part response
     * (result + clean aligned + draw_template_layout overlay) whose signature
     * also covers the overlay hash. Returns a typed result; a worker failure
     * is never converted into a success.
     */
    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
    {
        try {
            $manifest = $this->manifest($request);
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = (string) time();
            $signature = hash_hmac('sha256', implode("\n", [
                'POST',
                '/v1/layout-verification',
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
                ->post('/v1/layout-verification');
        } catch (ConnectionException $exception) {
            logger()->error('OMR layout-verification worker unreachable', ['exception' => $exception]);

            return OmrLayoutVerificationResult::failed('worker_unavailable', 'The OMR worker is unreachable.');
        } catch (JsonException|RuntimeException $exception) {
            logger()->error('OMR layout-verification request could not be built', ['exception' => $exception]);

            return OmrLayoutVerificationResult::failed('worker_configuration_invalid', 'The layout-verification request could not be built.');
        }

        if ($response->status() !== 200) {
            return $this->verificationFailure($response);
        }

        try {
            return $this->verificationSuccess($request, $response);
        } catch (JsonException|RuntimeException $exception) {
            logger()->error('OMR layout-verification output invalid', ['exception' => $exception]);

            return OmrLayoutVerificationResult::failed('worker_output_invalid', $exception->getMessage());
        }
    }

    private function verificationFailure(Response $response): OmrLayoutVerificationResult
    {
        $code = 'worker_http_'.$response->status();
        $detail = null;
        $rectification = null;
        try {
            $body = $this->readBounded($response->toPsrResponse()->getBody(), 4096);
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && is_string($decoded['code'] ?? null) && preg_match('/^[a-z0-9_]{1,64}$/', $decoded['code']) === 1) {
                $code = $decoded['code'];
            }
            if (is_array($decoded) && is_string($decoded['detail'] ?? null) && $decoded['detail'] !== '') {
                $detail = $decoded['detail'];
            }
            if (is_array($decoded) && isset($decoded['rectification']) && is_array($decoded['rectification'])) {
                if (! $this->containsForbiddenKeys($decoded['rectification'])) {
                    $rectification = $decoded['rectification'];
                }
            }
        } catch (JsonException|RuntimeException) {
        }
        if ($detail === null || $detail === '') {
            $detail = match ($code) {
                'multipart_part_too_large', 'request_too_large', 'worker_http_413' => 'The capture image exceeds the worker transport limit (413).',
                'marker_versions_only' => 'Layout verification requires a marker-bearing template version.',
                'marker_not_found', 'markers_not_found', 'marker_missing', 'marker_top_left_missing', 'marker_top_right_missing', 'marker_bottom_left_missing', 'marker_bottom_right_missing' => 'One or more alignment markers were missing or could not be detected on the sheet.',
                'marker_mismatch', 'marker_profile_mismatch', 'marker_geometry_invalid' => 'The alignment markers do not match the expected marker profile geometry.',
                'sheet_partial' => 'Part of the sheet is out of frame or alignment markers were only partially detected.',
                'image_decode_failed' => 'Image decoding failed or produced empty pixels.',
                'image_metadata_mismatch', 'image_dimensions_invalid', 'dimensions_invalid' => 'Decoded image dimensions do not match the required sheet canvas.',
                'field_block_out_of_bounds', 'geometry_out_of_bounds' => 'One or more field blocks are positioned outside the printable marker frame.',
                'manifest_invalid', 'worker_configuration_invalid' => 'The layout verification manifest is malformed or invalid.',
                'child_timeout', 'worker_timeout' => 'The OMR worker timed out processing the layout verification.',
                'worker_unavailable' => 'The OMR worker is unreachable.',
                'worker_output_invalid', 'child_output_invalid' => 'The OMR worker produced invalid or malformed output.',
                default => 'The OMR worker rejected the layout verification ('.$code.').',
            };
        }

        return OmrLayoutVerificationResult::failed($code, $detail, rectification: $rectification);
    }

    private function verificationSuccess(OmrDetectionRequest $request, Response $response): OmrLayoutVerificationResult
    {
        $headers = [
            'contract' => $response->header('X-Nexiam-Contract'),
            'run_id' => $response->header('X-Nexiam-Run-Id'),
            'timestamp' => $response->header('X-Nexiam-Timestamp'),
            'result_hash' => $response->header('X-Nexiam-Result-SHA256'),
            'aligned_hash' => $response->header('X-Nexiam-Aligned-SHA256'),
            'overlay_hash' => $response->header('X-Nexiam-Layout-Overlay-SHA256'),
            'signature' => $response->header('X-Nexiam-Signature'),
        ];
        if ($headers['contract'] !== $request->envelopeVersion || $headers['run_id'] !== $request->runId
            || ! is_string($headers['timestamp']) || ! ctype_digit($headers['timestamp'])
            || abs(time() - (int) $headers['timestamp']) > (int) config('omr.omrchecker.max_clock_skew_seconds', 30)) {
            throw new RuntimeException('Worker layout-verification identity is invalid.');
        }

        $parts = $this->parseMultipart(
            (string) $response->header('Content-Type'),
            $response->toPsrResponse()->getBody(),
            (int) config('omr.omrchecker.max_response_bytes', 25 * 1024 * 1024),
            ['result', 'aligned_image', 'layout_overlay'],
        );
        $resultBytes = $parts['result']['body'] ?? null;
        $alignedBytes = $parts['aligned_image']['body'] ?? null;
        $overlayBytes = $parts['layout_overlay']['body'] ?? null;
        if (! is_string($resultBytes) || ! is_string($alignedBytes) || ! is_string($overlayBytes)
            || strlen($resultBytes) > (int) config('omr.omrchecker.max_result_bytes', 8 * 1024 * 1024)
            || strlen($alignedBytes) > (int) config('omr.omrchecker.max_aligned_bytes', 15 * 1024 * 1024)
            || strlen($overlayBytes) > (int) config('omr.omrchecker.max_aligned_bytes', 15 * 1024 * 1024)
            || hash('sha256', $resultBytes) !== $headers['result_hash']
            || hash('sha256', $alignedBytes) !== $headers['aligned_hash']
            || hash('sha256', $overlayBytes) !== $headers['overlay_hash']) {
            throw new RuntimeException('Worker layout-verification response hashes are invalid.');
        }

        $expectedSignature = hash_hmac('sha256', implode("\n", [
            $headers['timestamp'],
            $request->runId,
            $headers['result_hash'],
            $headers['aligned_hash'],
            $headers['overlay_hash'],
        ]), $this->secret());
        if (! is_string($headers['signature']) || ! hash_equals($expectedSignature, $headers['signature'])) {
            throw new RuntimeException('Worker layout-verification response signature is invalid.');
        }

        $payload = json_decode($resultBytes, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || $this->containsForbiddenKeys($payload)) {
            throw new RuntimeException('Worker layout-verification output contains forbidden scoring data.');
        }
        $this->assertResponseIdentity($request, $payload);
        $rectification = is_array($payload['rectification'] ?? null) ? $payload['rectification'] : [];
        $this->assertDocumentRectification($request, $rectification);
        if (($parts['aligned_image']['mime'] ?? null) !== ($payload['aligned_image']['mime'] ?? null)
            || ($parts['layout_overlay']['mime'] ?? null) !== ($payload['layout_overlay']['mime'] ?? null)) {
            throw new RuntimeException('Worker layout-verification raster MIME is inconsistent.');
        }
        unset($parts, $resultBytes);

        $timings = (array) ($payload['timings'] ?? []);

        return new OmrLayoutVerificationResult(
            outcome: (string) ($payload['outcome'] ?? OmrLayoutVerificationResult::OUTCOME_SUCCEEDED),
            alignedBytes: $alignedBytes,
            alignedImage: (array) ($payload['aligned_image'] ?? []),
            overlayBytes: $overlayBytes,
            overlayImage: (array) ($payload['layout_overlay'] ?? []),
            markerMatches: (array) ($payload['marker_matches'] ?? []),
            fieldBlockShifts: (array) ($payload['field_block_shifts'] ?? []),
            bubbleMeans: (array) ($payload['bubble_means'] ?? []),
            itemEvidence: (array) ($payload['item_evidence'] ?? []),
            preprocessMs: isset($timings['preprocess_ms']) ? (float) $timings['preprocess_ms'] : null,
            verificationMs: isset($timings['verification_ms']) ? (float) $timings['verification_ms'] : null,
            rectification: isset($payload['rectification']) && is_array($payload['rectification']) ? $payload['rectification'] : null,
        );
    }

    /**
     * Registration-only capture preflight (tasks 10.7/10.18).
     *
     * POSTs to /v1/preflight with the same authenticated envelope and expects
     * a single signed JSON response. A worker failure maps to a typed
     * rejected preflight; success carries marker scores, declared/measured
     * frame dimensions, and an orientation hint.
     */
    public function preflight(OmrDetectionRequest $request): OmrCapturePreflightResult
    {
        if ($request->registrationMode !== OmrRegistrationMode::FiducialAnchored || $request->clientQuad !== null) {
            return OmrCapturePreflightResult::rejected('registration_mode_unsupported', 'Registration preflight remains an auxiliary marker-only v3 contract.');
        }
        try {
            $manifest = $this->preflightManifest($request);
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = (string) time();
            $signature = hash_hmac('sha256', implode("\n", [
                'POST',
                '/v1/preflight',
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
                    'Accept' => 'application/json',
                    'X-Nexiam-Contract' => self::AUXILIARY_ENVELOPE_VERSION,
                    'X-Nexiam-Run-Id' => $request->runId,
                    'X-Nexiam-Timestamp' => $timestamp,
                    'X-Nexiam-Signature' => $signature,
                ])
                ->attach('manifest', $manifestJson, 'manifest.json', ['Content-Type' => 'application/json'])
                ->attach('image', $request->imageBytes, 'sheet', ['Content-Type' => $request->imageMime])
                ->post('/v1/preflight');
        } catch (ConnectionException $exception) {
            logger()->error('OMR preflight worker unreachable', ['exception' => $exception]);

            return OmrCapturePreflightResult::rejected('worker_unavailable', 'The OMR worker is unreachable.');
        } catch (JsonException|RuntimeException $exception) {
            logger()->error('OMR preflight request could not be built', ['exception' => $exception]);

            return OmrCapturePreflightResult::rejected('worker_configuration_invalid', 'The preflight request could not be built.');
        }

        if ($response->status() !== 200) {
            return $this->preflightFailure($response);
        }

        try {
            return $this->preflightSuccess($request, $response);
        } catch (JsonException|RuntimeException $exception) {
            return OmrCapturePreflightResult::rejected('worker_output_invalid', $exception->getMessage());
        }
    }

    private function preflightFailure(Response $response): OmrCapturePreflightResult
    {
        $code = 'worker_http_'.$response->status();
        $detail = 'The OMR worker rejected the capture preflight.';
        $rectification = null;
        try {
            $body = $this->readBounded($response->toPsrResponse()->getBody(), 4096);
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && is_string($decoded['code'] ?? null) && preg_match('/^[a-z0-9_]{1,64}$/', $decoded['code']) === 1) {
                $code = $decoded['code'];
            }
            if (is_array($decoded) && is_string($decoded['detail'] ?? null) && $decoded['detail'] !== '') {
                $detail = $decoded['detail'];
            }
            if (is_array($decoded) && isset($decoded['rectification']) && is_array($decoded['rectification'])) {
                if (! $this->containsForbiddenKeys($decoded['rectification'])) {
                    $rectification = $decoded['rectification'];
                }
            }
        } catch (JsonException|RuntimeException) {
        }

        return OmrCapturePreflightResult::rejected($code, $detail, $this->orientationHintFrom($detail), rectification: $rectification);
    }

    private function preflightSuccess(OmrDetectionRequest $request, Response $response): OmrCapturePreflightResult
    {
        $headers = [
            'contract' => $response->header('X-Nexiam-Contract'),
            'run_id' => $response->header('X-Nexiam-Run-Id'),
            'timestamp' => $response->header('X-Nexiam-Timestamp'),
            'result_hash' => $response->header('X-Nexiam-Result-SHA256'),
            'signature' => $response->header('X-Nexiam-Signature'),
        ];
        if ($headers['contract'] !== self::AUXILIARY_ENVELOPE_VERSION || $headers['run_id'] !== $request->runId
            || ! is_string($headers['timestamp']) || ! ctype_digit($headers['timestamp'])
            || abs(time() - (int) $headers['timestamp']) > (int) config('omr.omrchecker.max_clock_skew_seconds', 30)) {
            throw new RuntimeException('Worker preflight identity is invalid.');
        }

        $body = $this->readBounded(
            $response->toPsrResponse()->getBody(),
            (int) config('omr.omrchecker.max_result_bytes', 2 * 1024 * 1024),
        );
        if (strlen($body) > (int) config('omr.omrchecker.max_result_bytes', 2 * 1024 * 1024)
            || hash('sha256', $body) !== $headers['result_hash']) {
            throw new RuntimeException('Worker preflight response hash is invalid.');
        }

        $expectedSignature = hash_hmac('sha256', implode("\n", [
            $headers['timestamp'],
            $request->runId,
            $headers['result_hash'],
        ]), $this->secret());
        if (! is_string($headers['signature']) || ! hash_equals($expectedSignature, $headers['signature'])) {
            throw new RuntimeException('Worker preflight response signature is invalid.');
        }

        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || $this->containsForbiddenKeys($payload)) {
            throw new RuntimeException('Worker preflight output contains forbidden scoring data.');
        }

        $declared = (array) ($payload['declared_dimensions'] ?? []);
        $measured = (array) ($payload['measured_dimensions'] ?? []);
        $markerMatches = (array) ($payload['marker_matches'] ?? []);
        $timings = (array) ($payload['timings'] ?? []);

        $gates = [
            'marker_count' => count($markerMatches) === 4 ? 'pass' : 'fail',
            'registration' => 'pass',
            'dimensions' => $this->dimensionsPass($declared, $measured) ? 'pass' : 'fail',
            'shifts' => 'not_run',
        ];
        $submitAllowed = $gates['marker_count'] === 'pass' && $gates['dimensions'] === 'pass';
        $outcome = $submitAllowed ? OmrCapturePreflightResult::OUTCOME_VALIDATED : OmrCapturePreflightResult::OUTCOME_REJECTED;

        $qrPayload = isset($payload['qr_payload']) && is_string($payload['qr_payload']) && trim($payload['qr_payload']) !== ''
            ? trim($payload['qr_payload'])
            : null;

        return new OmrCapturePreflightResult(
            mode: OmrCapturePreflightResult::MODE_MARKER,
            outcome: $outcome,
            code: $submitAllowed ? null : 'geometry_rejected',
            detail: $submitAllowed ? null : 'Registration completed but the normalized dimensions are outside tolerance.',
            orientationHint: 'upright',
            markerMatches: $markerMatches,
            gates: $gates,
            normalizedDimensions: [
                'width' => (int) ($declared['width'] ?? 0),
                'height' => (int) ($declared['height'] ?? 0),
            ],
            submitAllowed: $submitAllowed,
            qrPayload: $qrPayload,
            preprocessMs: isset($timings['preprocess_ms']) ? (float) $timings['preprocess_ms'] : null,
            preflightMs: isset($timings['preflight_ms']) ? (float) $timings['preflight_ms'] : null,
            rectification: isset($payload['rectification']) && is_array($payload['rectification']) ? $payload['rectification'] : null,
        );
    }

    /**
     * The engine warps to the marker-center rectangle; recognition resizes to
     * the declared frame, so the gate tolerates modest registration geometry
     * deviation (5% per axis; calibration data, tightened by the physical
     * matrix). Out-of-tolerance results stay rejected (fail-closed).
     *
     * @param  array<string, mixed>  $declared
     * @param  array<string, mixed>  $measured
     */
    private function dimensionsPass(array $declared, array $measured): bool
    {
        $declaredWidth = (int) ($declared['width'] ?? 0);
        $declaredHeight = (int) ($declared['height'] ?? 0);
        $measuredWidth = (int) ($measured['width'] ?? 0);
        $measuredHeight = (int) ($measured['height'] ?? 0);
        if ($declaredWidth <= 0 || $declaredHeight <= 0 || $measuredWidth <= 0 || $measuredHeight <= 0) {
            return false;
        }

        return abs($measuredWidth - $declaredWidth) / $declaredWidth <= 0.05
            && abs($measuredHeight - $declaredHeight) / $declaredHeight <= 0.05;
    }

    private function orientationHintFrom(string $detail): ?string
    {
        if (preg_match('/orientation_hint=([a-z0-9_]+)/', $detail, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function containsForbiddenKeys(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, ['answer_key', 'correct_option', 'correct_option_code', 'expected', 'expected_answer', 'expected_option', 'correctness', 'score', 'scores', 'area_scores', 'per_area_scores', 'converted_score'], true)) {
                return true;
            }
            if (is_array($value) && $this->containsForbiddenKeys($value)) {
                return true;
            }
        }

        return false;
    }

    public function inspectQr(string $imageBytes, string $imageMime): OmrQrObservation
    {
        $runId = (string) Str::uuid();
        $manifest = [
            'contract_version' => (string) config('omr.omrchecker.contract_version', 'nexiam.omr.detection.v3'),
            'run_id' => $runId,
            'correlation_id' => 'omr:canonical:qr-extract:'.$runId,
            'input_hash' => hash('sha256', $imageBytes),
            'image_mime' => $imageMime,
        ];

        try {
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = (string) time();
            $signature = hash_hmac('sha256', implode("\n", [
                'POST',
                '/v1/qr-extract',
                $timestamp,
                $runId,
                hash('sha256', $manifestJson),
                $manifest['input_hash'],
            ]), $this->secret());

            $response = Http::baseUrl($this->endpoint())
                ->connectTimeout((float) config('omr.omrchecker.connect_timeout_seconds', 2))
                ->timeout((float) config('omr.provider.deadline_seconds', 30))
                ->withoutRedirecting()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Nexiam-Contract' => $manifest['contract_version'],
                    'X-Nexiam-Run-Id' => $runId,
                    'X-Nexiam-Timestamp' => $timestamp,
                    'X-Nexiam-Signature' => $signature,
                ])
                ->attach('manifest', $manifestJson, 'manifest.json', ['Content-Type' => 'application/json'])
                ->attach('image', $imageBytes, 'sheet', ['Content-Type' => $imageMime])
                ->post('/v1/qr-extract');
        } catch (ConnectionException $exception) {
            throw new RuntimeException('qr_reader_unavailable: The QR extraction service is unavailable.', 0, $exception);
        } catch (JsonException|RuntimeException $exception) {
            throw new RuntimeException('qr_reader_response_invalid: '.$exception->getMessage(), 0, $exception);
        }

        if ($response->status() !== 200) {
            $status = $response->status();
            if (in_array($status, [502, 503, 504], true)) {
                throw new RuntimeException("qr_reader_unavailable: The QR extraction service returned HTTP {$status}.");
            }

            throw new RuntimeException("qr_reader_response_invalid: The QR extraction service returned HTTP {$status}.");
        }

        $contentType = (string) $response->header('Content-Type');
        $baseMime = strtolower(trim(explode(';', $contentType)[0]));
        if ($baseMime !== 'application/json') {
            throw new RuntimeException('qr_reader_response_invalid: Response content type must be application/json.');
        }

        $contract = (string) $response->header('X-Nexiam-Contract');
        $responseRunId = (string) $response->header('X-Nexiam-Run-Id');
        $responseTimestamp = (string) $response->header('X-Nexiam-Timestamp');
        $resultHash = (string) $response->header('X-Nexiam-Result-SHA256');
        $signature = (string) $response->header('X-Nexiam-Signature');

        if ($contract !== $manifest['contract_version']
            || $responseRunId !== $runId
            || ! ctype_digit($responseTimestamp)
            || abs(time() - (int) $responseTimestamp) > (int) config('omr.omrchecker.max_clock_skew_seconds', 30)
            || preg_match('/^[a-f0-9]{64}$/', $resultHash) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
            throw new RuntimeException('qr_reader_response_invalid: Response headers failed validation.');
        }

        $expected = hash_hmac('sha256', implode("\n", [$responseTimestamp, $runId, $resultHash]), $this->secret());
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('qr_reader_response_invalid: Response signature failed verification.');
        }

        $maxBytes = (int) config('omr.omrchecker.max_result_bytes', 2 * 1024 * 1024);
        try {
            $body = $this->readBounded($response->toPsrResponse()->getBody(), $maxBytes);
        } catch (RuntimeException $exception) {
            throw new RuntimeException('qr_reader_response_invalid: '.$exception->getMessage(), 0, $exception);
        }

        if (strlen($body) > $maxBytes || hash('sha256', $body) !== $resultHash) {
            throw new RuntimeException('qr_reader_response_invalid: Response body hash or length failed validation.');
        }

        try {
            $result = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('qr_reader_response_invalid: Response JSON is malformed.', 0, $exception);
        }

        if (! is_array($result) || $this->containsForbiddenKeys($result)) {
            throw new RuntimeException('qr_reader_response_invalid: Response payload shape is invalid.');
        }

        $keys = array_keys($result);
        sort($keys);
        if ($keys !== [
            'contract_version',
            'corners',
            'correction_quarter_turn',
            'found',
            'payload',
            'quarter_turn_residual_degrees',
            'run_id',
            'top_edge_angle_degrees',
        ]) {
            throw new RuntimeException('qr_reader_response_invalid: Response payload shape is invalid.');
        }

        if (($result['contract_version'] ?? null) !== $manifest['contract_version']
            || ($result['run_id'] ?? null) !== $runId) {
            throw new RuntimeException('qr_reader_response_invalid: Response payload metadata mismatch.');
        }

        if (! is_bool($result['found'])) {
            throw new RuntimeException('qr_reader_response_invalid: Response found field must be a boolean.');
        }

        $payload = $result['payload'] ?? null;
        if ($result['found'] === true && (! is_string($payload) || trim($payload) === '')) {
            throw new RuntimeException('qr_reader_response_invalid: Response payload must be a non-empty string when found is true.');
        }
        if ($result['found'] === false && $payload !== null && $payload !== '') {
            throw new RuntimeException('qr_reader_response_invalid: Response payload must be null when found is false.');
        }

        $corners = $result['corners'] ?? null;
        if (! is_array($corners) || ! array_is_list($corners) || ! in_array(count($corners), [0, 4], true)) {
            throw new RuntimeException('qr_reader_response_invalid: QR corners must be empty or contain four ordered points.');
        }
        $normalizedCorners = [];
        foreach ($corners as $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) !== 2
                || ! is_numeric($point[0]) || ! is_numeric($point[1])
                || ! is_finite((float) $point[0]) || ! is_finite((float) $point[1])
                || (float) $point[0] < 0 || (float) $point[1] < 0) {
                throw new RuntimeException('qr_reader_response_invalid: QR corner coordinates are invalid.');
            }
            $normalizedCorners[] = [(float) $point[0], (float) $point[1]];
        }

        $angle = $result['top_edge_angle_degrees'] ?? null;
        $residual = $result['quarter_turn_residual_degrees'] ?? null;
        $correction = $result['correction_quarter_turn'] ?? null;
        if (($angle !== null && (! is_numeric($angle) || ! is_finite((float) $angle)))
            || ($residual !== null && (! is_numeric($residual) || ! is_finite((float) $residual) || (float) $residual < 0 || (float) $residual > 45))
            || ! in_array($correction, [null, 0, 90, 180, 270], true)
            || ($correction !== null && (count($normalizedCorners) !== 4 || $residual === null || (float) $residual > 20))
            || ($result['found'] === false && ($normalizedCorners !== [] || $angle !== null || $residual !== null || $correction !== null))) {
            throw new RuntimeException('qr_reader_response_invalid: QR orientation geometry is invalid.');
        }

        return new OmrQrObservation(
            payload: $result['found'] === true ? trim((string) $payload) : null,
            corners: $normalizedCorners,
            topEdgeAngle: $angle !== null ? (float) $angle : null,
            quarterTurnResidual: $residual !== null ? (float) $residual : null,
            correctionQuarterTurn: $correction,
        );
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
