<?php

use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\RetryOmrTemplateVerification;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Jobs\Assessment\AnalyzeOmrTemplateVerificationCaptureJob;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

function methodIndependenceSetupBothMethods(User $actor, int $pageCount = 1): array
{
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
    ]);
    $draft = physicalChallengeDraft($actor, $pageCount);

    $createAction = app(CreateOmrPhysicalChallenge::class);
    $preResult = $createAction->handle($draft, $actor, 'quick');
    $run = $preResult['run'];
    $preChallenge = $preResult['challenge'];

    // Create hand_shaded challenge on the same run
    $handResult = $createAction->handle($draft, $actor, 'quick', 'hand_shaded');
    $handChallenge = $handResult['challenge'];
    $run = $handResult['run']->fresh();

    $coordinator = app(OmrPhysicalAssuranceCoordinator::class);
    // Mark both challenges printed to reach awaiting_capture
    $coordinator->markPrinted($run, $actor, 'pre_shaded', $preChallenge->id);
    $coordinator->markPrinted($run, $actor, 'hand_shaded', $handChallenge->id);

    return [
        'draft' => $draft,
        'run' => $run->fresh(),
        'preChallenge' => $preChallenge->fresh(),
        'handChallenge' => $handChallenge->fresh(),
        'coordinator' => $coordinator,
    ];
}
function methodIndependencePrintPayload(OmrTemplateVerificationRun $run, string $method = 'pre_shaded', ?string $key = null): array
{
    $methodRow = $run->methods()->where('method', $method)->firstOrFail();
    $challenge = $run->challenges()->where('method', $method)->latest('id')->firstOrFail();

    return [
        'method' => $method,
        'challenge_id' => (int) $challenge->id,
        'target_hash' => (string) $methodRow->target_hash,
        'plan_hash' => (string) $methodRow->plan_hash,
        'expected_vector_hash' => (string) $methodRow->expected_vector_hash,
        'idempotency_key' => $key ?? 'print-'.$run->id.'-'.$method,
    ];
}
function methodIndependenceChallengePlanForDraft(OmrTemplateAuthoringDraft $draft, ProofPurpose $purpose, string $method = 'pre_shaded'): OmrProofPlan
{
    $profile = $draft->markerProfile;
    $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
    $materialized = app(CanonicalArtifactMaterializer::class)->materialize($draft->fresh(['markerProfile']), $profile, $candidateVersion > 0 ? $candidateVersion : null);
    $target = new CandidateArtifactDiagnosticTarget($draft->fresh(['markerProfile']), $materialized, $profile);

    return app(OmrProofPlanner::class)->plan($target, $purpose, OmrProofPlanner::CURRENT_PLAN_VERSION, $method);
}

function methodIndependenceCapturePayload(OmrTemplateVerificationRun $run, string $method, $file, array $overrides = []): array
{
    $print = methodIndependencePrintPayload($run, $method);
    $payload = [
        'image' => $file,
        'capture_source' => 'upload',
        'method' => $method,
        'challenge_id' => $print['challenge_id'],
        'page_no' => 1,
        'target_hash' => $print['target_hash'],
        'plan_hash' => $print['plan_hash'],
        'expected_vector_hash' => $print['expected_vector_hash'],
        'idempotency_key' => 'cap-'.uniqid(),
    ];
    if ($method === 'hand_shaded') {
        $payload['medium'] = 'hb_2_pencil';
    }

    return array_merge($payload, $overrides);
}

