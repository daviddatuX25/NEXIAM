<?php

use App\Assessment\Omr\Diagnostics\OmrPinnedRequestDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use App\Assessment\Omr\OmrCaptureAnalysisPipeline;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCapturePreflightLimits;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrQuarterTurnNormalizer;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;

function createTestImage(): string
{
    $image = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $bg);
    ob_start();
    imagepng($image, null, 9, 0);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function omrRectificationShadowContractRequest(string $bytes): OmrDetectionRequest
{
    return new OmrDetectionRequest(
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'rectification-shadow-run',
        correlationId: 'rectification-shadow-correlation',
        imageBytes: $bytes,
        inputHash: hash('sha256', $bytes),
        imageMime: 'image/png',
        imageWidth: 100,
        imageHeight: 100,
        areas: [],
        items: [],
        identityRegions: [],
        nativeBundleHash: str_repeat('a', 64),
        manifestHash: str_repeat('b', 64),
        configHash: hash('sha256', '{"fixture":true}'),
        bookletHash: '',
        nativeTemplate: [
            'pageDimensions' => [2, 3],
            'markers' => [
                'family' => 'custom',
                'asset_name' => 'marker.jpg',
                'size' => 1,
                'quiet_zone' => 1,
                'inset' => 1,
                'paper' => [2, 3],
                'centers' => [
                    'top_left' => [1, 1],
                    'top_right' => [1, 1],
                    'bottom_left' => [1, 2],
                    'bottom_right' => [1, 2],
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
            'mapping_hash' => str_repeat('d', 64),
            'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
            'qr_mode' => 'qr_signed',
            'compiled_pages' => [['page_no' => 1]],
        ],
        reviewManifest: [],
        engineName: 'fake',
        engineCommit: null,
        patchIdentity: 'rectification-shadow',
        expectedFieldLabels: [],
        expectedOptionCodes: [],
        providerDeadline: 1.0,
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: OmrSourceImage::fromBytes($bytes),
        pageNo: 1,
        nativeAssets: ['marker.jpg' => 'marker'],
    );
}

test('shadow comparison mode records candidate telemetry on failed raw capture without promoting to score decisions', function (): void {
    $bytes = createTestImage();
    $target = new OmrPinnedRequestDiagnosticTarget(omrRectificationShadowContractRequest($bytes), false);

    $shadowLayout = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            // Raw CropOnMarkers fails, shadow rectification telemetry recorded
            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_FAILED,
                code: 'marker_missing',
                detail: 'Corner markers not detected in raw quadrants (orientation_hint=upright)',
                rectification: [
                    'attempted' => true,
                    'applied' => false,
                    'shadow_mode' => true,
                    'detector_version' => 'nexiam.rectifier.v1',
                    'candidate_count' => 1,
                    'valid_candidate_count' => 1,
                    'raw_state' => 'marker_missing',
                    'raw_marker_matches' => ['quarter_1' => 0.12],
                    'duration_ms' => 45.2,
                ],
            );
        }
    };

    $detector = new class implements OmrDetectionPort
    {
        public function detect(OmrDetectionRequest $request): OmrDetectionResult
        {
            return OmrDetectionResult::failed(OmrDetectionOutcome::TerminalFailure, 'marker_missing');
        }
    };

    $preflight = new OmrCapturePreflight(OmrCapturePreflightLimits::fromConfig());

    $pipeline = new OmrCaptureAnalysisPipeline(
        $preflight,
        new OmrQuarterTurnNormalizer,
        new OmrDiagnosticRequestBuilder,
        $shadowLayout,
        new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $shadowLayout, $detector),
    );

    $evidence = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(
            bytes: $bytes,
            mime: 'image/png',
            pageNo: 1,
            pageCount: 1,
            originalHash: hash('sha256', $bytes),
        ),
        target: $target,
        pageNo: 1,
    ));

    expect($evidence->succeeded())->toBeFalse();
    expect($evidence->code)->toBe('marker_missing');
    expect($evidence->layout)->not->toBeNull();
    expect($evidence->layout->rectification)->toBeArray();
    expect($evidence->layout->rectification['shadow_mode'])->toBeTrue();
    expect($evidence->layout->rectification['applied'])->toBeFalse();
    expect($evidence->layout->rectification['valid_candidate_count'])->toBe(1);
});

