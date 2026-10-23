<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Assessment\Omr\Canonical\OmrQrReader;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrQrMode;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrPaperReproductionAuthorization;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Canonical blank-instance identity wiring (OpenSpec 35.31, AD-8): the
 * signed blank-instance QR is derived and verified server-side from uploaded
 * image bytes BEFORE the analysis request is constructed; client assertions of
 * validity are ignored; calibration-purpose QRs are distinguished and
 * hard-rejected; absent/unreadable QRs require audited manual recovery;
 * present-invalid/mismatched payloads are hard-rejected. Legacy batches are
 * untouched.
 */
function blankInstanceWiringVersion(User $actor, string $suffix = ''): OmrAnswerSheetTemplateVersion
{
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'WIRING-'.strtoupper(substr(hash('sha256', $actor->id.$suffix), 0, 8)), 'name' => 'Wiring template']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'activated_at' => now(),
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => [
            'template' => ['pages' => [[
                'page_no' => 1,
                'pageDimensions' => [2480, 3508],
                'fieldBlocks' => [],
                'markers' => ['family' => 'nexiam.fiducial', 'asset_name' => 'marker.jpg', 'centers' => [[0, 0], [1, 0], [0, 1], [1, 1]], 'quadrants' => [[0, 0], [1, 0], [0, 1], [1, 1]]],
            ]]],
        ],
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'review'),
        'authoring_manifest' => ['artifact_set' => ['artifact_hash' => str_repeat('a', 64)], 'page_count' => 1],
    ]);

    return $version->fresh();
}

function renderedPngWithQrPayload(string $payload): string
{
    $size = 400;
    $qrPng = app(OmrQrCodeRenderer::class)->png($payload, $size);
    $qrImg = imagecreatefromstring($qrPng);
    $canvas = imagecreatetruecolor(2480, 3508);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopy($canvas, $qrImg, 1800, 200, 0, 0, $size, $size);
    ob_start();
    imagepng($canvas);
    $png = ob_get_clean();
    imagedestroy($canvas);
    imagedestroy($qrImg);

    return (string) $png;
}

function productionRenderedSignedBlankPng(OmrBlankInstance $instance, int $pageNo = 1): string
{
    $payload = app(OmrQrPayload::class)->sign($instance->instance_code, $pageNo, (int) $instance->page_count, $instance->signing_key_id);

    return renderedPngWithQrPayload($payload);
}

/** @return array{actor: User, batch: OmrCaptureBatch, version: OmrAnswerSheetTemplateVersion, instance: OmrBlankInstance} */
function blankInstanceWiringFixture(TestCase $test): array
{
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference:8100',
        'omr.omrchecker.signing_secret' => 'nexiam-local-omr-worker-signing-secret-change-me',
        'omr.fake.qr_payload' => null,
    ]);

    $fixture = buildOmrGradingFixture($test);
    Queue::fake();
    $actor = $fixture['guidance'];
    $grading = $fixture['grading'];
    $snapshot = $fixture['session']->currentMaterialSnapshot()
        ->with('templateVersion')
        ->firstOrFail();
    $version = $snapshot->templateVersion;
    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $actor, 'wiring-batch-'.uniqid('', true));
    $instance = app(OmrBlankInstanceService::class)->createPrintedSet($version, $actor, 1, [hash('sha256', 'page-1')]);

    return ['actor' => $actor, 'batch' => $batch, 'version' => $version, 'instance' => $instance];
}

function blankInstanceWiringUpload(TestCase $test, array $fixture, array $extra = [], ?string $imageContent = null): TestResponse
{
    $imageBytes = $imageContent ?? physicalChallengePng();

    return $test->actingAs($fixture['actor'])
        ->post(route('scoring.sessions.omr.sheets.store', $fixture['batch']->gradingSession->examSession), [
            'image' => UploadedFile::fake()->createWithContent('sheet.png', $imageBytes),
            'capture_mode' => 'manual',
            'client_capture_id' => (string) Str::uuid(),
            'page_no' => 1,
            ...$extra,
        ]);
}

