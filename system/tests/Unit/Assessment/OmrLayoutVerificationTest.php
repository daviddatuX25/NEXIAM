<?php

use App\Assessment\Omr\Diagnostics\OmrPinnedRequestDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use App\Assessment\Omr\FakeOmrDetection;
use App\Assessment\Omr\OmrCaptureAnalysisPipeline;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrOptionGeometry;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrQuarterTurnNormalizer;
use App\Assessment\Omr\OmrRegion;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use RuntimeException;

function layoutAdapterContractPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

function layoutAdapterContractRequest(): OmrDetectionRequest
{
    $options = [
        new OmrOptionGeometry('A', new OmrRegion(0.10, 0.20, 0.04, 0.04), 1),
        new OmrOptionGeometry('B', new OmrRegion(0.16, 0.20, 0.04, 0.04), 2),
        new OmrOptionGeometry('C', new OmrRegion(0.22, 0.20, 0.04, 0.04), 3),
        new OmrOptionGeometry('D', new OmrRegion(0.28, 0.20, 0.04, 0.04), 4),
    ];
    $bytes = layoutAdapterContractPng();

    return new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'layout-adapter-contract-run',
        correlationId: 'layout-adapter-contract-correlation',
        imageBytes: $bytes,
        inputHash: hash('sha256', $bytes),
        imageMime: 'image/png',
        imageWidth: 1,
        imageHeight: 1,
        areas: ['LOGIC' => 1],
        items: [new OmrItemGeometry(
            position: 1,
            areaCode: 'LOGIC',
            questionNo: 1,
            nativeLabel: 'LOGIC_Q_001',
            region: new OmrRegion(0.10, 0.20, 0.22, 0.04),
            options: $options,
        )],
        identityRegions: [],
        nativeBundleHash: str_repeat('b', 64),
        manifestHash: str_repeat('m', 64),
        configHash: hash('sha256', '{"fixture":true}'),
        bookletHash: '',
        nativeTemplate: [
            'pageDimensions' => [100, 200],
            'markers' => [
                'family' => 'nexiam.fiducial',
                'asset_name' => 'omr_marker.jpg',
                'profile' => 'profile-v1',
                'size' => 12,
                'quiet_zone' => 3,
                'inset' => 12,
                'paper' => [124, 224],
                'centers' => [
                    'quarter_1' => [10, 10],
                    'quarter_2' => [90, 10],
                    'quarter_3' => [10, 190],
                    'quarter_4' => [90, 190],
                ],
                'quadrants' => [
                    'quarter_1' => 'top_left',
                    'quarter_2' => 'top_right',
                    'quarter_3' => 'bottom_left',
                    'quarter_4' => 'bottom_right',
                ],
                'skew_tolerance_degrees' => 3,
                'perspective_tolerance' => 0.05,
            ],
            'fieldBlocks' => [
                'LOGICPartA' => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => ['LOGIC_Q_001'],
                    'bubblesGap' => 20,
                    'labelsGap' => 30,
                    'origin' => [10, 40],
                ],
            ],
        ],
        nativeConfigJson: '{"fixture":true}',
        nativeBundleMetadata: [
            'files' => ['config.json' => hash('sha256', '{"fixture":true}')],
            'mapping_hash' => str_repeat('g', 64),
            'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
            'qr_mode' => OmrQrMode::Disabled->value,
            'compiled_pages' => [['page_no' => 1]],
            'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('e', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
            'bundle_version' => 'nexiam.omr.native-bundle.v1',
        ],
        reviewManifest: [],
        engineName: 'OMRChecker',
        engineCommit: str_repeat('e', 40),
        patchIdentity: 'nexiam-headless-overlay-v1',
        expectedFieldLabels: [],
        expectedOptionCodes: [],
        providerDeadline: 2.0,
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: OmrSourceImage::fromBytes($bytes),
        pageNo: 1,
        nativeAssets: ['omr_marker.jpg' => 'marker-bytes'],
    );
}