test('1.2: pre_shaded capture failure does not terminalize parent run and leaves hand_shaded capture actionable', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $preChallenge = $setup['preChallenge'];
    $handChallenge = $setup['handChallenge'];

    // 1. Fail a pre_shaded capture (e.g. marker failure)
    physicalChallengeFakeWorker(false, ['code' => 'marker_missing', 'detail' => 'Corners not found']);
    $bytes = physicalChallengePng();

    $preCaptureResponse = $this->actingAs($actor)->post(
        route('setup.omr.templates.canonical.verification_runs.capture', $run),
        methodIndependenceCapturePayload($run, 'pre_shaded', UploadedFile::fake()->createWithContent('pre_fail.png', $bytes), ['idempotency_key' => 'pre-fail-key']),
        ['Accept' => 'application/json']
    );

    expect($preCaptureResponse->status())->toBe(201);

    $run = $run->fresh();
    $preMethod = $run->methods()->where('method', 'pre_shaded')->first();
    $handMethod = $run->methods()->where('method', 'hand_shaded')->first();

    // The pre_shaded method failed on this attempt
    expect($preMethod->method_state)->toBe(OmrTemplateVerificationMethod::STATE_FAILED);
    // Crucial: Parent run MUST NOT be failed because hand_shaded is still awaiting capture!
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->finished_at)->toBeNull()
        ->and($handMethod->method_state)->toBe(OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE);

    // 2. Sibling hand_shaded method can now be captured cleanly
    $handPlan = methodIndependenceChallengePlanForDraft($setup['draft']->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($handPlan->expectedVector, 'exact'));
    physicalChallengeFakeWorker(true);
    $handCaptureResponse = $this->actingAs($actor)->post(
        route('setup.omr.templates.canonical.verification_runs.capture', $run),
        methodIndependenceCapturePayload($run, 'hand_shaded', UploadedFile::fake()->createWithContent('hand_pass.png', $bytes), ['idempotency_key' => 'hand-pass-key']),
        ['Accept' => 'application/json']
    );

    expect($handCaptureResponse->status())->toBe(201);
});

test('status refresh preserves the failed method recovery and untouched sibling continuation', function (): void {
    $actor = physicalChallengeActor();
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
    ]);
    $draft = physicalChallengeDraft($actor);
    $preResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $preResult['run'];
    $preChallenge = $preResult['challenge'];
    $coordinator = app(OmrPhysicalAssuranceCoordinator::class);
    $coordinator->markPrinted($run, $actor, 'pre_shaded', $preChallenge->id);
    $run = $run->fresh();

    physicalChallengeFakeWorker(false, [
        'code' => 'marker_mismatch',
        'detail' => 'Corners do not match the challenge geometry.',
    ]);
    $this->actingAs($actor)->post(
        route('setup.omr.templates.canonical.verification_runs.capture', $run),
        methodIndependenceCapturePayload(
            $run,
            'pre_shaded',
            UploadedFile::fake()->createWithContent('pre-fail.png', physicalChallengePng()),
            ['idempotency_key' => 'status-refresh-method-recovery'],
        ),
        ['Accept' => 'application/json'],
    )->assertCreated();

    $statusUrl = route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]);
    $status = $this->actingAs($actor)->getJson($statusUrl)->assertOk()->json();
    $methods = collect($status['methods'])->keyBy('method');

    expect($status['status_url'])->toBe($statusUrl)
        ->and($methods['pre_shaded']['state'])->toBe(OmrTemplateVerificationMethod::STATE_FAILED)
        ->and($methods['pre_shaded']['run_status'])->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($methods['pre_shaded']['status_url'])->toBe($statusUrl)
        ->and($methods['pre_shaded']['available_actions'])->toBe(['retake_page'])
        ->and($methods['pre_shaded']['phase_detail']['phase_code'])->toBe(OmrTemplateVerificationRun::PHASE_FAILED)
        ->and($methods['pre_shaded']['phase_detail']['failed_phase_code'])->toBe(OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION)
        ->and($methods['pre_shaded']['phase_detail']['page_no'])->toBe(1)
        ->and($methods['pre_shaded']['safe_error'])->toBe('Corners do not match the challenge geometry.')
        ->and($methods['pre_shaded']['failure']['code'])->toBe('marker_mismatch')
        ->and($methods['pre_shaded']['failure']['what_happened'])->toBe('Sheet layout verification failed.')
        ->and($methods['pre_shaded']['failure']['observation'])->toBe('Corners do not match the challenge geometry.')
        ->and($methods['pre_shaded']['failure']['recommended_fix'])->toBe('Retake the page square to the camera with all four printed markers visible.')
        ->and($methods['pre_shaded']['failure']['technical_details']['phase_code'])->toBe(OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION)
        ->and($methods['pre_shaded']['attempts'][0]['failure_detail'])->toBe('Corners do not match the challenge geometry.')
        ->and($methods['pre_shaded']['attempts'][0]['phase_detail']['failed_phase_code'])->toBe(OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION)
        ->and($methods['hand_shaded']['state'])->toBe(OmrTemplateVerificationMethod::STATE_NOT_STARTED)
        ->and($methods['hand_shaded']['run_status'])->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($methods['hand_shaded']['status_url'])->toBe($statusUrl)
        ->and($methods['hand_shaded']['available_actions'])->toBe(['create_challenge'])
        ->and($methods['hand_shaded']['phase_detail'])->toBeNull();
});

