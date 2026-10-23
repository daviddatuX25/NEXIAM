<?php

use App\Assessment\Omr\Diagnostics\OmrPinnedRequestDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use App\Assessment\Omr\OmrCaptureAnalysisPipeline;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCaptureOrientationPort;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCapturePreflightLimits;
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

function pipelineContractImage(): string
{
    $image = imagecreatetruecolor(2, 3);
    $red = imagecolorallocate($image, 255, 0, 0);
    imagefill($image, 0, 0, $red);
    ob_start();
    imagepng($image, null, 9, 0);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function pipelineContractRequest(string $bytes): OmrDetectionRequest
{
    $sourceImage = OmrSourceImage::fromBytes($bytes);

    return new OmrDetectionRequest(
        registrationMode: OmrRegistrationMode::FiducialAnchored,
        templateVersionId: 1,
        candidateTargetId: null,
        sourceImage: $sourceImage,
        envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
        runId: 'pipeline-contract-run',
        correlationId: 'pipeline-contract-correlation',
        imageBytes: $bytes,
        inputHash: $sourceImage->inputHash,
        imageMime: 'image/png',
        imageWidth: $sourceImage->width,
        imageHeight: $sourceImage->height,
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
        patchIdentity: 'pipeline-contract',
        expectedFieldLabels: [],
        expectedOptionCodes: [],
        providerDeadline: 1.0,
        pageNo: 1,
        nativeAssets: ['marker.jpg' => 'marker'],
    );
}

final class PipelineContractLayoutFake implements OmrLayoutVerificationPort
{
    public int $calls = 0;

    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
    {
        $this->calls++;

        return new OmrLayoutVerificationResult(
            outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
            alignedImage: ['mime' => 'image/png', 'width' => 2, 'height' => 3],
            markerMatches: [
                'quarter_1' => 1.0 / $this->calls,
                'quarter_2' => 1.0 / $this->calls,
                'quarter_3' => 1.0 / $this->calls,
                'quarter_4' => 1.0 / $this->calls,
            ],
            gates: [
                'marker_count' => 'pass',
                'registration' => 'pass',
                'dimensions' => 'pass',
                'shifts' => 'pass',
                'unsafe_blocks' => [],
            ],
        );
    }
}

final class PipelineContractDetectorFake implements OmrDetectionPort
{
    public int $calls = 0;

    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        $this->calls++;

        return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
            engineName: $request->engineName,
            engineVersion: '1',
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
            calibrationStatus: 'unqualified',
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
}
final class PipelineContractOrientationFake implements OmrCaptureOrientationPort
{
    public ?string $inspectedHash = null;

    public ?string $inspectedMime = null;

    public function __construct(private readonly ?int $turn) {}

    public function inspect(string $bytes, string $mime, string $challengeCode, int $pageNo): ?int
    {
        $this->inspectedHash = hash('sha256', $bytes);
        $this->inspectedMime = $mime;

        return $this->turn;
    }
}

test('pipeline evaluates four orientations then detects only the unique layout winner', function (): void {
    $bytes = pipelineContractImage();
    $layout = new PipelineContractLayoutFake;
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(
        new OmrDiagnosticRequestBuilder,
        $layout,
        $detector,
    );
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
    );
    $input = new OmrCaptureInput(bytes: $bytes, mime: 'image/png');
    $baseRequest = pipelineContractRequest($bytes);
    $phases = [];
    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: $input,
        target: new OmrPinnedRequestDiagnosticTarget($baseRequest, 'contract-target'),
        pageNo: 1,
        onPhase: static function (string $phase) use (&$phases): void {
            $phases[] = $phase;
        },
    ));

    expect($prepared->succeeded())->toBeTrue()
        ->and($prepared->acceptedOrientation)->toBe(0)
        ->and($prepared->orientationDiagnostics->orientations())->toBe([0, 90, 180, 270])
        ->and($prepared->inputHash)->toBe(hash('sha256', $bytes))
        ->and($prepared->hashes['original_input'])->toBe(hash('sha256', $bytes))
        ->and($layout->calls)->toBe(4)
        ->and($detector->calls)->toBe(1)
        ->and($phases)->toBe(['preflight', 'orientation', 'layout_verification', 'recognition'])
        ->and(array_key_exists('score', $prepared->normalize()))->toBeFalse();
});

