<?php

use App\Assessment\Actions\Omr\Canonical\RunOmrPhysicalQualification;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\ReviewOmrPhysicalCapture;
use App\Assessment\Actions\Omr\Verification\SelectOmrPhysicalAttempt;
use App\Assessment\Actions\Omr\Verification\SubmitOmrPhysicalCapture;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticEvidence;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrQuarterTurnDiagnostics;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrPhysicalQualificationDeriver;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Http\Requests\Omr\Canonical\RunOmrPhysicalQualificationRequest;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/** Compiled draft bound to the fixture version with a durable passing digital run. */
function physicalChallengeCompiledDraft(User $actor, OmrAnswerSheetTemplateVersion $version): OmrTemplateAuthoringDraft
{
    $draft = app(OmrAuthoringDraftService::class)->create($actor, (string) $version->template?->code, 'Compiled challenge draft', 'html');
    $draft->forceFill([
        'marker_profile_id' => (int) ($version->authoring_manifest['marker_profile']['id'] ?? null),
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'compiled_version_id' => $version->id,
        'authoring_json' => [
            'canonical' => ['artifact_set' => ['artifact_hash' => (string) ($version->authoring_manifest['artifact_set']['artifact_hash'] ?? str_repeat('a', 64))]],
            'verification' => null,
        ],
    ])->save();
    $compiledIdentity = (string) ($version->authoring_manifest['geometry_identity'] ?? $version->authoring_manifest['mapping_hash'] ?? '');
    physicalChallengeSeedDigitalPass($draft->fresh(), $actor, null, [], [], $compiledIdentity);
    $match = app(CurrentOmrDigitalVerification::class)->find($draft->fresh());
    if ($match === null) {
        throw new RuntimeException('physical_challenge_fixture_digital_pass_invalid');
    }
    $manifest = (array) $version->authoring_manifest;
    $manifest['verification_hashes'] = [
        'digital_verification' => $match->verificationHash,
        'matrix_hash' => $match->matrixHash,
        'proof_hash' => $match->proofHash,
    ];
    $version->forceFill(['authoring_manifest' => $manifest])->save();

    return $draft->fresh();
}

function physicalChallengeFullPass(User $actor, ?OmrAnswerSheetTemplateVersion $version = null): OmrTemplateVerificationRun
{
    $version ??= physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $hand = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'full', OmrTemplateVerificationCapture::METHOD_HAND_SHADED);
    $pre = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'full', OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    $run = $pre['run']->fresh();
    $coordinator = app(OmrPhysicalAssuranceCoordinator::class);
    $coordinator->markPrinted($run, $actor, OmrTemplateVerificationCapture::METHOD_HAND_SHADED, (int) $hand['challenge']->id);
    $coordinator->markPrinted($run, $actor, OmrTemplateVerificationCapture::METHOD_PRE_SHADED, (int) $pre['challenge']->id);
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->mode)->toBe(OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION);
    $target = new CompiledVersionDiagnosticTarget($version->fresh());
    foreach ([
        [$hand, OmrTemplateVerificationCapture::METHOD_HAND_SHADED, OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL],
        [$pre, OmrTemplateVerificationCapture::METHOD_PRE_SHADED, null],
    ] as [$pack, $method, $medium]) {
        $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::FullQualification, OmrProofPlanner::CURRENT_PLAN_VERSION, $method);
        app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
        physicalChallengeFakeWorker();
        $submitted = app(SubmitOmrPhysicalCapture::class)->handle(
            run: $run->fresh(),
            actor: $actor,
            bytes: physicalChallengePng(),
            mime: 'image/png',
            captureSource: 'upload',
            pageNo: 1,
            methodName: $method,
            challengeId: (int) $pack['challenge']->id,
            targetHash: (string) $pack['challenge']->target_hash,
            planHash: (string) $pack['challenge']->plan_hash,
            expectedVectorHash: (string) $pack['challenge']->expected_vector_hash,
            medium: $medium,
            idempotencyKey: 'full-pass-'.$method,
            autoSelect: false,
        );
        app(SelectOmrPhysicalAttempt::class)->handle(
            run: $run->fresh(),
            actor: $actor,
            method: $method,
            pageNo: 1,
            captureId: (int) $submitted['capture']->id,
            reason: 'Exact full-qualification '.$method.' capture selected.',
        );
    }

    return $run->fresh();
}

/**
 * Persist an active document-bound physical capture without invoking a worker.
 *
 * @return array{run:OmrTemplateVerificationRun,capture:OmrTemplateVerificationCapture,challenge:OmrPhysicalChallenge,target:CompiledVersionDiagnosticTarget,plan:OmrProofPlan,input:OmrCaptureInput}
 */
function physicalChallengeDocumentBoundCaptureContext(
    User $actor,
    string $methodName,
    int $pageCount = 1,
    int $pageNo = 1,
): array {
    $draft = physicalChallengeDocumentBoundCompiledDraft($actor, $pageCount);
    $pack = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'full', $methodName);
    $run = $pack['run']->fresh();
    $challenge = $pack['challenge'];
    $method = $run->methods()->where('method', $methodName)->firstOrFail();
    $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($run->template_version_id);
    $target = new CompiledVersionDiagnosticTarget($version);
    $plan = app(OmrProofPlanner::class)->plan(
        $target,
        ProofPurpose::FullQualification,
        (string) $method->plan_version,
        $methodName,
    );
    app(OmrPhysicalAssuranceCoordinator::class)->markPrinted($run, $actor, $methodName, (int) $challenge->id);
    $input = new OmrCaptureInput(physicalChallengePng(), 'image/png');
    $submitted = app(SubmitOmrPhysicalCapture::class)->handle(
        run: $run->fresh(),
        actor: $actor,
        bytes: $input->bytes,
        mime: $input->mime,
        captureSource: 'upload',
        pageNo: $pageNo,
        methodName: $methodName,
        challengeId: (int) $challenge->id,
        targetHash: (string) $challenge->target_hash,
        planHash: (string) $challenge->plan_hash,
        expectedVectorHash: (string) $challenge->expected_vector_hash,
        medium: $methodName === OmrTemplateVerificationCapture::METHOD_HAND_SHADED
            ? OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL
            : null,
        idempotencyKey: 'document-bound-'.$methodName.'-'.$pageNo,
        autoSelect: false,
    );

    return [
        'run' => $run->fresh(),
        'capture' => $submitted['capture'],
        'challenge' => $challenge,
        'target' => $target,
        'plan' => $plan,
        'input' => $input,
    ];
}