test('1.2: aggregate run status precedence reflects child methods and aggregate progress counts all required pages', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor, 1);
    $run = $setup['run'];
    $coordinator = $setup['coordinator'];

    // Initial state with 2 methods, 1 page each => total 2 pages
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->progress_total)->toBe(2)
        ->and($run->progress_done)->toBe(0);

    // Pass and select pre_shaded
    $preMethod = $run->methods()->where('method', 'pre_shaded')->first();
    $prePage = $preMethod->pages()->where('page_no', 1)->first();

    $preCapture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $preMethod->id,
        'challenge_id' => $setup['preChallenge']->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => 'pre_shaded',
        'mode' => 'quick_rehearsal',
        'variant' => 'physical_capture',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => (string) $preMethod->target_kind,
        'target_id' => (int) $preMethod->target_id,
        'target_version' => (string) $preMethod->target_version,
        'target_hash' => (string) $preMethod->target_hash,
        'plan_version' => (string) $preMethod->plan_version,
        'plan_seed' => (string) $preMethod->plan_seed,
        'plan_hash' => (string) $preMethod->plan_hash,
        'challenge_hash' => hash('sha256', 'pre'),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => hash('sha256', 'input1'),
        'evidence_hash' => hash('sha256', 'ev1'),
        'expected_vector_hash' => (string) $preMethod->expected_vector_hash,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $coordinator->selectAttempt(
        run: $run,
        actor: $actor,
        methodName: 'pre_shaded',
        pageNo: 1,
        captureId: $preCapture->id,
        reason: '', // First selection does not require reason
    );

    $run = $run->fresh();
    // Pre-shaded is passed, but hand_shaded is still awaiting capture => parent remains awaiting_capture
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->progress_done)->toBe(1)
        ->and($run->progress_total)->toBe(2);

    // Pass and select hand_shaded
    $handMethod = $run->methods()->where('method', 'hand_shaded')->first();
    $handPage = $handMethod->pages()->where('page_no', 1)->first();

    $handCapture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $handMethod->id,
        'challenge_id' => $setup['handChallenge']->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => 'hand_shaded',
        'mode' => 'full_qualification',
        'variant' => 'physical_capture',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => (string) $handMethod->target_kind,
        'target_id' => (int) $handMethod->target_id,
        'target_version' => (string) $handMethod->target_version,
        'target_hash' => (string) $handMethod->target_hash,
        'plan_version' => (string) $handMethod->plan_version,
        'plan_seed' => (string) $handMethod->plan_seed,
        'plan_hash' => (string) $handMethod->plan_hash,
        'challenge_hash' => hash('sha256', 'hand'),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => hash('sha256', 'input2'),
        'evidence_hash' => hash('sha256', 'ev2'),
        'expected_vector_hash' => (string) $handMethod->expected_vector_hash,
        'medium' => 'hb_2_pencil',
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $coordinator->selectAttempt(
        run: $run,
        actor: $actor,
        methodName: 'hand_shaded',
        pageNo: 1,
        captureId: $handCapture->id,
        reason: '',
    );

    $run = $run->fresh();
    // Both methods passed and selected => parent becomes PASSED with finished_at
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->progress_done)->toBe(2)
        ->and($run->progress_total)->toBe(2)
        ->and($run->finished_at)->not->toBeNull();
});

test('1.3: first acceptance does not require free text and defaults structured reason to staff_confirmed_exact_pass', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $coordinator = $setup['coordinator'];
    $preMethod = $run->methods()->where('method', 'pre_shaded')->first();

    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $preMethod->id,
        'challenge_id' => $setup['preChallenge']->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => 'pre_shaded',
        'mode' => 'quick_rehearsal',
        'variant' => 'physical_capture',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => (string) $preMethod->target_kind,
        'target_id' => (int) $preMethod->target_id,
        'target_version' => (string) $preMethod->target_version,
        'target_hash' => (string) $preMethod->target_hash,
        'plan_version' => (string) $preMethod->plan_version,
        'plan_seed' => (string) $preMethod->plan_seed,
        'plan_hash' => (string) $preMethod->plan_hash,
        'challenge_hash' => hash('sha256', 'chal'),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => hash('sha256', 'in'),
        'evidence_hash' => hash('sha256', 'ev'),
        'expected_vector_hash' => (string) $preMethod->expected_vector_hash,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $page = $coordinator->selectAttempt(
        run: $run,
        actor: $actor,
        methodName: 'pre_shaded',
        pageNo: 1,
        captureId: $capture->id,
        reason: '   ', // whitespace only => should default
    );

    expect($page->selected_attempt_id)->toBe($capture->id);
    $capture = $capture->fresh();
    expect($capture->selection_reason)->toBe('staff_confirmed_exact_pass');
});

