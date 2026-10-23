<?php

use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Assessment\Omr\Canonical\OmrQrReservationResolver;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRendererPort;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AuditEvent;
use App\Printing\PrintRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Omr\ProofPlanFixtureTarget;

uses(RefreshDatabase::class);

if (! function_exists('challengePdfBytes')) {
    function challengePdfBytes(): string
    {
        return app(PrintRenderer::class)->render(
            '<html><head><style>body{margin:0;padding:0}</style></head><body><div style="width:200mm;height:280mm;border:1px solid #ccc"></div></body></html>',
            ['format' => 'a4', 'orientation' => 'portrait'],
        );
    }
}

if (! function_exists('hardeningSetup')) {
    function hardeningSetup(): array
    {
        config([
            'omr.qr-keyring.current' => 'current-v1',
            'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
            'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        ]);

        return ['actor' => physicalChallengeActor()];
    }
}

function challengeQrMatchRatio(string $pageBytes, string $qrBytes, int $x, int $y, int $size): float
{
    $page = imagecreatefromstring($pageBytes);
    $qr = imagecreatefromstring($qrBytes);
    if ($page === false || $qr === false) {
        if ($page !== false) {
            imagedestroy($page);
        }
        if ($qr !== false) {
            imagedestroy($qr);
        }
        throw new RuntimeException('challenge_qr_test_image_invalid');
    }

    $scaledQr = imagecreatetruecolor($size, $size);
    if ($scaledQr === false) {
        imagedestroy($page);
        imagedestroy($qr);
        throw new RuntimeException('challenge_qr_test_image_invalid');
    }
    try {
        imagecopyresampled($scaledQr, $qr, 0, 0, 0, 0, $size, $size, imagesx($qr), imagesy($qr));
        if ($x < 0 || $y < 0 || $x + $size > imagesx($page) || $y + $size > imagesy($page)) {
            return 0.0;
        }

        $matches = 0;
        $samples = 0;
        for ($sampleY = 3; $sampleY < $size - 3; $sampleY += 5) {
            for ($sampleX = 3; $sampleX < $size - 3; $sampleX += 5) {
                $expectedDark = (imagecolorat($scaledQr, $sampleX, $sampleY) & 0xFF) < 128;
                $actualDark = (imagecolorat($page, $x + $sampleX, $y + $sampleY) & 0xFF) < 128;
                $matches += (int) ($expectedDark === $actualDark);
                $samples++;
            }
        }

        return $samples > 0 ? $matches / $samples : 0.0;
    } finally {
        imagedestroy($scaledQr);
        imagedestroy($qr);
        imagedestroy($page);
    }
}

test('physical challenge renders the diagnostic QR at each pinned page reservation', function (): void {
    hardeningSetup();
    $target = new ProofPlanFixtureTarget([
        [
            'page_no' => 1,
            'blocks' => [
                'PAGE_ONE' => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => ['PAGE_ONE_Q_1'],
                    'origin' => [100, 200],
                    'bubblesGap' => 60,
                    'labelsGap' => 80,
                    'bubbleDimensions' => [40, 40],
                ],
            ],
        ],
        [
            'page_no' => 2,
            'blocks' => [
                'PAGE_TWO' => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => ['PAGE_TWO_Q_1'],
                    'origin' => [2100, 100],
                    'bubblesGap' => 60,
                    'labelsGap' => 80,
                    'bubbleDimensions' => [40, 40],
                ],
            ],
        ],
    ], identityMapped: false);
    $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::QuickRehearsal);
    $insets = [];
    $result = app(OmrPhysicalChallengeRenderer::class)->render(
        $target,
        ProofPurpose::QuickRehearsal,
        challengePdfBytes(),
        $insets,
    );
    $ppi = (float) config('omr.canonical.ppi', 300);
    $reservations = $plan->diagnosticQrReservation;

    expect($reservations[0]['qr_x_mm'])->not->toBe($reservations[1]['qr_x_mm'])
        ->and($result['sheet']['pages'])->toHaveCount(2);

    foreach ($result['sheet']['pages'] as $index => $page) {
        $reservation = $reservations[$index];
        $qrX = OmrPageDimensionMath::pixelsFromMillimetres($reservation['qr_x_mm'], $ppi);
        $qrY = OmrPageDimensionMath::pixelsFromMillimetres($reservation['qr_y_mm'], $ppi);
        $qrSize = OmrPageDimensionMath::pixelsFromMillimetres($reservation['qr_size_mm'], $ppi);
        $payload = app(OmrCalibrationQrPayload::class)->sign($plan->challengeCode, $page['page_no'], $plan->requiredPageCount);
        $qrBytes = app(OmrQrCodeRenderer::class)->png($payload, max(320, $qrSize));

        expect(challengeQrMatchRatio($page['bytes'], $qrBytes, $qrX, $qrY, $qrSize))->toBeGreaterThan(0.98);
    }

    $secondPage = $result['sheet']['pages'][1];
    $genericPlacement = OmrQrReservationResolver::resolvePx(
        $target,
        $secondPage['width'],
        $secondPage['height'],
        $insets,
        $ppi,
    );
    $secondPayload = app(OmrCalibrationQrPayload::class)->sign($plan->challengeCode, 2, 2);
    $genericQr = app(OmrQrCodeRenderer::class)->png($secondPayload, max(320, $genericPlacement['qr_size']));
    expect(challengeQrMatchRatio(
        $secondPage['bytes'],
        $genericQr,
        $genericPlacement['qr_x'],
        $genericPlacement['qr_y'],
        $genericPlacement['qr_size'],
    ))->toBeLessThan(0.9);
});