test('trusted QR orientation short-circuits after its unchanged layout gate passes', function (): void {
    $bytes = pipelineContractImage();
    $layout = new PipelineContractLayoutFake;
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $orientation = new PipelineContractOrientationFake(270);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
        $orientation,
    );

    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
        challengeCode: 'CHAL-PIPELINE',
    ));

    expect($prepared->succeeded())->toBeTrue()
        ->and($prepared->acceptedOrientation)->toBe(270)
        ->and($prepared->orientationDiagnostics->orientations())->toBe([270, 0, 90, 180])
        ->and($prepared->orientationDiagnostics->results)->toHaveCount(1)
        ->and($layout->calls)->toBe(1)
        ->and($detector->calls)->toBe(1);
});

test('marker-valid fallback cannot override a contradictory trusted QR orientation', function (): void {
    $bytes = pipelineContractImage();
    $layout = new class implements OmrLayoutVerificationPort
    {
        public int $calls = 0;

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $this->calls++;
            if ($this->calls !== 2) {
                return OmrLayoutVerificationResult::failed('marker_missing', 'missing marker');
            }

            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                alignedImage: ['mime' => 'image/png', 'width' => 3, 'height' => 2],
                markerMatches: [
                    'quarter_1' => 0.9,
                    'quarter_2' => 0.9,
                    'quarter_3' => 0.9,
                    'quarter_4' => 0.9,
                ],
                gates: [
                    'marker_count' => 'pass',
                    'registration' => 'pass',
                    'dimensions' => 'pass',
                    'shifts' => 'pass',
                    'unsafe_blocks' => [],
                ],
            );
        }
    };
    $detector = new PipelineContractDetectorFake;
    $orientation = new PipelineContractOrientationFake(0);
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
        $orientation,
    );

    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
        challengeCode: 'CHAL-PIPELINE',
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('orientation_mismatch')
        ->and($layout->calls)->toBe(4)
        ->and($detector->calls)->toBe(0);
});

test('QR orientation inspects the EXIF-normalized canonical candidate', function (): void {
    $bytes = file_get_contents(base_path('docker/inference/tests/fixtures/hand-shaded-perspective-redacted.jpg'));
    expect($bytes)->toBeString();
    $layout = new PipelineContractLayoutFake;
    $detector = new PipelineContractDetectorFake;
    $orientation = new PipelineContractOrientationFake(null);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10 * 1024 * 1024, 20_000_000, 20)),
        new OmrQuarterTurnNormalizer,
        new OmrDiagnosticRequestBuilder,
        $layout,
        new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector),
        $orientation,
    );

    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/jpeg'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
        challengeCode: 'CHAL-PIPELINE',
    ));

    expect($orientation->inspectedHash)->toBe($prepared->orientationDiagnostics->candidates[0]->sha256)
        ->and($orientation->inspectedMime)->toBe('image/jpeg')
        ->and([$prepared->orientationDiagnostics->candidates[0]->width, $prepared->orientationDiagnostics->candidates[0]->height])
        ->toBe([3000, 4000]);
});

test('pipeline fails closed on missing markers without invoking detection', function (): void {
    $bytes = pipelineContractImage();
    $layout = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return OmrLayoutVerificationResult::failed('marker_missing', 'missing marker');
        }
    };
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
    );
    $phases = [];
    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
        onPhase: static function (string $phase) use (&$phases): void {
            $phases[] = $phase;
        },
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('marker_missing')
        ->and($prepared->orientationDiagnostics->orientations())->toBe([0, 90, 180, 270])
        ->and($detector->calls)->toBe(0)
        ->and($phases)->toBe(['preflight', 'orientation', 'layout_verification']);
});

