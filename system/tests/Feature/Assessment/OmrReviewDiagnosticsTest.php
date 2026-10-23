<?php

use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\TestCaptureOmrTemplate;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrCapturePreflightLimits;
use App\Assessment\Omr\OmrCapturePreflightResult;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Authorization\CapabilityAuthorizer;
use App\Http\Controllers\Setup\OmrConfigurationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OmrReviewDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function omrTestCaptureImageBytes(): string
    {
        $image = imagecreatetruecolor(2060, 3144);
        $bg = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bg);
        ob_start();
        imagepng($image, null, 9, 0);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    public function test_layout_verification_result_carries_rectification_telemetry(): void
    {
        $rect = [
            'attempted' => true,
            'applied' => true,
            'detector_version' => 'nexiam.rectifier.v1',
            'candidate_count' => 1,
            'selected_candidate_index' => 0,
            'selection_reason' => 'unique_four_marker_pass',
            'quad_normalized' => [[0.1, 0.05], [0.9, 0.05], [0.95, 0.95], [0.05, 0.95]],
            'quad_area_ratio' => 0.82,
            'quad_edge_score' => 0.91,
            'homography_hash' => 'sha256:11223344',
            'input_dimensions' => [3000, 4000],
            'output_dimensions' => [2060, 3144],
            'raw_marker_matches' => ['quarter_1' => 0.15],
            'rectified_marker_matches' => ['quarter_1' => 0.85, 'quarter_2' => 0.84, 'quarter_3' => 0.86, 'quarter_4' => 0.85],
            'duration_ms' => 110.5,
        ];

        $result = new OmrLayoutVerificationResult(
            outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
            alignedBytes: 'aligned-bytes',
            alignedImage: ['mime' => 'image/png', 'width' => 2060, 'height' => 3144, 'hash' => hash('sha256', 'aligned-bytes')],
            overlayBytes: 'overlay-bytes',
            overlayImage: ['mime' => 'image/png', 'width' => 2060, 'height' => 3144, 'hash' => hash('sha256', 'overlay-bytes')],
            markerMatches: ['quarter_1' => 0.85, 'quarter_2' => 0.84, 'quarter_3' => 0.86, 'quarter_4' => 0.85],
            fieldBlockShifts: [],
            bubbleMeans: [],
            gates: ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass', 'unsafe_blocks' => []],
            rectification: $rect,
        );

        $this->assertTrue($result->succeeded());
        $this->assertIsArray($result->rectification);
        $this->assertTrue($result->rectification['applied']);
        $this->assertSame('sha256:11223344', $result->rectification['homography_hash']);
    }

    public function test_preflight_result_carries_rectification_telemetry(): void
    {
        $rect = [
            'attempted' => true,
            'applied' => true,
            'detector_version' => 'nexiam.rectifier.v1',
            'candidate_count' => 1,
            'selected_candidate_index' => 0,
            'selection_reason' => 'unique_four_marker_pass',
            'quad_normalized' => [[0.1, 0.05], [0.9, 0.05], [0.95, 0.95], [0.05, 0.95]],
            'homography_hash' => 'sha256:55667788',
        ];

        $preflight = new OmrCapturePreflightResult(
            mode: OmrCapturePreflightResult::MODE_MARKER,
            outcome: OmrCapturePreflightResult::OUTCOME_VALIDATED,
            markerMatches: ['quarter_1' => 0.80, 'quarter_2' => 0.80, 'quarter_3' => 0.80, 'quarter_4' => 0.80],
            gates: ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass', 'shifts' => 'not_run'],
            normalizedDimensions: ['width' => 2060, 'height' => 3144],
            submitAllowed: true,
            rectification: $rect,
        );

        $this->assertSame(OmrCapturePreflightResult::OUTCOME_VALIDATED, $preflight->outcome);
        $this->assertTrue($preflight->submitAllowed);
        $this->assertIsArray($preflight->rectification);
        $this->assertTrue($preflight->rectification['applied']);
    }

    public function test_test_capture_action_preserves_rectification_in_response(): void
    {
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $blueprint = [
            'schema' => 'nexiam.omr.layout-blueprint.v1',
            'paper' => 'long',
            'columns' => 6,
            'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
            'markers' => ['enabled' => true],
            'sections' => [
                [
                    'area_code' => 'PROFILE_VERBAL',
                    'question_from' => 1,
                    'question_to' => 25,
                    'option_count' => 4,
                    'break_before' => null,
                ],
            ],
        ];
        $version = app(SaveAuthoredTemplateDraft::class)->handle(
            'SYN-OMR-TEST-CAP-DIAG',
            'Test capture template diag',
            [],
            $actor,
            null,
            true,
            $blueprint,
        );
        $fakeDetection = new class implements OmrLayoutVerificationPort
        {
            public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
            {
                return new OmrLayoutVerificationResult(
                    outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                    alignedBytes: 'aligned-data',
                    alignedImage: ['mime' => 'image/png', 'width' => 2060, 'height' => 3144, 'hash' => hash('sha256', 'aligned-data')],
                    overlayBytes: 'overlay-data',
                    overlayImage: ['mime' => 'image/png', 'width' => 2060, 'height' => 3144, 'hash' => hash('sha256', 'overlay-data')],
                    markerMatches: ['quarter_1' => 0.88, 'quarter_2' => 0.88, 'quarter_3' => 0.88, 'quarter_4' => 0.88],
                    fieldBlockShifts: [],
                    bubbleMeans: [],
                    itemEvidence: [],
                    rectification: [
                        'attempted' => true,
                        'applied' => true,
                        'detector_version' => 'nexiam.rectifier.v1',
                        'candidate_count' => 1,
                        'selected_candidate_index' => 0,
                        'selection_reason' => 'unique_four_marker_pass',
                        'homography_hash' => 'sha256:99887766',
                    ],
                );
            }
        };

        $preflight = new OmrCapturePreflight(OmrCapturePreflightLimits::fromConfig());
        $action = new TestCaptureOmrTemplate($fakeDetection, new OmrDiagnosticRequestBuilder, $preflight);
        $result = $action->handle($version, $this->omrTestCaptureImageBytes(), 'image/png', $actor);
        $this->assertTrue($result->succeeded());
        $this->assertNotNull($result->rectification);
        $this->assertTrue($result->rectification['applied']);
        $this->assertSame('sha256:99887766', $result->rectification['homography_hash']);
    }

    public function test_test_capture_action_and_controller_preserve_rectification_on_failure(): void
    {
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $blueprint = [
            'schema' => 'nexiam.omr.layout-blueprint.v1',
            'paper' => 'long',
            'columns' => 6,
            'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
            'markers' => ['enabled' => true],
            'sections' => [
                [
                    'area_code' => 'PROFILE_VERBAL',
                    'question_from' => 1,
                    'question_to' => 25,
                    'option_count' => 4,
                    'break_before' => null,
                ],
            ],
        ];
        $version = app(SaveAuthoredTemplateDraft::class)->handle(
            'SYN-OMR-TEST-CAP-FAIL',
            'Test capture template failure diag',
            [],
            $actor,
            null,
            true,
            $blueprint,
        );
        $rectification = [
            'attempted' => true,
            'applied' => false,
            'detector_version' => 'nexiam.rectifier.v1',
            'candidate_count' => 1,
            'valid_candidate_count' => 0,
            'reason' => 'no_valid_candidate',
            'raw_marker_matches' => ['quarter_1' => 0.12, 'quarter_2' => 0.15, 'quarter_3' => null, 'quarter_4' => null],
            'duration_ms' => 42.1,
        ];
        $fakeDetection = new class($rectification) implements OmrLayoutVerificationPort
        {
            public function __construct(private readonly array $rectification) {}

            public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
            {
                return OmrLayoutVerificationResult::failed(
                    'marker_missing',
                    'One or more alignment markers could not be detected.',
                    $this->rectification,
                );
            }
        };

        $preflight = new OmrCapturePreflight(OmrCapturePreflightLimits::fromConfig());
        $action = new TestCaptureOmrTemplate($fakeDetection, new OmrDiagnosticRequestBuilder, $preflight);
        $result = $action->handle($version, $this->omrTestCaptureImageBytes(), 'image/png', $actor);
        $this->assertFalse($result->succeeded());
        $this->assertSame('marker_missing', $result->code);
        $this->assertSame($rectification, $result->rectification);

        $controller = app(OmrConfigurationController::class);
        $uploadedFile = UploadedFile::fake()->createWithContent(
            'sheet.png',
            $this->omrTestCaptureImageBytes(),
            'image/png'
        );
        $request = Request::create(
            route('setup.omr.templates.test_capture', $version),
            'POST',
            [],
            [],
            ['image' => $uploadedFile],
        );
        $request->setUserResolver(fn () => $actor);

        $response = $controller->testCapture(
            $request,
            app(CapabilityAuthorizer::class),
            $action,
            $version,
        );

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('failed', $data['outcome']);
        $this->assertSame('marker_missing', $data['code']);
        $this->assertSame($rectification, $data['rectification']);
    }
}