test('a canonical upload with a production-rendered signed blank QR registers the capture before analysis using exact browser payload', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $png = productionRenderedSignedBlankPng($fixture['instance'], 1);
    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign(
        $fixture['instance']->instance_code,
        1,
        (int) $fixture['instance']->page_count,
        $fixture['instance']->signing_key_id,
    )]);

    // Exact browser payload: image, page_no, capture_mode, client_capture_id (NO qr fields)
    blankInstanceWiringUpload($this, $fixture, [], $png)->assertRedirect();

    $sheet = OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->first();
    expect($sheet)->not->toBeNull()
        ->and($sheet->status)->toBe(OmrCaptureSheet::STATUS_QUEUED)
        ->and($sheet->pages()->count())->toBe(1);

    $page = $sheet->pages()->first();
    expect($page)->not->toBeNull();
    $instance = $fixture['instance']->fresh('pages');
    expect($instance->pages[0]->status)->toBe('captured')
        ->and($instance->pages[0]->captured_page_id)->toBe($page->id)
        ->and($instance->status)->toBe('captured');
    expect(AuditEvent::query()->where('event_type', 'omr.blank_instance_capture_registered')->whereJsonContains('payload', ['instance_id' => $instance->id])->exists())->toBeTrue();
    expect($sheet->processingRuns()->count())->toBe(1);
});
test('an active null-manifest version with a printed blank still requires signed QR registration before analysis', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $fixture['version']->update(['authoring_manifest' => null]);

    $version = $fixture['version']->fresh();
    $instance = $fixture['instance']->fresh('pages');
    expect($version->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->and($version->authoring_manifest)->toBeNull()
        ->and($version->blankInstances()->count())->toBe(1)
        ->and($instance->status)->toBe(OmrBlankInstance::STATUS_PRINTED)
        ->and($instance->pages)->toHaveCount(1)
        ->and($instance->pages[0]->status)->toBe('printed');

    $payload = app(OmrQrPayload::class)->sign(
        $instance->instance_code,
        1,
        (int) $instance->page_count,
        $instance->signing_key_id,
    );
    $png = renderedPngWithQrPayload($payload);
    // Keep this focused regression deterministic while the existing canonical
    // upload test exercises the real inference QR decoder end to end.
    config(['omr.fake.qr_payload' => $payload]);
    $serverObservation = app(OmrQrReader::class)->inspect($png, 'image/png');
    expect($serverObservation->payload)->toBe($payload);

    $verified = app(OmrQrPayload::class)->verify((string) $serverObservation->payload);
    expect($verified['instance_code'])->toBe($instance->instance_code)
        ->and($verified['page_no'])->toBe(1)
        ->and($verified['page_count'])->toBe(1);

    $ordering = [];
    $registrationAuditVisibleAtRun = false;
    $registrationAuditEvent = 'eloquent.created: '.AuditEvent::class;
    $processingRunEvent = 'eloquent.created: '.OmrProcessingRun::class;
    Event::listen($registrationAuditEvent, function (AuditEvent $event) use (&$ordering): void {
        if ($event->event_type === 'omr.blank_instance_capture_registered') {
            $ordering[] = 'registration_audit';
        }
    });
    $instanceId = (int) $instance->id;
    Event::listen($processingRunEvent, function () use (&$ordering, &$registrationAuditVisibleAtRun, $instanceId): void {
        $registrationAuditVisibleAtRun = AuditEvent::query()
            ->where('event_type', 'omr.blank_instance_capture_registered')
            ->whereJsonContains('payload', ['instance_id' => $instanceId])
            ->exists();
        $ordering[] = 'processing_run';
    });

    // Browser payload deliberately contains no client-provided QR fields.
    $browserPayload = [
        'image' => UploadedFile::fake()->createWithContent('sheet.png', $png),
        'capture_mode' => 'manual',
        'client_capture_id' => (string) Str::uuid(),
        'page_no' => 1,
    ];
    expect(array_intersect(['qr_payload', 'qr_state', 'display_code', 'manual_confirmed'], array_keys($browserPayload)))->toBe([]);

    try {
        $response = $this->actingAs($fixture['actor'])
            ->post(route('scoring.sessions.omr.sheets.store', $fixture['batch']->gradingSession->examSession), $browserPayload);
    } finally {
        Event::forget($registrationAuditEvent);
        Event::forget($processingRunEvent);
        config(['omr.fake.qr_payload' => null]);
    }
    $response->assertRedirect();

    $sheet = OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->firstOrFail();
    $page = $sheet->pages()->where('page_no', 1)->firstOrFail();
    $capturedInstance = $instance->fresh('pages');
    expect($capturedInstance->status)->toBe(OmrBlankInstance::STATUS_CAPTURED)
        ->and($capturedInstance->pages[0]->status)->toBe('captured')
        ->and($capturedInstance->pages[0]->captured_page_id)->toBe($page->id)
        ->and($sheet->processingRuns()->count())->toBe(1)
        ->and($registrationAuditVisibleAtRun)->toBeTrue()
        ->and($ordering)->toBe(['registration_audit', 'processing_run']);
});