test('guarded recovery mode promotes unique rectified candidate to layout and preserves full provenance', function (): void {
    $bytes = createTestImage();
    $target = new OmrPinnedRequestDiagnosticTarget(omrRectificationShadowContractRequest($bytes), false);

    $rectifiedLayout = new class implements OmrLayoutVerificationPort
    {
        public int $calls = 0;

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $this->calls++;
            $score = 1.0 / $this->calls;

            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                alignedBytes: 'aligned-bytes',
                alignedImage: ['mime' => 'image/png', 'width' => 2, 'height' => 3, 'hash' => hash('sha256', 'aligned-bytes')],
                overlayBytes: 'overlay-bytes',
                overlayImage: ['mime' => 'image/png', 'width' => 2, 'height' => 3, 'hash' => hash('sha256', 'overlay-bytes')],
                markerMatches: ['quarter_1' => $score, 'quarter_2' => $score, 'quarter_3' => $score, 'quarter_4' => $score],
                fieldBlockShifts: [['block' => 'Part_1', 'x' => 0, 'y' => 0]],
                bubbleMeans: [],
                itemEvidence: [],
                gates: ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass', 'unsafe_blocks' => []],
                rectification: [
                    'attempted' => true,
                    'applied' => true,
                    'detector_version' => 'nexiam.rectifier.v1',
                    'candidate_count' => 1,
                    'selected_candidate_index' => 0,
                    'selection_reason' => 'unique_four_marker_pass',
                    'quad_normalized' => [[0.1, 0.05], [0.9, 0.05], [0.95, 0.95], [0.05, 0.95]],
                    'quad_area_ratio' => 0.82,
                    'quad_edge_score' => 0.91,
                    'homography_hash' => 'sha256:abcd1234',
                    'input_dimensions' => [3000, 4000],
                    'output_dimensions' => [2060, 3144],
                    'raw_marker_matches' => ['quarter_1' => 0.12],
                    'rectified_marker_matches' => ['quarter_1' => 0.85, 'quarter_2' => 0.84, 'quarter_3' => 0.86, 'quarter_4' => 0.85],
                    'duration_ms' => 120.4,
                ],
            );
        }
    };

    $detector = new class implements OmrDetectionPort
    {
        public function detect(OmrDetectionRequest $request): OmrDetectionResult
        {
            return OmrDetectionResult::succeeded(
                new OmrDetectionSuccess(
                    engineName: $request->engineName,
                    engineVersion: 'de3f6982',
                    engineCommit: $request->engineCommit,
                    inputHash: $request->inputHash,
                    configHash: $request->configHash,
                    applicantNumberProposal: '2000600002',
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
                    calibrationStatus: 'synthetic',
                    fieldBlockShifts: [['block' => 'Part_1', 'x' => 0, 'y' => 0]],
                    alignedImage: ['mime' => 'image/png', 'width' => 2, 'height' => 3, 'hash' => hash('sha256', 'aligned-bytes'), 'bytes' => 'aligned-bytes'],
                    imageMime: $request->imageMime,
                    imageWidth: $request->imageWidth,
                    imageHeight: $request->imageHeight,
                    rectification: [
                        'attempted' => true,
                        'applied' => true,
                        'detector_version' => 'nexiam.rectifier.v1',
                        'candidate_count' => 1,
                        'selected_candidate_index' => 0,
                        'selection_reason' => 'unique_four_marker_pass',
                        'quad_normalized' => [[0.1, 0.05], [0.9, 0.05], [0.95, 0.95], [0.05, 0.95]],
                        'quad_area_ratio' => 0.82,
                        'quad_edge_score' => 0.91,
                        'homography_hash' => 'sha256:abcd1234',
                        'input_dimensions' => [3000, 4000],
                        'output_dimensions' => [2060, 3144],
                        'raw_marker_matches' => ['quarter_1' => 0.12],
                        'rectified_marker_matches' => ['quarter_1' => 0.85, 'quarter_2' => 0.84, 'quarter_3' => 0.86, 'quarter_4' => 0.85],
                        'duration_ms' => 120.4,
                    ],
                )
            );
        }
    };

    $preflight = new OmrCapturePreflight(OmrCapturePreflightLimits::fromConfig());
    $pipeline = new OmrCaptureAnalysisPipeline(
        $preflight,
        new OmrQuarterTurnNormalizer,
        new OmrDiagnosticRequestBuilder,
        $rectifiedLayout,
        new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $rectifiedLayout, $detector),
    );

    $evidence = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(
            bytes: $bytes,
            mime: 'image/png',
            pageNo: 1,
            pageCount: 1,
            originalHash: hash('sha256', $bytes),
        ),
        target: $target,
        pageNo: 1,
    ));

    expect($evidence->succeeded())->toBeTrue();
    expect($evidence->layout)->not->toBeNull();
    expect($evidence->layout->rectification)->toBeArray();
    expect($evidence->layout->rectification['applied'])->toBeTrue();
    expect($evidence->layout->rectification['selection_reason'])->toBe('unique_four_marker_pass');

    $normalized = $evidence->normalize();
    expect($normalized['rectification'])->toBeArray();
    expect($normalized['rectification']['applied'])->toBeTrue();
    expect($normalized['rectification']['homography_hash'])->toBe('sha256:abcd1234');
});

test('failed layout verification result retains rectification telemetry', function (): void {
    $rect = [
        'attempted' => true,
        'applied' => false,
        'detector_version' => 'nexiam.rectifier.v1',
        'candidate_count' => 1,
        'valid_candidate_count' => 0,
        'reason' => 'no_valid_candidate',
        'raw_marker_matches' => ['quarter_1' => 0.12, 'quarter_2' => 0.15],
    ];

    $result = OmrLayoutVerificationResult::failed(
        'marker_missing',
        'One or more alignment markers could not be detected.',
        $rect,
    );

    expect($result->succeeded())->toBeFalse()
        ->and($result->code)->toBe('marker_missing')
        ->and($result->rectification)->toBe($rect);
});
