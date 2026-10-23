<?php

use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrOptionGeometry;
use App\Assessment\Omr\OmrRegion;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use Illuminate\Support\Facades\Http;

function omrCheckerMemoryRequest(): OmrDetectionRequest
{
    $bytes = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $sourceImage = OmrSourceImage::fromBytes($bytes);
    $options = [
        new OmrOptionGeometry('A', new OmrRegion(0.01, 0.01, 0.02, 0.01), 1),
        new OmrOptionGeometry('B', new OmrRegion(0.04, 0.01, 0.02, 0.01), 2),
        new OmrOptionGeometry('C', new OmrRegion(0.07, 0.01, 0.02, 0.01), 3),
        new OmrOptionGeometry('D', new OmrRegion(0.10, 0.01, 0.02, 0.01), 4),
    ];
    $items = [];
    foreach (range(1, 150) as $position) {
        $area = 'AREA_'.(int) ceil($position / 25);
        $question = (($position - 1) % 25) + 1;
        $items[] = new OmrItemGeometry(
            position: $position,
            areaCode: $area,
            questionNo: $question,
            nativeLabel: $area.'_Q_'.str_pad((string) $question, 3, '0', STR_PAD_LEFT),
            region: new OmrRegion(0.08, 0.12 + ($position * 0.004), 0.20, 0.01),
            options: $options,
        );
    }

    return new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'memory-regression-run',
        correlationId: 'memory-regression-correlation',
        imageBytes: $bytes,
        inputHash: $sourceImage->inputHash,
        imageMime: 'image/png',
        imageWidth: $sourceImage->width,
        imageHeight: $sourceImage->height,
        areas: [],
        items: $items,
        identityRegions: [],
        nativeBundleHash: 'native-bundle-hash',
        manifestHash: 'manifest-hash',
        configHash: hash('sha256', '{"fixture":true}'),
        bookletHash: 'booklet-hash',
        nativeTemplate: [],
        nativeConfigJson: '{"fixture":true}',
        nativeBundleMetadata: ['files' => ['config.json' => hash('sha256', '{"fixture":true}')]],
        reviewManifest: [],
        engineName: 'OMRChecker',
        engineCommit: str_repeat('c', 40),
        patchIdentity: 'nexiam-headless-overlay-v1',
        expectedFieldLabels: [],
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: $sourceImage,
        pageNo: 1,
        expectedOptionCodes: [],
        providerDeadline: 30.0,
    );
}

function omrCheckerReplaceOnce(string $search, string $replacement, string $subject): string
{
    $position = strpos($subject, $search);
    if ($position === false) {
        return $subject;
    }

    return substr($subject, 0, $position).$replacement.substr($subject, $position + strlen($search));
}

