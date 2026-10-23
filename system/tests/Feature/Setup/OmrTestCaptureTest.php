<?php

use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\TestCaptureOmrTemplate;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * Builder Test capture backend seam (tasks 10.5/10.6): read-only layout
 * verification against a marker-bearing template version through the
 * authenticated worker envelope. Marker-less versions are rejected; nothing
 * mutates activation, pairs, answer keys, or scores; failures are typed.
 */

function omrTestCaptureSections(): array
{
    $areas = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
    $sections = [];
    foreach ($areas as $index => $area) {
        $sections[] = [
            'area_code' => $area,
            'question_from' => 1,
            'question_to' => 25,
            'option_count' => 4,
            'break_before' => $index === 0 ? null : 'next_column',
        ];
    }

    return $sections;
}

function omrTestCaptureBlueprint(bool $markers = true): array
{
    return [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 6,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'markers' => $markers ? ['enabled' => true] : null,
        'sections' => omrTestCaptureSections(),
    ];
}

/** Extract the request manifest from a signed multipart worker call. */
function omrTestCaptureManifestFromBody(string $body): array
{
    if (! str_contains($body, 'name="manifest"')) {
        return [];
    }

    $parts = preg_split("/\r\n--[A-Za-z0-9._-]+/", $body);
    foreach ($parts as $part) {
        if (! str_contains($part, 'name="manifest"')) {
            continue;
        }
        [, $json] = explode("\r\n\r\n", $part, 2);
        $decoded = json_decode(trim($json), true);

        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

/** Builds an authenticated three-part layout-verification worker response. */
function omrTestCaptureWorkerResponse(string $runId, string $requestBody, array $overrides = []): array
{
    $manifest = omrTestCaptureManifestFromBody($requestBody);
    $targetIdentity = array_key_exists('template_version_id', $manifest)
        ? ['template_version_id' => $manifest['template_version_id']]
        : ['candidate_target_id' => $manifest['candidate_target_id']];
    $payload = array_merge([
        'contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'registration_mode' => $manifest['registration_mode'],
        ...$targetIdentity,
        'expected_page_no' => $manifest['expected_page_no'],
        'source_image' => $manifest['source_image'],
        'run_id' => $runId,
        'correlation_id' => $manifest['correlation_id'],
        'outcome' => 'succeeded',
        'engine' => [
            'name' => $manifest['engine']['name'],
            'version' => 'de3f69828228',
            'commit' => $manifest['engine']['commit'],
            'patch_identity' => $manifest['engine']['patch_identity'],
        ],
        'input_hash' => $manifest['input_hash'],
        'native_bundle_hash' => $manifest['native_bundle_hash'],
        'manifest_hash' => $manifest['manifest_hash'],
        'config_hash' => $manifest['config_hash'],
        'booklet_hash' => $manifest['booklet_hash'],
        'calibration_status' => 'unqualified',
        'image' => [
            'mime' => $manifest['image_mime'],
            'width' => $manifest['image_width'],
            'height' => $manifest['image_height'],
        ],
        'marker_matches' => ['quarter_1' => 0.61, 'quarter_2' => 0.62, 'quarter_3' => 0.60, 'quarter_4' => 0.63],
        'aligned_image' => ['mime' => 'image/png', 'width' => 1638, 'height' => 2722],
        'layout_overlay' => ['mime' => 'image/png', 'width' => 1638, 'height' => 2722],
        'field_block_shifts' => [['block' => 'LOGICPartA', 'x' => 0, 'y' => 0]],
        'bubble_means' => ['LOGICPartA' => [['label' => 'LOGIC_Q_1', 'option' => 'A', 'mean' => 245.0]]],
        'timings' => ['preprocess_ms' => 12.5, 'verification_ms' => 30.0],
    ], $overrides);

    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $raster = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $boundary = 'nexiam-test-capture';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"layout-overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."--\r\n";

    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $raster);
    $overlayHash = hash('sha256', $raster);
    $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $resultHash, $alignedHash, $overlayHash]), (string) config('omr.omrchecker.signing_secret'));

    return [$body, [
        'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        'X-Nexiam-Contract' => OmrDetectionRequest::ENVELOPE_VERSION,
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Aligned-SHA256' => $alignedHash,
        'X-Nexiam-Layout-Overlay-SHA256' => $overlayHash,
        'X-Nexiam-Signature' => $signature,
    ]];
}

function omrTestCaptureImageBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

beforeEach(function (): void {
    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => str_repeat('e', 40),
        'omr.omrchecker.patch_identity' => 'nexiam-headless-overlay-v1',
    ]);
});
test('configured auxiliary v3 worker can serve the v4 detection adapter without accepting unknown contracts', function (): void {
    config(['omr.omrchecker.contract_version' => 'nexiam.omr.detection.v3']);
    expect(app(OmrDetectionPort::class))->toBeInstanceOf(OmrCheckerDetection::class);

    app()->forgetInstance(OmrDetectionPort::class);
    config(['omr.omrchecker.contract_version' => 'nexiam.omr.detection.v2']);
    expect(fn () => app(OmrDetectionPort::class))->toThrow(RuntimeException::class, 'incomplete or disabled');
});

test('test capture runs the worker diagnostic and computes gates', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-TEST-CAP',
        'Test capture template',
        [],
        $actor,
        null,
        true,
        omrTestCaptureBlueprint(),
    );

    Http::fake(['*/layout-verification' => function ($request) {
        // The envelope carries the run id in a header; echo it back like the
        // real worker does.
        [$body, $headers] = omrTestCaptureWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $request->body());

        return Http::response($body, 200, $headers);
    }]);

    $result = app(TestCaptureOmrTemplate::class)->handle($version->fresh(), omrTestCaptureImageBytes(), 'image/png', $actor);

    expect($result->succeeded())->toBeTrue()
        ->and($result->alignedBytes)->not->toBeNull()
        ->and($result->overlayBytes)->not->toBeNull()
        ->and($result->markerMatches)->toHaveCount(4)
        ->and((float) $result->bubbleMeans['LOGICPartA'][0]['mean'])->toBe(245.0)
        ->and($result->gates['marker_count'])->toBe('pass')
        ->and($result->gates['dimensions'])->toBe('pass')
        ->and($result->gates['shifts'])->toBe('pass')
        ->and($result->gates['unsafe_blocks'])->toBe([]);
});

test('test capture resolves the canonical layout adapter through its port', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-TEST-CAP-PORT',
        'Test capture port template',
        [],
        $actor,
        null,
        true,
        omrTestCaptureBlueprint(),
    );
    $layout = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $frame = (array) ($request->nativeTemplate['pageDimensions'] ?? [1, 1]);

            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                alignedImage: ['mime' => 'image/png', 'width' => (int) ($frame[0] ?? 1), 'height' => (int) ($frame[1] ?? 1)],
                markerMatches: ['quarter_1' => 0.9, 'quarter_2' => 0.9, 'quarter_3' => 0.9, 'quarter_4' => 0.9],
                fieldBlockShifts: [],
            );
        }
    };
    app()->instance(OmrLayoutVerificationPort::class, $layout);

    $result = app(TestCaptureOmrTemplate::class)->handle($version->fresh(), omrTestCaptureImageBytes(), 'image/png', $actor);

    expect($result->succeeded())->toBeTrue()
        ->and($result->gates)->toMatchArray([
            'marker_count' => 'pass',
            'registration' => 'pass',
            'dimensions' => 'pass',
            'shifts' => 'pass',
        ]);
});

test('test capture fails its shift gate when layout evidence crosses an option pitch', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-TEST-CAP-SHIFT',
        'Test capture shift template',
        [],
        $actor,
        null,
        true,
        omrTestCaptureBlueprint(),
    );
    $request = app(OmrDiagnosticRequestBuilder::class)
        ->build($version->fresh(), omrTestCaptureImageBytes(), 'image/png');
    expect($request->nativeBundleMetadata['rectification_marker_insets_px'] ?? null)
        ->toBeArray()
        ->toHaveKeys(['top', 'right', 'bottom', 'left']);
    $blocks = (array) ($request->nativeTemplate['fieldBlocks'] ?? []);
    $mcqBlocks = array_filter($blocks, static fn (mixed $candidate): bool => is_array($candidate) && ($candidate['fieldType'] ?? null) === 'QTYPE_MCQ4');
    $block = (string) array_key_first($mcqBlocks);

    Http::fake(['*/layout-verification' => function ($sent) use ($block) {
        [$body, $headers] = omrTestCaptureWorkerResponse(
            (string) ($sent->header('X-Nexiam-Run-Id')[0] ?? ''),
            $sent->body(),
            ['field_block_shifts' => [['block' => $block, 'x' => 10000, 'y' => 0]]],
        );

        return Http::response($body, 200, $headers);
    }]);

    $result = app(TestCaptureOmrTemplate::class)->handle($version->fresh(), omrTestCaptureImageBytes(), 'image/png', $actor);

    expect($result->succeeded())->toBeTrue()
        ->and($result->gates['shifts'])->toBe('fail')
        ->and($result->gates['unsafe_blocks'])->toBe([$block]);
});

