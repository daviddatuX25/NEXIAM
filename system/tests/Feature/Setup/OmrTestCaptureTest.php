<?php

use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\TestCaptureOmrTemplate;
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

/** Builds an authenticated three-part layout-verification worker response. */
function omrTestCaptureWorkerResponse(string $runId, array $overrides = []): array
{
    $payload = array_merge([
        'contract_version' => 'nexiam.omr.detection.v2',
        'run_id' => $runId,
        'correlation_id' => 'omr:test-capture:1',
        'outcome' => 'succeeded',
        'engine' => ['name' => 'OMRChecker', 'version' => 'de3f69828228', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
        'input_hash' => str_repeat('i', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'calibration_status' => 'unqualified',
        'image' => ['mime' => 'image/png', 'width' => 3000, 'height' => 4000],
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
        'X-Nexiam-Contract' => 'nexiam.omr.detection.v2',
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
        [$body, $headers] = omrTestCaptureWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''));

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
        ->toThrow(RuntimeException::class, 'marker-bearing');
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
        ->and($version->fresh()->status)->toBe(\App\Models\Assessment\OmrAnswerSheetTemplateVersion::STATUS_DRAFT);
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
        [$body, $headers] = omrTestCaptureWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''));

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
        ->assertJsonPath('error', fn (string $error): bool => str_contains($error, 'marker-bearing'));
});