/** @return array{body:string,headers:array<string,string>} */
function layoutAdapterContractResponse(OmrDetectionRequest $request, array $overrides = []): array
{
    $aligned = layoutAdapterContractPng();
    $overlay = layoutAdapterContractPng().'overlay';
    $payload = array_replace_recursive([
        'contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'registration_mode' => $request->registrationMode->value,
        'template_version_id' => $request->templateVersionId,
        'expected_page_no' => $request->pageNo,
        'source_image' => $request->sourceImage->toArray(),
        'run_id' => $request->runId,
        'correlation_id' => $request->correlationId,
        'outcome' => OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
        'engine' => [
            'name' => 'OMRChecker',
            'version' => 'fixture',
            'commit' => $request->engineCommit,
            'patch_identity' => $request->patchIdentity,
        ],
        'marker_matches' => [
            'quarter_1' => 0.91,
            'quarter_2' => 0.92,
            'quarter_3' => 0.93,
            'quarter_4' => 0.94,
        ],
        'aligned_image' => ['mime' => 'image/png', 'width' => 100, 'height' => 200],
        'layout_overlay' => ['mime' => 'image/png', 'width' => 100, 'height' => 200],
        'field_block_shifts' => [['block' => 'LOGICPartA', 'x' => 2, 'y' => 1]],
        'bubble_means' => ['LOGICPartA' => [['label' => 'LOGIC_Q_001', 'option' => 'A', 'mean' => 181.5]]],
        'item_evidence' => [['label' => 'LOGIC_Q_001', 'option' => 'A', 'mean' => 181.5]],
        'timings' => ['preprocess_ms' => 12.0, 'verification_ms' => 31.0],
    ], $overrides);
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $boundary = 'layout-adapter-contract';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$aligned."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$overlay."\r\n"
        .'--'.$boundary."--\r\n";
    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $aligned);
    $overlayHash = hash('sha256', $overlay);
    $signature = hash_hmac(
        'sha256',
        implode("\n", [$timestamp, $request->runId, $resultHash, $alignedHash, $overlayHash]),
        (string) config('omr.omrchecker.signing_secret'),
    );

    return [
        'body' => $body,
        'headers' => [
            'Content-Type' => 'multipart/form-data; boundary='.$boundary,
            'X-Nexiam-Contract' => OmrDetectionRequest::ENVELOPE_VERSION,
            'X-Nexiam-Run-Id' => $request->runId,
            'X-Nexiam-Timestamp' => $timestamp,
            'X-Nexiam-Result-SHA256' => $resultHash,
            'X-Nexiam-Aligned-SHA256' => $alignedHash,
            'X-Nexiam-Layout-Overlay-SHA256' => $overlayHash,
            'X-Nexiam-Signature' => $signature,
        ],
    ];
}

final class LayoutAdapterDetectionOnlyFake implements OmrDetectionPort
{
    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        throw new RuntimeException('The detection-only fixture must not be called.');
    }
}

test('client quad preserves ordered normalized coordinate-pair wire shape', function (): void {
    $points = [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]];
    $payload = [
        'points' => $points,
        'width' => 100,
        'height' => 200,
        'orientation' => 1,
        'input_hash' => str_repeat('a', 64),
        'page_no' => 1,
    ];
    $quad = OmrClientQuad::fromArray($payload);

    expect($quad->toArray()['points'])->toBe($points)
        ->and(fn () => OmrClientQuad::fromArray([
            ...$payload,
            'points' => array_map(static fn (array $point): array => ['x' => $point[0], 'y' => $point[1]], $points),
        ]))->toThrow(InvalidArgumentException::class);
});