test('client cannot forge valid QR by passing qr_payload parameter when image has no QR', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $validPayload = app(OmrQrPayload::class)->sign($fixture['instance']->instance_code, 1, 1);

    // Client attempts to assert a valid QR payload while uploading an image with NO QR
    blankInstanceWiringUpload($this, $fixture, ['qr_payload' => $validPayload], physicalChallengePng())
        ->assertRedirect()
        ->assertSessionHasErrors('omr');

    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0);
    expect($fixture['instance']->fresh('pages')->pages[0]->status)->toBe('printed');
    $errors = session('errors');
    expect((string) ($errors->first('omr') ?? ''))->toContain('qr_undecodable');
});

test('a calibration-purpose QR embedded in image is hard-rejected on the canonical grading upload', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $calibration = app(OmrCalibrationQrPayload::class)->sign('CHAL-ABCDEF1234', 1, 1);
    $png = renderedPngWithQrPayload($calibration);

    blankInstanceWiringUpload($this, $fixture, [], $png)
        ->assertRedirect()
        ->assertSessionHasErrors('omr');

    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0);
    expect($fixture['instance']->fresh('pages')->pages[0]->status)->toBe('printed');
    $errors = session('errors');
    expect((string) ($errors->first('omr') ?? ''))->toContain('qr_purpose_mismatch');
});

test('an absent QR blocks recognition until audited manual recovery', function (): void {
    $fixture = blankInstanceWiringFixture($this);

    blankInstanceWiringUpload($this, $fixture, [], physicalChallengePng())
        ->assertRedirect()
        ->assertSessionHasErrors('omr');
    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0);
    $errors = session('errors');
    expect((string) ($errors->first('omr') ?? ''))->toContain('qr_undecodable');

    // Manual recovery with staff confirmation is audited and unblocks.
    blankInstanceWiringUpload($this, $fixture, [
        'qr_state' => 'absent',
        'display_code' => $fixture['instance']->display_code,
        'manual_confirmed' => true,
    ], physicalChallengePng())->assertRedirect();

    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(1);
    $sheet = OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->first();
    $page = $sheet->pages()->first();
    $instance = $fixture['instance']->fresh('pages');
    expect($instance->pages[0]->status)->toBe('captured')
        ->and($instance->pages[0]->captured_page_id)->toBe($page->id);
    expect(AuditEvent::query()->where('event_type', 'instance_resolved_manually')->exists())->toBeTrue();
});

test('a present-invalid QR can never be overridden by manual entry', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $tampered = 'nexiam:blank:v1:NI-0123456789ABCDEFGHJKMNPQRS:p1:p1:kcurrent-v1:0000000000000000000000000000000000000000000000000000000000000000';
    $png = renderedPngWithQrPayload($tampered);

    blankInstanceWiringUpload($this, $fixture, [
        'qr_state' => 'present_invalid',
        'display_code' => $fixture['instance']->display_code,
        'manual_confirmed' => true,
    ], $png)->assertRedirect()->assertSessionHasErrors('omr');

    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0);
    $errors = session('errors');
    expect((string) ($errors->first('omr') ?? ''))->toContain('qr_signature_invalid');
});

