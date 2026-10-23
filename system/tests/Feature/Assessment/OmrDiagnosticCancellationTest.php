<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Camera\OmrCameraFrameReservation;
use App\Assessment\Actions\Omr\Operational\CancelOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CaptureOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompareOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\RecordOmrDiagnosticItemReview;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Support\TestKitMockSession;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * @return array{fixture: array<string, mixed>, actor: User, version: TestKitVersion}
 */
function omrCancellationMaterials(TestCase $test): array
{
    $fixture = buildOmrGradingFixture($test);
    $actor = $fixture['guidance'];
    require_once base_path('tests/Support/TestKitHelpers.php');
    $kit = testKitAcceptanceFixture($actor, Institution::current());

    return [
        'fixture' => $fixture,
        'actor' => $actor,
        'version' => $kit['version'],
    ];
}

function omrCancellationAuthorizeStaffSession(TestCase $test, User $user): void
{
    StaffAuthenticationState::query()->updateOrCreate(
        ['user_id' => $user->id],
        [
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'session_version' => 1,
            'mfa_required' => false,
            'must_change_password' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_generation' => (string) Str::uuid(),
        ],
    );

    $test->actingAs($user);
}

function omrCancellationStartMockRun(
    TestCase $test,
    User $actor,
    TestKitVersion $version,
    ?string $idempotencyKey = null,
): OmrDiagnosticRun {
    $payload = [
        'test_kit_version_id' => $version->id,
        'expected_revision' => $version->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
    ];
    $guide = $test->actingAs($actor)->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', $payload))
        ->assertOk();
    $started = $test->actingAs($actor)->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        ...$payload,
        'diagnostic_purpose' => OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL,
        'expected_mock_manifest_sha256' => $guide->json('manifest_sha256'),
        'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
    ])->assertOk();

    return OmrDiagnosticRun::query()->where('public_id', $started->json('context.public_id'))->firstOrFail();
}

function omrCancellationStartMaterialDiagnostic(TestCase $test, array $fixture): OmrDiagnosticRun
{
    $pair = canonicalGradingBooklet($fixture['guidance']);
    $started = $test->actingAs($fixture['guidance'])->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        'template_version_id' => $pair->template_version_id,
        'booklet_version_id' => $pair->booklet_version_id,
        'declared_set_code' => 'A',
    ])->assertOk();

    return OmrDiagnosticRun::query()->where('public_id', $started->json('context.public_id'))->firstOrFail();
}

/** @return array{expected_revision: int, reason: string, idempotency_key: string} */
function omrCancellationPayload(
    OmrDiagnosticRun $run,
    ?int $expectedRevision = null,
    ?string $idempotencyKey = null,
    string $reason = 'Staff cancelled the Mock rehearsal.',
): array {
    return [
        'expected_revision' => $expectedRevision ?? (int) $run->revision,
        'reason' => $reason,
        'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
    ];
}

function postOmrDiagnosticCancellation(
    TestCase $test,
    User $actor,
    OmrDiagnosticRun $run,
    array $payload,
): TestResponse {
    return $test->actingAs($actor)->postJson(
        route('setup.exam-materials.omr-test.runs.cancel', ['run' => $run->public_id]),
        $payload,
    );
}

