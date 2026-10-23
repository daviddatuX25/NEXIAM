<?php

use App\Assessment\Actions\DiscardTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitEligibility;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('creates and consumes a pairing code for an authorized diagnostic workbench', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $pair = canonicalGradingBooklet($fixture['guidance']);
    $template = OmrAnswerSheetTemplateVersion::query()->findOrFail($pair->template_version_id);
    $booklet = OmrQuestionBookletVersion::query()->findOrFail($pair->booklet_version_id);
    $this->actingAs($fixture['guidance'])
        ->postJson(route('setup.exam-materials.omr-test.runs.start'), [
            'template_version_id' => $template->id,
            'booklet_version_id' => $booklet->id,
            'declared_set_code' => 'A',
        ])
        ->assertOk();
    $run = OmrDiagnosticRun::query()->latest('id')->firstOrFail();
    $selectedPageNo = min(2, (int) $template->page_count);
    $otherPageNo = $selectedPageNo === 1 ? 2 : 1;

    $create = $this->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => 'diagnostic:'.$run->public_id,
        'capture_page_no' => $selectedPageNo,
    ])->assertCreated()->assertJsonStructure([
        'pairing_id', 'pair_code', 'sensor_url', 'endpoints' => ['join', 'poll', 'command', 'ack', 'frames'],
    ])->assertJsonPath('target.pageNo', $selectedPageNo);

    $code = $create->json('pair_code');
    expect(strlen($code))->toBe(6);

    $join = $this->postJson(route('grading.omr.sensor.pair.join'), ['pair_code' => $code])
        ->assertOk()
        ->assertJsonStructure(['pairing_id', 'node_id', 'node_token', 'generation', 'controller_generation',
            'source_generation', 'config_revision', 'config' => ['target', 'mode', 'batchTrigger', 'steadyDelayMs']]);
    $headers = [
        'X-Omr-Camera-Role' => 'node',
        'X-Omr-Camera-Token' => $join->json('node_token'),
        'X-Omr-Camera-Generation' => (string) $join->json('node_generation'),
    ];
    $heartbeat = fn (array $payload) => $this->withHeaders($headers)->postJson(
        route('grading.omr.sensor.pair.heartbeat', $join->json('pairing_id')),
        ['protocol_version' => config('omr-camera.protocol_version'), ...$payload]);
    $baseObservation = [
        'protocol_version' => config('omr-camera.protocol_version'),
        'frame_sequence' => 1,
        'sequence' => 1, 'source_generation' => $join->json('source_generation'),
        'config_revision' => $join->json('config_revision'), 'target_key' => $join->json('config.target.targetKey'),
        'observed_at_age_ms' => 200, 'ready' => true, 'kind' => 'quality',
        'quality' => ['in_bounds' => true, 'angle_ok' => true, 'steady_ok' => true, 'lighting_ok' => true],
        'result' => ['type' => 'analysis', 'generation' => 1, 'frameId' => 1,
            'quad' => [['x' => 0.1, 'y' => 0.1], ['x' => 0.9, 'y' => 0.1],
                ['x' => 0.9, 'y' => 0.9], ['x' => 0.1, 'y' => 0.9]],
            'gates' => ['in_bounds' => true, 'angle_ok' => true, 'steady_ok' => true, 'lighting_ok' => true]],
    ];
    $heartbeat([...$baseObservation, 'noise' => str_repeat('x', 9000)])->assertStatus(413);
    $heartbeat(['protocol_version' => config('omr-camera.protocol_version'), ...$baseObservation])->assertOk();
    $heartbeat(['sequence' => 2, 'source_generation' => $join->json('source_generation'),
        'config_revision' => $join->json('config_revision'), 'target_key' => $join->json('config.target.targetKey'),
        'observed_at_age_ms' => 0, 'kind' => 'qr_intent', 'command' => 'NEXT_PAGE', 'command_id' => (string) Str::uuid()])
        ->assertOk();
    $heartbeat(['sequence' => 3, 'source_generation' => $join->json('source_generation'),
        'config_revision' => $join->json('config_revision'), 'target_key' => $join->json('config.target.targetKey'),
        'observed_at_age_ms' => 0, 'kind' => 'qr_card_removed', 'ready' => false, 'qr_card_removed' => true])
        ->assertOk();
    $controllerHeaders = [
        'X-Omr-Camera-Role' => 'controller',
        'X-Omr-Camera-Token' => $create->json('controller_token'),
        'X-Omr-Camera-Generation' => (string) $create->json('controller_generation'),
    ];
    $this->withHeaders($controllerHeaders)->postJson(route('grading.omr.sensor.pair.heartbeat', $join->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'), 'ready' => true, 'sequence' => 1,
        'source_generation' => $join->json('source_generation'), 'target_key' => $join->json('config.target.targetKey'),
    ])->assertOk();
    $poll = $this->withHeaders($controllerHeaders)->getJson(route('grading.omr.sensor.pair.poll', $join->json('pairing_id')))
        ->assertOk()->assertJsonPath('observations.0.kind', 'quality')
        ->assertJsonPath('observations.1.command', 'NEXT_PAGE')
        ->assertJsonPath('observations.2.qr_card_removed', true);
    expect($poll->json('observations.0.received_at'))->toContain('.')
        ->and($poll->json('observations.0.age_ms'))->toBeGreaterThanOrEqual(200);

    $configCommandId = (string) Str::uuid();
    $setConfig = $this->withHeaders($controllerHeaders)->postJson(route('grading.omr.sensor.pair.command', $join->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'), 'command' => 'SET_CONFIG', 'command_id' => $configCommandId,
        'config_revision' => $join->json('config_revision'), 'source_generation' => $join->json('source_generation'),
        'config' => ['mode' => 'auto', 'steadyDelayMs' => 500],
    ])->assertAccepted()->assertJsonPath('config.mode', 'auto')->assertJsonPath('config.steadyDelayMs', 500)
        ->assertJsonPath('config_revision', 2);
    $this->withHeaders($headers)->postJson(route('grading.omr.sensor.pair.ack', $join->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'),
        'command_id' => $setConfig->json('command_id'), 'status' => 'applied',
        'source_generation' => 3, 'config_revision' => 2, 'target_key' => $create->json('target.targetKey'),
    ])->assertOk()->assertJsonPath('status', 'applied')->assertJsonMissingPath('frame_id');
    $this->withHeaders($controllerHeaders)->postJson(route('grading.omr.sensor.pair.command', $join->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'), 'command' => 'SET_CONFIG', 'command_id' => $configCommandId,
        'config_revision' => $join->json('config_revision'), 'source_generation' => $join->json('source_generation'),
        'config' => ['mode' => 'auto', 'steadyDelayMs' => 500],
    ])->assertOk()->assertJsonPath('command_id', $configCommandId)->assertJsonPath('config_revision', 2);

    $target = array_intersect_key($create->json('target'), array_flip([
        'workbenchId', 'clientCaptureId', 'pageNo', 'captureRevision', 'instanceId', 'sheetId', 'targetKey',
    ]));
    $captureCommandId = (string) Str::uuid();
    $captureOperationId = (string) Str::uuid();
    $capturePayload = [
        'protocol_version' => config('omr-camera.protocol_version'),
        'command' => 'CAPTURE', 'command_id' => $captureCommandId, 'operation_id' => $captureOperationId,
        'config_revision' => 2, 'source_generation' => 3, 'target' => $target, 'trigger' => 'keyboard',
    ];
    $capture = $this->withHeaders($controllerHeaders)
        ->postJson(route('grading.omr.sensor.pair.command', $join->json('pairing_id')), $capturePayload)
        ->assertAccepted()->assertJsonPath('trigger', 'keyboard')->assertJsonPath('mode', 'auto');
    $this->withHeaders($controllerHeaders)->postJson(route('grading.omr.sensor.pair.command', $join->json('pairing_id')), [
        ...$capturePayload, 'trigger' => 'steady',
    ])->assertConflict();
    $this->withHeaders($controllerHeaders)->postJson(route('grading.omr.sensor.pair.command', $join->json('pairing_id')), $capturePayload)
        ->assertOk()->assertJsonPath('command_id', $captureCommandId);
    $nodeHeaders = [
        'X-Omr-Camera-Role' => 'node',
        'X-Omr-Camera-Token' => $join->json('node_token'),
        'X-Omr-Camera-Generation' => (string) $join->json('node_generation'),
    ];
    $this->withHeaders([...$nodeHeaders, 'X-Omr-Camera-Token' => $create->json('controller_token')])
        ->postJson(route('grading.omr.sensor.pair.heartbeat', $join->json('pairing_id')), $baseObservation)
        ->assertForbidden();
    $this->withHeaders([...$nodeHeaders, 'X-Omr-Camera-Generation' => (string) ((int) $join->json('node_generation') + 1)])
        ->postJson(route('grading.omr.sensor.pair.heartbeat', $join->json('pairing_id')), $baseObservation)
        ->assertForbidden();
    $original = UploadedFile::fake()->image('original.png', 100, 100);
    $originalBytes = file_get_contents($original->getRealPath());
    $acquired = $this->withHeaders($nodeHeaders)->postJson(route('grading.omr.sensor.pair.ack', $join->json('pairing_id')), [
        'protocol_version' => config('omr-camera.protocol_version'),
        'command_id' => $capture->json('command_id'), 'status' => 'acquired',
        'input_hash' => hash('sha256', $originalBytes), 'mime' => 'image/png',
        'byte_count' => strlen($originalBytes), 'width' => 100, 'height' => 100, 'orientation' => 1,
        'target_key' => $target['targetKey'], 'config_revision' => 2, 'source_generation' => 3,
    ])->assertOk();
    $staged = $this->withHeaders([...$nodeHeaders, 'Accept' => 'application/json'])
        ->post(route('grading.omr.sensor.pair.frames.store', $join->json('pairing_id')), [
            'command_id' => $capture->json('command_id'), 'frame_id' => $acquired->json('frame_id'),
            'target_key' => $target['targetKey'], 'frame' => $original,
        ])->assertCreated();
    $frame = OmrCameraFrame::query()->where('public_id', $acquired->json('frame_id'))->firstOrFail();
    expect($frame->asset_disk)->toBe('sensitive')
        ->and($frame->asset_hmac)->toBeString()
        ->and(Storage::disk('sensitive')->get($frame->asset_path))->not->toBe($originalBytes);
    $this->withHeaders($controllerHeaders)->get($staged->json('preview_url'))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertContent($originalBytes);
    $rejoinHeaders = [
        'X-Omr-Camera-Role' => 'controller',
        'X-Omr-Camera-Token' => $create->json('controller_token'),
        'X-Omr-Camera-Generation' => (string) $create->json('controller_generation'),
    ];
    $this->withHeaders($rejoinHeaders)->postJson(route('grading.omr.sensor.pair.node.rejoin', $join->json('pairing_id')), [
        'confirmed' => true, 'capture_page_no' => $otherPageNo,
    ])->assertConflict();
    $this->withHeaders($rejoinHeaders)->postJson(route('grading.omr.sensor.pair.node.rejoin', $join->json('pairing_id')), [
        'confirmed' => true, 'capture_page_no' => $selectedPageNo,
    ])->assertOk()->assertJsonPath('config.target.pageNo', $selectedPageNo)
        ->assertJsonStructure(['pair_code', 'sensor_url', 'endpoints', 'config',
            'controller_generation', 'node_generation', 'source_generation', 'config_revision'])
        ->assertJsonMissingPath('node_token')
        ->assertJsonMissingPath('controller_token');
    $this->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => 'diagnostic:'.$run->public_id, 'takeover' => true,
        'expected_controller_generation' => $create->json('controller_generation') + 1,
    ])->assertStatus(409)->assertJsonPath('code', 'omr_camera_controller_active');
    $this->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => 'diagnostic:'.$run->public_id, 'takeover' => true,
        'expected_controller_generation' => $create->json('controller_generation'),
    ])->assertCreated()->assertJsonPath('controller_generation', 2);
    $this->travel(61)->seconds();
    $this->postJson(route('grading.omr.sensor.pair.join'), ['pair_code' => $code])
        ->assertNotFound();
});