test('1.3: replacement of an existing selected pass requires non-empty reason and supersedes old pass', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $coordinator = $setup['coordinator'];
    $preMethod = $run->methods()->where('method', 'pre_shaded')->first();

    $capture1 = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $preMethod->id,
        'challenge_id' => $setup['preChallenge']->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => 'pre_shaded',
        'mode' => 'quick_rehearsal',
        'variant' => 'physical_capture',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => (string) $preMethod->target_kind,
        'target_id' => (int) $preMethod->target_id,
        'target_version' => (string) $preMethod->target_version,
        'target_hash' => (string) $preMethod->target_hash,
        'plan_version' => (string) $preMethod->plan_version,
        'plan_seed' => (string) $preMethod->plan_seed,
        'plan_hash' => (string) $preMethod->plan_hash,
        'challenge_hash' => hash('sha256', 'c1'),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => hash('sha256', 'in1'),
        'evidence_hash' => hash('sha256', 'ev1'),
        'expected_vector_hash' => (string) $preMethod->expected_vector_hash,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $coordinator->selectAttempt($run, $actor, 'pre_shaded', 1, $capture1->id, '');

    $capture2 = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $preMethod->id,
        'challenge_id' => $setup['preChallenge']->id,
        'attempt' => 1,
        'attempt_no' => 2,
        'page_no' => 1,
        'method' => 'pre_shaded',
        'mode' => 'quick_rehearsal',
        'variant' => 'physical_capture',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => (string) $preMethod->target_kind,
        'target_id' => (int) $preMethod->target_id,
        'target_version' => (string) $preMethod->target_version,
        'target_hash' => (string) $preMethod->target_hash,
        'plan_version' => (string) $preMethod->plan_version,
        'plan_seed' => (string) $preMethod->plan_seed,
        'plan_hash' => (string) $preMethod->plan_hash,
        'challenge_hash' => hash('sha256', 'c2'),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => hash('sha256', 'in2'),
        'evidence_hash' => hash('sha256', 'ev2'),
        'expected_vector_hash' => (string) $preMethod->expected_vector_hash,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    // Replacing without reason MUST fail
    expect(fn () => $coordinator->selectAttempt($run, $actor, 'pre_shaded', 1, $capture2->id, ''))
        ->toThrow(RuntimeException::class, 'replacement_reason_required');

    // Replacing with a reason succeeds and supersedes capture1
    $page = $coordinator->selectAttempt($run, $actor, 'pre_shaded', 1, $capture2->id, 'Higher resolution flatbed scan');

    expect($page->selected_attempt_id)->toBe($capture2->id);
    $capture1 = $capture1->fresh();
    $capture2 = $capture2->fresh();
    expect($capture1->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_SUPERSEDED)
        ->and($capture1->superseded_by_attempt_id)->toBe($capture2->id)
        ->and($capture1->supersede_reason)->toBe('Higher resolution flatbed scan')
        ->and($capture2->selection_reason)->toBe('Higher resolution flatbed scan');
});

