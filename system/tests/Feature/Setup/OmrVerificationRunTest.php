<?php

use App\Assessment\Actions\Omr\Verification\StartOmrTemplateVerification;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Authorization\CapabilityCatalog;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Jobs\Assessment\RunOmrTemplateVerificationVariantJob;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/** Extract the request manifest from a signed multipart worker call. */
function durableRunManifestFromBody(string $body): array
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

function durableRunWorkerResponse(string $runId, bool $success, string $requestBody): array
{
    if (! $success) {
        return [json_encode(['code' => 'marker_missing', 'detail' => 'fixture rejection'], JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']];
    }
    $manifest = durableRunManifestFromBody($requestBody);
    $targetIdentity = array_key_exists('template_version_id', $manifest)
        ? ['template_version_id' => $manifest['template_version_id']]
        : ['candidate_target_id' => $manifest['candidate_target_id']];
    $payload = [
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
        'image' => [
            'mime' => $manifest['image_mime'],
            'width' => $manifest['image_width'],
            'height' => $manifest['image_height'],
        ],
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
        'X-Nexiam-Contract' => OmrDetectionRequest::ENVELOPE_VERSION,
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
    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        $items = array_map(static function ($geometry): OmrDetectedItem {
            $selected = $geometry->options[0]->code ?? null;

            return OmrDetectedItem::fromGeometry(
                geometry: $geometry,
                selectedOption: $selected,
                state: OmrDetectedItem::STATE_MARKED,
                confidence: null,
            );
        }, $request->items);

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
            registrationMode: $request->registrationMode->value,
            templateVersionId: $request->templateVersionId,
            candidateTargetId: $request->candidateTargetId,
            expectedPageNo: $request->pageNo,
            sourceImage: $request->sourceImage->toArray(),
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
            'pages' => [[
                'page_no' => 1,
                'paper' => ['width_mm' => 210, 'height_mm' => 297],
            ]],
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

function durableRunUseWorker(): void
{
    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    app()->instance(
        OmrLayoutVerificationPort::class,
        new OmrCheckerDetection,
    );
}

function durableRunFakeWorker(int $successUntil = 1): void
{
    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    app()->instance(
        OmrLayoutVerificationPort::class,
        new OmrCheckerDetection,
    );
    $calls = 0;
    Http::swap(new Factory);
    Http::fake(['*/layout-verification' => function ($request) use (&$calls, $successUntil) {
        $calls++;
        [$body, $headers] = durableRunWorkerResponse(
            (string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''),
            $calls <= $successUntil,
            $request->body(),
        );

        return Http::response($body, $calls <= $successUntil ? 200 : 422, $headers);
    }]);
}

/** Execute the coordinator and all queued variant jobs inline until terminal or blocked. */
function durableRunJobInline(int $runId, int $maxSteps = 30): void
{
    $run = OmrTemplateVerificationRun::query()->find($runId);
    if ($run !== null && $run->status === OmrTemplateVerificationRun::STATUS_QUEUED) {
        try {
            app()->call([new RunOmrTemplateVerificationJob($runId, (int) $run->attempt, (int) $run->optimistic_version), 'handle']);
        } catch (Throwable) {
            return;
        }
    }

    $steps = 0;
    while ($steps++ < $maxSteps) {
        $run = OmrTemplateVerificationRun::query()->find($runId);
        if ($run === null || $run->isTerminal()) {
            break;
        }
        $phase = (array) $run->phase_detail;
        $variant = $phase['current_variant'] ?? null;
        $state = $phase['state'] ?? null;
        if (! is_string($variant) || $variant === '' || $state !== 'ready') {
            break;
        }
        try {
            app()->call([new RunOmrTemplateVerificationVariantJob($runId, (int) $run->attempt, $variant, (int) $run->optimistic_version), 'handle']);
        } catch (Throwable) {
            break;
        }
    }
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
        ->postJson(route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft), ['idempotency_key' => 'start-key-1'])
        ->assertStatus(202)
        ->assertJsonStructure(['run_id', 'status_url']);

    $run = OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->first();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($run->attempt)->toBe(1)
        ->and($run->mode)->toBe('digital')
        ->and($run->progress_done)->toBe(0)
        ->and($run->progress_total)->toBe(14)
        ->and(strlen($run->plan_hash))->toBe(64)
        ->and(strlen($run->artifact_hash))->toBe(64);

    Queue::assertPushed(RunOmrTemplateVerificationJob::class, function (RunOmrTemplateVerificationJob $job) use ($run): bool {
        return $job->runId === $run->id && $job->queue === 'omr-verification' && $job->timeout === 75 && $job->tries === 1;
    });
    expect(AuditEvent::query()->where('event_type', 'omr.verification.started')->whereJsonContains('payload', ['run_id' => $run->id])->exists())->toBeTrue();
});

test('verification coordinator and variant jobs enforce 75s timeout and omr-verification queue', function (): void {
    $coordinator = new RunOmrTemplateVerificationJob(42, 1, 0);
    expect($coordinator->queue)->toBe('omr-verification')
        ->and($coordinator->timeout)->toBe(75)
        ->and($coordinator->tries)->toBe(1);

    $variant = new RunOmrTemplateVerificationVariantJob(42, 1, 'clean', 0);
    expect($variant->queue)->toBe('omr-verification')
        ->and($variant->timeout)->toBe(75)
        ->and($variant->tries)->toBe(1);
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
        ->postJson(route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft), ['idempotency_key' => 'start-key-a'])
        ->assertStatus(202)
        ->json();
    $second = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft), ['idempotency_key' => 'start-key-b'])
        ->assertStatus(202)
        ->json();

    expect($second['run_id'])->toBe($first['run_id']);
    expect(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->count())->toBe(1);
    Queue::assertPushed(RunOmrTemplateVerificationJob::class, 1);
});