function createOmrCancellationPendingSet(
    OmrDiagnosticRun $run,
    User $actor,
    string $status = OmrPendingCaptureSet::STATUS_PENDING,
): OmrPendingCaptureSet {
    $now = now();
    $id = DB::table('omr_pending_capture_sets')->insertGetId([
        'public_id' => (string) Str::uuid(),
        'diagnostic_run_id' => $run->id,
        'institution_id' => $run->institution_id,
        'academic_cycle_id' => $run->academic_cycle_id,
        'template_version_id' => $run->template_version_id,
        'created_by_user_id' => $actor->id,
        'registration_mode' => 'document_bound',
        'copy_policy' => 'diagnostic_only',
        'identity_mode' => 'sheet_bound',
        'expected_pages_json' => json_encode([['page_no' => 1]], JSON_THROW_ON_ERROR),
        'page_count' => 1,
        'active_page_no' => 1,
        'pages_hash' => hash('sha256', 'cancellation-pages-'.$run->id.'-'.$status),
        'native_bundle_hash' => hash('sha256', 'cancellation-bundle-'.$run->id.'-'.$status),
        'artifact_hash' => hash('sha256', 'cancellation-artifact-'.$run->id.'-'.$status),
        'idempotency_key' => (string) Str::uuid(),
        'payload_fingerprint' => hash('sha256', 'cancellation-set-'.$run->id.'-'.$status),
        'revision' => 1,
        'status' => $status,
        'expires_at' => now()->addHour(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return OmrPendingCaptureSet::query()->findOrFail($id);
}

/** @return array{capture: OmrDiagnosticCapture, asset: OmrTemplateAsset, path: string, bytes: string} */
function createOmrCancellationCapture(
    OmrDiagnosticRun $run,
    User $actor,
    string $status,
): array {
    $store = app(SensitiveAssetStore::class);
    $bytes = physicalChallengePng();
    $clientCaptureId = (string) Str::uuid();
    $path = 'omr/diagnostic/'.$run->public_id.'/'.$clientCaptureId.'_input.bin';
    $store->store($bytes, $path);
    $hash = hash('sha256', $bytes);
    $asset = OmrTemplateAsset::query()->create([
        'template_version_id' => $run->template_version_id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => $path,
        'mime' => 'image/png',
        'bytes' => strlen($bytes),
        'sha256' => $hash,
        'metadata' => [
            'source' => 'omr_diagnostic',
            'role' => 'capture_input',
            'diagnostic_run_id' => (int) $run->id,
            'hmac' => $store->hmac($bytes),
        ],
    ]);
    $evidence = $status === OmrDiagnosticCapture::STATUS_FAILED
        ? ['failure_code' => 'analysis_failed', 'items' => []]
        : null;
    $capture = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $run->id,
        'client_capture_id' => $clientCaptureId,
        'status' => $status,
        'version' => $status === OmrDiagnosticCapture::STATUS_PROCESSING ? 0 : 1,
        'input_asset_id' => $asset->id,
        'input_asset_hash' => $hash,
        'evidence_json' => $evidence,
        'evidence_hash' => $evidence === null ? null : hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
        'failure_code' => $status === OmrDiagnosticCapture::STATUS_FAILED ? 'analysis_failed' : null,
        'failure_detail' => $status === OmrDiagnosticCapture::STATUS_FAILED ? 'Recognition did not finish.' : null,
        'created_by_user_id' => $actor->id,
        'captured_at' => now(),
        'completed_at' => $status === OmrDiagnosticCapture::STATUS_FAILED ? now() : null,
    ]);

    return compact('capture', 'asset', 'path', 'bytes');
}

test('cancelling a Mock run preserves customer-visible evidence and only invalidates its live pending sets', function (): void {
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $unrelatedRun = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $stored = createOmrCancellationCapture($run, $actor, OmrDiagnosticCapture::STATUS_FAILED);
    $pendingSet = createOmrCancellationPendingSet($run, $actor);
    $readySet = createOmrCancellationPendingSet($run, $actor, OmrPendingCaptureSet::STATUS_READY);
    $unrelatedPendingSet = createOmrCancellationPendingSet($unrelatedRun, $actor);
    $payload = omrCancellationPayload($run);
    $auditCount = DB::table('audit_events')->where('event_type', 'omr.diagnostic.cancelled')->count();

    $this->actingAs($actor)
        ->getJson(route('setup.exam-materials.omr-test.runs.show', ['run' => $run->public_id]))
        ->assertOk()
        ->assertJsonPath('context.public_id', $run->public_id)
        ->assertJsonPath('commands', fn (array $commands): bool => in_array('diagnostic_cancel', $commands, true));

    $response = postOmrDiagnosticCancellation($this, $actor, $run, $payload)
        ->assertOk()
        ->assertJsonPath('workbench_id', 'diagnostic:'.$run->public_id)
        ->assertJsonPath('context.status', OmrDiagnosticRun::STATUS_CANCELLED)
        ->assertJsonPath('context.raw_phase', OmrDiagnosticRun::PHASE_COMPLETE)
        ->assertJsonPath('phase', 'complete')
        ->assertJsonPath('completion.kind', 'diagnostic_cancellation')
        ->assertJsonPath('completion.outcome', 'cancelled')
        ->assertJsonPath('recovery.outcome.kind', 'success')
        ->assertJsonFragment(['capture_id' => (int) $stored['capture']->id]);

    expect($response->json('context.completed_at'))->toBeString()->not->toBeEmpty()
        ->and($response->json('commands'))->not->toContain('diagnostic_cancel')
        ->and($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_CANCELLED)
        ->and($run->fresh()->completed_at)->not->toBeNull()
        ->and($stored['capture']->fresh()->evidence_json)->toBe($stored['capture']->evidence_json)
        ->and($stored['capture']->fresh()->input_asset_id)->toBe($stored['asset']->id)
        ->and($pendingSet->fresh()->status)->toBe(OmrPendingCaptureSet::STATUS_CANCELLED)
        ->and($pendingSet->fresh()->revision)->toBe(2)
        ->and($readySet->fresh()->status)->toBe(OmrPendingCaptureSet::STATUS_READY)
        ->and($readySet->fresh()->revision)->toBe(1)
        ->and($unrelatedPendingSet->fresh()->status)->toBe(OmrPendingCaptureSet::STATUS_PENDING)
        ->and($unrelatedPendingSet->fresh()->revision)->toBe(1);

    $this->actingAs($actor)
        ->get(route('setup.exam-materials.omr-test.runs.asset', [
            'run' => $run->public_id,
            'asset' => $stored['asset']->id,
        ]))
        ->assertOk()
        ->assertContent($stored['bytes']);

    $receipt = DB::table('omr_command_receipts')
        ->where('actor_id', $actor->id)
        ->where('command_name', 'omr.diagnostic.cancel')
        ->where('aggregate_id', (string) $run->id)
        ->where('idempotency_key', $payload['idempotency_key'])
        ->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe('succeeded')
        ->and(DB::table('audit_events')->where('event_type', 'omr.diagnostic.cancelled')->count())->toBe($auditCount + 1);

    $audit = DB::table('audit_events')
        ->where('event_type', 'omr.diagnostic.cancelled')
        ->where('actor', (string) $actor->id)
        ->latest('created_at')
        ->first();
    expect($audit)->not->toBeNull()
        ->and(json_decode((string) $audit->payload, true)['reason'])->toBe($payload['reason']);

    postOmrDiagnosticCancellation($this, $actor, $run, $payload)->assertOk()
        ->assertJsonPath('context.status', OmrDiagnosticRun::STATUS_CANCELLED)
        ->assertJsonPath('recovery.outcome.kind', 'success');
    expect(DB::table('audit_events')->where('event_type', 'omr.diagnostic.cancelled')->count())->toBe($auditCount + 1)
        ->and($run->fresh()->revision)->toBe((int) $response->json('revision'));

    postOmrDiagnosticCancellation($this, $actor, $run, [
        ...$payload,
        'reason' => 'Different cancellation reason.',
    ])->assertConflict()->assertJsonPath('code', 'omr_diagnostic_cancel_idempotency_conflict');

    $guidePayload = [
        'test_kit_version_id' => $materials['version']->id,
        'expected_revision' => $materials['version']->revision,
        'instance_count' => 2,
        'pattern_mode' => 'perfect_set_a',
    ];
    $guide = $this->actingAs($actor)
        ->getJson(route('setup.exam-materials.omr-test.runs.mock-guide', $guidePayload))
        ->assertOk();
    $this->actingAs($actor)->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        ...$guidePayload,
        'diagnostic_purpose' => OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL,
        'expected_mock_manifest_sha256' => $guide->json('manifest_sha256'),
        'idempotency_key' => (string) Str::uuid(),
        'resume_run_public_id' => $run->public_id,
    ])->assertConflict()->assertJsonPath('code', 'omr_mock_resume_context_mismatch');

    $launchedAfterCancellation = omrCancellationStartMockRun($this, $actor, $materials['version']);
    expect($launchedAfterCancellation->public_id)->not->toBe($run->public_id)
        ->and($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_CANCELLED);
});