test('1.4: route-bound selection ignores missing body capture_id and rejects mismatched capture bindings', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $preMethod = $run->methods()->where('method', 'pre_shaded')->first();

    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'method_id' => $preMethod->id,
        'challenge_id' => $setup['preChallenge']->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => 'pre_shaded',
        'mode' => 'quick_rehearsal',
        'variant' => 'physical_capture',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'target_kind' => (string) $preMethod->target_kind,
        'target_id' => (int) $preMethod->target_id,
        'target_version' => (string) $preMethod->target_version,
        'target_hash' => (string) $preMethod->target_hash,
        'plan_version' => (string) $preMethod->plan_version,
        'plan_seed' => (string) $preMethod->plan_seed,
        'plan_hash' => (string) $preMethod->plan_hash,
        'challenge_hash' => hash('sha256', 'c'),
        'expected' => 'pass',
        'actual' => 'pass',
        'status' => 'pass',
        'input_hash' => hash('sha256', 'i'),
        'evidence_hash' => hash('sha256', 'e'),
        'expected_vector_hash' => (string) $preMethod->expected_vector_hash,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    // POST to route with NO capture_id in the JSON body: MUST succeed
    $response = $this->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.verification_runs.captures.select', ['run' => $run, 'capture' => $capture]),
        [
            'method' => 'pre_shaded',
            'page_no' => 1,
            'idempotency_key' => 'select-route-test',
        ]
    );

    expect($response->status())->toBe(200)
        ->and($response->json('selected_attempt_id'))->toBe($capture->id)
        ->and($response->json('status_url'))->toBe(route('setup.omr.templates.canonical.verification_runs.status', $run));
    // Mismatched method in body vs capture MUST fail with conflict
    $response2 = $this->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.verification_runs.captures.select', ['run' => $run, 'capture' => $capture]),
        [
            'method' => 'hand_shaded', // Wrong method for this capture!
            'page_no' => 1,
            'idempotency_key' => 'select-mismatch-test',
        ]
    );

    expect($response2->status())->toBe(409);
});

test('1.5: coordinator enforces one active capture analysis per run', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $preChallenge = $setup['preChallenge'];

    $coordinator = $setup['coordinator'];
    Queue::fake([AnalyzeOmrTemplateVerificationCaptureJob::class]);
    $bytes = physicalChallengePng();

    // Begin capture 1
    $coordinator->beginCapture(
        run: $run,
        actor: $actor,
        methodName: 'pre_shaded',
        challengeId: $preChallenge->id,
        pageNo: 1,
        bytes: $bytes,
        mime: 'image/png',
        captureSource: 'upload',
        medium: null,
        targetHash: (string) $run->artifact_hash,
        planHash: (string) $preChallenge->plan_hash,
        expectedVectorHash: (string) $preChallenge->expected_vector_hash,
        idempotencyKey: 'active-cap-1',
    );

    // Attempting a second concurrent capture while the first is active MUST fail
    expect(fn () => $coordinator->beginCapture(
        run: $run,
        actor: $actor,
        methodName: 'pre_shaded',
        challengeId: $preChallenge->id,
        pageNo: 1,
        bytes: $bytes,
        mime: 'image/png',
        captureSource: 'upload',
        medium: null,
        targetHash: (string) $run->artifact_hash,
        planHash: (string) $preChallenge->plan_hash,
        expectedVectorHash: (string) $preChallenge->expected_vector_hash,
        idempotencyKey: 'active-cap-2',
    ))->toThrow(RuntimeException::class, 'verification_capture_active');
});

test('4.4: capture worker records truthful phase progression and surfaces in status projection', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $preChallenge = $setup['preChallenge'];

    $plan = methodIndependenceChallengePlanForDraft($setup['draft']->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker(true);

    $bytes = physicalChallengePng();

    $response = $this->actingAs($actor)->post(
        route('setup.omr.templates.canonical.verification_runs.capture', $run),
        methodIndependenceCapturePayload($run, 'pre_shaded', UploadedFile::fake()->createWithContent('phase_test.png', $bytes), ['idempotency_key' => 'phase-prog-key']),
        ['Accept' => 'application/json']
    )->assertCreated();

    $captureId = $response->json('capture_id');
    $capture = OmrTemplateVerificationCapture::query()->findOrFail($captureId);

    $run = $run->fresh();
    expect($run->phase_detail)->toBeArray()
        ->and($run->phase_detail['stage'] ?? null)->toBeIn(['awaiting_review', 'passed'])
        ->and($run->last_heartbeat_at)->not->toBeNull();

    // Status projection exposes the phase detail cleanly
    $statusResponse = $this->actingAs($actor)->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run));
    $statusResponse->assertOk()
        ->assertJsonPath('phase_detail.method', 'pre_shaded');
});

