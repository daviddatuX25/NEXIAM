<?php

use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/OmrAssistedCaptureTestRequests.php';

/** @return array{actor: User, run: OmrDiagnosticRun} */
function startCaptureTransitionRun(TestCase $test): array
{
    $fixture = buildOmrGradingFixture($test);
    $pair = canonicalGradingBooklet($fixture['guidance']);
    $response = $test->actingAs($fixture['guidance'])->postJson(
        route('setup.exam-materials.omr-test.runs.start'),
        [
            'template_version_id' => $pair->template_version_id,
            'booklet_version_id' => $pair->booklet_version_id,
            'declared_set_code' => 'A',
        ],
    );
    $response->assertOk();

    return [
        'actor' => $fixture['guidance'],
        'run' => OmrDiagnosticRun::query()->where('created_by_user_id', $fixture['guidance']->id)->latest('id')->firstOrFail(),
    ];
}
test('next page transition requires an accepted operation receipt', function (): void {
    $context = startCaptureTransitionRun($this);
    $response = $this->actingAs($context['actor'])->postJson(
        route('setup.exam-materials.omr-test.runs.capture-actions', ['run' => $context['run']->public_id]),
        [
            'action' => 'next_page',
            'expected_revision' => 1,
            'authorization_id' => str_repeat('a', 64),
            'accepted_operation_id' => (string) Str::uuid(),
            'accepted_target_key' => '["unknown"]',
            'idempotency_key' => str_repeat('a', 64),
        ],
    );

    $response->assertNotFound()->assertJsonPath('code', 'omr_camera_accepted_capture_missing');
});

test('an accepted final Mock page authorizes a real next instance set', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    require_once base_path('tests/Support/TestKitHelpers.php');
    $kit = testKitAcceptanceFixture($actor, Institution::current());
    $version = $kit['version'];
    $guide = $this->actingAs($actor)->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', [
        'test_kit_version_id' => $version->id,
        'expected_revision' => $version->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
    ]))->assertOk();
    $started = $this->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        'diagnostic_purpose' => 'mock_rehearsal',
        'test_kit_version_id' => $version->id,
        'expected_revision' => $version->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
        'expected_mock_manifest_sha256' => $guide->json('manifest_sha256'),
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();
    $runId = (string) $started->json('context.public_id');
    $run = OmrDiagnosticRun::query()->where('public_id', $runId)->firstOrFail();
    $showUrl = route('setup.exam-materials.omr-test.runs.show', $runId);
    $captureUrl = route('setup.exam-materials.omr-test.runs.capture', $runId);
    $payload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('mock-page.png', physicalChallengePng(), 'image/png'),
    ]);
    $captured = $this->post($captureUrl, $payload, ['Accept' => 'application/json']);
    expect($captured->status())->toBe(200, $captured->getContent());
    $captured->assertJsonPath('context.capture_receipt.status', 'accepted');
    $captureModel = OmrDiagnosticCapture::query()->where('diagnostic_run_id', $run->id)->latest('id')->firstOrFail();
    $captureModel->forceFill(['status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW])->saveOrFail();

    $fresh = $this->getJson($showUrl)->assertOk();
    $receipt = $fresh->json('context.capture_receipt');
    $grant = $fresh->json('context.capture_actions.next_set');
    expect($receipt['status'] ?? null)->toBe('accepted')
        ->and($grant)->toBeArray()
        ->and($fresh->json('context.capture_target'))->toBeNull()
        ->and($fresh->json('context.capture_actions.next_page'))->toBeNull();

    $transitionPayload = [
        'action' => 'next_set',
        'expected_revision' => $fresh->json('revision'),
        'authorization_id' => $grant['authorizationId'],
        'accepted_operation_id' => $receipt['operation_id'],
        'accepted_target_key' => $grant['acceptedTargetKey'],
        'idempotency_key' => $grant['authorizationId'],
    ];
    $transition = $this->postJson(
        route('setup.exam-materials.omr-test.runs.capture-actions', $runId),
        $transitionPayload,
    );
    $transition->assertOk();

    $target = $transition->json('context.capture_target');
    expect($target['instance_id'])->toBe('instance-002')
        ->and($target['page_no'])->toBe(1)
        ->and($target['pending_capture_id'])->toBeString()
        ->and(OmrPendingCaptureSet::query()
            ->where('public_id', $target['pending_capture_id'])
            ->where('mock_instance_key', 'instance-002')
            ->exists())->toBeTrue();
    $this->postJson(route('setup.exam-materials.omr-test.runs.capture-actions', $runId), $transitionPayload)
        ->assertOk();
    expect(OmrPendingCaptureSet::query()
        ->where('diagnostic_run_id', $run->id)
        ->where('mock_instance_key', 'instance-002')
        ->count())->toBe(1);
    $paired = $this->postJson(route('grading.omr.sensor.pair.create'), [
        'workbench_id' => 'diagnostic:'.$runId,
    ])->assertCreated();
    expect($paired->json('target.instanceId'))->toBe('instance-002')
        ->and($paired->json('target.pending_capture_id'))->toBe($target['pending_capture_id']);
    $run->forceFill([
        'status' => OmrDiagnosticRun::STATUS_COMPLETED,
        'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
        'completed_at' => now(),
    ])->saveOrFail();
    $closed = $this->getJson($showUrl)->assertOk();
    expect($closed->json('context.capture_actions.next_set'))->toBeNull()
        ->and($closed->json('context.capture_target'))->toBeNull();
});

test('redo transition requires a nonempty reason before any action is considered', function (): void {
    $context = startCaptureTransitionRun($this);
    $response = $this->actingAs($context['actor'])->postJson(
        route('setup.exam-materials.omr-test.runs.capture-actions', ['run' => $context['run']->public_id]),
        [
            'action' => 'redo_last',
            'expected_revision' => 1,
            'authorization_id' => str_repeat('b', 64),
            'accepted_operation_id' => (string) Str::uuid(),
            'accepted_target_key' => '["unknown"]',
            'idempotency_key' => str_repeat('b', 64),
        ],
    );

    $response->assertUnprocessable()->assertJsonValidationErrors(['replacement_reason']);
});