test('pipeline elevates transport 413 without masking as marker missing', function (): void {
    $bytes = pipelineContractImage();
    $layout = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return OmrLayoutVerificationResult::failed(
                'multipart_part_too_large',
                'The capture image exceeds the worker transport limit (413).',
            );
        }
    };
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
    );
    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('multipart_part_too_large')
        ->and($prepared->detail)->toContain('transport limit')
        ->and($prepared->code)->not->toBe('marker_missing')
        ->and($detector->calls)->toBe(0);
});

test('pipeline elevates worker unavailable without masking as marker missing', function (): void {
    $bytes = pipelineContractImage();
    $layout = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return OmrLayoutVerificationResult::failed(
                'worker_unavailable',
                'The OMR worker is unreachable.',
            );
        }
    };
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
    );
    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('worker_unavailable')
        ->and($prepared->detail)->toBe('The OMR worker is unreachable.')
        ->and($prepared->code)->not->toBe('marker_missing')
        ->and($detector->calls)->toBe(0);
});

test('pipeline preserves sheet partial error without converting to marker missing', function (): void {
    $bytes = pipelineContractImage();
    $layout = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return OmrLayoutVerificationResult::failed(
                'sheet_partial',
                'Part of the sheet is out of frame or alignment markers were only partially detected.',
            );
        }
    };
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
    );
    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('sheet_partial')
        ->and($prepared->detail)->toContain('Part of the sheet is out of frame')
        ->and($prepared->code)->not->toBe('marker_missing')
        ->and($detector->calls)->toBe(0);
});

test('pipeline prefers a partial sheet observation over rotated marker mismatches', function (): void {
    $bytes = pipelineContractImage();
    $layout = new class implements OmrLayoutVerificationPort
    {
        private int $calls = 0;

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $this->calls++;

            return $this->calls === 1
                ? OmrLayoutVerificationResult::failed(
                    'sheet_partial',
                    'Three corner markers were detected, but the fourth edge is outside the capture.',
                )
                : OmrLayoutVerificationResult::failed(
                    'marker_mismatch',
                    'This rotated candidate does not match the expected marker geometry.',
                );
        }
    };
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
    );
    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('sheet_partial')
        ->and($prepared->detail)->toContain('Three corner markers');
});

test('pipeline preserves expected vector hash when no winner satisfies layout gates', function (): void {
    $bytes = pipelineContractImage();
    $layout = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return OmrLayoutVerificationResult::failed(
                'marker_missing',
                'One or more alignment markers could not be detected.',
            );
        }
    };
    $detector = new PipelineContractDetectorFake;
    $runner = new OmrTemplateDiagnosticRunner(new OmrDiagnosticRequestBuilder, $layout, $detector);
    $pipeline = new OmrCaptureAnalysisPipeline(
        new OmrCapturePreflight(new OmrCapturePreflightLimits(10000, 100, 20)),
        new OmrQuarterTurnNormalizer(100),
        new OmrDiagnosticRequestBuilder,
        $layout,
        $runner,
    );
    $prepared = $pipeline->analyze(new OmrCaptureAnalysisRequest(
        capture: new OmrCaptureInput(bytes: $bytes, mime: 'image/png'),
        target: new OmrPinnedRequestDiagnosticTarget(pipelineContractRequest($bytes), 'contract-target'),
        pageNo: 1,
        expectedVectorHash: 'sha256:expected-vector-12345',
    ));

    expect($prepared->succeeded())->toBeFalse()
        ->and($prepared->code)->toBe('marker_missing')
        ->and($prepared->expectedVectorHash)->toBe('sha256:expected-vector-12345')
        ->and($detector->calls)->toBe(0);
});
