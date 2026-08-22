<?php

use App\Assessment\Actions\Omr\PreflightOmrCapture;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/*
 * Capture preflight backend (tasks 10.7/10.18): authoritative, read-only,
 * worker-backed registration preflight for the ordinary capture page.
 * Marker batches -> validated/rejected with typed codes + gates; scan batches
 * -> mode=scan not_applicable; nothing is mutated; capability-gated.
 */

function omrPreflightSections(): array
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

function omrPreflightMarkerDraft(string $code, User $actor): OmrAnswerSheetTemplateVersion
{
    return app(SaveAuthoredTemplateDraft::class)->handle(
        $code,
        'Preflight marker template',
        [],
        $actor,
        null,
        true,
        [
            'schema' => 'nexiam.omr.layout-blueprint.v1',
            'paper' => 'long',
            'columns' => 6,
            'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
            'markers' => ['enabled' => true],
            'sections' => omrPreflightSections(),
        ],
    );
}

function omrPreflightBooklet(string $code, User $actor): \App\Models\Assessment\OmrQuestionBookletVersion
{
    $questions = [];
    $position = 1;
    foreach (omrPreflightSections() as $section) {
        for ($no = 1; $no <= 25; $no++) {
            $questions[] = [
                'area_id' => (int) AptitudeArea::query()->where('code', $section['area_code'])->value('id'),
                'question_no' => $no,
                'position' => $position,
                'correct_option_code' => ['A', 'B', 'C', 'D'][($position - 1) % 4],
            ];
            $position++;
        }
    }

    return app(SaveAuthoredBookletVersion::class)->handle($code, 'Preflight key', $questions, $actor);
}

function omrPreflightBatch(int $gradingSessionId, int $templateVersionId, int $bookletVersionId, int $uploadedByUserId): OmrCaptureBatch
{
    return OmrCaptureBatch::query()->create([
        'grading_session_id' => $gradingSessionId,
        'template_version_id' => $templateVersionId,
        'booklet_version_id' => $bookletVersionId,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 0,
        'idempotency_key' => 'preflight-test-'.uniqid('', true),
        'uploaded_by_user_id' => $uploadedByUserId,
        'opened_by_user_id' => $uploadedByUserId,
        'opened_at' => now(),
    ]);
}

function omrPreflightImageBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

/** Builds an authenticated single-JSON preflight worker response. */
function omrPreflightWorkerResponse(string $runId, array $overrides = []): array
{
    $payload = array_merge([
        'contract_version' => 'nexiam.omr.detection.v2',
        'run_id' => $runId,
        'correlation_id' => 'omr:diagnostic:1',
        'outcome' => 'validated',
        'engine' => ['name' => 'OMRChecker', 'version' => 'de3f69828228', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
        'input_hash' => str_repeat('i', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'calibration_status' => 'unqualified',
        'image' => ['mime' => 'image/png', 'width' => 3000, 'height' => 4000],
        'marker_matches' => ['quarter_1' => 0.61, 'quarter_2' => 0.62, 'quarter_3' => 0.60, 'quarter_4' => 0.63],
        'declared_dimensions' => ['width' => 1638, 'height' => 2722],
        'measured_dimensions' => ['width' => 1642, 'height' => 2732],
        'orientation_hint' => 'upright',
        'timings' => ['preprocess_ms' => 11.0, 'preflight_ms' => 22.0],
    ], $overrides);

    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $resultHash]), (string) config('omr.omrchecker.signing_secret'));

    return [$resultBytes, [
        'Content-Type' => 'application/json',
        'X-Nexiam-Contract' => 'nexiam.omr.detection.v2',
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Signature' => $signature,
    ]];
}

function omrPreflightFakeWorker(array $responseOverrides = [], int $status = 200): void
{
    Http::fake(['*/preflight' => function ($request) use ($responseOverrides, $status) {
        $runId = (string) ($request->header('X-Nexiam-Run-Id')[0] ?? '');
        [$body, $headers] = omrPreflightWorkerResponse($runId, $responseOverrides);

        return Http::response($body, $status, $headers);
    }]);
}

test('marker batch preflight validates when every gate passes', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);

    $fixture = buildOmrGradingFixture($this);
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = omrPreflightMarkerDraft('SYN-OMR-PREFLIGHT-MARKER', $actor);
    $booklet = omrPreflightBooklet('SYN-OMR-PREFLIGHT-KEY', $actor);
    $batch = omrPreflightBatch((int) $fixture['grading']->id, (int) $template->id, (int) $booklet->id, (int) $actor->id);
    omrPreflightFakeWorker();

    $result = app(PreflightOmrCapture::class)->handle($batch->load('templateVersion'), omrPreflightImageBytes(), 'image/png', $actor);

    expect($result->mode)->toBe(OmrCapturePreflight::MODE_MARKER)
        ->and($result->outcome)->toBe(OmrCapturePreflight::OUTCOME_VALIDATED)
        ->and($result->code)->toBeNull()
        ->and($result->markerMatches)->toHaveCount(4)
        ->and($result->gates['marker_count'])->toBe('pass')
        ->and($result->gates['dimensions'])->toBe('pass')
        ->and($result->normalizedDimensions)->toBe(['width' => 1638, 'height' => 2722])
        ->and($result->submitAllowed)->toBeTrue();
});