/** @return array{body:string,headers:array<string,string>,aligned:string,overlay:string} */
function omrCheckerMemoryResponse(OmrDetectionRequest $request, bool $layout): array
{
    $aligned = "\x89PNG\r\n\x1a\n".str_repeat('a', 1_479_184);
    $overlay = "\x89PNG\r\n\x1a\n".str_repeat('o', 1_318_735);
    $payload = [
        'contract_version' => $request->envelopeVersion,
        'registration_mode' => $request->registrationMode->value,
        'template_version_id' => $request->templateVersionId,
        'expected_page_no' => $request->pageNo,
        'source_image' => $request->sourceImage->toArray(),
        'run_id' => $request->runId,
        'correlation_id' => $request->correlationId,
        'outcome' => 'succeeded',
        'engine' => [
            'name' => $request->engineName,
            'version' => 'de3f69828228',
            'commit' => $request->engineCommit,
            'patch_identity' => $request->patchIdentity,
        ],
        'input_hash' => $request->inputHash,
        'native_bundle_hash' => $request->nativeBundleHash,
        'manifest_hash' => $request->manifestHash,
        'config_hash' => $request->configHash,
        'booklet_hash' => $request->bookletHash,
        'image' => ['mime' => $request->imageMime, 'width' => $request->imageWidth, 'height' => $request->imageHeight],
        'calibration_status' => 'unqualified',
        'sheet_confidence' => null,
        'applicant_number_proposal' => '123456789',
        'applicant_number_confidence' => null,
        'identity_fields' => [[
            'code' => 'applicant_number',
            'native_label' => 'APPLICANT_NUMBER_1',
            'position' => 1,
            'value' => '123456789',
            'confidence' => null,
            'region' => ['x' => 0.02, 'y' => 0.02, 'w' => 0.10, 'h' => 0.20],
        ]],
        'items' => array_map(static fn (OmrItemGeometry $item): array => [
            'position' => $item->position,
            'area_code' => $item->areaCode,
            'question_no' => $item->questionNo,
            'selected_option' => 'A',
            'state' => 'marked',
            'confidence' => null,
            'region' => $item->region->toArray(),
            'observed_options' => ['A'],
            'option_observations' => [
                ['code' => 'A', 'mean' => 32.0, 'classification' => 'marked'],
                ['code' => 'B', 'mean' => 181.0, 'classification' => 'unmarked'],
                ['code' => 'C', 'mean' => 182.0, 'classification' => 'unmarked'],
                ['code' => 'D', 'mean' => 183.0, 'classification' => 'unmarked'],
            ],
            'issue_codes' => [],
        ], $request->items),
        'issues' => [],
        'field_block_shifts' => [],
        'aligned_image' => ['mime' => 'image/png', 'width' => 2196, 'height' => 3201],
        'timings' => ['preprocess_ms' => 1.0, 'detection_ms' => 2.0, 'verification_ms' => 3.0],
    ];
    if ($layout) {
        $payload['layout_overlay'] = ['mime' => 'image/png', 'width' => 2196, 'height' => 3201];
        $payload['marker_matches'] = ['top_left' => 0.99, 'top_right' => 0.99, 'bottom_left' => 0.99, 'bottom_right' => 0.99];
        $payload['bubble_means'] = [];
        $payload['item_evidence'] = array_map(static fn (OmrItemGeometry $item): array => [
            'label' => $item->nativeLabel,
            'selected' => 'A',
        ], $request->items);
    }

    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $boundary = $layout ? 'omr-memory-layout-boundary' : 'omr-memory-detection-boundary';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$aligned."\r\n";
    if ($layout) {
        $body .= '--'.$boundary."\r\n"
            ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$overlay."\r\n";
    }
    $body .= '--'.$boundary."--\r\n";

    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $aligned);
    $overlayHash = hash('sha256', $overlay);
    $signatureParts = [$timestamp, $request->runId, $resultHash, $alignedHash];
    if ($layout) {
        $signatureParts[] = $overlayHash;
    }

    return [
        'body' => $body,
        'headers' => [
            'Content-Type' => 'multipart/form-data; boundary='.$boundary,
            'X-Nexiam-Contract' => $request->envelopeVersion,
            'X-Nexiam-Run-Id' => $request->runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $resultHash,
            'X-Nexiam-Aligned-SHA256' => $alignedHash,
            'X-Nexiam-Layout-Overlay-SHA256' => $overlayHash,
            'X-Nexiam-Signature' => hash_hmac('sha256', implode("\n", $signatureParts), (string) config('omr.omrchecker.signing_secret')),
        ],
        'aligned' => $aligned,
        'overlay' => $overlay,
    ];
}

test('150-item real detector response stays below 128 MiB while preserving evidence', function (): void {
    $originalMemoryLimit = ini_get('memory_limit');
    ini_set('memory_limit', '128M');
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    $request = omrCheckerMemoryRequest();
    $response = omrCheckerMemoryResponse($request, false);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response($response['body'], 200, $response['headers'])]);

    $guard = str_repeat('g', max(0, (120 * 1024 * 1024) - memory_get_usage(true)));
    try {
        $result = app(OmrCheckerDetection::class)->detect($request);
        $peak = memory_get_peak_usage(true);
    } finally {
        unset($guard);
        if (is_string($originalMemoryLimit) && $originalMemoryLimit !== '') {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    expect($result->outcome)->toBe(OmrDetectionOutcome::Succeeded)
        ->and($result->success)->not->toBeNull()
        ->and($result->success->items)->toHaveCount(150)
        ->and($result->success->items[0]->position)->toBe(1)
        ->and($result->success->items[149]->position)->toBe(150)
        ->and($result->success->identityFields[0]->value)->toBe('123456789')
        ->and($result->success->alignedImage['bytes'])->toBe($response['aligned'])
        ->and($result->success->alignedImage['hash'])->toBe(hash('sha256', $response['aligned']))
        ->and($peak)->toBeLessThan(128 * 1024 * 1024);
});