/**
 * @return array{prepared:OmrPreparedCaptureEvidence,evidence:OmrDiagnosticEvidence}
 */
function physicalChallengeDocumentBoundPreparedEvidence(
    CompiledVersionDiagnosticTarget $target,
    OmrProofPlan $plan,
    OmrCaptureInput $input,
    int $pageNo,
    ?array $gates = null,
    ?array $detectionRectification = null,
    ?int $workerPageNo = null,
    bool $missingDetectionRectification = false,
    bool $mismatchedWorkerInputHash = false,
    array $items = [],
): array {
    $rectification = [
        'attempted' => true,
        'applied' => true,
        'mode' => 'document_bound',
        'status' => 'success',
        'plausible_candidate_count' => 1,
        'selected_candidate_index' => 0,
        'homography_count' => 1,
        'warp_count' => 1,
        'document_gates' => ['accepted' => true],
        'content_gate' => ['accepted' => true],
        'homography_hash' => 'sha256:'.str_repeat('a', 64),
        'quad_normalized' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
    ];
    $detectionRectification ??= $rectification;
    if ($missingDetectionRectification) {
        $detectionRectification = null;
    }
    $gates ??= [
        'marker_count' => 'not_applicable',
        'registration' => 'pass',
        'document' => 'pass',
        'content' => 'pass',
        'dimensions' => 'pass',
        'shifts' => 'pass',
        'rectification_consistency' => 'pass',
        'unsafe_blocks' => [],
    ];
    $request = $target->buildRequest(
        app(OmrDiagnosticRequestBuilder::class),
        $input->bytes,
        $input->mime,
        $pageNo,
    );
    $success = new OmrDetectionSuccess(
        engineName: $request->engineName,
        engineVersion: 'test-worker',
        engineCommit: $request->engineCommit,
        inputHash: $mismatchedWorkerInputHash ? str_repeat('0', 64) : $request->inputHash,
        configHash: $request->configHash,
        applicantNumberProposal: null,
        applicantNumberConfidence: null,
        identityFields: [],
        items: [],
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
        rectification: $detectionRectification,
        registrationMode: $request->registrationMode->value,
        templateVersionId: $request->templateVersionId,
        candidateTargetId: $request->candidateTargetId,
        expectedPageNo: $workerPageNo ?? $pageNo,
        sourceImage: $request->sourceImage->toArray(),
    );
    $providerResult = OmrDetectionResult::succeeded($success);
    $pageDimensions = is_array($request->nativeTemplate['pageDimensions'] ?? null)
        ? $request->nativeTemplate['pageDimensions']
        : [1, 1];
    $alignedImage = [
        'mime' => 'image/png',
        'width' => (int) ($pageDimensions[0] ?? 1),
        'height' => (int) ($pageDimensions[1] ?? 1),
    ];
    $layout = new OmrLayoutVerificationResult(
        outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
        alignedImage: $alignedImage,
        overlayImage: $alignedImage,
        gates: $gates,
        rectification: $rectification,
    );
    $evidence = OmrDiagnosticEvidence::successful(
        target: $target,
        pageNo: $pageNo,
        inputHash: $input->sha256,
        capturedMime: $input->mime,
        capturedBytes: $input->bytes,
        layout: $layout,
        gates: $gates,
        items: $items,
        identity: [],
        timings: [],
        issues: [],
        plan: $plan,
        calibrationStatus: 'unqualified',
        providerResult: $providerResult,
    );
    $prepared = OmrPreparedCaptureEvidence::fromDiagnostic(
        target: $target,
        input: $input,
        pageNo: $pageNo,
        orientationDiagnostics: new OmrQuarterTurnDiagnostics([]),
        diagnostic: $evidence,
        acceptedOrientation: null,
        layout: $layout,
        providerResult: $providerResult,
        detectionRequest: $request,
        expectedVectorHash: $plan->expectedVectorHash,
    );

    return ['prepared' => $prepared, 'evidence' => $evidence];
}

test('full challenge refuses a mismatched compiled target before physical creation', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $foreignActor = physicalChallengeActor();
    $version = physicalChallengeVersion($foreignActor);
    $actor = physicalChallengeActor();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PHY-MISMATCH-TEST', 'Mismatched compiled challenge', 'html');
    $draft->forceFill([
        'marker_profile_id' => (int) ($version->authoring_manifest['marker_profile']['id'] ?? null),
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'compiled_version_id' => $version->id,
        'authoring_json' => [
            'canonical' => ['artifact_set' => ['artifact_hash' => (string) ($version->authoring_manifest['artifact_set']['artifact_hash'] ?? '')]],
            'verification' => null,
        ],
    ])->save();
    physicalChallengeSeedDigitalPass($draft->fresh(), $actor);

    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, 'full'))
        ->toThrow(RuntimeException::class, 'digital_pass_required');
});
test('full challenge refuses a compiled version whose artifact differs from the durable pass', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $mismatchedHash = str_repeat('c', 64);
    $authoring = (array) $draft->authoring_json;
    $authoring['canonical']['artifact_set']['artifact_hash'] = $mismatchedHash;
    $draft->forceFill(['authoring_json' => $authoring])->save();
    OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail()
        ->forceFill(['artifact_hash' => $mismatchedHash])
        ->save();

    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, 'full'))
        ->toThrow(RuntimeException::class, 'compiled_version_mismatch');
});