test('marker batch preflight rejects missing markers with a typed code and hint', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);

    $fixture = buildOmrGradingFixture($this);
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = omrPreflightMarkerDraft('SYN-OMR-PREFLIGHT-MARKER-2', $actor);
    $booklet = omrPreflightBooklet('SYN-OMR-PREFLIGHT-KEY-2', $actor);
    $batch = omrPreflightBatch((int) $fixture['grading']->id, (int) $template->id, (int) $booklet->id, (int) $actor->id);

    Http::fake(['*/preflight' => Http::response(
        json_encode(['code' => 'marker_missing', 'detail' => 'No corner markers were detected in the photo. (orientation_hint=unknown)'], JSON_THROW_ON_ERROR),
        422,
        ['Content-Type' => 'application/json'],
    )]);

    $result = app(PreflightOmrCapture::class)->handle($batch->load('templateVersion'), omrPreflightImageBytes(), 'image/png', $actor);

    expect($result->outcome)->toBe(OmrCapturePreflight::OUTCOME_REJECTED)
        ->and($result->code)->toBe('marker_missing')
        ->and($result->orientationHint)->toBe('unknown')
        ->and($result->detail)->toContain('No corner markers')
        ->and($result->submitAllowed)->toBeFalse();
});

test('scan batch preflight is not applicable and never calls the worker', function (): void {
    $fixture = buildOmrGradingFixture($this); // seeds the scan pair
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $scanTemplate = OmrAnswerSheetTemplateVersion::query()
        ->whereHas('template', fn ($query) => $query->where('code', 'SYN-OMR-ONE-PAGE'))
        ->where('version', 4)
        ->firstOrFail();
    $scanBooklet = \App\Models\Assessment\OmrQuestionBookletVersion::query()
        ->whereHas('booklet', fn ($query) => $query->where('code', 'SYN-OMR-BOOKLET-A'))
        ->where('version', 4)
        ->firstOrFail();
    $batch = omrPreflightBatch((int) $fixture['grading']->id, (int) $scanTemplate->id, (int) $scanBooklet->id, (int) $fixture['guidance']->id);

    Http::fake(); // any worker call would fail the scan assertion below

    $result = app(PreflightOmrCapture::class)->handle($batch->load('templateVersion'), omrPreflightImageBytes(), 'image/png', $actor);

    expect($result->mode)->toBe(OmrCapturePreflight::MODE_SCAN)
        ->and($result->outcome)->toBe(OmrCapturePreflight::OUTCOME_NOT_APPLICABLE)
        ->and($result->submitAllowed)->toBeFalse()
        ->and($result->detail)->toContain('scan')
        ->and($result->markerMatches)->toBeNull();
});

test('preflight route requires the OMR scoring capability', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);

    $fixture = buildOmrGradingFixture($this);
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = omrPreflightMarkerDraft('SYN-OMR-PREFLIGHT-MARKER-3', $actor);
    $booklet = omrPreflightBooklet('SYN-OMR-PREFLIGHT-KEY-3', $actor);
    $batch = omrPreflightBatch((int) $fixture['grading']->id, (int) $template->id, (int) $booklet->id, (int) $actor->id);

    $upload = UploadedFile::fake()->createWithContent('sheet.png', omrPreflightImageBytes(), 'image/png');
    $this->actingAs($fixture['registrar'])
        ->postJson(route('grading.sessions.omr.batches.preflight', ['gradingSession' => $batch->grading_session_id, 'batch' => $batch->id]), ['image' => $upload])
        ->assertForbidden();
});

test('preflight route validates the marker gate and never mutates state', function (): void {
    seedE2Foundation();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);

    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = omrPreflightMarkerDraft('SYN-OMR-PREFLIGHT-MARKER-4', $actor);
    $booklet = omrPreflightBooklet('SYN-OMR-PREFLIGHT-KEY-4', $actor);
    $batch = omrPreflightBatch((int) $fixture['grading']->id, (int) $template->id, (int) $booklet->id, (int) $actor->id);
    omrPreflightFakeWorker();

    $before = [
        'batches' => OmrCaptureBatch::query()->count(),
        'sheets' => OmrCaptureSheet::query()->count(),
        'pairs' => OmrTemplateBookletPair::query()->count(),
        'template_status' => $template->fresh()->status,
    ];

    $upload = UploadedFile::fake()->createWithContent('sheet.png', omrPreflightImageBytes(), 'image/png');
    $response = $this->actingAs($guidance)
        ->postJson(route('grading.sessions.omr.batches.preflight', ['gradingSession' => $batch->grading_session_id, 'batch' => $batch->id]), ['image' => $upload]);

    $response->assertOk()
        ->assertJson([
            'mode' => 'marker',
            'outcome' => 'validated',
            'submit_allowed' => true,
            'calibration_status' => 'unqualified',
            'gates' => ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass'],
        ]);
    $json = $response->json();
    expect($json['marker_matches'])->toHaveCount(4)
        ->and($json['normalized_dimensions'])->toBe(['width' => 1638, 'height' => 2722])
        ->and($json['orientation_hint'])->toBe('upright');

    expect(OmrCaptureBatch::query()->count())->toBe($before['batches'])
        ->and(OmrCaptureSheet::query()->count())->toBe($before['sheets'])
        ->and(OmrTemplateBookletPair::query()->count())->toBe($before['pairs'])
        ->and($template->fresh()->status)->toBe($before['template_status']);
});