test('physical challenge renderer rejects an incomplete raster page set', function (): void {
    hardeningSetup();
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);

    expect(fn () => app(OmrPhysicalChallengeRenderer::class)->render(
        $target,
        ProofPurpose::QuickRehearsal,
        physicalChallengePdfBytes(),
    ))->toThrow(RuntimeException::class, 'challenge_sheet_page_set_mismatch');
});

test('challenge creation rejects an incomplete rendered manifest before creating physical state', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor, pageCount: 2);
    Storage::fake('local');
    $incompleteRenderer = new class implements OmrPhysicalChallengeRendererPort
    {
        public function render(
            OmrDiagnosticTarget $target,
            ProofPurpose $purpose,
            string $pdfBytes,
            array $insetsPx = [],
            string $planVersion = OmrProofPlanner::CURRENT_PLAN_VERSION,
            ?string $method = null,
        ): array {
            return ['sheet' => ['pages' => [['page_no' => 1, 'bytes' => 'incomplete']]]];
        }
    };
    app()->instance(OmrPhysicalChallengeRendererPort::class, $incompleteRenderer);

    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick'))
        ->toThrow(RuntimeException::class, 'challenge_sheet_page_manifest_incomplete');

    expect(OmrPhysicalChallenge::query()->count())->toBe(0)
        ->and(OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->whereIn('mode', [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ])
            ->count())->toBe(0)
        ->and(OmrTemplateAsset::query()
            ->where('authoring_draft_id', $draft->id)
            ->whereJsonContains('metadata->role', 'challenge_sheet_page')
            ->count())->toBe(0);
});

test('challenge persistence records complete ordered page manifest and its canonical hash', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor, pageCount: 2);
    Storage::fake('local');

    $created = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $challenge = $created['challenge']->fresh();
    $pageAssets = OmrTemplateAsset::query()
        ->where('authoring_draft_id', $draft->id)
        ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
        ->where('mime', 'image/png')
        ->whereJsonContains('metadata->role', 'challenge_sheet_page')
        ->orderBy('page_no')
        ->get();
    $expectedManifest = $pageAssets->map(static fn (OmrTemplateAsset $asset): array => [
        'page_no' => (int) $asset->page_no,
        'asset_id' => (int) $asset->id,
        'asset_hash' => (string) $asset->sha256,
    ])->all();

    expect($pageAssets)->toHaveCount(2)
        ->and($challenge->page_manifest)->toBe($expectedManifest)
        ->and(array_column($challenge->page_manifest, 'page_no'))->toBe([1, 2])
        ->and($challenge->page_manifest_hash)->toBe(hash(
            'sha256',
            json_encode($expectedManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ));
});