test('cancellation rejects stale revisions, non-Mock diagnostics, and terminal runs', function (): void {
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);

    postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload(
        $run,
        expectedRevision: (int) $run->revision + 1,
    ))->assertConflict()->assertJsonPath('code', 'omr_diagnostic_run_revision_stale');
    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and(DB::table('omr_command_receipts')->where('command_name', 'omr.diagnostic.cancel')->count())->toBe(0);

    postOmrDiagnosticCancellation(
        $this,
        $materials['fixture']['registrar'],
        $run,
        omrCancellationPayload($run),
    )->assertForbidden();
    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN);

    $materialRun = omrCancellationStartMaterialDiagnostic($this, $materials['fixture']);
    postOmrDiagnosticCancellation($this, $actor, $materialRun, omrCancellationPayload($materialRun))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'omr_diagnostic_cancel_purpose_invalid');
    expect($materialRun->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN);

    $success = postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload($run))->assertOk();
    $cancelledRun = $run->fresh();
    postOmrDiagnosticCancellation($this, $actor, $cancelledRun, omrCancellationPayload(
        $cancelledRun,
        idempotencyKey: (string) Str::uuid(),
    ))->assertConflict()->assertJsonPath('code', 'omr_diagnostic_run_terminal');
    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_CANCELLED)
        ->and($run->fresh()->revision)->toBe((int) $success->json('revision'));
});