test('full challenge refuses a compiled version whose draft code differs', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $draft->forceFill(['code' => 'PHY-CODE-TAMPER'])->save();

    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, 'full'))
        ->toThrow(RuntimeException::class, 'compiled_version_mismatch');
});

test('full challenge refuses a compiled version with a mismatched verification hash', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $manifest = (array) $version->authoring_manifest;
    $manifest['verification_hashes']['proof_hash'] = str_repeat('d', 64);
    $version->forceFill(['authoring_manifest' => $manifest])->save();

    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, 'full'))
        ->toThrow(RuntimeException::class, 'compiled_version_mismatch');
});

test('full challenge leaves no run or challenge when rendering fails', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $assetCount = OmrTemplateAsset::query()->count();
    config(['omr.qr-keyring.keys' => []]);

    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, 'full'))
        ->toThrow(RuntimeException::class);

    expect(OmrTemplateVerificationRun::query()->whereIn('mode', [
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
    ])->count())->toBe(0)
        ->and(OmrPhysicalChallenge::query()->count())->toBe(0)
        ->and(OmrTemplateAsset::query()->count())->toBe($assetCount);
});
test('full physical challenge cannot qualify or report legacy physical provenance', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $run = physicalChallengeFullPass($actor);
    $capture = $run->captures()->firstOrFail();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->mode)->toBe(OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION)
        ->and($capture->status)->toBe(OmrTemplateVerificationCapture::STATUS_PASS)
        ->and($capture->input_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($capture->evidence_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($capture->input_asset_id)->not->toBeNull();

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run, $actor, [
        'renderer_version' => 'canonical-renderer-v1',
        'printer' => 'HP LaserJet',
        'device' => 'desk-scanner',
        'paper' => 'A4 80gsm',
        'measured_marker_center_mm' => [186.2, 270.8],
    ]))->toThrow(RuntimeException::class, 'physical_provenance_unavailable');

    $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($run->template_version_id);
    expect($version->authoring_manifest['physical_qualification'] ?? null)->toBeNull()
        ->and(AuditEvent::query()->where('event_type', 'omr.canonical.physical_qualified')->count())->toBe(0);
    $manifest = (array) $version->authoring_manifest;
    $manifest['physical_qualification'] = ['status' => 'physical_qualified', 'run_id' => (int) $run->id];
    $version->forceFill(['authoring_manifest' => $manifest])->save();
    $draft = OmrTemplateAuthoringDraft::query()->where('compiled_version_id', $version->id)->firstOrFail();
    $workflow = app(OmrCanonicalWorkflowProjection::class)->workflowProps($draft, $actor);

    expect($workflow['draft']['physical_qualified'])->toBeFalse()
        ->and($version->fresh()->authoring_manifest['physical_qualification']['status'])->toBe('physical_qualified');
});

test('physical measurements reject malformed centers and unverifiable provenance', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $measurements = [
        'printer' => 'printer',
        'device' => 'scanner',
        'paper' => 'A4',
        'measured_marker_center_mm' => [186, 271],
    ];
    $unverifiedMeasurements = app(OmrPhysicalQualificationDeriver::class)->derive(null, $version, $measurements);
    $provenanceCheck = collect($unverifiedMeasurements['checklist'])->firstWhere('key', 'measurements_recorded');

    $explicitMalformed = app(OmrPhysicalQualificationDeriver::class)->derive(
        null,
        $version,
        [...$measurements, 'expected_marker_center_mm' => ['not-numeric', 271]],
    );
    $explicitCheck = collect($explicitMalformed['checklist'])->firstWhere('key', 'measurements_recorded');

    $manifest = (array) $version->authoring_manifest;
    $manifest['paper']['width_mm'] = 'not-numeric';
    $version->forceFill(['authoring_manifest' => $manifest])->save();
    $invalidFallback = app(OmrPhysicalQualificationDeriver::class)->derive(null, $version->fresh(), $measurements);
    $fallbackCheck = collect($invalidFallback['checklist'])->firstWhere('key', 'measurements_recorded');

    expect($explicitCheck['passed'])->toBeFalse()
        ->and($fallbackCheck['passed'])->toBeFalse()
        ->and($provenanceCheck['passed'])->toBeFalse()
        ->and($provenanceCheck['code'])->toBe('physical_provenance_unavailable')
        ->and($unverifiedMeasurements['ready'])->toBeFalse()
        ->and($unverifiedMeasurements['qualified'])->toBeFalse();
});