test('test capture rejects marker-less versions with a typed error', function (): void {
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-TEST-SCAN',
        'Scan-only template',
        [],
        $actor,
        null,
        true,
        omrTestCaptureBlueprint(markers: false),
    );

    expect(fn () => app(TestCaptureOmrTemplate::class)->handle($version->fresh(), omrTestCaptureImageBytes(), 'image/png', $actor))
        ->toThrow(RuntimeException::class, 'compiled_version_marker_contract_missing');
});

test('test capture maps worker typed failures without mutating state', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-TEST-CAP-2',
        'Test capture failure template',
        [],
        $actor,
        null,
        true,
        omrTestCaptureBlueprint(),
    );

    Http::fake(['*/layout-verification' => Http::response(json_encode(['code' => 'marker_missing', 'detail' => 'No corner markers were detected in the photo.'], JSON_THROW_ON_ERROR), 422, ['Content-Type' => 'application/json'])]);

    $before = [
        'pairs' => OmrTemplateBookletPair::query()->count(),
        'active' => OmrTemplateBookletPair::query()->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->count(),
    ];

    $result = app(TestCaptureOmrTemplate::class)->handle($version->fresh(), omrTestCaptureImageBytes(), 'image/png', $actor);

    expect($result->succeeded())->toBeFalse()
        ->and($result->code)->toBe('marker_missing')
        ->and($result->detail)->toContain('No corner markers')
        ->and(OmrTemplateBookletPair::query()->count())->toBe($before['pairs'])
        ->and(OmrTemplateBookletPair::query()->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->count())->toBe($before['active'])
        ->and($version->fresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_DRAFT);
});

test('test capture route serves the diagnostic JSON and streams stored rasters', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    Storage::fake('local');

    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-TEST-CAP-3',
        'Test capture route template',
        [],
        $actor,
        null,
        true,
        omrTestCaptureBlueprint(),
    );

    Http::fake(['*/layout-verification' => function ($request) {
        // The envelope carries the run id in a header; echo it back like the
        // real worker does.
        [$body, $headers] = omrTestCaptureWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $request->body());

        return Http::response($body, 200, $headers);
    }]);

    $upload = UploadedFile::fake()->createWithContent('sheet.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true), 'image/png');
    $response = $this->actingAs($guidance)
        ->postJson(route('setup.omr.templates.test_capture', $version), ['image' => $upload]);

    $response->assertOk()
        ->assertJson([
            'outcome' => 'succeeded',
            'calibration_status' => 'unqualified',
            'gates' => ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
        ]);
    $json = $response->json();
    expect($json['aligned_url'])->toBeString()
        ->and($json['overlay_url'])->toBeString()
        ->and($json['marker_matches'])->toHaveCount(4)
        ->and((float) $json['bubble_means']['LOGICPartA'][0]['mean'])->toBe(245.0);

    $this->actingAs($guidance)->get($json['aligned_url'])->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($guidance)->get($json['overlay_url'])->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('test capture route rejects marker-less versions before any worker call', function (): void {
    seedE2Foundation();

    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-OMR-TEST-SCAN-2',
        'Scan-only route template',
        [],
        $actor,
        null,
        true,
        omrTestCaptureBlueprint(markers: false),
    );

    Http::fake(); // any worker call would fail the test

    $upload = UploadedFile::fake()->createWithContent('sheet.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true), 'image/png');
    $this->actingAs($guidance)
        ->postJson(route('setup.omr.templates.test_capture', $version), ['image' => $upload])
        ->assertStatus(422)
        ->assertJsonPath('error', fn (string $error): bool => str_contains($error, 'compiled_version_marker_contract_missing'));
    Http::assertNothingSent();
});