test('cancellation rejects processing captures from unselected Mock history', function (): void {
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $capture = createOmrCancellationCapture($run, $actor, OmrDiagnosticCapture::STATUS_PROCESSING)['capture'];

    expect(app(TestKitMockSession::class)->currentCaptureIds($run))->not->toContain((int) $capture->id);

    postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload($run))
        ->assertConflict()
        ->assertJsonPath('code', 'omr_diagnostic_capture_active');
    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and($capture->fresh()->status)->toBe(OmrDiagnosticCapture::STATUS_PROCESSING)
        ->and(DB::table('omr_command_receipts')->where('command_name', 'omr.diagnostic.cancel')->count())->toBe(0);
});

test('cancellation rejects unresolved camera image receipts independently of current selection', function (): void {
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $operationId = (string) Str::uuid();
    OmrCameraCommand::query()->create([
        'operation_id' => $operationId,
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => hash('sha256', 'diagnostic:'.$run->public_id),
        'actor_id' => $materials['fixture']['registrar']->id,
        'receipt_key' => hash('sha256', 'unresolved-'.$operationId),
        'payload_json' => ['workbench' => ['diagnostic_run_id' => (int) $run->id]],
        'payload_fingerprint' => hash('sha256', 'unresolved-'.$operationId),
        'issued_at' => now(),
        'domain_outcome' => 'uncertain',
    ]);

    postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload($run))
        ->assertConflict()
        ->assertJsonPath('code', 'omr_diagnostic_cancel_outcome_unknown');
    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and(DB::table('omr_command_receipts')->where('command_name', 'omr.diagnostic.cancel')->count())->toBe(0);
});

test('cancellation rejects an unacknowledged camera shutter and an acquired remote frame', function (): void {
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $workbenchId = 'diagnostic:'.$run->public_id;
    $workbenchKey = hash('sha256', $workbenchId);
    $pairing = OmrCameraPairing::query()->create([
        'public_id' => (string) Str::uuid(),
        'operator_id' => $actor->id,
        'institution_id' => $run->institution_id,
        'academic_cycle_id' => $run->academic_cycle_id,
        'diagnostic_run_id' => $run->id,
        'workbench_id' => $workbenchId,
        'workbench_key' => $workbenchKey,
        'absolute_expires_at' => now()->addHour(),
        'config_json' => [],
    ]);
    $unacknowledged = OmrCameraCommand::query()->create([
        'pairing_id' => $pairing->id,
        'operation_id' => (string) Str::uuid(),
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'sequence' => 1,
        'kind' => 'CAPTURE',
        'workbench_key' => $workbenchKey,
        'actor_id' => $actor->id,
        'payload_json' => [],
        'payload_fingerprint' => hash('sha256', 'unacknowledged-shutter'),
        'target_json' => [],
        'issued_at' => now(),
        'execution_expires_at' => now()->addMinute(),
    ]);

    postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload($run))
        ->assertConflict()
        ->assertJsonPath('code', 'omr_diagnostic_cancel_outcome_unknown');

    $unacknowledged->forceFill([
        'device_outcome' => 'rejected',
        'completed_at' => now(),
    ])->saveOrFail();
    $acquiredCommand = OmrCameraCommand::query()->create([
        'pairing_id' => $pairing->id,
        'operation_id' => (string) Str::uuid(),
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'sequence' => 2,
        'kind' => 'CAPTURE',
        'workbench_key' => $workbenchKey,
        'actor_id' => $actor->id,
        'payload_json' => [],
        'payload_fingerprint' => hash('sha256', 'acquired-frame'),
        'target_json' => [],
        'issued_at' => now(),
        'execution_expires_at' => now()->addMinute(),
        'device_outcome' => 'acquired',
        'device_outcome_json' => ['status' => 'acquired'],
    ]);
    OmrCameraFrame::query()->create([
        'public_id' => (string) Str::uuid(),
        'pairing_id' => $pairing->id,
        'command_id' => $acquiredCommand->id,
        'node_generation' => (int) $pairing->node_generation,
        'target_json' => [],
        'target_key' => 'target',
        'config_revision' => (int) $pairing->config_revision,
        'source_generation' => (int) $pairing->source_generation,
        'input_sha256' => hash('sha256', 'private-frame'),
        'byte_count' => 13,
        'mime_type' => 'image/png',
        'width' => 10,
        'height' => 10,
        'orientation' => 1,
        'state' => OmrCameraFrame::STATE_ACQUIRED,
        'acquired_at' => now(),
        'upload_expires_at' => now()->addMinutes(5),
    ]);

    $activeFrameProjection = $this->actingAs($actor)
        ->getJson(route('setup.exam-materials.omr-test.runs.show', ['run' => $run->public_id]))
        ->assertOk();
    expect($activeFrameProjection->json('commands'))->toContain('capture_start')
        ->and($activeFrameProjection->json('commands'))->not->toContain('diagnostic_cancel');

    postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload($run))
        ->assertConflict()
        ->assertJsonPath('code', 'omr_diagnostic_cancel_outcome_unknown');
    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and(DB::table('omr_command_receipts')->where('command_name', 'omr.diagnostic.cancel')->count())->toBe(0);
});