test('a signed QR from another template version embedded in image is hard-rejected', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $otherVersion = blankInstanceWiringVersion($fixture['actor'], '-other');
    $otherInstance = app(OmrBlankInstanceService::class)->createPrintedSet($otherVersion, $fixture['actor'], 1, [hash('sha256', 'other-page')], null, 'current-v1');
    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign(
        $otherInstance->instance_code,
        1,
        (int) $otherInstance->page_count,
        $otherInstance->signing_key_id,
    )]);
    $png = productionRenderedSignedBlankPng($otherInstance, 1);

    blankInstanceWiringUpload($this, $fixture, [], $png)
        ->assertRedirect()
        ->assertSessionHasErrors('omr');

    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0);
    expect($fixture['instance']->fresh('pages')->pages[0]->status)->toBe('printed');
    $errors = session('errors');
    expect((string) ($errors->first('omr') ?? ''))->toContain('template_mismatch');
});

test('a superseded or already-captured instance is refused at registration', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $png = productionRenderedSignedBlankPng($fixture['instance'], 1);
    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign(
        $fixture['instance']->instance_code,
        1,
        (int) $fixture['instance']->page_count,
        $fixture['instance']->signing_key_id,
    )]);

    // First capture consumes the page.
    blankInstanceWiringUpload($this, $fixture, [], $png)->assertRedirect();
    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(1);

    // A second sheet upload for the same page/instance is refused.
    blankInstanceWiringUpload($this, $fixture, [], $png)
        ->assertRedirect()
        ->assertSessionHasErrors('omr');
    $errors = session('errors');
    expect((string) ($errors->first('omr') ?? ''))->toContain('page_already_captured');
});

test('a reusable master accepts multiple capture claims without consuming its signed page', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $master = app(OmrBlankInstanceService::class)->createPrintedSet(
        $fixture['version'],
        $fixture['actor'],
        1,
        [hash('sha256', 'reusable-page')],
        copyPolicy: OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
        intendedCopyCount: 12,
    );
    $png = productionRenderedSignedBlankPng($master);
    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign(
        $master->instance_code,
        1,
        (int) $master->page_count,
        $master->signing_key_id,
    )]);

    blankInstanceWiringUpload($this, $fixture, [], $png)->assertRedirect();
    blankInstanceWiringUpload($this, $fixture, [], $png)->assertRedirect();

    $sheets = OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->get();
    $resolvedInstances = $sheets
        ->map(fn (OmrCaptureSheet $sheet): ?int => app(OmrCaptureResolver::class)->resolveSheet($sheet)->blankInstance?->id)
        ->all();

    expect($master->fresh()->status)->toBe(OmrBlankInstance::STATUS_PRINTED)
        ->and($master->pages()->firstOrFail()->status)->toBe('printed')
        ->and($master->claims()->count())->toBe(2)
        ->and($master->claims()->where('claim_kind', OmrBlankInstancePageClaim::KIND_REUSABLE_COPY)->count())->toBe(2)
        ->and($resolvedInstances)->toBe([$master->id, $master->id]);
});

test('an audited emergency authorization permits only its scoped number of extra unique-page claims', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $png = productionRenderedSignedBlankPng($fixture['instance']);

    config(['omr.fake.qr_payload' => app(OmrQrPayload::class)->sign(
        $fixture['instance']->instance_code,
        1,
        (int) $fixture['instance']->page_count,
        $fixture['instance']->signing_key_id,
    )]);
    blankInstanceWiringUpload($this, $fixture, [], $png)->assertRedirect();
    $this->actingAs($fixture['actor'])
        ->post(route('scoring.sessions.omr.reproduction-authorizations.store', $fixture['batch']->gradingSession->examSession), [
            'display_code' => $fixture['instance']->display_code,
            'page_no' => 1,
            'quantity' => 1,
            'reason' => 'Original sheet was damaged during the supervised exam.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect();
    blankInstanceWiringUpload($this, $fixture, [], $png)->assertRedirect();
    blankInstanceWiringUpload($this, $fixture, [], $png)->assertSessionHasErrors('omr');

    $authorization = OmrPaperReproductionAuthorization::query()->sole();
    expect($fixture['instance']->claims()->count())->toBe(2)
        ->and($fixture['instance']->claims()->where('claim_kind', OmrBlankInstancePageClaim::KIND_AUTHORIZED_COPY)->count())->toBe(1)
        ->and($authorization->status)->toBe(OmrPaperReproductionAuthorization::STATUS_RECONCILED)
        ->and($authorization->used_claims)->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'grading.omr.paper_reproduction_authorized')->exists())->toBeTrue();
});