test('physical capture with an empty gate map does not pass', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    Queue::fake();
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $pack = app(CreateOmrPhysicalChallenge::class)->handle(
        $draft,
        $actor,
        'full',
        OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
    );
    $run = $pack['run']->fresh();
    $challenge = $pack['challenge'];
    $coordinator = app(OmrPhysicalAssuranceCoordinator::class);
    $coordinator->markPrinted($run, $actor, OmrTemplateVerificationCapture::METHOD_PRE_SHADED, (int) $challenge->id);
    $target = new CompiledVersionDiagnosticTarget($version->fresh());
    $plan = app(OmrProofPlanner::class)->plan(
        $target,
        ProofPurpose::FullQualification,
        OmrProofPlanner::CURRENT_PLAN_VERSION,
        OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
    );
    $submitted = app(SubmitOmrPhysicalCapture::class)->handle(
        run: $run->fresh(),
        actor: $actor,
        bytes: physicalChallengePng(),
        mime: 'image/png',
        captureSource: 'upload',
        pageNo: 1,
        methodName: OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        challengeId: (int) $challenge->id,
        targetHash: (string) $challenge->target_hash,
        planHash: (string) $challenge->plan_hash,
        expectedVectorHash: (string) $challenge->expected_vector_hash,
        medium: null,
        idempotencyKey: 'empty-gates',
        autoSelect: false,
    );
    $input = new OmrCaptureInput(physicalChallengePng(), 'image/png');
    $evidence = new OmrDiagnosticEvidence(
        outcome: OmrDiagnosticEvidence::OUTCOME_SUCCEEDED,
        code: null,
        detail: null,
        pageNo: 1,
        target: ['kind' => $target->kind(), 'id' => $target->targetId()],
        hashes: ['input' => $input->sha256, 'aligned' => null, 'overlay' => null, 'plan' => $plan->planHash()],
        assets: ['captured' => ['mime' => 'image/png', 'sha256' => $input->sha256, 'bytes' => $input->bytes], 'aligned' => null, 'overlay' => null],
        gates: [],
        markerMatches: [],
        fieldBlockShifts: [],
        items: [],
        identity: [],
        timings: [],
        issues: [],
        calibrationStatus: 'unqualified',
        planHash: $plan->planHash(),
        planPurpose: 'full_qualification',
        providerItemEvidence: null,
    );
    $prepared = OmrPreparedCaptureEvidence::fromDiagnostic(
        $target,
        $input,
        1,
        new OmrQuarterTurnDiagnostics([]),
        $evidence,
        0,
        layout: new OmrLayoutVerificationResult(OmrLayoutVerificationResult::OUTCOME_SUCCEEDED, gates: []),
        expectedVectorHash: $run->expected_vector_hash,
    );
    $result = $coordinator->completeCapture(
        $submitted['capture'],
        $prepared,
        $evidence,
        $actor,
        vectorMatch: true,
    );

    expect($result['capture']->status)->toBe(OmrTemplateVerificationCapture::STATUS_FAIL)
        ->and($result['capture']->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED)
        ->and($result['run']->status)->not->toBe(OmrTemplateVerificationRun::STATUS_PASSED);
});

test('document-bound capture gates pass for every selected page and method while provenance stays blocked', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    Queue::fake();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDocumentBoundCompiledDraft($actor, 2);
    $preShaded = app(CreateOmrPhysicalChallenge::class)->handle(
        $draft,
        $actor,
        'full',
        OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
    );
    $handShaded = app(CreateOmrPhysicalChallenge::class)->handle(
        $draft->fresh(),
        $actor,
        'full',
        OmrTemplateVerificationCapture::METHOD_HAND_SHADED,
    );
    $run = $preShaded['run']->fresh();
    $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($run->template_version_id);
    $target = new CompiledVersionDiagnosticTarget($version);
    $coordinator = app(OmrPhysicalAssuranceCoordinator::class);
    $challengeMethods = [
        [$preShaded['challenge'], OmrTemplateVerificationCapture::METHOD_PRE_SHADED, null],
        [$handShaded['challenge'], OmrTemplateVerificationCapture::METHOD_HAND_SHADED, OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL],
    ];

    foreach ($challengeMethods as [$challenge, $methodName, $medium]) {
        $method = $run->methods()->where('method', $methodName)->firstOrFail();
        $coordinator->markPrinted($run->fresh(), $actor, $methodName, (int) $challenge->id);
        $plan = app(OmrProofPlanner::class)->plan(
            $target,
            ProofPurpose::FullQualification,
            (string) $method->plan_version,
            $methodName,
        );

        foreach ([1, 2] as $pageNo) {
            $input = new OmrCaptureInput(physicalChallengePng(), 'image/png');
            $submitted = app(SubmitOmrPhysicalCapture::class)->handle(
                run: $run->fresh(),
                actor: $actor,
                bytes: $input->bytes,
                mime: $input->mime,
                captureSource: 'upload',
                pageNo: $pageNo,
                methodName: $methodName,
                challengeId: (int) $challenge->id,
                targetHash: (string) $challenge->target_hash,
                planHash: (string) $challenge->plan_hash,
                expectedVectorHash: (string) $challenge->expected_vector_hash,
                medium: $medium,
                idempotencyKey: 'document-bound-full-'.$methodName.'-'.$pageNo,
                autoSelect: false,
            );
            $fixture = physicalChallengeDocumentBoundPreparedEvidence($target, $plan, $input, $pageNo);
            $completed = $coordinator->completeCapture(
                $submitted['capture'],
                $fixture['prepared'],
                $fixture['evidence'],
                $actor,
                vectorMatch: true,
            );

            expect($completed['capture']->status)->toBe(OmrTemplateVerificationCapture::STATUS_PASS);

            app(SelectOmrPhysicalAttempt::class)->handle(
                $completed['run']->fresh(),
                $actor,
                $methodName,
                $pageNo,
                (int) $completed['capture']->id,
                'Selected a synthetic document-bound gate fixture.',
            );
            $run = $completed['run']->fresh();
            if ($methodName === OmrTemplateVerificationCapture::METHOD_PRE_SHADED && $pageNo === 1) {
                $partialReadiness = app(OmrPhysicalQualificationDeriver::class)->derive($run, $version);
                $partialChecks = collect($partialReadiness['checklist'])->keyBy('key');

                expect($partialChecks['pre_shaded_passed']['passed'])->toBeFalse()
                    ->and($partialChecks['hand_shaded_passed']['passed'])->toBeFalse()
                    ->and($partialReadiness['ready'])->toBeFalse();
            }
        }
    }

    $run = $run->fresh();
    $physicalEvidence = [
        'renderer_version' => 'synthetic-document-bound-renderer',
        'printer' => 'synthetic printer',
        'device' => 'synthetic capture device',
        'paper' => 'A4',
    ];
    $readiness = app(OmrPhysicalQualificationDeriver::class)->derive($run, $version, $physicalEvidence);
    $checks = collect($readiness['checklist'])->keyBy('key');

    $firstPreShadedCapture = $run->captures()
        ->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)
        ->where('page_no', 1)
        ->firstOrFail();

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($firstPreShadedCapture->gate_result)->toEqual([
            'marker_count' => 'not_applicable',
            'registration' => 'pass',
            'document' => 'pass',
            'content' => 'pass',
            'dimensions' => 'pass',
            'shifts' => 'pass',
            'rectification_consistency' => 'pass',
            'unsafe_blocks' => [],
        ])
        ->and($checks['pre_shaded_passed']['passed'])->toBeTrue()
        ->and($checks['hand_shaded_passed']['passed'])->toBeTrue()
        ->and($checks['marker_profile_approved']['passed'])->toBeTrue()
        ->and($checks['marker_profile_approved']['label'])->toBe('marker profile not required')
        ->and($checks['measurements_recorded']['passed'])->toBeFalse()
        ->and($checks['measurements_recorded']['code'])->toBe('physical_provenance_unavailable')
        ->and($readiness['ready'])->toBeFalse()
        ->and($readiness['qualified'])->toBeFalse();

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run, $actor, $physicalEvidence))
        ->toThrow(RuntimeException::class);

    $selectedCapture = $firstPreShadedCapture->fresh();
    $summary = $selectedCapture->summary;
    $preparedCapture = $summary['prepared_capture'];
    $preparedCapture['gates']['marker_count'] = 'pass';
    $preparedCapture['diagnostic']['gates']['marker_count'] = 'pass';
    $summary['gates']['marker_count'] = 'pass';
    $summary['prepared_capture'] = $preparedCapture;
    expect(fn () => DB::transaction(fn () => DB::table('omr_template_verification_captures')
        ->where('id', $selectedCapture->id)
        ->update([
            'summary' => json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'gate_result' => json_encode($preparedCapture['gates'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ])))->toThrow(QueryException::class);
    expect($selectedCapture->fresh()->summary['gates']['marker_count'])->toBe('not_applicable');
    $rederived = app(OmrPhysicalQualificationDeriver::class)->derive($run->fresh(), $version->fresh(), $physicalEvidence);
    expect($rederived['ready'])->toBeFalse()
        ->and($rederived['qualified'])->toBeFalse();

    $version = $version->fresh();
    expect($version->authoring_manifest['physical_qualification'] ?? null)->toBeNull()
        ->and(AuditEvent::query()->where('event_type', 'omr.canonical.physical_qualified')->count())->toBe(0);
});