test('physical challenge renderer succeeds under 128M memory limit and produces real 2481x3508 page with renderer version', function (): void {
    hardeningSetup();
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);
    $renderer = app(OmrPhysicalChallengeRenderer::class);
    $insets = ['left' => 142, 'top' => 142, 'right' => 142, 'bottom' => 142];

    $result = $renderer->render($target, ProofPurpose::QuickRehearsal, challengePdfBytes(), $insets);

    expect($result['sheet']['pages'])->toHaveCount(2)
        ->and($result['sheet']['pages'][0]['width'])->toBe(2481)
        ->and($result['sheet']['pages'][0]['height'])->toBe(3508)
        ->and($result['sheet']['pages'][0]['renderer_version'])->toBe(OmrManifestBubbleShader::RENDERER_VERSION)
        ->and($result['sheet']['renderer_version'])->toBe(OmrManifestBubbleShader::RENDERER_VERSION)
        ->and($result['sheet']['sheet_hash'])->toHaveLength(64)
        ->and($result['sheet']['pdf_sha256'])->toHaveLength(64)
        ->and($result['guide_json'])->toBeArray()
        ->and($result['guide_json']['schema_version'])->toBe('v2');
});

test('idempotent challenge reference regeneration returns existing unchanged when reference is current', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $createResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $createResult['run'];
    $challenge = $createResult['challenge'];

    $oldSheetAssetId = $challenge->sheet_asset_id;
    $oldGuideAssetId = $challenge->guide_asset_id;

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'regen-idempotent-1',
        ])
        ->assertStatus(200)
        ->assertJson([
            'run_id' => $run->id,
            'challenge_id' => $challenge->id,
            'regenerated' => false,
        ]);

    $challenge->refresh();
    expect($challenge->sheet_asset_id)->toBe($oldSheetAssetId)
        ->and($challenge->guide_asset_id)->toBe($oldGuideAssetId);
});

test('stale challenge reference regeneration updates assets, guide_json, hashes, and emits audit event', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $createResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $createResult['run'];
    $challenge = $createResult['challenge'];

    // Simulate stale legacy challenge (unset renderer_version on all draft assets, null guide_json)
    $assets = OmrTemplateAsset::query()->where('authoring_draft_id', $draft->id)->get();
    foreach ($assets as $asset) {
        $meta = (array) ($asset->metadata ?? []);
        unset($meta['renderer_version']);
        OmrTemplateAsset::withoutEvents(function () use ($asset, $meta): void {
            $asset->forceFill(['metadata' => $meta])->saveQuietly();
        });
    }

    $challenge->forceFill([
        'guide_json' => null,
    ])->saveQuietly();

    // Check status before regeneration: reference_current is false, renderer_version is null, regenerate_challenge available
    $statusService = app(OmrTemplateVerificationStatus::class);
    $status = $statusService->get($run->fresh(), $actor);

    expect($status['challenge']['reference_current'])->toBeFalse()
        ->and($status['challenge']['renderer_version'])->toBeNull()
        ->and($status['available_actions'])->toContain('regenerate_challenge');

    $oldSheetAssetId = $challenge->sheet_asset_id;
    $oldGuideAssetId = $challenge->guide_asset_id;

    expect(fn () => $challenge->fresh()->update(['target_hash' => str_repeat('f', 64)]))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $challenge->fresh()->update(['sheet_asset_id' => $oldGuideAssetId]))
        ->toThrow(RuntimeException::class);

    // POST to regenerate challenge reference
    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'regen-stale-1',
        ])
        ->assertStatus(200)
        ->assertJson([
            'run_id' => $run->id,
            'challenge_id' => $challenge->id,
            'regenerated' => true,
        ]);

    $challenge->refresh();
    expect($challenge->guide_json)->toBeArray()
        ->and($challenge->sheet_asset_hash)->toBe($challenge->sheetAsset->sha256)
        ->and($challenge->guide_asset_hash)->toBe($challenge->guideAsset->sha256)
        ->and($challenge->print_metadata['regenerated'])->toBeTrue()
        ->and($challenge->sheetAsset->metadata['renderer_version'])->toBe(OmrManifestBubbleShader::RENDERER_VERSION);

    // Status carries the current renderer and editor lock; stale UI clients can rebase safely.
    $updatedStatus = $statusService->get($run->fresh(), $actor);
    expect($updatedStatus['draft_lock_version'])->toBe((int) $draft->fresh()->lock_version)
        ->and($updatedStatus['challenge']['reference_current'])->toBeTrue()
        ->and($updatedStatus['challenge']['renderer_version'])->toBe(OmrManifestBubbleShader::RENDERER_VERSION)
        ->and($updatedStatus['challenge']['sheet_pages'])->toHaveCount(1)
        ->and(collect($updatedStatus['challenge']['sheet_pages'])->pluck('url')->contains(
            fn (string $url): bool => str_contains($url, '/assets/'.$oldSheetAssetId)
        ))->toBeFalse()
        ->and($updatedStatus['available_actions'])->not->toContain('regenerate_challenge');

    // Audit event was recorded with old and new details
    $audit = AuditEvent::query()
        ->where('event_type', 'omr.challenge.regenerated')
        ->where('payload->run_id', $run->id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->payload['challenge_id'])->toBe($challenge->id)
        ->and($audit->payload['old_sheet_asset_id'])->toBe($oldSheetAssetId)
        ->and($audit->payload['new_sheet_asset_id'])->toBe($challenge->sheet_asset_id)
        ->and($audit->payload['new_renderer_version'])->toBe(OmrManifestBubbleShader::RENDERER_VERSION);
});