test('child retake is idempotent and stale-page guarded', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $method = $run->methods()->where('method', 'pre_shaded')->firstOrFail();
    $page = $method->pages()->where('page_no', 1)->firstOrFail();
    $method->forceFill(['method_state' => OmrTemplateVerificationMethod::STATE_FAILED])->save();
    $page->forceFill(['page_state' => 'failed', 'page_version' => $page->page_version + 1])->save();
    $setup['preChallenge']->forceFill(['status' => 'failed'])->save();
    $version = (int) $page->fresh()->page_version;
    $url = route('setup.omr.templates.canonical.verification_runs.pages.retake', [
        'run' => $run,
        'method' => 'pre_shaded',
        'page' => 1,
    ]);

    $payload = ['page_version' => $version, 'idempotency_key' => 'child-retake'];
    $this->actingAs($actor)->postJson($url, $payload)
        ->assertOk()
        ->assertJsonPath('page_state', 'awaiting_capture');
    $this->actingAs($actor)->postJson($url, $payload)
        ->assertOk()
        ->assertJsonPath('page_state', 'awaiting_capture');

    expect($method->fresh()->method_state)->toBe(OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE)
        ->and($page->fresh()->page_version)->toBe($version + 1);
});

test('stale capture cannot reopen a passed physical run', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $run->forceFill([
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'finished_at' => now(),
    ])->save();

    expect(fn () => $setup['coordinator']->beginCapture(
        run: $run->fresh(),
        actor: $actor,
        methodName: 'pre_shaded',
        challengeId: $setup['preChallenge']->id,
        pageNo: 1,
        bytes: physicalChallengePng(),
        mime: 'image/png',
        captureSource: 'upload',
        medium: null,
        targetHash: (string) $setup['preChallenge']->target_hash,
        planHash: (string) $setup['preChallenge']->plan_hash,
        expectedVectorHash: (string) $setup['preChallenge']->expected_vector_hash,
        idempotencyKey: 'stale-passed-capture',
    ))->toThrow(RuntimeException::class, 'verification_run_not_awaiting_capture');

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->fresh()->finished_at)->not->toBeNull();
});

test('late worker failure preserves a run-wide timeout', function (): void {
    Queue::fake([AnalyzeOmrTemplateVerificationCaptureJob::class]);
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $reservation = $setup['coordinator']->beginCapture(
        run: $run,
        actor: $actor,
        methodName: 'pre_shaded',
        challengeId: $setup['preChallenge']->id,
        pageNo: 1,
        bytes: physicalChallengePng(),
        mime: 'image/png',
        captureSource: 'upload',
        medium: null,
        targetHash: (string) $setup['preChallenge']->target_hash,
        planHash: (string) $setup['preChallenge']->plan_hash,
        expectedVectorHash: (string) $setup['preChallenge']->expected_vector_hash,
        idempotencyKey: 'late-timeout-capture',
    );
    $run->fresh()->forceFill([
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'error_code' => 'worker_timeout',
        'safe_error' => 'Worker timed out.',
        'finished_at' => now(),
    ])->save();

    $setup['coordinator']->failCapture($reservation['capture'], $actor, 'analysis_failed', 'Late worker result.');
    expect(app(OmrVerificationRunService::class)->syncPhysicalWorkerPhase(
        $run->id,
        $reservation['capture']->id,
        OmrTemplateVerificationRun::STATUS_ANALYZING_CAPTURE,
        ['phase_detail' => ['stage' => 'recognition']],
    ))->toBeFalse();

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->fresh()->error_code)->toBe('worker_timeout')
        ->and($run->fresh()->finished_at)->not->toBeNull();
});