test('document-bound candidate qualification remains rehearsal-only', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDocumentBoundDraft($actor);
    $run = physicalChallengeQuickRun($actor, $draft);
    $readiness = app(OmrPhysicalQualificationDeriver::class)->derive($run);
    $checks = collect($readiness['checklist'])->keyBy('key');

    expect($readiness['status'])->toBe('rehearsal_only')
        ->and($readiness['ready'])->toBeFalse()
        ->and($readiness['qualified'])->toBeFalse()
        ->and($readiness['blockers'][0]['code'])->toBe('candidate_not_qualification_capable')
        ->and($checks['marker_profile_approved']['passed'])->toBeTrue()
        ->and($checks['marker_profile_approved']['label'])->toBe('marker profile not required');
});

test('document-bound capture gates reject marker claims, missing gates, invalid rectification, and mismatched worker identity', function (
    array $overriddenGates,
    array $removedGates,
    bool $differentWarp,
    ?int $workerPageNo,
    bool $missingDetectionRectification = false,
    bool $mismatchedWorkerInputHash = false,
    string $expectedFailure = '',
): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    Queue::fake();
    $actor = physicalChallengeActor();
    $context = physicalChallengeDocumentBoundCaptureContext(
        $actor,
        OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
    );
    $gates = array_replace([
        'marker_count' => 'not_applicable',
        'registration' => 'pass',
        'document' => 'pass',
        'content' => 'pass',
        'dimensions' => 'pass',
        'shifts' => 'pass',
        'rectification_consistency' => 'pass',
        'unsafe_blocks' => [],
    ], $overriddenGates);
    foreach ($removedGates as $gate) {
        unset($gates[$gate]);
    }
    $detectionRectification = null;
    if ($differentWarp) {
        $detectionRectification = [
            'attempted' => true,
            'applied' => true,
            'mode' => 'document_bound',
            'status' => 'success',
            'plausible_candidate_count' => 1,
            'selected_candidate_index' => 0,
            'homography_count' => 1,
            'warp_count' => 1,
            'document_gates' => ['accepted' => true],
            'content_gate' => ['accepted' => true],
            'homography_hash' => 'sha256:'.str_repeat('b', 64),
            'quad_normalized' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
        ];
    }
    $fixture = physicalChallengeDocumentBoundPreparedEvidence(
        $context['target'],
        $context['plan'],
        $context['input'],
        1,
        $gates,
        $detectionRectification,
        $workerPageNo,
        $missingDetectionRectification,
        $mismatchedWorkerInputHash,
    );
    $result = app(OmrPhysicalAssuranceCoordinator::class)->completeCapture(
        $context['capture'],
        $fixture['prepared'],
        $fixture['evidence'],
        $actor,
        vectorMatch: true,
    );

    expect($result['capture']->status)->toBe(OmrTemplateVerificationCapture::STATUS_FAIL)
        ->and($result['capture']->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED)
        ->and($result['run']->status)->not->toBe(OmrTemplateVerificationRun::STATUS_PASSED);
    $capture = $result['capture'];
    expect($capture->actual)->toBe('typed_rejection')
        ->and($capture->failure_code)->toBe($expectedFailure)
        ->and($capture->failure_detail)->toBe($expectedFailure)
        ->and($capture->summary['verification_result']['gates_pass'])->toBeFalse()
        ->and($capture->summary['verification_result']['needs_review_count'])->toBeNull();
    $status = app(OmrTemplateVerificationStatus::class)->get($result['run'], $actor);
    $method = collect($status['methods'])->firstWhere('method', $capture->method);
    expect($method['state'])->toBe('failed')
        ->and($method['attempts'][0]['state'])->toBe('failed')
        ->and($method['attempts'][0]['failure_reason'])->toBe($expectedFailure);
    expect(fn () => app(ReviewOmrPhysicalCapture::class)->handle(
        run: $result['run'], actor: $actor, passed: true, captureId: $capture->id,
    ))->toThrow(RuntimeException::class, 'verification_capture_exact_recovery_required');
    expect($capture->fresh()->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED);
})->with([
    'marker count cannot pass in document-bound mode' => [['marker_count' => 'pass'], [], false, null, false, false, 'marker_count_gate_failed'],
    'marker count cannot be absent' => [[], ['marker_count'], false, null, false, false, 'marker_count_gate_failed'],
    'document gate is required' => [[], ['document'], false, null, false, false, 'document_gate_failed'],
    'content gate is required' => [[], ['content'], false, null, false, false, 'content_gate_failed'],
    'rectification consistency gate is required' => [[], ['rectification_consistency'], false, null, false, false, 'rectification_consistency_gate_failed'],
    'accepted but different homographies are rejected' => [[], [], true, null, false, false, 'alignment_gate_failed'],
    'worker success must identify the requested page' => [[], [], false, 2, false, false, 'worker_page_mismatch'],
    'registration gate must pass' => [['registration' => 'fail'], [], false, null, false, false, 'registration_gate_failed'],
    'dimensions gate must pass' => [['dimensions' => 'fail'], [], false, null, false, false, 'dimensions_mismatch'],
    'shifts gate must pass' => [['shifts' => 'fail'], [], false, null, false, false, 'shifts_gate_failed'],
    'unsafe blocks must be empty' => [['unsafe_blocks' => [['field' => 'unsafe']]], [], false, null, false, false, 'unsafe_blocks_gate_failed'],
    'unknown gates are rejected' => [['unexpected_gate' => 'pass'], [], false, null, false, false, 'unexpected_gate'],
    'missing worker rectification evidence is rejected' => [[], [], false, null, true, false, 'alignment_gate_failed'],
    'worker success input hash must match the request' => [[], [], false, null, false, true, 'worker_input_hash_mismatch'],
]);

