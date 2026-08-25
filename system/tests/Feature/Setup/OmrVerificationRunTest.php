<?php

use App\Assessment\Actions\Omr\Verification\StartOmrTemplateVerification;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Authorization\CapabilityCatalog;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDetectedItem;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function durableRunWorkerResponse(string $runId, bool $success): array
{
    if (! $success) {
        return [json_encode(['code' => 'marker_missing', 'detail' => 'fixture rejection'], JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']];
    }
    $payload = [
        'contract_version' => 'nexiam.omr.detection.v2',
        'run_id' => $runId,
        'correlation_id' => 'omr:durable-run',
        'outcome' => 'succeeded',
        'engine' => ['name' => 'OMRChecker', 'version' => 'de3f69828228', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
        'input_hash' => str_repeat('i', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'calibration_status' => 'unqualified',
        'aligned_image' => ['mime' => 'image/png', 'width' => 2480, 'height' => 3508],
        'layout_overlay' => ['mime' => 'image/png', 'width' => 2480, 'height' => 3508],
        'marker_matches' => ['quarter_1' => .9, 'quarter_2' => .9, 'quarter_3' => .9, 'quarter_4' => .9],
        'field_block_shifts' => [],
        'bubble_means' => [],
        'item_evidence' => [],
        'timings' => ['preprocess_ms' => 1.0, 'verification_ms' => 2.0],
    ];
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $raster = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $boundary = 'durable-run-worker';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."--\r\n";
    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $raster);
    $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $resultHash, $alignedHash, $alignedHash]), (string) config('omr.omrchecker.signing_secret'));

    return [$body, [
        'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        'X-Nexiam-Contract' => 'nexiam.omr.detection.v2',
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Aligned-SHA256' => $alignedHash,
        'X-Nexiam-Layout-Overlay-SHA256' => $alignedHash,
        'X-Nexiam-Signature' => $signature,
    ]];
}

final class DurableRunDetectorFake implements OmrDetectionPort
{
    public function detect(\App\Assessment\Omr\OmrDetectionRequest $request): OmrDetectionResult
    {
        $items = array_map(static fn ($geometry): OmrDetectedItem => new OmrDetectedItem(
            position: $geometry->position,
            areaCode: $geometry->areaCode,
            questionNo: $geometry->questionNo,
            selectedOption: $geometry->options[0]->code ?? null,
            state: OmrDetectedItem::STATE_MARKED,
            confidence: null,
            region: $geometry->region,
        ), $request->items);

        return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
            engineName: $request->engineName,
            engineVersion: 'test',
            engineCommit: $request->engineCommit,
            inputHash: $request->inputHash,
            configHash: $request->configHash,
            applicantNumberProposal: null,
            applicantNumberConfidence: null,
            identityFields: [],
            items: $items,
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
            fieldBlockShifts: [],
            imageMime: $request->imageMime,
            imageWidth: $request->imageWidth,
            imageHeight: $request->imageHeight,
        ));
    }
}

function durableRunDraft(User $actor): OmrTemplateAuthoringDraft
{
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'durable-run.marker',
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'size_mm' => 12,
        'quiet_zone_mm' => 3,
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'qualification_evidence_hash' => hash('sha256', 'calibration'),
        'created_by_user_id' => $actor->id,
        'qualified_by_user_id' => $actor->id,
        'qualified_at' => now(),
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'DURABLE-RUN', 'Durable run', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'questionsCount' => 1,
                'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
            ]]],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['LOGIC_Q_1'],
                    'bubblesGap' => 71, 'labelsGap' => 80, 'origin' => [295, 826], 'bubbleDimensions' => [53, 53],
                ]],
            ],
        ],
    ])->save();

    return $draft->fresh();
}

function durableRunCapableUser(): User
{
    $user = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $user->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    return $user;
}

function durableRunFakeWorker(int $successUntil = 1): int
{
    $calls = 0;
    Http::fake(['*/layout-verification' => function ($request) use (&$calls, $successUntil) {
        $calls++;
        [$body, $headers] = durableRunWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $calls <= $successUntil);
        return Http::response($body, $calls <= $successUntil ? 200 : 422, $headers);
    }]);

    return $calls;
}

/** Execute the durable job inline even while Queue::fake() is active. */
function durableRunJobInline(int $runId): void
{
    app()->call([new RunOmrTemplateVerificationJob($runId), 'handle']);
}