test('late capture and compare commands cannot mutate a cancelled Mock run', function (): void {
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $stored = createOmrCancellationCapture($run, $actor, OmrDiagnosticCapture::STATUS_FAILED);
    postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload($run))->assertOk();
    $cancelled = $run->fresh();
    $captureCount = OmrDiagnosticCapture::query()->where('diagnostic_run_id', $run->id)->count();

    expect(fn () => app(CaptureOmrDiagnosticRun::class)->handle(
        $cancelled,
        physicalChallengePng(),
        'image/png',
        (string) Str::uuid(),
        $actor,
        (int) $cancelled->revision,
        (string) Str::uuid(),
        caseId: 'instance-001',
        targetRevision: (int) $cancelled->revision,
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_run_terminal');

    expect(fn () => app(CaptureOmrDiagnosticRun::class)->retry(
        $cancelled,
        $stored['capture'],
        $actor,
        (int) $cancelled->revision,
        idempotencyKey: (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_run_terminal');

    $itemEvidenceHash = hash('sha256', 'late-review-after-cancellation');
    $item = OmrDiagnosticItemEvidence::query()->create([
        'diagnostic_capture_id' => $stored['capture']->id,
        'item_key' => 'PROFILE_VERBAL_Q_001',
        'page_no' => 1,
        'area_code' => 'PROFILE_VERBAL',
        'question_no' => 1,
        'option_code' => 'A',
        'evidence_hash' => $itemEvidenceHash,
        'evidence_version' => 'v1',
    ]);
    expect(fn () => app(RecordOmrDiagnosticItemReview::class)->handle(
        $item,
        OmrReviewDecision::ConfirmMachine,
        (int) $cancelled->revision,
        (int) $stored['capture']->version,
        0,
        1,
        $itemEvidenceHash,
        (string) Str::uuid(),
        $actor,
        finalOption: 'A',
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_run_not_reviewable');

    $workbenchId = 'diagnostic:'.$run->public_id;
    $operationId = (string) Str::uuid();
    $fingerprint = hash('sha256', 'late-camera-reservation');
    expect(fn () => app(OmrCameraFrameFinalization::class)->reserveOperation(
        $actor,
        hash('sha256', $workbenchId),
        [
            'workbench_id' => $workbenchId,
            'diagnostic_run_id' => (int) $run->id,
            'revision' => (int) $cancelled->revision,
        ],
        [],
        $operationId,
        (string) Str::uuid(),
        $fingerprint,
        'auto_document_quad',
        null,
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_run_terminal');

    $finalizationOperationId = (string) Str::uuid();
    $finalizationReceiptKey = hash('sha256', 'late-finalization-'.$finalizationOperationId);
    expect(fn () => DB::transaction(function () use (
        $actor,
        $cancelled,
        $finalizationOperationId,
        $finalizationReceiptKey,
        $workbenchId,
    ): void {
        $finalizationFingerprint = hash('sha256', 'late-camera-finalization');
        $leaseToken = (string) Str::uuid();
        $operation = OmrCameraCommand::query()->create([
            'operation_id' => $finalizationOperationId,
            'command_id' => (string) Str::uuid(),
            'issuer_role' => 'controller',
            'kind' => 'CAPTURE_RECEIPT',
            'workbench_key' => hash('sha256', $workbenchId),
            'actor_id' => $actor->id,
            'receipt_key' => $finalizationReceiptKey,
            'payload_json' => [
                'workbench' => [
                    'workbench_id' => $workbenchId,
                    'diagnostic_run_id' => (int) $cancelled->id,
                ],
            ],
            'payload_fingerprint' => $finalizationFingerprint,
            'issued_at' => now(),
            'domain_outcome' => 'reserved',
            'finalization_lease_token' => $leaseToken,
            'finalization_lease_expires_at' => now()->addMinute(),
            'finalization_absolute_expires_at' => now()->addMinutes(2),
            'finalization_lease_generation' => 1,
        ]);
        app(OmrCameraFrameFinalization::class)->commitAccepted(
            new OmrCameraFrameReservation($operation, null, $leaseToken, $finalizationFingerprint, leaseGeneration: 1),
            ['status' => 'accepted'],
        );
    }))->toThrow(RuntimeException::class, 'omr_diagnostic_run_terminal');
    expect(OmrCameraCommand::query()->where('receipt_key', $finalizationReceiptKey)->exists())->toBeFalse();

    expect(fn () => app(CompareOmrDiagnosticRun::class)->handle(
        $cancelled,
        $actor,
        (int) $cancelled->revision,
        (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_run_not_comparable')
        ->and($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_CANCELLED)
        ->and($run->fresh()->revision)->toBe((int) $cancelled->revision)
        ->and(OmrDiagnosticCapture::query()->where('diagnostic_run_id', $run->id)->count())->toBe($captureCount)
        ->and(OmrDiagnosticReviewRevision::query()->where('diagnostic_item_evidence_id', $item->id)->count())->toBe(0);
});

test('same-capability Guidance actor cannot cancel another owner Mock run', function (): void {
    $materials = omrCancellationMaterials($this);
    $owner = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $owner, $materials['version']);
    $otherGuidance = User::factory()->create(['email' => 'guidance.cancel-other-owner@example.test']);
    $staffAttributes = $owner->staffProfile->getAttributes();
    unset($staffAttributes['id'], $staffAttributes['user_id'], $staffAttributes['created_at'], $staffAttributes['updated_at']);
    $otherGuidance->staffProfile()->create($staffAttributes);
    $otherGuidance->syncRoles($owner->getRoleNames());
    $otherGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $this->actingAs($otherGuidance);
    omrCancellationAuthorizeStaffSession($this, $otherGuidance);

    $this->postJson(route('setup.exam-materials.omr-test.runs.cancel', ['run' => $run->public_id]), omrCancellationPayload($run))
        ->assertNotFound()
        ->assertJsonPath('code', 'omr_diagnostic_owner_mismatch');
    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and(DB::table('omr_command_receipts')->where('command_name', 'omr.diagnostic.cancel')->count())->toBe(0);
});

test('cancelling an unknown Mock run returns not found without changing an existing run', function (): void {
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $this->actingAs($actor);
    omrCancellationAuthorizeStaffSession($this, $actor);

    $this->postJson(route('setup.exam-materials.omr-test.runs.cancel', ['run' => (string) Str::uuid()]), omrCancellationPayload($run))
        ->assertNotFound()
        ->assertJsonPath('code', 'omr_diagnostic_run_not_found');

    expect($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and(DB::table('omr_command_receipts')->where('command_name', 'omr.diagnostic.cancel')->count())->toBe(0);
});

test('cancelling a Mock run revokes only its camera pairing and blocks later join, signal, and heartbeat', function (): void {
    config(['omr-camera.remote_enabled' => true]);
    $materials = omrCancellationMaterials($this);
    $actor = $materials['actor'];
    $run = omrCancellationStartMockRun($this, $actor, $materials['version']);
    $otherRun = omrCancellationStartMockRun($this, $actor, $materials['version']);
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

    postOmrDiagnosticCancellation($this, $actor, $run, omrCancellationPayload($run))->assertOk();

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