test('Stage 3 unresolved observations remain reviewable but cannot become an exact machine pass', function (string $state, bool $vectorMatch): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    Queue::fake();
    $actor = physicalChallengeActor();
    $context = physicalChallengeDocumentBoundCaptureContext($actor, OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    $fixture = physicalChallengeDocumentBoundPreparedEvidence(
        $context['target'], $context['plan'], $context['input'], 1,
        items: [[
            'item_id' => 'SPATIAL_Q_11', 'area_key' => 'SPATIAL', 'question_no' => 11,
            'expected' => null, 'exact' => null, 'options' => [['code' => 'A'], ['code' => 'D']],
            'detected' => ['state' => $state, 'selected_option' => $state === 'marked' ? 'D' : null],
        ]],
    );
    $result = app(OmrPhysicalAssuranceCoordinator::class)->completeCapture(
        $context['capture'], $fixture['prepared'], $fixture['evidence'], $actor, $vectorMatch,
    );
    $capture = $result['capture'];
    $status = app(OmrTemplateVerificationStatus::class)->get($result['run'], $actor);
    $method = collect($status['methods'])->firstWhere('method', $capture->method);
    expect($capture->actual)->toBe('answer_vector_mismatch')
        ->and($capture->status)->toBe('fail')
        ->and($capture->summary['verification_result']['gates_pass'])->toBeTrue()
        ->and($method['state'])->toBe('needs_review')
        ->and($method['attempts'][0]['needs_review_count'])->toBe(1)
        ->and($method['pages'][0]['selected_attempt_id'])->toBeNull();
    expect(fn () => app(ReviewOmrPhysicalCapture::class)->handle(
        run: $result['run'], actor: $actor, passed: true, captureId: $capture->id,
    ))->toThrow(RuntimeException::class, 'verification_capture_exact_recovery_required');
    expect(fn () => app(SelectOmrPhysicalAttempt::class)->handle(
        run: $result['run'], actor: $actor, method: $capture->method, pageNo: 1, captureId: $capture->id,
        reason: '',
    ))->toThrow(RuntimeException::class, 'selection_attempt_not_passed');
    expect($capture->fresh()->status)->toBe('fail')
        ->and(AuditEvent::query()->where('event_type', 'omr.canonical.physical_qualified')->count())->toBe(0);
})->with([
    'ambiguous' => [OmrDetectedItem::STATE_AMBIGUOUS, false],
    'unreadable despite inconsistent vector flag' => [OmrDetectedItem::STATE_UNREADABLE, true],
    'unexpected mark on blank remainder' => [OmrDetectedItem::STATE_MARKED, false],
]);

