<?php

use App\Assessment\Omr\Diagnostics\OmrPinnedRequestDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use App\Assessment\Omr\OmrCaptureAnalysisPipeline;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCapturePreflightLimits;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrQuarterTurnCandidate;
use App\Assessment\Omr\OmrQuarterTurnDiagnostics;
use App\Assessment\Omr\OmrQuarterTurnNormalizer;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;

function makeBinaryTestImage(): string
{
    $image = imagecreatetruecolor(4, 4);
    $red = imagecolorallocate($image, 255, 0, 0);
    imagefill($image, 0, 0, $red);
    ob_start();
    imagepng($image, null, 9, 0);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

test('prepared capture evidence serializes bounded metadata and hashes cleanly even with invalid utf8 binary layout bytes', function (): void {
    $candidate = new OmrQuarterTurnCandidate(
        orientation: 0,
        bytes: 'dummy_bytes',
        mime: 'image/png',
        sha256: str_repeat('c', 64),
        width: 100,
        height: 100,
    );

    // Raw non-UTF-8 binary bytes simulating PNG/JPEG compression buffers with 0x80-0xFF bytes
    $nonUtf8BinaryBytes = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\xff\xfe\x80\x90\xaa\xbb\xcc\xdd\xee\xff";

    $layoutResult = new OmrLayoutVerificationResult(
        outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
        code: null,
        detail: null,
        alignedBytes: $nonUtf8BinaryBytes,
        alignedImage: ['mime' => 'image/png', 'width' => 100, 'height' => 100, 'hash' => str_repeat('a', 64)],
        overlayBytes: $nonUtf8BinaryBytes,
        overlayImage: ['mime' => 'image/png', 'width' => 100, 'height' => 100, 'hash' => str_repeat('o', 64)],
        markerMatches: ['quarter_1' => 0.95, 'quarter_2' => 0.94, 'quarter_3' => 0.96, 'quarter_4' => 0.93],
        fieldBlockShifts: [['block' => 'Part_1', 'x' => 0, 'y' => 0]],
        gates: ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
        rectification: [
            'attempted' => true,
            'applied' => true,
            'detector_version' => 'nexiam.rectifier.v1',
            'homography_hash' => 'sha256:'.str_repeat('h', 64),
            'quad_normalized' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        ],
    );

    $records = [
        [
            'orientation' => 0,
            'candidate_hash' => str_repeat('c', 64),
            'mime' => 'image/png',
            'width' => 100,
            'height' => 100,
            'status' => 'layout_valid',
            'code' => null,
            'detail' => null,
            'marker_matches' => $layoutResult->markerMatches,
            'gates' => $layoutResult->gates,
            'duration_ms' => 12.5,
        ],
    ];

    $diagnostics = new OmrQuarterTurnDiagnostics(
        candidates: [$candidate],
        failures: [],
        timings: ['layout_ms' => 12.5],
        results: $records,
    );

    $evidence = new OmrPreparedCaptureEvidence(
        outcome: OmrPreparedCaptureEvidence::OUTCOME_SUCCEEDED,
        code: null,
        detail: null,
        pageNo: 1,
        inputHash: str_repeat('i', 64),
        acceptedOrientation: 0,
        orientationDiagnostics: $diagnostics,
        layout: $layoutResult,
        diagnosticEvidence: null,
        target: ['kind' => 'candidate', 'id' => '1'],
        hashes: ['input' => str_repeat('i', 64), 'aligned' => str_repeat('a', 64), 'overlay' => str_repeat('o', 64)],
        assets: [
            'captured' => ['asset_id' => 1, 'sha256' => str_repeat('i', 64)],
            'aligned' => ['asset_id' => 2, 'sha256' => str_repeat('a', 64)],
            'overlay' => ['asset_id' => 3, 'sha256' => str_repeat('o', 64)],
        ],
        gates: $layoutResult->gates,
        markerMatches: $layoutResult->markerMatches,
        fieldBlockShifts: $layoutResult->fieldBlockShifts,
        items: [],
        identity: [],
        timings: ['detection_ms' => 15.0],
        issues: [],
    );

    // Hashing must succeed without JsonException: Malformed UTF-8 characters
    $evidenceHash = $evidence->evidenceHash();
    expect($evidenceHash)->toBeString()->toHaveLength(64);

    $array = $evidence->toArray();
    expect($array)->toHaveKey('evidence_hash', $evidenceHash)
        ->and($array['layout'])->not->toHaveKey('alignedBytes')
        ->and($array['layout'])->not->toHaveKey('overlayBytes')
        ->and($array['orientation_diagnostics']['results'][0])->not->toHaveKey('layout');
});

test('analysis pipeline produces clean scalar orientation diagnostics without binary byte leakage', function (): void {
    $imageBytes = makeBinaryTestImage();
    $input = new OmrCaptureInput(bytes: $imageBytes, mime: 'image/png');

    $layoutPort = new class implements OmrLayoutVerificationPort
    {
        public int $calls = 0;

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $this->calls++;
            // Return binary bytes with non-UTF8 characters
            $nonUtf8 = "\x89PNG\r\n\x1a\n\xff\xfe\x80\x90\xaa";
            $score = 1.0 / $this->calls;

            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                alignedBytes: $nonUtf8,
                alignedImage: ['mime' => 'image/png', 'width' => 4, 'height' => 4, 'hash' => hash('sha256', $nonUtf8)],
                overlayBytes: $nonUtf8,
                overlayImage: ['mime' => 'image/png', 'width' => 4, 'height' => 4, 'hash' => hash('sha256', $nonUtf8)],
                markerMatches: ['quarter_1' => $score, 'quarter_2' => $score, 'quarter_3' => $score, 'quarter_4' => $score],
                gates: ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass', 'unsafe_blocks' => []],
                rectification: ['attempted' => false, 'applied' => false],
            );
        }
    };

    $detectorPort = new class implements OmrDetectionPort
    {
        public function detect(OmrDetectionRequest $request): OmrDetectionResult
        {
            return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
                engineName: $request->engineName,
                engineVersion: 'de3f698',
                engineCommit: $request->engineCommit,
                inputHash: $request->inputHash,
                configHash: $request->configHash,
                applicantNumberProposal: null,
                applicantNumberConfidence: null,
                identityFields: [],
                items: [],
                issues: [],
                sheetConfidence: null,
                responseEnvelopeVersion: $request->envelopeVersion,
                runId: $request->runId,
                correlationId: $request->correlationId,
                bookletHash: $request->bookletHash,
                nativeBundleHash: $request->nativeBundleHash,
                manifestHash: $request->manifestHash,
                patchIdentity: $request->patchIdentity,
                registrationMode: $request->registrationMode->value,
                templateVersionId: $request->templateVersionId,
                candidateTargetId: $request->candidateTargetId,
                expectedPageNo: $request->pageNo,
                sourceImage: $request->sourceImage->toArray(),
                imageMime: $request->imageMime,
                imageWidth: $request->imageWidth,
                imageHeight: $request->imageHeight,
            ));
        }
    };

    $preflight = new OmrCapturePreflight(new OmrCapturePreflightLimits(
        maxUploadBytes: 10 * 1024 * 1024,
        maxPixelsPerPage: 60_000_000,
        maxPagesPerSource: 20,
    ));

    $normalizer = new OmrQuarterTurnNormalizer;
    $requests = new OmrDiagnosticRequestBuilder;
    $runner = new OmrTemplateDiagnosticRunner($requests, $layoutPort, $detectorPort);

    $pipeline = new OmrCaptureAnalysisPipeline(
        preflight: $preflight,
        normalizer: $normalizer,
        requests: $requests,
        layout: $layoutPort,
        runner: $runner,
    );

    $detectionRequest = new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'serialization-test-run',
        correlationId: 'serialization-test-corr',
        imageBytes: $imageBytes,
        inputHash: hash('sha256', $imageBytes),
        imageMime: 'image/png',
        imageWidth: 4,
        imageHeight: 4,
        areas: [],
        items: [],
        identityRegions: [],
        nativeBundleHash: str_repeat('b', 64),
        manifestHash: str_repeat('m', 64),
        configHash: hash('sha256', '{"fixture":true}'),
        bookletHash: '',
        nativeTemplate: [
            'pageDimensions' => [4, 4],
            'markers' => [
                'family' => 'custom',
                'asset_name' => 'marker.jpg',
                'size' => 1,
                'quiet_zone' => 1,
                'inset' => 1,
                'paper' => [4, 4],
                'centers' => [
                    'top_left' => [1, 1],
                    'top_right' => [3, 1],
                    'bottom_left' => [1, 3],
                    'bottom_right' => [3, 3],
                ],
                'quadrants' => [
                    'quarter_1' => 'top_left',
                    'quarter_2' => 'top_right',
                    'quarter_3' => 'bottom_left',
                    'quarter_4' => 'bottom_right',
                ],
            ],
            'fieldBlocks' => [],
        ],
        nativeConfigJson: '{"fixture":true}',
        nativeBundleMetadata: [
            'files' => ['config.json' => hash('sha256', '{"fixture":true}')],
            'mapping_hash' => str_repeat('m', 64),
            'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
            'qr_mode' => 'qr_signed',
            'compiled_pages' => [['page_no' => 1]],
        ],
        reviewManifest: [],
        engineName: 'fake',
        engineCommit: null,
        patchIdentity: 'test-patch',
        expectedFieldLabels: [],
        expectedOptionCodes: [],
        providerDeadline: 1.0,
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: OmrSourceImage::fromBytes($imageBytes),
        pageNo: 1,
        nativeAssets: ['marker.jpg' => 'marker'],
    );

    $target = new OmrPinnedRequestDiagnosticTarget($detectionRequest, 'serialization-target');
    $analysisRequest = new OmrCaptureAnalysisRequest(
        target: $target,
        capture: $input,
        pageNo: 1,
    );

    $result = $pipeline->analyze($analysisRequest);

    expect($result->succeeded())->toBeTrue();
    // Verify evidenceHash computes without throwing JsonException
    expect($result->evidenceHash())->toBeString()->toHaveLength(64);

    $array = $result->toArray();
    foreach ($array['orientation_diagnostics']['results'] as $entry) {
        expect($entry)->not->toHaveKey('layout');
        foreach ($entry as $key => $val) {
            expect($val)->not->toBeObject();
        }
    }
});