test('anchored captures reject client quads before invoking registration or detection workers', function (): void {
    $request = layoutAdapterContractRequest();
    $quad = OmrClientQuad::fromArray([
        'points' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        'width' => $request->sourceImage->width,
        'height' => $request->sourceImage->height,
        'orientation' => $request->sourceImage->orientation,
        'input_hash' => $request->sourceImage->inputHash,
        'page_no' => $request->pageNo,
    ]);
    $layout = new class implements OmrLayoutVerificationPort
    {
        public int $calls = 0;

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $this->calls++;

            throw new RuntimeException('An anchored client quad must not reach registration.');
        }
    };
    $requests = new OmrDiagnosticRequestBuilder;
    $pipeline = new OmrCaptureAnalysisPipeline(
        preflight: new OmrCapturePreflight,
        normalizer: new OmrQuarterTurnNormalizer,
        requests: $requests,
        layout: $layout,
        runner: new OmrTemplateDiagnosticRunner($requests, $layout, new FakeOmrDetection),
    );

    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $request->imageBytes, mime: $request->imageMime, clientQuad: $quad),
        target: new OmrPinnedRequestDiagnosticTarget($request, 'client-quad-policy'),
        pageNo: $request->pageNo,
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('client_quad_registration_mismatch')
        ->and($prepared->diagnosticEvidence?->code)->toBe('client_quad_registration_mismatch')
        ->and($layout->calls)->toBe(0);
});

test('the container shares one guarded adapter for detection and layout verification', function (): void {
    config([
        'omr.provider.driver' => 'fake',
        'omr.provider.allow_fake' => true,
    ]);
    app()->forgetInstance(OmrLayoutVerificationPort::class);
    app()->forgetInstance(OmrDetectionPort::class);

    $layout = app(OmrLayoutVerificationPort::class);
    $detection = app(OmrDetectionPort::class);

    expect($layout)->toBeInstanceOf(FakeOmrDetection::class)
        ->and($layout)->toBe($detection)
        ->and(app(OmrLayoutVerificationPort::class))->toBe($layout);

    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => str_repeat('e', 40),
        'omr.omrchecker.patch_identity' => 'nexiam-headless-overlay-v1',
    ]);
    app()->forgetInstance(OmrLayoutVerificationPort::class);
    app()->forgetInstance(OmrDetectionPort::class);

    $workerLayout = app(OmrLayoutVerificationPort::class);
    expect($workerLayout)->toBeInstanceOf(OmrCheckerDetection::class)
        ->and($workerLayout)->toBe(app(OmrDetectionPort::class))
        ->and(app(OmrLayoutVerificationPort::class))->toBe($workerLayout);
    expect(app(OmrCaptureAnalysisPort::class))->toBeInstanceOf(OmrCaptureAnalysisPipeline::class);
});

test('layout binding fails closed when a detection override lacks the layout contract', function (): void {
    app()->instance(OmrDetectionPort::class, new LayoutAdapterDetectionOnlyFake);
    app()->forgetInstance(OmrLayoutVerificationPort::class);

    expect(fn () => app(OmrLayoutVerificationPort::class))
        ->toThrow(RuntimeException::class, 'does not provide layout verification');
});