test('Stage 3 historical typed rejection remains rejected without inventing its failed check', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    Queue::fake();
    $actor = physicalChallengeActor();
    $context = physicalChallengeDocumentBoundCaptureContext($actor, OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    $capture = $context['capture'];
    $capture->forceFill([
        'attempt_state' => 'failed', 'status' => 'fail', 'actual' => 'typed_rejection',
        'vector_match' => true, 'failure_code' => 'capture_needs_review', 'summary' => [],
    ])->save();
    $method = $context['run']->methods()->whereKey($capture->method_id)->firstOrFail();
    $method->forceFill(['method_state' => 'needs_review'])->save();
    $method->pages()->where('page_no', 1)->update(['page_state' => 'needs_review']);
    $original = $capture->getRawOriginal();
    $status = app(OmrTemplateVerificationStatus::class)->get($context['run']->fresh(), $actor);
    $projection = collect($status['methods'])->firstWhere('method', $capture->method);
    expect($projection['state'])->toBe('failed')
        ->and($projection['failure']['code'])->toBe('binding_evidence_unrecorded')
        ->and($projection['attempts'][0]['needs_review_count'])->toBeNull()
        ->and($projection['pages'][0]['state'])->toBe('failed')
        ->and($capture->fresh()->getRawOriginal())->toBe($original);
});

test('qualification request requires measurements only for fiducial-anchored runs', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $documentDraft = physicalChallengeDocumentBoundCompiledDraft($actor);
    $documentRun = app(CreateOmrPhysicalChallenge::class)
        ->handle($documentDraft, $actor, 'full', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)['run'];
    $anchoredRun = physicalChallengeFullPass($actor);
    $payload = [
        'renderer_version' => 'synthetic renderer',
        'printer' => 'synthetic printer',
        'device' => 'synthetic capture device',
        'paper' => 'A4',
        'idempotency_key' => 'physical-qualification-request-test',
    ];
    $rulesForRun = static function (OmrTemplateVerificationRun $run): array {
        $request = new RunOmrPhysicalQualificationRequest;
        $route = new class($run)
        {
            public function __construct(private readonly OmrTemplateVerificationRun $run) {}

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $name === 'run' ? $this->run : $default;
            }
        };
        $request->setRouteResolver(static fn () => $route);

        return $request->rules();
    };
    $documentRules = $rulesForRun($documentRun);
    $anchoredRules = $rulesForRun($anchoredRun);
    $documentWithoutMarkers = Validator::make($payload, $documentRules);
    $documentWithForbiddenMarkers = Validator::make([
        ...$payload,
        'measured_marker_center_mm' => [186, 271],
    ], $documentRules);
    $anchoredWithoutMarkers = Validator::make($payload, $anchoredRules);
    $anchoredWithMarkers = Validator::make([
        ...$payload,
        'measured_marker_center_mm' => [186, 271],
    ], $anchoredRules);

    expect($documentWithoutMarkers->passes())->toBeTrue()
        ->and($documentWithForbiddenMarkers->fails())->toBeTrue()
        ->and($anchoredWithoutMarkers->fails())->toBeTrue()
        ->and($anchoredWithMarkers->passes())->toBeTrue();
});

test('physical qualification derives the locked current page selection instead of a stale pointer snapshot', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $run = physicalChallengeFullPass($actor);
    $selected = $run->captures()
        ->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)
        ->firstOrFail();
    $replacement = $selected->replicate();
    $replacement->forceFill([
        'attempt_no' => (int) $selected->attempt_no + 1,
        'selected_by_user_id' => null,
        'selected_at' => null,
        'selection_reason' => null,
    ])->save();
    DB::table('omr_template_verification_captures')->where('id', $selected->id)->update([
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED,
        'vector_match' => false,
    ]);
    $pageId = DB::table('omr_template_verification_method_pages')
        ->where('method_id', $selected->method_id)
        ->where('page_no', $selected->page_no)
        ->value('id');

    $switched = false;
    DB::listen(function ($query) use (&$switched, $pageId, $replacement): void {
        if ($switched) {
            return;
        }
        $sql = strtolower($query->sql);
        $inTransaction = DB::connection()->transactionLevel() > 0;
        if (($inTransaction && str_contains($sql, 'omr_template_verification_methods'))
            || (! $inTransaction && str_contains($sql, 'omr_template_verification_method_pages'))) {
            $switched = true;
            DB::table('omr_template_verification_method_pages')
                ->where('id', $pageId)
                ->update(['selected_attempt_id' => $replacement->id]);
        }
    });

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run, $actor, [
        'renderer_version' => 'canonical-renderer-v1',
        'printer' => 'HP LaserJet',
        'device' => 'desk-scanner',
        'paper' => 'A4 80gsm',
        'measured_marker_center_mm' => [186.2, 270.8],
    ]))->toThrow(RuntimeException::class, 'physical_provenance_unavailable');

    expect($switched)->toBeTrue();
});

test('a candidate (draft-targeted) run is refused with a typed error', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $run = physicalChallengeQuickRun($actor, $draft);
    $profile = $draft->markerProfile()->firstOrFail();
    $profileStatus = $profile->status;
    $profileEvidenceHash = $profile->qualification_evidence_hash;
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->mode)->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($run->plan_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($run->expected_vector_hash)->toMatch('/^[a-f0-9]{64}$/');

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run, $actor, [
        'renderer_version' => 'v1', 'printer' => 'p', 'device' => 'd', 'paper' => 'A4',
        'measured_marker_center_mm' => [186, 271],
    ]))->toThrow(RuntimeException::class, 'physical_qualification_mode_invalid');
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE)
        ->and($draft->fresh()->authoring_json['physical_qualification'] ?? null)->toBeNull()
        ->and($profile->fresh()->status)->toBe($profileStatus)
        ->and($profile->fresh()->qualification_evidence_hash)->toBe($profileEvidenceHash);
});