test('same idempotency key resumes only the current digital run with target flags', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $url = route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft);

    $first = $this->actingAs($actor)
        ->postJson($url, ['idempotency_key' => 'start-replay-current'])
        ->assertStatus(202)
        ->assertJson([
            'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
            'target_current' => true,
            'target_stale' => false,
            'digital_verification_current' => false,
        ])
        ->json();
    $replay = $this->actingAs($actor)
        ->postJson($url, ['idempotency_key' => 'start-replay-current'])
        ->assertStatus(202)
        ->assertJson([
            'run_id' => $first['run_id'],
            'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
            'target_current' => true,
            'target_stale' => false,
        ])
        ->json();

    expect($replay['status_url'])->toBe($first['status_url'])
        ->and(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->count())->toBe(1);
    Queue::assertPushed(RunOmrTemplateVerificationJob::class, 1);
});

test('digital start supersedes a stale active physical run without returning it', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $stale = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('o', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('x', 64),
        'progress_done' => 0,
        'progress_total' => 1,
        'started_by_user_id' => $actor->id,
        'started_at' => now()->subHour(),
        'last_heartbeat_at' => now(),
    ]);

    $current = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);

    expect($current->mode)->toBe(OmrTemplateVerificationRun::MODE_DIGITAL)
        ->and($current->draft_id)->toBe($draft->id)
        ->and($current->artifact_hash)->not->toBe($stale->artifact_hash)
        ->and($current->mapping_hash)->not->toBe($stale->mapping_hash)
        ->and($stale->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($stale->fresh()->error_code)->toBe('target_changed_superseded')
        ->and(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)->count())->toBe(1);

    Queue::assertPushed(RunOmrTemplateVerificationJob::class, 1);
});

test('digital start refuses a current-target physical run instead of returning the wrong mode', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $digital = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    $digital->forceFill(['status' => OmrTemplateVerificationRun::STATUS_FAILED, 'finished_at' => now()])->save();
    $physical = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'attempt' => 1,
        'plan_version' => $digital->plan_version,
        'plan_hash' => $digital->plan_hash,
        'expected_vector_hash' => $digital->expected_vector_hash,
        'artifact_hash' => $digital->artifact_hash,
        'native_bundle_hash' => $digital->native_bundle_hash,
        'manifest_hash' => $digital->manifest_hash,
        'config_hash' => $digital->config_hash,
        'mapping_hash' => $digital->mapping_hash,
        'progress_done' => 0,
        'progress_total' => 1,
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    expect(fn () => app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor))
        ->toThrow(RuntimeException::class, 'verification_active_run_mode_conflict');
    expect($physical->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)->count())->toBe(1);
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
        ->postJson(route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft), ['idempotency_key' => 'start-key-x'])
        ->assertStatus(202)
        ->json();
    RunOmrTemplateVerificationJob::dispatchSync($first['run_id']);

    $replay = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft), ['idempotency_key' => 'start-key-x'])
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
        ->assertJsonStructure([
            'target' => ['kind', 'id', 'hashes' => ['artifact', 'native_bundle', 'manifest', 'config', 'mapping']],
        ])
        ->assertJsonPath('target.kind', 'candidate')
        ->assertJsonPath('target.id', $draft->id)
        ->assertJsonPath('target.hashes.artifact', str_repeat('a', 64))
        ->assertJsonPath('target.hashes.native_bundle', str_repeat('n', 64))
        ->assertJsonPath('target.hashes.manifest', str_repeat('m', 64))
        ->assertJsonPath('target.hashes.config', str_repeat('g', 64))
        ->assertJsonPath('target.hashes.mapping', str_repeat('d', 64))
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