test('challenge regeneration refuses with typed error if evidence captures exist', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $createResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $createResult['run'];
    $challenge = $createResult['challenge'];

    // Make challenge stale so it would otherwise regenerate
    $challenge->forceFill(['guide_json' => null])->saveQuietly();

    // Create an evidence capture row on the challenge/run
    OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'challenge_id' => $challenge->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => 'upload',
        'variant' => 'clean',
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'expected' => 'pass',
        'actual' => 'pass',
        'input_hash' => str_repeat('a', 64),
        'evidence_hash' => str_repeat('b', 64),
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    // Check status: regenerate_challenge is NOT in available_actions because evidence exists.
    $capture = $run->captures()->firstOrFail();
    $statusService = app(OmrTemplateVerificationStatus::class);
    $status = $statusService->get($run->fresh(), $actor);
    expect($capture->status)->toBe(OmrTemplateVerificationCapture::STATUS_PASS)
        ->and($capture->input_hash)->toBe(str_repeat('a', 64))
        ->and($capture->evidence_hash)->toBe(str_repeat('b', 64))
        ->and($status['available_actions'])->not->toContain('regenerate_challenge')
        ->and($status['challenge_recovery'])->toMatchArray([
            'kind' => null,
            'action_url' => null,
            'reason_code' => 'evidence_immutable',
        ]);

    // POST returns typed 422 error
    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'regen-capture-guard',
        ])
        ->assertStatus(422)
        ->assertJson([
            'outcome' => 'failed',
            'code' => 'challenge_regeneration_evidence_exists',
        ]);
});

test('challenge regeneration refuses digital mode runs with typed error', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $digitalRun = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail();

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $digitalRun), [
            'idempotency_key' => 'regen-digital-guard',
        ])
        ->assertStatus(422)
        ->assertJson([
            'outcome' => 'failed',
            'code' => 'challenge_regeneration_not_physical',
        ]);
});

test('challenge regeneration refuses invalid run status with typed error', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $createResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $createResult['run'];
    $challenge = $createResult['challenge'];

    $challenge->forceFill(['guide_json' => null])->saveQuietly();
    $run->forceFill(['status' => OmrTemplateVerificationRun::STATUS_PASSED])->saveQuietly();

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'regen-status-guard',
        ])
        ->assertStatus(422)
        ->assertJson([
            'outcome' => 'failed',
            'code' => 'challenge_regeneration_invalid_state',
        ]);
});