test('worker timeout closes child state and retry accepts the next capture', function (): void {
    Queue::fake([AnalyzeOmrTemplateVerificationCaptureJob::class]);
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $reservation = $setup['coordinator']->beginCapture(
        run: $run,
        actor: $actor,
        methodName: 'pre_shaded',
        challengeId: $setup['preChallenge']->id,
        pageNo: 1,
        bytes: physicalChallengePng(),
        mime: 'image/png',
        captureSource: 'upload',
        medium: null,
        targetHash: (string) $setup['preChallenge']->target_hash,
        planHash: (string) $setup['preChallenge']->plan_hash,
        expectedVectorHash: (string) $setup['preChallenge']->expected_vector_hash,
        idempotencyKey: 'stale-prior-attempt-capture',
    );
    $run->fresh()->forceFill([
        'last_heartbeat_at' => now()->subHours(2),
    ])->save();

    $status = app(OmrTemplateVerificationStatus::class)->get($run->fresh(), $actor);
    $timedOutMethod = $run->methods()->where('method', 'pre_shaded')->firstOrFail();
    $timedOutPage = $timedOutMethod->pages()->where('page_no', 1)->firstOrFail();

    expect($status['status'])->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($status['safe_issue']['code'])->toBe('worker_timeout')
        ->and($reservation['capture']->fresh()->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED)
        ->and($timedOutMethod->method_state)->toBe(OmrTemplateVerificationMethod::STATE_FAILED)
        ->and($timedOutPage->page_state)->toBe('failed')
        ->and($setup['preChallenge']->fresh()->status)->toBe('failed');

    $resumed = app(RetryOmrTemplateVerification::class)->handle(
        $run->fresh(),
        $actor,
        'repair-stale-prior-attempt',
    );
    $method = $resumed->methods()->where('method', 'pre_shaded')->firstOrFail();
    $page = $method->pages()->where('page_no', 1)->firstOrFail();

    expect($resumed->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($resumed->attempt)->toBe(2)
        ->and($method->method_state)->toBe(OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE)
        ->and($page->page_state)->toBe('awaiting_capture')
        ->and($setup['preChallenge']->fresh()->status)->toBe('awaiting_capture');

    $next = $setup['coordinator']->beginCapture(
        run: $resumed,
        actor: $actor,
        methodName: 'pre_shaded',
        challengeId: $setup['preChallenge']->id,
        pageNo: 1,
        bytes: physicalChallengePng(),
        mime: 'image/png',
        captureSource: 'upload',
        medium: null,
        targetHash: (string) $setup['preChallenge']->target_hash,
        planHash: (string) $setup['preChallenge']->plan_hash,
        expectedVectorHash: (string) $setup['preChallenge']->expected_vector_hash,
        idempotencyKey: 'capture-after-stale-repair',
    );

    expect($next['capture']->attempt_no)->toBe(2)
        ->and($next['capture']->attempt)->toBe(2);
});

test('reconciliation re-derives eligible historical child-only failures and remains selective against drifted targets', function (): void {
    $actor = physicalChallengeActor();
    $setup = methodIndependenceSetupBothMethods($actor);
    $run = $setup['run'];
    $method = $run->methods()->where('method', 'pre_shaded')->firstOrFail();
    $page = $method->pages()->where('page_no', 1)->firstOrFail();
    $method->forceFill(['method_state' => OmrTemplateVerificationMethod::STATE_FAILED])->save();
    $page->forceFill(['page_state' => 'failed'])->save();
    $setup['preChallenge']->forceFill(['status' => 'failed'])->save();
    $run->forceFill([
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'error_code' => 'marker_missing',
        'safe_error' => 'Historical child failure.',
        'finished_at' => now(),
    ])->save();

    // Create a second failed run whose draft target has drifted
    $draft2 = physicalChallengeDraft($actor);
    $setup2 = methodIndependenceSetupBothMethods($actor);
    $driftedRun = $setup2['run'];
    $driftedMethod = $driftedRun->methods()->where('method', 'pre_shaded')->firstOrFail();
    $driftedPage = $driftedMethod->pages()->where('page_no', 1)->firstOrFail();
    $driftedMethod->forceFill(['method_state' => OmrTemplateVerificationMethod::STATE_FAILED])->save();
    $driftedPage->forceFill(['page_state' => 'failed'])->save();
    $setup2['preChallenge']->forceFill(['status' => 'failed'])->save();
    $driftedRun->forceFill([
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'error_code' => 'marker_missing',
        'safe_error' => 'Historical child failure.',
        'finished_at' => now(),
    ])->save();

    // Mutate the draft so targetLoader sees drifted geometry/artifact
    $authoring = (array) $setup2['draft']->fresh()->authoring_json;
    $authoring['canonical']['artifact_set']['artifact_hash'] = hash('sha256', 'drifted_artifact');
    $setup2['draft']->forceFill(['authoring_json' => $authoring])->saveQuietly();

    $this->artisan('omr:reconcile-physical-runs')
        ->expectsOutputToContain('Reconciled 1 physical verification run(s).')
        ->assertSuccessful();

    // Eligible run is reconciled and reopened
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->fresh()->error_code)->toBeNull()
        ->and($run->fresh()->finished_at)->toBeNull();

    // Drifted run remains terminal failed
    expect($driftedRun->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($driftedRun->fresh()->error_code)->toBe('marker_missing')
        ->and($driftedRun->fresh()->finished_at)->not->toBeNull();
});