test('start returns 202 with run_id/status_url and dispatches the durable job', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.verify.digital', $draft), ['idempotency_key' => 'start-key-1'])
        ->assertStatus(202)
        ->assertJsonStructure(['run_id', 'status_url']);

    $run = OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->first();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($run->attempt)->toBe(1)
        ->and($run->mode)->toBe('digital')
        ->and(strlen($run->plan_hash))->toBe(64)
        ->and(strlen($run->artifact_hash))->toBe(64);

    Queue::assertPushed(RunOmrTemplateVerificationJob::class, 1);
    expect(AuditEvent::query()->where('event_type', 'omr.verification.started')->whereJsonContains('payload', ['run_id' => $run->id])->exists())->toBeTrue();
});

test('duplicate start replays the same active run without a second dispatch', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);

    $first = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.verify.digital', $draft), ['idempotency_key' => 'start-key-a'])
        ->assertStatus(202)
        ->json();
    $second = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.verify.digital', $draft), ['idempotency_key' => 'start-key-b'])
        ->assertStatus(202)
        ->json();

    expect($second['run_id'])->toBe($first['run_id']);
    expect(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->count())->toBe(1);
    Queue::assertPushed(RunOmrTemplateVerificationJob::class, 1);
});

test('the same idempotency key replays the terminal run after it passed', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    durableRunFakeWorker(1);
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);

    $first = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.verify.digital', $draft), ['idempotency_key' => 'start-key-x'])
        ->assertStatus(202)
        ->json();
    RunOmrTemplateVerificationJob::dispatchSync($first['run_id']);

    $replay = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.verify.digital', $draft), ['idempotency_key' => 'start-key-x'])
        ->assertStatus(202)
        ->json();

    expect($replay['run_id'])->toBe($first['run_id'])
        ->and(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->count())->toBe(1)
        ->and($replay['status_url'])->toBe($first['status_url']);
});

test('status endpoint reports phase, real progress, safe issue, actions, run version, and evidence URLs', function (): void {
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX,
        'mode' => 'digital',
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'progress_done' => 4,
        'progress_total' => 14,
        'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'rotation'],
        'started_by_user_id' => $actor->id,
        'started_at' => now()->subMinutes(2),
        'last_heartbeat_at' => now(),
    ]);
    Storage::disk('local')->put('omr-canonical/drafts/'.$draft->id.'/evidence-fake.png', 'aligned-bytes');
    $asset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => 'omr-canonical/drafts/'.$draft->id.'/evidence-fake.png',
        'mime' => 'image/png',
        'bytes' => 13,
        'sha256' => hash('sha256', 'aligned-bytes'),
        'metadata' => ['run_id' => $run->id, 'variant' => 'clean', 'role' => 'aligned.png'],
    ]);
    OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => 'digital',
        'variant' => 'clean',
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => str_repeat('i', 64),
        'aligned_asset_id' => $asset->id,
        'evidence_hash' => str_repeat('h', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'detected_vector_hash' => str_repeat('v', 64),
        'vector_match' => true,
        'summary' => ['execution' => 'omrchecker_layout_verification', 'detail' => null],
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk()
        ->assertJson([
            'run_id' => $run->id,
            'status' => 'variant_matrix',
            'phase' => 'variant_matrix',
            'current_variant' => 'rotation',
            'mode' => 'digital',
            'attempt' => 1,
            'progress' => ['done' => 4, 'total' => 14],
            'safe_issue' => null,
            'available_actions' => [],
            'run_version' => 0,
            'plan' => ['plan_hash' => str_repeat('p', 64), 'plan_version' => 'v1', 'challenge_code' => null],
        ])
        ->assertJsonPath('evidence.0.variant', 'clean')
        ->assertJsonPath('evidence.0.vector_match', true)
        ->assertJsonPath('evidence.0.urls.aligned', route('setup.omr.templates.canonical.verification_runs.assets.show', ['run' => $run->id, 'asset' => $asset->id]));

    $assetResponse = $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', ['run' => $run->id, 'asset' => $asset->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    $cacheControl = (string) $assetResponse->headers->get('Cache-Control');
    expect($cacheControl)->toContain('private')->toContain('no-store');

    $foreign = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_SOURCE,
        'disk' => 'local',
        'path' => 'omr-canonical/drafts/'.$draft->id.'/foreign.bin',
        'mime' => 'application/octet-stream',
        'bytes' => 1,
        'sha256' => hash('sha256', 'x'),
        'metadata' => [],
    ]);
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', ['run' => $run->id, 'asset' => $foreign->id]))
        ->assertNotFound();
});

test('draft-owned runs are owner-scoped: a foreign staff actor cannot read, retry, or fetch evidence', function (): void {
    $actor = durableRunCapableUser();
    $outsider = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => 'digital',
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
        'finished_at' => now(),
        'error_code' => 'no_go_variant_failed',
        'safe_error' => 'fixture failure',
    ]);

    $this->actingAs($outsider)->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))->assertNotFound();
    $this->actingAs($outsider)->postJson(route('setup.omr.templates.canonical.verification_runs.retry', $run))->assertNotFound();
    $this->actingAs($actor)->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))->assertOk();

    $outsider->revokePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $this->actingAs($outsider)->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))->assertForbidden();
});