test('qualification status exposes the six-gate rehearsal-only checklist', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $run = physicalChallengeQuickRun($actor, $draft);
    $status = app(OmrPhysicalQualificationDeriver::class)->derive($run);

    expect($status['status'])->toBe('rehearsal_only')
        ->and($status['label'])->toBe('Rehearsal only')
        ->and($status['ready'])->toBeFalse()
        ->and($status['qualified'])->toBeFalse()
        ->and(array_column($status['checklist'], 'key'))->toEqual(OmrPhysicalQualificationDeriver::CHECK_KEYS)
        ->and($status['blockers'][0]['code'])->toBe('candidate_not_qualification_capable')
        ->and($status['next_action'])->toContain('Compile an immutable version');
});

test('a quick rehearsal run is refused even when compiled and passed', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $result = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $result['run'];
    expect($run->mode)->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL);

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run, $actor, [
        'renderer_version' => 'v1', 'printer' => 'p', 'device' => 'd', 'paper' => 'A4',
        'measured_marker_center_mm' => [186, 271],
    ]))->toThrow(RuntimeException::class, 'physical_qualification_mode_invalid');
});

test('qualification requires a passed run', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $run = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'full')['run'];

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run, $actor, [
        'renderer_version' => 'v1', 'printer' => 'p', 'device' => 'd', 'paper' => 'A4',
        'measured_marker_center_mm' => [186, 271],
    ]))->toThrow(RuntimeException::class, 'physical_qualification_run_not_passed');
});

test('a reviewed (non-exact) capture never qualifies', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    $challengeResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'full');
    $run = $challengeResult['run'];
    $challenge = $challengeResult['challenge'];
    app(OmrPhysicalAssuranceCoordinator::class)->markPrinted($run, $actor, 'pre_shaded', $challenge->id);

    $target = new CompiledVersionDiagnosticTarget($version->fresh());
    $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::FullQualification);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'mismatch'));
    physicalChallengeFakeWorker();
    $method = $run->methods()->where('method', 'pre_shaded')->firstOrFail();
    app(SubmitOmrPhysicalCapture::class)->handle(
        run: $run->fresh(),
        actor: $actor,
        bytes: physicalChallengePng(),
        mime: 'image/png',
        captureSource: 'upload',
        pageNo: 1,
        methodName: 'pre_shaded',
        challengeId: $challenge->id,
        targetHash: $method->target_hash,
        planHash: $method->plan_hash,
        expectedVectorHash: $method->expected_vector_hash,
        idempotencyKey: 'reviewed-non-exact-capture',
    );
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);

    // Human review can mark the retained evidence eligible for selection, but
    // it cannot satisfy the dual-method or exact-recovery qualification gate.
    app(ReviewOmrPhysicalCapture::class)->handle($run->fresh(), $actor, true, 'staff confirmed sheet');
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run->fresh(), $actor, [
        'renderer_version' => 'v1', 'printer' => 'p', 'device' => 'd', 'paper' => 'A4',
        'measured_marker_center_mm' => [186, 271],
    ]))->toThrow(RuntimeException::class, 'physical_qualification_run_not_passed');
    expect(($version->fresh()->authoring_manifest['physical_qualification']['status'] ?? null))->toBeNull();
});

test('out-of-tolerance measurements refuse qualification', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $run = physicalChallengeFullPass($actor);

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run, $actor, [
        'renderer_version' => 'v1', 'printer' => 'p', 'device' => 'd', 'paper' => 'A4',
        'measured_marker_center_mm' => [170.0, 240.0],
    ]))->toThrow(RuntimeException::class, 'physical_measurement_out_of_tolerance');
    expect(($run->templateVersion->fresh()->authoring_manifest['physical_qualification']['status'] ?? null))->toBeNull();
});

test('a version whose marker profile is not qualified cannot qualify', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $native = (array) $version->native_bundle_json;
    $native['manifest']['marker_profile']['status'] = 'candidate';
    $version->forceFill(['native_bundle_json' => $native])->save();
    $run = physicalChallengeFullPass($actor, $version->fresh());
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED);

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run->fresh(), $actor, [
        'renderer_version' => 'v1', 'printer' => 'p', 'device' => 'd', 'paper' => 'A4',
        'measured_marker_center_mm' => [186, 271],
    ]))->toThrow(RuntimeException::class, 'marker_profile_unqualified');
});

test('physical challenge rejects either cross-mode active run without creating a duplicate', function (string $activeMode, string $requestedMode): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $draft = physicalChallengeCompiledDraft($actor, $version);
    app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, $activeMode);

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => $requestedMode,
            'idempotency_key' => 'cross-mode-'.$activeMode.'-'.$requestedMode,
        ])
        ->assertStatus(422)
        ->assertJson([
            'outcome' => 'failed',
            'code' => 'challenge_exists_resume_required',
        ]);

    expect($response->json('detail'))->toContain('another mode')
        ->and(OmrTemplateVerificationRun::query()
            ->where('template_version_id', $version->id)
            ->whereIn('mode', [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ])
            ->count())->toBe(1);
})->with([
    [OmrPhysicalChallenge::MODE_QUICK, OmrPhysicalChallenge::MODE_FULL],
    [OmrPhysicalChallenge::MODE_FULL, OmrPhysicalChallenge::MODE_QUICK],
]);