test('queued digital status exposes the planned denominator before worker claim', function (): void {
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_QUEUED,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'progress_done' => 0,
        'progress_total' => 0,
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);

    $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk()
        ->assertJson([
            'status' => OmrTemplateVerificationRun::STATUS_QUEUED,
            'progress' => ['done' => 0, 'total' => 14, 'percent' => 0.0],
            'safe_issue' => null,
        ]);
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
    // solved geometry moves, so rematerialization no longer matches the run.
    $changedAuthoring = (array) $draft->authoring_json;
    $changedAuthoring['mapping']['field_blocks']['Part_1']['origin'][0] = (int) ($changedAuthoring['mapping']['field_blocks']['Part_1']['origin'][0] ?? 0) + 1;
    $draft->forceFill(['authoring_json' => $changedAuthoring])->save();

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
    durableRunUseWorker();
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

test('durable verification drains entire 14-variant chain to terminal pass and promotes draft', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    durableRunFakeWorker(7); // 7 exact-recovery variants pass, 3 reject variants reject
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    durableRunJobInline($run->id);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->progress_done)->toBe(14)
        ->and($run->progress_total)->toBe(14)
        ->and($run->captures()->count())->toBe(14)
        ->and($run->error_code)->toBeNull()
        ->and($run->finished_at)->not->toBeNull();

    $draft = $draft->fresh();
    expect($draft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($draft->authoring_json['verification']['proof_hash'] ?? null)->toBe($run->proof_hash);

    $passedEvent = AuditEvent::query()->where('event_type', 'omr.verification.passed')->latest('id')->first();
    expect($passedEvent)->not->toBeNull()
        ->and($passedEvent->payload['run_id'] ?? null)->toBe($run->id)
        ->and($passedEvent->payload['draft_id'] ?? null)->toBe($draft->id);
});
test('duplicate variant delivery is a no-op that leaves run and captures intact', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    durableRunFakeWorker(7);
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    // Run coordinator (step 0)
    app()->call([new RunOmrTemplateVerificationJob($run->id, (int) $run->attempt, (int) $run->optimistic_version), 'handle']);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY);

    // Run clean variant (step 1)
    app()->call([new RunOmrTemplateVerificationVariantJob($run->id, (int) $run->attempt, 'clean', (int) $run->optimistic_version), 'handle']);

    $run = $run->fresh();
    expect($run->captures()->count())->toBe(1)
        ->and($run->captures()->first()->variant)->toBe('clean')
        ->and($run->status)->toBe(OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX);

    $versionBefore = $run->optimistic_version;
    // Deliver a duplicate clean variant job with the previous optimistic version
    app()->call([new RunOmrTemplateVerificationVariantJob($run->id, (int) $run->attempt, 'clean', $versionBefore - 1), 'handle']);

    // Run state and captures must be unchanged
    $run = $run->fresh();
    expect($run->captures()->count())->toBe(1)
        ->and($run->optimistic_version)->toBe($versionBefore)
        ->and($run->status)->toBe(OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX);
});

test('out of order variant delivery is rejected without corrupting the run', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    durableRunFakeWorker(7);
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    // Run coordinator only (step 0, run is in clean_recovery waiting for 'clean')
    app()->call([new RunOmrTemplateVerificationJob($run->id, (int) $run->attempt, (int) $run->optimistic_version), 'handle']);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY);

    // Attempt to deliver variant 'rotation' out of order (expecting 'clean')
    app()->call([new RunOmrTemplateVerificationVariantJob($run->id, (int) $run->attempt, 'rotation', (int) $run->optimistic_version), 'handle']);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY)
        ->and($run->captures()->count())->toBe(0);
});