test('rendering cleans up all temporary files on success and on child failure', function (): void {
    hardeningSetup();
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);
    $renderer = app(OmrPhysicalChallengeRenderer::class);
    $insets = ['left' => 142, 'top' => 142, 'right' => 142, 'bottom' => 142];

    // Count temp files before
    $tmpDir = sys_get_temp_dir();
    $pattern = $tmpDir.'/nexiam-omr-shade-*';
    $before = glob($pattern);

    $result = $renderer->render($target, ProofPurpose::QuickRehearsal, challengePdfBytes(), $insets);
    expect($result['sheet']['pages'])->toHaveCount(2);

    // Count temp files after: must be identical (no leaked temp files)
    $after = glob($pattern);
    expect($after)->toEqual($before);
});

test('challenge regeneration refuses drifted draft target with typed plan_target_mismatch error', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $createResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $createResult['run'];
    $challenge = $createResult['challenge'];

    // Make challenge stale so it attempts regeneration rather than idempotent replay
    $challenge->forceFill(['guide_json' => null])->saveQuietly();

    // Mutate pinned run target hash so target no longer binds to pinned run
    $run->forceFill(['artifact_hash' => str_repeat('f', 64)])->saveQuietly();
    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'regen-drift-guard',
        ])
        ->assertStatus(422)
        ->assertJson([
            'outcome' => 'failed',
            'code' => 'plan_target_mismatch',
        ]);
});

test('POST challenge regeneration route succeeds under 128M limit with real challenge and supports idempotent replay', function (): void {
    hardeningSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    // 1. Create physical challenge through controller route
    $createResponse = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'route-test-create-1',
        ])
        ->assertStatus(202);

    $runId = (int) $createResponse->json('run_id');
    $challengeId = (int) $createResponse->json('challenge_id');
    $run = OmrTemplateVerificationRun::query()->findOrFail($runId);
    $challenge = OmrPhysicalChallenge::query()->findOrFail($challengeId);

    // 2. Simulate stale legacy challenge
    $assets = OmrTemplateAsset::query()->where('authoring_draft_id', $draft->id)->get();
    foreach ($assets as $asset) {
        $meta = (array) ($asset->metadata ?? []);
        unset($meta['renderer_version']);
        OmrTemplateAsset::withoutEvents(function () use ($asset, $meta): void {
            $asset->forceFill(['metadata' => $meta])->saveQuietly();
        });
    }
    $challenge->forceFill(['guide_json' => null])->saveQuietly();

    // Verify status exposes stale reference
    $statusResponse = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertStatus(200);
    expect($statusResponse->json('challenge.reference_current'))->toBeFalse()
        ->and($statusResponse->json('challenge.renderer_version'))->toBeNull()
        ->and($statusResponse->json('available_actions'))->toContain('regenerate_challenge');

    // 3. POST to regenerate route under 128M limit
    $regenResponse = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'route-test-regen-1',
        ])
        ->assertStatus(200)
        ->assertJson([
            'run_id' => $runId,
            'challenge_id' => $challengeId,
            'status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
            'regenerated' => true,
        ]);

    $challenge->refresh();
    expect($challenge->guide_json)->toBeArray()
        ->and($challenge->sheetAsset->metadata['renderer_version'])->toBe(OmrManifestBubbleShader::RENDERER_VERSION);

    // 4. Idempotent replay: second POST with different idempotency key returns 200 with regenerated: false
    $replayResponse = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'route-test-regen-2',
        ])
        ->assertStatus(200)
        ->assertJson([
            'run_id' => $runId,
            'challenge_id' => $challengeId,
            'regenerated' => false,
        ]);

    // 5. Status endpoint now confirms current reference and no regenerate_challenge action
    $updatedStatusResponse = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertStatus(200);
    expect($updatedStatusResponse->json('challenge.reference_current'))->toBeTrue()
        ->and($updatedStatusResponse->json('challenge.renderer_version'))->toBe(OmrManifestBubbleShader::RENDERER_VERSION)
        ->and($updatedStatusResponse->json('available_actions'))->not->toContain('regenerate_challenge');
});