test('a timed-out worker surfaces retry through the status endpoint', function (): void {
    config(['omr.verification.run_timeout_seconds' => 60]);
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX,
        'mode' => 'digital',
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'progress_done' => 2,
        'progress_total' => 14,
        'started_by_user_id' => $actor->id,
        'started_at' => now()->subHours(2),
        'last_heartbeat_at' => now()->subHours(2),
    ]);

    $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk()
        ->assertJson([
            'status' => 'failed',
            'safe_issue' => ['code' => 'worker_timeout'],
            'available_actions' => ['retry'],
            'progress' => ['done' => 2, 'total' => 14],
        ]);
});

test('retry creates the next attempt against the same pinned plan and dispatches the job', function (): void {
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'mode' => 'digital',
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'started_by_user_id' => $actor->id,
        'started_at' => now()->subMinutes(5),
        'last_heartbeat_at' => now()->subMinutes(5),
        'finished_at' => now(),
        'error_code' => 'worker_timeout',
        'safe_error' => 'fixture timeout',
    ]);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.retry', $run))
        ->assertStatus(202)
        ->assertJson([
            'run_id' => $run->id,
            'status' => 'queued',
            'attempt' => 2,
        ]);

    $retried = $run->fresh();
    expect($retried->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($retried->attempt)->toBe(2)
        ->and($retried->plan_hash)->toBe(str_repeat('p', 64))
        ->and($retried->error_code)->toBeNull()
        ->and($retried->progress_done)->toBe(0);
    Queue::assertPushed(RunOmrTemplateVerificationJob::class, 1);
    $retryEvent = AuditEvent::query()->where('event_type', 'omr.verification.retried')->latest('id')->first();
    expect($retryEvent)->not->toBeNull()
        ->and($retryEvent->payload['run_id'] ?? null)->toBe($run->id)
        ->and($retryEvent->payload['attempt'] ?? null)->toBe(2);
});

test('a stale target fails closed at job execution and preserves completed evidence', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED);

    // Simulate a concurrent authoring change AFTER the run was pinned: the
    // artifact set hash moves, so the pinned run can never pass.
    $draft->forceFill([
        'authoring_json' => [
            ...(array) $draft->authoring_json,
            'canonical' => [...(array) ($draft->authoring_json['canonical'] ?? []), 'artifact_set' => [
                ...(array) ($draft->authoring_json['canonical']['artifact_set'] ?? []),
                'artifact_hash' => str_repeat('x', 64),
            ]],
        ],
    ])->save();

    durableRunFakeWorker(1);
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);
    durableRunJobInline($run->id);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->error_code)->toBe('stale_target')
        ->and($run->finished_at)->not->toBeNull();
    expect($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE);
    $failureEvent = AuditEvent::query()->where('event_type', 'omr.verification.failed')->latest('id')->first();
    expect($failureEvent)->not->toBeNull()
        ->and($failureEvent->payload['run_id'] ?? null)->toBe($run->id)
        ->and($failureEvent->payload['error_code'] ?? null)->toBe('stale_target');
});

test('worker unavailable preserves partial evidence and exposes retry', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED);

    // The worker is unreachable from the very first (clean) request: the
    // run must fail closed and keep every completed evidence row.
    Http::fake(['*/layout-verification' => fn () => throw new ConnectionException('Connection to the worker failed.')]);
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);
    durableRunJobInline($run->id);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->error_code)->toBe('worker_unavailable')
        ->and($run->safe_error)->not->toBeNull()
        ->and($run->captures()->count())->toBe(1) // clean capture row preserved
        ->and($run->captures()->first()->variant)->toBe('clean')
        ->and($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE);

    $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk()
        ->assertJson(['status' => 'failed', 'available_actions' => ['retry']]);

    $failureEvent = AuditEvent::query()->where('event_type', 'omr.verification.failed')->latest('id')->first();
    expect($failureEvent)->not->toBeNull()
        ->and($failureEvent->payload['run_id'] ?? null)->toBe($run->id)
        ->and($failureEvent->payload['error_code'] ?? null)->toBe('worker_unavailable');
});

test('a failed matrix run records a typed no_go failure and never promotes the draft', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    durableRunFakeWorker(0); // clean itself is rejected by the worker
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    durableRunJobInline($run->id);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->error_code)->toBe('marker_missing')
        ->and($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE);
});