it('discards an open Test Kit preparation draft cleanly without mutating locked versions and launches a mock_rehearsal run without an acceptance plan', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    require_once base_path('tests/Support/TestKitHelpers.php');
    $kit = testKitAcceptanceFixture($actor, Institution::current());
    $v1 = $kit['version'];
    $v1HashBefore = $v1->manifest_sha256;

    $prep = app(StartTestKitPreparation::class)->handle($actor, (int) $v1->test_kit_id, [
        'expected_family_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    expect($prep->state)->toBe(TestKitPreparation::STATE_PREPARING);

    $discarded = app(DiscardTestKitPreparation::class)->handle($actor, (int) $v1->test_kit_id, [
        'preparation_id' => (int) $prep->id,
        'expected_revision' => (int) $prep->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    expect($discarded->state)->toBe(TestKitPreparation::STATE_DISCARDED)
        ->and($v1->fresh()->manifest_sha256)->toBe($v1HashBefore);

    $guide = $this->actingAs($actor)
        ->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', [
            'test_kit_version_id' => $v1->id,
            'expected_revision' => $v1->revision,
            'instance_count' => 2,
            'pattern_mode' => 'perfect_set_a',
        ]))
        ->assertOk();

    $manifestSha = $guide->json('manifest_sha256');
    expect($manifestSha)->toBeString()->and(strlen($manifestSha))->toBe(64);

    $start = $this->actingAs($actor)
        ->postJson(route('setup.exam-materials.omr-test.runs.start'), [
            'diagnostic_purpose' => 'mock_rehearsal',
            'test_kit_version_id' => $v1->id,
            'expected_revision' => $v1->revision,
            'instance_count' => 2,
            'pattern_mode' => 'perfect_set_a',
            'expected_mock_manifest_sha256' => $manifestSha,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertOk()
        ->assertJsonPath('context.diagnostic_purpose', 'mock_rehearsal')
        ->assertJsonPath('context.capture_target.instance_id', 'instance-001')
        ->assertJsonPath('context.capture_target.page_no', 1)
        ->assertJsonPath('context.capture_target.sheet_id', null);

    $mockRun = OmrDiagnosticRun::query()->where('public_id', $start->json('context.public_id'))->firstOrFail();
    expect($mockRun->acceptance_plan_id)->toBeNull()
        ->and($mockRun->diagnostic_purpose)->toBe('mock_rehearsal');

    $this->actingAs($actor)
        ->get(route('setup.exam-materials.omr-test.runs.reference-asset', [
            'templateVersion' => $v1->template_version_id,
            'pageNo' => 1,
            'test_kit_version_id' => $v1->id,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $this->actingAs($actor)
        ->get(route('setup.omr.templates.preview', [
            'version' => $v1->template_version_id,
            'page' => 1,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('rehearses optional mapped materials without granting production OMR applicability', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    require_once base_path('tests/Support/TestKitHelpers.php');
    $kit = testKitAcceptanceFixture($actor, Institution::current(), scenario: 'optional-attachment');
    $version = $kit['version'];
    $composition = app(KitCompositionContext::class)->resolveVersion($version);
    expect($composition['requirements']['omr_applicable'])->toBeFalse()
        ->and($composition['attachment_state'])->toBe('complete');

    $payload = [
        'test_kit_version_id' => $version->id,
        'expected_revision' => $version->revision,
        'instance_count' => 1,
        'pattern_mode' => 'perfect_set_a',
    ];
    $guide = $this->actingAs($actor)
        ->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', $payload))
        ->assertOk();
    $startPayload = [
        ...$payload,
        'diagnostic_purpose' => 'mock_rehearsal',
        'expected_mock_manifest_sha256' => $guide->json('manifest_sha256'),
        'idempotency_key' => (string) Str::uuid(),
    ];
    $start = $this->postJson(route('setup.exam-materials.omr-test.runs.start'), $startPayload)
        ->assertOk()
        ->assertJsonPath('context.diagnostic_purpose', 'mock_rehearsal')
        ->assertJsonPath('context.capture_target.instance_id', 'instance-001')
        ->assertJsonPath('context.capture_target.page_no', 1);
    $this->postJson(route('setup.exam-materials.omr-test.runs.start'), $startPayload)
        ->assertOk()
        ->assertJsonPath('context.public_id', $start->json('context.public_id'));

    $eligibility = app(TestKitEligibility::class);
    $cycle = AcademicCycle::query()->findOrFail($fixture['session']->academic_cycle_id);
    $production = $eligibility->evaluate($version, $cycle, $eligibility::PURPOSE_NEW_USE, operation: 'omr');
    expect(array_column($production['blockers'], 'code'))->toContain('omr_not_applicable');
    expect(OmrDiagnosticRun::query()->where('public_id', $start->json('context.public_id'))->firstOrFail()->acceptance_plan_id)->toBeNull();
});

it('cancelling a Mock run revokes only its camera pairing and blocks later join, signal, and heartbeat', function (): void {
    config(['omr-camera.remote_enabled' => true]);
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    require_once base_path('tests/Support/TestKitHelpers.php');
    $kit = testKitAcceptanceFixture($actor, Institution::current());
    $version = $kit['version'];
    $startMock = function () use ($actor, $version): OmrDiagnosticRun {
        $payload = [
            'test_kit_version_id' => $version->id,
            'expected_revision' => $version->revision,
            'instance_count' => 2,
            'pattern_mode' => 'perfect_set_a',
        ];
        $guide = $this->actingAs($actor)
            ->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', $payload))
            ->assertOk();
        $started = $this->actingAs($actor)->postJson(route('setup.exam-materials.omr-test.runs.start'), [
            ...$payload,
            'diagnostic_purpose' => 'mock_rehearsal',
            'expected_mock_manifest_sha256' => $guide->json('manifest_sha256'),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();

        return OmrDiagnosticRun::query()->where('public_id', $started->json('context.public_id'))->firstOrFail();
    };

    $run = $startMock();
    $otherRun = $startMock();
    $pairing = $this->actingAs($actor)->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => 'diagnostic:'.$run->public_id,
    ])->assertCreated();
    $otherPairing = $this->actingAs($actor)->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => 'diagnostic:'.$otherRun->public_id,
    ])->assertCreated();
    $controllerHeaders = [
        'X-Omr-Camera-Role' => 'controller',
        'X-Omr-Camera-Token' => $pairing->json('controller_token'),
        'X-Omr-Camera-Generation' => (string) $pairing->json('controller_generation'),
    ];

    $this->actingAs($actor)->postJson(route('setup.exam-materials.omr-test.runs.cancel', ['run' => $run->public_id]), [
        'expected_revision' => (int) $run->revision,
        'reason' => 'Close this Mock scanner session.',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    $join = $this->actingAs($actor)->postJson(route('grading.omr.sensor.pair.join'), [
        'pair_code' => $pairing->json('pair_code'),
    ]);
    $signal = $this->actingAs($actor)->withHeaders($controllerHeaders)->postJson(
        route('grading.omr.sensor.pair.signal', $pairing->json('pairing_id')),
        [
            'kind' => 'offer',
            'generation' => (int) $pairing->json('controller_generation'),
            'payload' => ['type' => 'offer', 'sdp' => 'v=0'],
        ],
    );
    $heartbeat = $this->actingAs($actor)->withHeaders($controllerHeaders)->postJson(
        route('grading.omr.sensor.pair.heartbeat', $pairing->json('pairing_id')),
        [
            'protocol_version' => config('omr-camera.protocol_version'),
            'ready' => true,
            'sequence' => 1,
            'source_generation' => (int) $pairing->json('source_generation'),
            'target_key' => (string) $pairing->json('target.targetKey'),
        ],
    );
    $revokedPairing = OmrCameraPairing::query()->where('public_id', $pairing->json('pairing_id'))->first();
    $untouchedPairing = OmrCameraPairing::query()->where('public_id', $otherPairing->json('pairing_id'))->first();

    expect($revokedPairing)->not->toBeNull()
        ->and($revokedPairing->revoked_at)->not->toBeNull()
        ->and($untouchedPairing)->not->toBeNull()
        ->and($untouchedPairing->revoked_at)->toBeNull()
        ->and($join->status())->toBe(404)
        ->and($signal->status())->toBe(404)
        ->and($heartbeat->status())->toBe(404);
});