test('multipart parser rejects oversized and injected headers with a typed failure', function (): void {
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    $request = omrCheckerMemoryRequest();
    $response = omrCheckerMemoryResponse($request, false);
    $mutators = [
        'oversized header line' => static fn (string $body): string => omrCheckerReplaceOnce(
            'Content-Disposition: form-data; name="result"',
            'Content-Disposition: '.str_repeat('x', 8200),
            $body,
        ),
        'unknown header' => static fn (string $body): string => omrCheckerReplaceOnce(
            "Content-Disposition: form-data; name=\"result\"\r\n",
            "Content-Disposition: form-data; name=\"result\"\r\nX-Injected: yes\r\n",
            $body,
        ),
        'many unknown headers' => static fn (string $body): string => omrCheckerReplaceOnce(
            "Content-Disposition: form-data; name=\"result\"\r\n",
            "Content-Disposition: form-data; name=\"result\"\r\n".implode('', array_map(
                static fn (int $number): string => "X-Noise-{$number}: value\r\n",
                range(1, 12),
            )),
            $body,
        ),
        'control-bearing value' => static fn (string $body): string => omrCheckerReplaceOnce(
            "Content-Type: application/json\r\n\r\n",
            "Content-Type: application/json\x07\r\n\r\n",
            $body,
        ),
        'folded header' => static fn (string $body): string => omrCheckerReplaceOnce(
            "Content-Disposition: form-data; name=\"result\"\r\n",
            "Content-Disposition: form-data; name=\"result\"\r\n continuation: yes\r\n",
            $body,
        ),
        'wrong result MIME' => static fn (string $body): string => omrCheckerReplaceOnce(
            'Content-Type: application/json',
            'Content-Type: text/plain',
            $body,
        ),
        'wrong raster MIME' => static fn (string $body): string => omrCheckerReplaceOnce(
            'Content-Type: image/png',
            'Content-Type: image/jpeg',
            $body,
        ),
    ];
    Http::preventStrayRequests();
    $outcomes = [];

    foreach ($mutators as $name => $mutator) {
        $body = $mutator($response['body']);
        Http::fake(['*' => Http::response($body, 200, $response['headers'])]);
        $result = app(OmrCheckerDetection::class)->detect($request);
        $outcomes[$name] = [
            $result->outcome->value,
            $result->safeDiagnosticCode,
            $result->safeDiagnosticMessage,
        ];
        unset($body);
    }

    expect($outcomes)->toBe(array_fill_keys(
        array_keys($mutators),
        [OmrDetectionOutcome::TerminalFailure->value, 'worker_output_invalid', null],
    ));
});

test('layout response preserves aligned and overlay bytes through the streamed parser', function (): void {
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    $request = omrCheckerMemoryRequest();
    $response = omrCheckerMemoryResponse($request, true);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response($response['body'], 200, $response['headers'])]);

    $result = app(OmrCheckerDetection::class)->verifyLayout($request);

    expect($result)->toBeInstanceOf(OmrLayoutVerificationResult::class)
        ->and($result->succeeded())->toBeTrue()
        ->and($result->alignedBytes)->toBe($response['aligned'])
        ->and($result->overlayBytes)->toBe($response['overlay'])
        ->and(hash('sha256', $result->alignedBytes))->toBe(hash('sha256', $response['aligned']))
        ->and(hash('sha256', $result->overlayBytes))->toBe(hash('sha256', $response['overlay']))
        ->and($result->itemEvidence)->toHaveCount(150);
});

test('detection preserves bounded worker rectification on typed rejection without admitting answer keys', function (): void {
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    Http::preventStrayRequests();
    $request = omrCheckerMemoryRequest();
    $diagnostic = [
        'attempted' => true,
        'mode' => OmrRegistrationMode::DocumentBound->value,
        'document_gates' => ['accepted' => false],
    ];
    Http::fakeSequence()
        ->push(['code' => 'document_boundary_ambiguous', 'rectification' => $diagnostic], 422)
        ->push(['code' => 'document_boundary_ambiguous', 'rectification' => $diagnostic + ['answer_key' => ['A']]], 422);

    $rejected = app(OmrCheckerDetection::class)->detect($request);
    expect($rejected->outcome)->toBe(OmrDetectionOutcome::TerminalFailure)
        ->and($rejected->safeDiagnosticCode)->toBe('document_boundary_ambiguous')
        ->and($rejected->rectification)->toBe($diagnostic);

    expect(app(OmrCheckerDetection::class)->detect($request)->rectification)->toBeNull();
});