test('partial failure at variant preserves prior captures and retrying finishes full chain', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    // Worker succeeds for clean (1) only, then fails on perspective (2)
    durableRunFakeWorker(1);
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    durableRunJobInline($run->id);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->attempt)->toBe(1)
        ->and($run->captures()->count())->toBe(14); // All variants executed and recorded
    // Retry the run with Queue fake to verify queued status before draining
    Queue::fake();
    durableRunFakeWorker(7);
    $retryResponse = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.retry', $run))
        ->assertStatus(202)
        ->json();

    $retried = $run->fresh();
    expect($retried->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($retried->attempt)->toBe(2);
    Queue::assertPushed(RunOmrTemplateVerificationJob::class, 1);

    // Drain attempt 2 to completion
    durableRunFakeWorker(7);
    app()->instance(OmrDetectionPort::class, new DurableRunDetectorFake);
    durableRunJobInline($retried->id);

    $retried = $retried->fresh();
    expect($retried->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($retried->attempt)->toBe(2)
        ->and($retried->captures()->where('attempt', 2)->count())->toBe(14)
        ->and($retried->captures()->where('attempt', 1)->count())->toBe(14) // attempt 1 captures preserved
        ->and($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED);
});

test('multi-page digital verification advances through every page matrix', function (): void {
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $authoring = (array) $draft->authoring_json;
    $authoring['pages'][] = ['page_no' => 2, 'paper' => ['width_mm' => 210, 'height_mm' => 297]];
    $authoring['mapping']['input']['page_count'] = 2;
    $draft->forceFill(['requested_page_count' => 2, 'authoring_json' => $authoring])->save();
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'attempt' => 1,
        'plan_version' => 'v3',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'progress_done' => 13,
        'progress_total' => 28,
        'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'page-1:multipage_order', 'state' => 'ready'],
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);
    $service = app(OmrVerificationRunService::class);
    $claimed = $service->claimVariant($run->id, 1, 'page-1:multipage_order', 0);

    expect($claimed)->not->toBeNull();

    $advanced = $service->completeVariant(
        $run->id,
        1,
        'page-1:multipage_order',
        (int) $claimed->optimistic_version,
        static function (): void {},
    );

    expect($advanced?->progress_done)->toBe(14)
        ->and($advanced?->progress_total)->toBe(28)
        ->and($advanced?->phase_detail['current_variant'])->toBe('page-2:clean')
        ->and($advanced?->phase_detail['state'])->toBe('ready');
});

test('document-bound digital cursor advances its eight-variant matrix per page', function (): void {
    $actor = durableRunCapableUser();
    $draft = durableRunDraft($actor);
    $authoring = (array) $draft->authoring_json;
    $authoring['pages'][] = ['page_no' => 2, 'paper' => ['width_mm' => 210, 'height_mm' => 297]];
    $authoring['mapping']['input']['page_count'] = 2;
    $draft->forceFill(['registration_mode' => OmrRegistrationMode::DocumentBound, 'requested_page_count' => 2, 'authoring_json' => $authoring])->save();
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_CLEAN_RECOVERY,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'attempt' => 1,
        'plan_version' => 'v3',
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('a', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'mapping_hash' => str_repeat('d', 64),
        'progress_done' => 0,
        'progress_total' => 16,
        'phase_detail' => ['stage' => 'clean_recovery', 'current_variant' => 'page-1:clean', 'state' => 'ready'],
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'last_heartbeat_at' => now(),
    ]);
    $service = app(OmrVerificationRunService::class);
    expect($service->claimVariant($run->id, 1, 'perspective', 0))->toBeNull();
    $claimed = $service->claimVariant($run->id, 1, 'page-1:clean', 0);
    expect($claimed)->not->toBeNull();

    $advanced = $service->completeVariant($run->id, 1, 'page-1:clean', (int) $claimed->optimistic_version, static function (): void {});
    expect($advanced?->progress_done)->toBe(1)
        ->and($advanced?->progress_total)->toBe(16)
        ->and($advanced?->phase_detail['current_variant'])->toBe('page-1:mild_shear');
    $advanced->forceFill([
        'progress_done' => 8,
        'phase_detail' => ['stage' => 'variant_matrix', 'current_variant' => 'page-2:clean', 'state' => 'ready'],
    ])->save();
    $pageTwo = $service->claimVariant($run->id, 1, 'page-2:clean', (int) $advanced->optimistic_version);
    expect($pageTwo)->not->toBeNull();
    expect($service->completeVariant($run->id, 1, 'page-2:clean', (int) $pageTwo->optimistic_version, static function (): void {})?->phase_detail['current_variant'])->toBe('page-2:mild_shear');
});