test('the registered adapter posts ordered marker/profile frame diagnostics and preserves modest skew', function (): void {
    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => str_repeat('e', 40),
        'omr.omrchecker.patch_identity' => 'nexiam-headless-overlay-v1',
    ]);
    app()->forgetInstance(OmrLayoutVerificationPort::class);
    app()->forgetInstance(OmrDetectionPort::class);
    $request = layoutAdapterContractRequest();
    $seen = [];
    Http::preventStrayRequests();
    Http::fake(['*/layout-verification' => function ($sent) use (&$seen, $request) {
        $seen = [
            'method' => $sent->method(),
            'url' => $sent->url(),
            'body' => $sent->body(),
            'run_id' => $sent->header('X-Nexiam-Run-Id')[0] ?? null,
        ];
        $response = layoutAdapterContractResponse($request);

        return Http::response($response['body'], 200, $response['headers']);
    }]);

    $result = app(OmrLayoutVerificationPort::class)->verifyLayout($request);

    expect($seen['method'])->toBe('POST')
        ->and($seen['url'])->toEndWith('/v1/layout-verification')
        ->and($seen['run_id'])->toBe($request->runId)
        ->and($seen['body'])->toContain('name="manifest"')
        ->and($seen['body'])->toContain('name="image"')
        ->and($seen['body'])->toContain('profile-v1')
        ->and($result->succeeded())->toBeTrue()
        ->and(array_keys($result->markerMatches ?? []))->toBe(['quarter_1', 'quarter_2', 'quarter_3', 'quarter_4'])
        ->and($result->markerMatches)->toBe([
            'quarter_1' => 0.91,
            'quarter_2' => 0.92,
            'quarter_3' => 0.93,
            'quarter_4' => 0.94,
        ])
        ->and($result->alignedImage)->toMatchArray(['mime' => 'image/png', 'width' => 100, 'height' => 200])
        ->and($result->overlayImage)->toMatchArray(['mime' => 'image/png', 'width' => 100, 'height' => 200])
        ->and($result->fieldBlockShifts)->toBe([['block' => 'LOGICPartA', 'x' => 2, 'y' => 1]])
        ->and($result->bubbleMeans['LOGICPartA'][0]['mean'])->toBe(181.5)
        ->and($result->preprocessMs)->toBe(12.0)
        ->and($result->verificationMs)->toBe(31.0);
});

test('the adapter returns typed marker-missing and marker-mismatch failures', function (): void {
    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => str_repeat('e', 40),
        'omr.omrchecker.patch_identity' => 'nexiam-headless-overlay-v1',
    ]);
    app()->forgetInstance(OmrLayoutVerificationPort::class);
    app()->forgetInstance(OmrDetectionPort::class);
    $request = layoutAdapterContractRequest();

    foreach ([
        'marker_missing' => 'No corner markers were detected. orientation_hint=unknown',
        'marker_mismatch' => 'The markers do not match the selected profile. orientation_hint=upright_required',
    ] as $code => $detail) {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/layout-verification' => Http::response(
            json_encode(['code' => $code, 'detail' => $detail], JSON_THROW_ON_ERROR),
            422,
            ['Content-Type' => 'application/json'],
        )]);

        $result = app(OmrLayoutVerificationPort::class)->verifyLayout($request);

        expect($result->succeeded())->toBeFalse()
            ->and($result->code)->toBe($code)
            ->and($result->detail)->toBe($detail)
            ->and($result->alignedBytes)->toBeNull()
            ->and($result->overlayBytes)->toBeNull();
    }
});

test('malformed worker failures remain typed for recognition and layout consumers', function (): void {
    config([
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Http::preventStrayRequests();
    Http::fake([
        '*/detections' => Http::response('<html>Worker unavailable</html>', 503),
        '*/layout-verification' => Http::response('<html>Worker unavailable</html>', 503),
    ]);
    $request = layoutAdapterContractRequest();
    $adapter = new OmrCheckerDetection;

    $detection = $adapter->detect($request);
    $layout = $adapter->verifyLayout($request);

    expect($detection->outcome)->toBe(OmrDetectionOutcome::RetryableFailure)
        ->and($detection->safeDiagnosticCode)->toBe('worker_http_503')
        ->and($layout->succeeded())->toBeFalse()
        ->and($layout->code)->toBe('worker_http_503');
});

test('fake layout verification remains guarded when fake provider is disabled', function (): void {
    config([
        'omr.provider.driver' => 'fake',
        'omr.provider.allow_fake' => false,
    ]);
    app()->forgetInstance(OmrLayoutVerificationPort::class);
    app()->forgetInstance(OmrDetectionPort::class);

    expect(fn () => app(OmrLayoutVerificationPort::class))
        ->toThrow(RuntimeException::class, 'fake OMR provider is not permitted');
});