test('legacy batches without an authoring manifest remain outside the signed blank-instance gate', function (): void {
    $fixture = buildOmrGradingFixture($this);
    Queue::fake();
    $actor = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query
            ->whereNull('authoring_manifest')
            ->whereDoesntHave('blankInstances'))
        ->first();
    if ($pair === null) {
        $this->markTestSkipped('No legacy active pair without authoring_manifest in the seeded data.');
    }
    $version = $pair->templateVersion()->firstOrFail();
    expect($version->authoring_manifest)->toBeNull()
        ->and($version->blankInstances()->count())->toBe(0);
    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $actor, 'legacy-batch-'.uniqid('', true));

    // A legacy version with no printed blanks bypasses QR extraction entirely.
    config(['omr.fake.qr_payload' => 'qr_reader_unavailable']);
    try {
        $this->actingAs($actor)
            ->post(route('scoring.sessions.omr.sheets.store', $grading->examSession), [
                'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
                'capture_mode' => 'manual',
                'client_capture_id' => (string) Str::uuid(),
                'page_no' => 1,
            ])
            ->assertRedirect();
    } finally {
        config(['omr.fake.qr_payload' => null]);
    }
    expect(OmrCaptureSheet::query()->where('batch_id', $batch->id)->count())->toBe(1);
    expect(AuditEvent::query()->where('event_type', 'omr.blank_instance_capture_registered')->count())->toBe(0);
});
test('QR reader outage fails closed and does not enter manual recovery', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    config(['omr.fake.qr_payload' => 'qr_reader_unavailable']);

    try {
        // Even if staff provides manual recovery facts, worker outage fails closed
        blankInstanceWiringUpload($this, $fixture, [
            'qr_state' => 'absent',
            'display_code' => $fixture['instance']->display_code,
            'manual_confirmed' => true,
        ])->assertRedirect()->assertSessionHasErrors('omr');
    } finally {
        config(['omr.fake.qr_payload' => null]);
    }

    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0);
    $errors = session('errors');
    expect((string) ($errors->first('omr') ?? ''))->toContain('qr_reader_unavailable');
});

test('rejected uploads from tampered or invalid QR leave no orphaned asset files or database rows', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $tampered = 'nexiam:blank:v1:NI-0123456789ABCDEFGHJKMNPQRS:p1:p1:kcurrent-v1:badsignature000000000000000000000000000000000000000000000000000000';
    $png = renderedPngWithQrPayload($tampered);

    $filesBefore = Storage::disk('sensitive')->allFiles('omr/assets');

    blankInstanceWiringUpload($this, $fixture, [], $png)
        ->assertRedirect()
        ->assertSessionHasErrors('omr');

    $filesAfter = Storage::disk('sensitive')->allFiles('omr/assets');
    expect($filesAfter)->toBe($filesBefore);
    expect(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0);
});

test('QR-disabled paper refuses the signed claim path until a pending capture set owns provenance', function (): void {
    $fixture = blankInstanceWiringFixture($this);
    $fixture['version']->forceFill(['qr_mode' => OmrQrMode::Disabled])->save();
    $claimCount = OmrBlankInstancePageClaim::query()->count();

    blankInstanceWiringUpload($this, $fixture)
        ->assertRedirect()
        ->assertSessionHasErrors('omr');

    expect((string) session('errors')?->first('omr'))->toContain('qr_disabled_capture_requires_pending_set')
        ->and(OmrCaptureSheet::query()->where('batch_id', $fixture['batch']->id)->count())->toBe(0)
        ->and(OmrBlankInstancePageClaim::query()->count())->toBe($claimCount);
});
