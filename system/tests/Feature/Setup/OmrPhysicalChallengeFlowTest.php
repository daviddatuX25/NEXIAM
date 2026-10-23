<?php

use App\Assessment\Actions\Omr\Canonical\RunOmrPhysicalQualification;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Assessment\Omr\Canonical\Queries\OmrCanonicalWorkflowProjection;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRendererPort;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Assessment\Omr\Verification\OmrVerificationRunService;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Operation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => 1,
    ]);
});

function physicalChallengeSetup(): array
{
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
        'queue.default' => 'sync',
        // Physical flow characterization exercises the registered
        // OmrCheckerDetection/CropOnMarkers adapter through Http::fake.
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
    ]);

    return ['actor' => physicalChallengeActor()];
}

/** @return array<string, mixed> */
function physicalPrintPayload(OmrTemplateVerificationRun $run, string $method = 'pre_shaded', ?string $key = null): array
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

/** Rebuild the exact plan the challenge action pinned for a candidate draft. */
function physicalChallengePlanForDraft(OmrTemplateAuthoringDraft $draft, ProofPurpose $purpose): OmrProofPlan
{
    $profile = $draft->markerProfile;
    $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
    $materialized = app(CanonicalArtifactMaterializer::class)->materialize($draft->fresh(['markerProfile']), $profile, $candidateVersion > 0 ? $candidateVersion : null);
    $target = new CandidateArtifactDiagnosticTarget($draft->fresh(['markerProfile']), $materialized, $profile);

    return app(OmrProofPlanner::class)->plan($target, $purpose);
}

test('quick challenge creation requires the current digital pass', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $profile = physicalChallengeProfile($actor);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'NO-PASS-'.strtoupper(substr(hash('sha256', 'x'), 0, 8)), 'No pass', 'html');
    $draft->forceFill(['marker_profile_id' => $profile->id, 'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE, 'authoring_json' => []])->save();

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), ['mode' => 'quick', 'idempotency_key' => 'ch-1'])
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_pass_required']);
    expect(OmrTemplateVerificationRun::query()->count())->toBe(0);
});

test('quick challenge renders sheet+guide assets, pins plan/vector/QR and transitions to awaiting_print', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $digitalRun = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail();
    $digitalMatch = app(CurrentOmrDigitalVerification::class)->find($draft->fresh());
    expect($draft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE)
        ->and($draft->authoring_json['verification'] ?? null)->toBeNull()
        ->and($digitalRun->captures()->count())->toBe(14)
        ->and($digitalRun->captures()->where('status', OmrTemplateVerificationCapture::STATUS_PASS)->count())->toBe(14)
        ->and($digitalRun->result_summary['matrix_hash'] ?? null)->toHaveLength(64)
        ->and($digitalRun->result_summary['proof_hash'] ?? null)->toBe($digitalRun->proof_hash)
        ->and($digitalRun->result_summary['expected_vector_hash'] ?? null)->toBe($digitalRun->expected_vector_hash)
        ->and($digitalRun->result_summary['clean_vector_hash'] ?? null)->toBe($digitalRun->clean_vector_hash)
        ->and($digitalMatch)->not->toBeNull();
    expect($digitalMatch?->run->id)->toBe($digitalRun->id)
        ->and($digitalMatch?->proofAssets)->not->toBeEmpty()
        ->and($digitalMatch?->verificationHash)->toHaveLength(64);
    expect(OmrNoGoMatrixRunner::VARIANTS)->toHaveCount(14);
    Storage::fake('local');

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), ['mode' => 'quick', 'idempotency_key' => 'ch-2'])
        ->assertStatus(202)
        ->assertJsonStructure(['run_id', 'challenge_id', 'challenge_code', 'status', 'print_url', 'guide_url', 'status_url']);

    $run = OmrTemplateVerificationRun::query()->findOrFail($response->json('run_id'));
    $challenge = OmrPhysicalChallenge::query()->findOrFail($response->json('challenge_id'));
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->mode)->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($run->attempt)->toBe(1)
        ->and($run->challenge_code)->toBe($challenge->challenge_code)
        ->and($challenge->mode)->toBe(OmrPhysicalChallenge::MODE_QUICK)
        ->and($challenge->plan_hash)->toBe($run->plan_hash)
        ->and($challenge->expected_vector_hash)->toBe($run->expected_vector_hash)
        ->and(strlen((string) $challenge->qr_payload_hash))->toBe(64)
        ->and($challenge->sheet_asset_id)->not->toBeNull()
        ->and($challenge->guide_asset_id)->not->toBeNull();

    $anchoredTarget = app(OmrVerificationTargetLoader::class)->loadPhysical($run, 'pre_shaded')['target'];
    expect($anchoredTarget->registrationMode())->toBe(OmrRegistrationMode::FiducialAnchored)
        ->and($anchoredTarget->markerProfileQualified())->toBeTrue();

    $sheetAsset = $challenge->sheetAsset;
    $guideAsset = $challenge->guideAsset;
    expect($sheetAsset)->not->toBeNull()
        ->and($guideAsset)->not->toBeNull()
        ->and($sheetAsset->kind)->toBe(OmrTemplateAsset::KIND_PRINT_PDF)
        ->and($sheetAsset->mime)->toBe('application/pdf')
        ->and($guideAsset->kind)->toBe(OmrTemplateAsset::KIND_PRINT_PDF)
        ->and($guideAsset->mime)->toBe('application/pdf')
        ->and($sheetAsset->metadata['run_id'] ?? null)->toBe($run->id)
        ->and(Storage::disk('local')->exists($sheetAsset->path))->toBeTrue();

    $pageAsset = OmrTemplateAsset::query()
        ->where('authoring_draft_id', $draft->id)
        ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
        ->where('mime', 'image/png')
        ->whereJsonContains('metadata->role', 'challenge_sheet_page')
        ->first();
    expect($pageAsset)->not->toBeNull()
        ->and(Storage::disk('local')->exists($pageAsset->path))->toBeTrue()
        ->and($challenge->guide_json)->not->toBeNull()
        ->and($challenge->guide_json['schema_version'])->toBe('v2')
        ->and($challenge->guide_json['mode'])->toBe('quick');
    // The persisted calibration QR payload hash matches a re-sign of the
    // same challenge/page and verifies with the keyring.
    $qr = app(OmrCalibrationQrPayload::class);
    $expectedPayload = $qr->sign($challenge->challenge_code, 1, 1);
    expect($challenge->qr_payload_hash)->toBe($qr->payloadHash($expectedPayload));
    $verified = $qr->verify($expectedPayload);
    expect($verified['challenge_code'])->toBe($challenge->challenge_code);

    expect(AuditEvent::query()->where('event_type', 'omr.challenge.created')->whereJsonContains('payload', ['challenge_id' => $challenge->id])->exists())->toBeTrue();
});

test('document-bound candidate challenge binds marker-free page policy and persists printable rehearsal assets', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    Storage::fake('local');
    $draft = physicalChallengeDocumentBoundDraft($actor, 2);

    expect($draft->registration_mode)->toBe(OmrRegistrationMode::DocumentBound)
        ->and($draft->qr_mode)->toBe(OmrQrMode::Disabled)
        ->and($draft->marker_profile_id)->toBeNull();

    $created = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $run = $created['run']->fresh();
    $challenge = $created['challenge']->fresh();
    $loaded = app(OmrVerificationTargetLoader::class)->loadPhysical($run, 'pre_shaded');
    $target = $loaded['target'];
    $printMetadata = $challenge->print_metadata;
    $pageManifest = $challenge->page_manifest;
    $sheetAsset = $challenge->sheetAsset;
    $guideAsset = $challenge->guideAsset;
    $sheetBytes = Storage::disk($sheetAsset->disk)->get($sheetAsset->path);
    $guideBytes = Storage::disk($guideAsset->disk)->get($guideAsset->path);

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->mode)->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($target->registrationMode())->toBe(OmrRegistrationMode::DocumentBound)
        ->and($target->qrMode())->toBe(OmrQrMode::Disabled)
        ->and($target->compiledPages())->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($target->qualificationCapable())->toBeFalse()
        ->and($challenge->mode)->toBe(OmrPhysicalChallenge::MODE_QUICK)
        ->and($challenge->print_metadata['registration_mode'])->toBe(OmrRegistrationMode::DocumentBound->value)
        ->and($challenge->print_metadata['qr_mode'])->toBe(OmrQrMode::Disabled->value)
        ->and($challenge->print_metadata['compiled_pages'])->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($challenge->page_manifest_hash)->toBe(hash('sha256', json_encode($pageManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))
        ->and(array_column($pageManifest, 'page_no'))->toBe([1, 2])
        ->and($challenge->guide_json['mode'])->toBe('quick')
        ->and($challenge->guide_json['required_page_count'])->toBe(2)
        ->and($sheetBytes)->toStartWith('%PDF-')
        ->and(hash('sha256', $sheetBytes))->toBe($sheetAsset->sha256)
        ->and($guideBytes)->toStartWith('%PDF-')
        ->and(hash('sha256', $guideBytes))->toBe($guideAsset->sha256)
        ->and($challenge->sheet_asset_hash)->toBe($sheetAsset->sha256)
        ->and($challenge->guide_asset_hash)->toBe($guideAsset->sha256)
        ->and($printMetadata['target_policy_hash'])->toBe(hash('sha256', json_encode([
            'registration_mode' => OmrRegistrationMode::DocumentBound->value,
            'qr_mode' => OmrQrMode::Disabled->value,
            'compiled_pages' => [['page_no' => 1], ['page_no' => 2]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
});

test('document-bound physical target refuses stale marker metadata', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    Storage::fake('local');
    $draft = physicalChallengeDocumentBoundDraft($actor);
    $created = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $run = $created['run'];
    $authoring = (array) $draft->authoring_json;
    $authoring['canonical']['artifact_set']['manifest']['marker_profile'] = ['id' => 123];
    $draft->forceFill(['authoring_json' => $authoring])->save();

    expect(fn () => app(OmrVerificationTargetLoader::class)->loadPhysical($run, 'pre_shaded'))
        ->toThrow(OmrDiagnosticTargetException::class, 'candidate_registration_mode_mismatch')
        ->and($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->captures()->count())->toBe(0);
});

test('document-bound compiled full challenge uses exact pinned source pdf and ordered pages', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    Storage::fake('local');
    $draft = physicalChallengeDocumentBoundCompiledDraft($actor, 2);
    $artifactSet = $draft->authoring_json['canonical']['artifact_set'];

    $created = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, OmrPhysicalChallenge::MODE_FULL);
    $run = $created['run']->fresh();
    $challenge = $created['challenge']->fresh();
    $loaded = app(OmrVerificationTargetLoader::class)->loadPhysical($run, 'pre_shaded');
    $target = $loaded['target'];
    $sheetAsset = $challenge->sheetAsset;
    $guideAsset = $challenge->guideAsset;
    $sheetBytes = Storage::disk($sheetAsset->disk)->get($sheetAsset->path);
    $guideBytes = Storage::disk($guideAsset->disk)->get($guideAsset->path);

    expect($draft->registration_mode)->toBe(OmrRegistrationMode::DocumentBound)
        ->and($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->mode)->toBe(OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION)
        ->and($run->template_version_id)->toBe($draft->compiled_version_id)
        ->and($target->kind())->toBe(OmrDiagnosticTarget::KIND_COMPILED_VERSION)
        ->and($target->registrationMode())->toBe(OmrRegistrationMode::DocumentBound)
        ->and($target->qrMode())->toBe(OmrQrMode::Disabled)
        ->and($target->compiledPages())->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($target->qualificationCapable())->toBeTrue()
        ->and($challenge->source_artifact_hashes['input_pdf'])->toBe($artifactSet['pdf_hash'])
        ->and($challenge->print_metadata['compiled_pages'])->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and(array_column($challenge->page_manifest, 'page_no'))->toBe([1, 2])
        ->and($challenge->guide_json['mode'])->toBe('full')
        ->and($challenge->guide_json['required_page_count'])->toBe(2)
        ->and($sheetBytes)->toStartWith('%PDF-')
        ->and(hash('sha256', $sheetBytes))->toBe($sheetAsset->sha256)
        ->and($guideBytes)->toStartWith('%PDF-')
        ->and(hash('sha256', $guideBytes))->toBe($guideAsset->sha256);
});

test('document-bound rendered page rectifies through live worker but rejects unresolved identity', function (): void {
    $signingSecret = config('omr.omrchecker.signing_secret');
    physicalChallengeSetup();
    config([
        'omr.omrchecker.signing_secret' => $signingSecret,
        'omr.omrchecker.endpoint' => 'http://inference:8100',
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => 'de3f6982822895491be760325fd43e65e90fa293',
        'omr.omrchecker.patch_identity' => 'nexiam-headless-overlay-v1',
    ]);
    $worker = new OmrCheckerDetection;
    app()->instance(OmrDetectionPort::class, $worker);
    app()->instance(OmrLayoutVerificationPort::class, $worker);
    $actor = physicalChallengeActor();
    Storage::fake('local');
    $draft = physicalChallengeDocumentBoundDraft($actor);
    $created = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $run = $created['run']->fresh();
    $challenge = $created['challenge']->fresh();
    $pageAsset = OmrTemplateAsset::query()->findOrFail($challenge->page_manifest[0]['asset_id']);
    $pageBytes = Storage::disk($pageAsset->disk)->get($pageAsset->path);
    $pageImage = imagecreatefromstring($pageBytes);
    expect($pageImage)->not->toBeFalse();
    $framed = OmrDigitalProofGenerator::frameDocumentBoundPage($pageImage);
    imagedestroy($pageImage);
    ob_start();
    imagepng($framed['image']);
    $captureBytes = ob_get_clean();
    imagedestroy($framed['image']);
    expect($captureBytes)->toBeString()->not->toBeEmpty();

    $this->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.verification_runs.print', $run),
        physicalPrintPayload($run),
    )->assertAccepted();
    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [
        ...physicalPrintPayload($run),
        'image' => UploadedFile::fake()->createWithContent('framed-document.png', $captureBytes),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'live-document-capture',
    ])->assertCreated();

    $capture = $run->fresh()->captures()->sole();
    expect($capture->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_FAILED)
        ->and($capture->status)->toBe(OmrTemplateVerificationCapture::STATUS_FAIL)
        ->and($capture->vector_match)->toBeTrue()
        ->and($capture->gate_result['marker_count'])->toBe('not_applicable')
        ->and($capture->gate_result['rectification_consistency'])->toBe('pass')
        ->and($capture->summary['document_rectification_evidence']['layout']['homography_count'])->toBe(1)
        ->and($capture->summary['document_rectification_evidence']['detection']['homography_count'])->toBe(1)
        ->and($capture->summary['issues'])->toContain([
            'code' => 'identity_unresolved',
            'severity' => 'required',
            'area_code' => null,
            'question_no' => null,
        ])
        ->and($draft->fresh()->compiled_version_id)->toBeNull();
});

test('physical workflow and evidence payloads preserve current authority boundaries', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');

    $create = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'payload-snapshot-create',
        ])
        ->assertStatus(202)
        ->assertJsonStructure([
            'run_id', 'challenge_id', 'challenge_code', 'status', 'mode',
            'available_actions', 'print_url', 'print_mime', 'pdf_ready',
            'guide_url', 'guide_pdf_url', 'status_url', 'challenge', 'operation_id',
            'challenge' => [
                'challenge_id', 'challenge_code', 'mode', 'status', 'plan_hash',
                'expected_vector_hash', 'created_at', 'print_url', 'print_mime',
                'pdf_ready', 'guide_url', 'guide_pdf_url', 'guide' => [
                    'schema_version', 'mode', 'challenge_code', 'sections',
                ],
            ],
        ]);

    $run = OmrTemplateVerificationRun::query()->findOrFail($create->json('run_id'));
    $challenge = OmrPhysicalChallenge::query()->findOrFail($create->json('challenge_id'));
    $createPayload = $create->json();
    expect($createPayload)->toHaveKeys(['run_id', 'challenge_id', 'challenge_code', 'status', 'mode', 'available_actions', 'status_url'])
        ->and($createPayload['mode'])->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($createPayload['challenge']['mode'])->toBe(OmrPhysicalChallenge::MODE_QUICK)
        ->and($createPayload['challenge']['status'])->toBe(OmrPhysicalChallenge::STATUS_CREATED)
        ->and($createPayload['available_actions'])->toBe(['print_challenge'])
        ->and($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->mode)->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($run->attempt)->toBe(1)
        ->and($run->progress_total)->toBe(2)
        ->and($run->plan_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($run->expected_vector_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($run->artifact_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($run->native_bundle_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($run->manifest_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($run->config_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($run->mapping_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($challenge->plan_hash)->toBe($run->plan_hash)
        ->and($challenge->expected_vector_hash)->toBe($run->expected_vector_hash)
        ->and($challenge->qr_payload_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($challenge->sheet_asset_id)->not->toBeNull()
        ->and($challenge->guide_asset_id)->not->toBeNull();

    $workflow = app(OmrCanonicalWorkflowProjection::class)->workflowProps($draft->fresh(), $actor);
    expect($workflow)->toHaveKeys([
        'page_state', 'draft', 'physical_run_id', 'verification_run',
        'marker_profiles', 'aptitude_areas', 'stages', 'urls',
    ])
        ->and($workflow['page_state'])->toBe('ready')
        ->and($workflow['physical_run_id'])->toBeNull()
        ->and($workflow['draft'])->toHaveKeys([
            'id', 'code', 'name', 'stage', 'lock_version', 'digital_verified',
            'matching_verification_run_id', 'matching_verification',
            'marker_profile_id', 'compiled_version_id', 'physical_qualified',
            'owner_user_id', 'can_edit', 'authoring', 'persisted_preview',
        ])
        ->and($workflow['draft']['physical_qualified'])->toBeFalse()
        ->and($workflow['verification_run'])->toMatchArray([
            'projection' => 'summary',
            'run_id' => $run->id,
            'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
            'status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
            'attempt' => 1,
            'progress' => ['done' => 0, 'total' => 2, 'percent' => 0.0],
            'safe_issue' => null,
        ]);

    $status = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk()
        ->assertJsonStructure([
            'run_id', 'draft_lock_version', 'status', 'phase', 'phase_detail',
            'mode', 'attempt', 'progress' => ['done', 'total', 'percent'],
            'current_variant', 'safe_issue', 'result_summary',
            'available_actions', 'challenge_recovery', 'run_version',
            'plan' => ['plan_hash', 'plan_version', 'challenge_code', 'expected_vector_hash'],
            'target' => ['kind', 'id', 'hashes' => ['artifact', 'native_bundle', 'manifest', 'config', 'mapping']],
            'timestamps' => ['started_at', 'finished_at', 'last_heartbeat_at'],
            'evidence',
            'challenge' => [
                'challenge_id', 'challenge_code', 'mode', 'status', 'plan_hash',
                'expected_vector_hash', 'created_at', 'print_url', 'print_mime',
                'pdf_ready', 'guide_url', 'guide_pdf_url', 'guide', 'sheet_pages',
                'renderer_version', 'reference_current',
            ],
        ]);
    expect($status->json('mode'))->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($status->json('phase'))->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($status->json('target.kind'))->toBe('candidate')
        ->and($status->json('target.hashes'))->toHaveKeys(['artifact', 'native_bundle', 'manifest', 'config', 'mapping'])
        ->and($status->json('available_actions'))->toBe(['print_challenge'])
        ->and($status->json('evidence'))->toBe([])
        ->and($status->json('challenge.reference_current'))->toBeTrue()
        ->and($status->json('challenge.print_url'))->toContain('/assets/'.$challenge->sheet_asset_id)
        ->and($status->json('challenge.guide_url'))->toContain('/assets/'.$challenge->guide_asset_id);

    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))
        ->assertStatus(202)
        ->assertJson(['run_id' => $run->id, 'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE]);

    $capture = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('payload.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'payload-snapshot-capture', ])
        ->assertStatus(201)
        ->assertJsonStructure(['run_id', 'capture_id', 'status', 'status_url']);

    $captureRow = OmrTemplateVerificationCapture::query()->findOrFail($capture->json('capture_id'));
    DB::table('omr_template_verification_captures')
        ->where('id', $captureRow->id)
        ->update([
            'summary' => json_encode([
                'evidence' => [
                    'items' => [[
                        'item_id' => 'Q1',
                        'diagnostics' => str_repeat('x', 1_000_000),
                    ]],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
    $after = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk()
        ->assertJsonPath('evidence.0.capture_id', $captureRow->id)
        ->assertJsonPath('evidence.0.status', OmrTemplateVerificationCapture::STATUS_PASS);
    $evidence = $after->json('evidence.0');
    $method = collect($after->json('methods'))
        ->firstWhere('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    expect($capture->json())->toHaveKeys(['run_id', 'capture_id', 'status', 'status_url'])
        ->and($captureRow->input_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($captureRow->evidence_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($captureRow->expected_vector_hash)->toBe($run->expected_vector_hash)
        ->and($captureRow->input_asset_id)->not->toBeNull()
        ->and($captureRow->aligned_asset_id)->not->toBeNull()
        ->and($captureRow->overlay_asset_id)->not->toBeNull()
        ->and($evidence)->toHaveKeys([
            'capture_id', 'attempt', 'page_no', 'variant', 'expected', 'actual',
            'status', 'error_code', 'superseded_by_attempt_id', 'superseded_at',
            'input_hash', 'input_asset_id',
            'input_asset_sha256', 'evidence_hash', 'expected_vector_hash',
            'detected_vector_hash', 'vector_match', 'summary', 'captured_at',
            'urls',
        ])
        ->and($evidence['urls'])->toHaveKeys(['input', 'aligned', 'overlay'])
        ->and(strlen((string) $after->getContent()))->toBeLessThan(262_144)
        ->and($evidence['summary'])->toBeNull()
        ->and($method['attempts'])->toHaveCount(1)
        ->and($method['attempts'][0]['capture_id'])->toBe($captureRow->id)
        ->and($method['attempts'][0]['summary'])->toBeNull();
});
test('physical challenge rejects a failed durable digital run', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $digitalRun = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail();
    $digitalRun->forceFill(['status' => OmrTemplateVerificationRun::STATUS_FAILED])->save();
    Storage::fake('local');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), ['mode' => 'quick', 'idempotency_key' => 'ch-failed'])
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_pass_required']);
    expect(OmrTemplateVerificationRun::query()->whereIn('mode', [
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
    ])->count())->toBe(0);
});

test('physical challenge rejects an incomplete durable digital run', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $digitalRun = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail();
    $digitalRun->captures()->latest('id')->firstOrFail()->delete();
    Storage::fake('local');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), ['mode' => 'quick', 'idempotency_key' => 'ch-incomplete'])
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_pass_required']);
});

test('physical challenge rejects legacy passed JSON without a durable digital run', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $digitalRun = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail();
    $digitalRun->captures()->delete();
    $digitalRun->delete();
    $authoring = (array) $draft->authoring_json;
    $authoring['verification'] = [
        'status' => 'passed',
        'artifact_hash' => $authoring['canonical']['artifact_set']['artifact_hash'],
    ];
    $draft->forceFill(['authoring_json' => $authoring])->save();
    Storage::fake('local');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), ['mode' => 'quick', 'idempotency_key' => 'ch-legacy-only'])
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_pass_required']);
});

test('physical challenge resumes a second active request for the same target and mode', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $run = physicalChallengeQuickRun($actor, $draft);
    $challenge = $run->challenges()->firstOrFail();

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'ch-active',
        ])
        ->assertStatus(202)
        ->assertJson([
            'run_id' => $run->id,
            'challenge_id' => $challenge->id,
            'challenge_code' => $challenge->challenge_code,
            'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        ]);

    expect($response->json('available_actions'))->toContain('print_challenge')
        ->and(OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
            ->count())->toBe(1);
});

test('physical challenge keeps an active digital run as an active-run refusal', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $quickRun = physicalChallengeQuickRun($actor, $draft);
    app(OmrVerificationRunService::class)->fail($quickRun->id, 'worker_failure', 'legacy failure');
    $digitalRun = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail();
    $digitalRun->forceFill(['status' => OmrTemplateVerificationRun::STATUS_VARIANT_MATRIX])->save();
    Storage::fake('local');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'active-digital-key',
        ])
        ->assertStatus(422)
        ->assertJson([
            'code' => 'verification_active_run_in_progress',
            'detail' => 'A digital verification run is already active for this draft; wait for it to finish before creating a physical challenge.',
        ]);

    expect(OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->whereIn('mode', [OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL, OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION])
        ->count())->toBe(1);
});

test('physical challenge rejects a target rematerialized after digital verification', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $authoring = (array) $draft->authoring_json;
    $authoring['mapping']['field_blocks']['Part_1']['origin'][0]++;
    $draft->forceFill(['authoring_json' => $authoring])->save();
    Storage::fake('local');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), ['mode' => 'quick', 'idempotency_key' => 'ch-stale-materialized'])
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_pass_required']);
    expect(OmrTemplateVerificationRun::query()->whereIn('mode', [
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
    ])->count())->toBe(0);
});

test('physical challenge cleans assets and run when asset storage fails', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $assetCount = OmrTemplateAsset::query()->count();
    config(['omr.canonical.storage.disk' => 'missing-disk']);

    try {
        expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick'))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        config(['omr.canonical.storage.disk' => 'local']);
    }

    expect(OmrTemplateVerificationRun::query()->whereIn('mode', [
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
    ])->count())->toBe(0)
        ->and(OmrPhysicalChallenge::query()->count())->toBe(0)
        ->and(OmrTemplateAsset::query()->count())->toBe($assetCount);
});

test('renderer failure finalizes operation safely and allows a new-key retry', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');

    $initialRunCount = OmrTemplateVerificationRun::query()->whereIn('mode', [
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
    ])->count();
    $initialChallengeCount = OmrPhysicalChallenge::query()->count();
    $initialAssetCount = OmrTemplateAsset::query()->count();
    $initialAuditCount = AuditEvent::query()->where('event_type', 'omr.challenge.created')->count();

    $renderer = new class implements OmrPhysicalChallengeRendererPort
    {
        public function render(
            OmrDiagnosticTarget $target,
            ProofPurpose $purpose,
            string $pdfBytes,
            array $insetsPx = [],
            string $planVersion = OmrProofPlanner::CURRENT_PLAN_VERSION,
            ?string $method = null,
        ): array {
            throw new Exception('vendor payload must never reach the HTTP response');
        }
    };
    $this->app->instance(OmrPhysicalChallengeRendererPort::class, $renderer);

    $failed = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'renderer-failure-key',
        ])
        ->assertStatus(503)
        ->assertJson([
            'outcome' => 'failed',
            'code' => 'omr_renderer_unavailable',
            'detail' => 'The OMR renderer is temporarily unavailable.',
        ]);

    $operation = Operation::query()
        ->where('idempotency_key', sprintf('%s:%s:%s:%s', $actor->id, 'omr.canonical.physical_challenge', $draft->id, 'renderer-failure-key'))
        ->firstOrFail();
    expect($operation->status)->toBe(Operation::STATUS_FAILED)
        ->and($operation->last_error)->toBe('omr_renderer_unavailable: The OMR renderer is temporarily unavailable.')
        ->and($failed->json('detail'))->not->toContain('vendor payload')
        ->and(OmrTemplateVerificationRun::query()->whereIn('mode', [
            OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
            OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
        ])->count())->toBe($initialRunCount)
        ->and(OmrPhysicalChallenge::query()->count())->toBe($initialChallengeCount)
        ->and(OmrTemplateAsset::query()->count())->toBe($initialAssetCount)
        ->and(AuditEvent::query()->where('event_type', 'omr.challenge.created')->count())->toBe($initialAuditCount);

    $this->app->instance(OmrPhysicalChallengeRendererPort::class, app(OmrPhysicalChallengeRenderer::class));
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'renderer-retry-key',
        ])
        ->assertStatus(202);

    expect(OmrTemplateVerificationRun::query()->whereIn('mode', [
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
    ])->count())->toBe($initialRunCount + 1)
        ->and(OmrPhysicalChallenge::query()->count())->toBe($initialChallengeCount + 1)
        ->and(AuditEvent::query()->where('event_type', 'omr.challenge.created')->count())->toBe($initialAuditCount + 1);
});

test('physical challenge uses persisted bundle when runtime materialization drifts', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');

    $persistedHash = (string) $draft->authoring_json['canonical']['artifact_set']['artifact_hash'];
    $profile = $draft->markerProfile;
    $candidateVersion = (int) $draft->authoring_json['canonical']['artifact_set']['candidate_version'];
    $originalRatio = config('omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio');
    config(['omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio' => 18.0]);
    try {
        $fresh = app(CanonicalArtifactMaterializer::class)
            ->materialize($draft->fresh(['markerProfile', 'template']), $profile, $candidateVersion);
        expect($fresh->artifactHash)->not->toBe($persistedHash);
    } finally {
        config(['omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio' => $originalRatio]);
    }

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'persisted-bundle-drift',
        ])
        ->assertStatus(202);

    $digitalRun = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->latest('id')
        ->firstOrFail();
    $physicalRun = OmrTemplateVerificationRun::query()->findOrFail($response->json('run_id'));
    expect($physicalRun->artifact_hash)->toBe($digitalRun->artifact_hash)
        ->and($physicalRun->artifact_hash)->toBe($persistedHash);
});

test('full challenge on an uncompiled draft is refused with a typed error', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), ['mode' => 'full', 'idempotency_key' => 'ch-3'])
        ->assertStatus(422)
        ->assertJson(['code' => 'full_requires_compiled_version']);
    expect(OmrTemplateVerificationRun::query()->whereIn('mode', [
        OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
    ])->count())->toBe(0);
});

test('print acknowledgment moves the quick run to awaiting_capture', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $run = physicalChallengeQuickRun($actor, $draft);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))
        ->assertStatus(202)
        ->assertJson(['status' => 'awaiting_capture']);

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->fresh()->challenges()->first()->status)->toBe(OmrPhysicalChallenge::STATUS_PRINTED);
});

test('quick challenge waiting for print survives stale heartbeat status refresh', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $result = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $result['run'];
    $challenge = $result['challenge'];
    $run->forceFill(['last_heartbeat_at' => now()->subHours(2)])->save();

    $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk()
        ->assertJson([
            'status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
            'available_actions' => ['print_challenge'],
            'challenge' => [
                'challenge_id' => $challenge->id,
                'status' => OmrPhysicalChallenge::STATUS_CREATED,
            ],
        ]);

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);
});

test('failed unprinted challenge retry resumes the existing sheet without creating a pack', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $result = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $result['run'];
    $challenge = $result['challenge'];
    $assetCount = OmrTemplateAsset::query()->count();
    $runCount = OmrTemplateVerificationRun::query()->count();
    $challengeCount = OmrPhysicalChallenge::query()->count();
    app(OmrVerificationRunService::class)->fail($run->id, 'worker_failure', 'legacy failure');

    $retry = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.retry', $run))
        ->assertStatus(202)
        ->assertJson([
            'run_id' => $run->id,
            'status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
            'attempt' => 2,
        ]);

    expect($retry->json('status_url'))->toBe(route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]))
        ->and($run->fresh()->challenge_code)->toBe($challenge->challenge_code)
        ->and($run->fresh()->challenges()->first()->id)->toBe($challenge->id)
        ->and($run->fresh()->challenges()->first()->status)->toBe(OmrPhysicalChallenge::STATUS_CREATED)
        ->and(OmrTemplateAsset::query()->count())->toBe($assetCount)
        ->and(OmrTemplateVerificationRun::query()->count())->toBe($runCount)
        ->and(OmrPhysicalChallenge::query()->count())->toBe($challengeCount);
});

test('duplicate challenge creation resumes the existing challenge without a new run or assets', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $run = physicalChallengeQuickRun($actor, $draft);
    $challenge = $run->challenges()->firstOrFail();
    app(OmrVerificationRunService::class)->fail($run->id, 'worker_failure', 'legacy failure');
    $assetCount = OmrTemplateAsset::query()->count();
    $runCount = OmrTemplateVerificationRun::query()->count();
    $challengeCount = OmrPhysicalChallenge::query()->count();

    $response = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => 'duplicate-resume-key',
        ])
        ->assertStatus(202)
        ->assertJson([
            'run_id' => $run->id,
            'challenge_id' => $challenge->id,
            'challenge_code' => $challenge->challenge_code,
            'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        ]);

    expect($response->json('status_url'))->toBe(route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]))
        ->and($response->json('challenge.print_url'))->toBeString()
        ->and($response->json('challenge.guide_url'))->toBeString()
        ->and($response->json('available_actions'))->toContain('retry')
        ->and(OmrTemplateAsset::query()->count())->toBe($assetCount)
        ->and(OmrTemplateVerificationRun::query()->count())->toBe($runCount)
        ->and(OmrPhysicalChallenge::query()->count())->toBe($challengeCount);
});

test('physical challenge idempotency replays the original run and mode', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $route = route('setup.omr.templates.canonical.drafts.challenge.create', $draft);
    $payload = ['mode' => 'quick', 'idempotency_key' => 'physical-replay-key'];

    $first = $this->actingAs($actor)
        ->postJson($route, $payload)
        ->assertStatus(202);
    $runId = (int) $first->json('run_id');
    $challengeId = (int) $first->json('challenge_id');
    $operation = Operation::query()
        ->where('type', 'omr.canonical.physical_challenge')
        ->latest('created_at')
        ->firstOrFail();

    expect($operation->payload['result'] ?? null)->toMatchArray([
        'run_id' => $runId,
        'challenge_id' => $challengeId,
        'mode' => 'quick',
    ]);

    $this->actingAs($actor)
        ->postJson($route, $payload)
        ->assertStatus(202)
        ->assertJson([
            'run_id' => $runId,
            'challenge_id' => $challengeId,
            'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        ]);

    $this->actingAs($actor)
        ->postJson($route, ['mode' => 'full', 'idempotency_key' => 'physical-replay-key'])
        ->assertStatus(409)
        ->assertJson([
            'code' => 'idempotency_key_conflict',
            'detail' => 'This idempotency key is already bound to a different physical challenge request.',
        ]);
});

test('physical challenge idempotency remains isolated per draft', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $firstDraft = physicalChallengeDraft($actor);
    $secondDraft = physicalChallengeDraft($actor);
    Storage::fake('local');

    $second = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $secondDraft), [
            'mode' => 'quick',
            'idempotency_key' => 'same-key-different-draft',
        ])
        ->assertStatus(202);
    $first = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $firstDraft), [
            'mode' => 'quick',
            'idempotency_key' => 'same-key-different-draft',
        ])
        ->assertStatus(202);
    $secondResume = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $secondDraft), [
            'mode' => 'quick',
            'idempotency_key' => 'different-resume-key',
        ])
        ->assertStatus(202);

    expect($secondResume->json('run_id'))->toBe($second->json('run_id'))
        ->and($secondResume->json('challenge_id'))->toBe($second->json('challenge_id'))
        ->and($second->json('run_id'))->not->toBe($first->json('run_id'))
        ->and($second->json('challenge_id'))->not->toBe($first->json('challenge_id'))
        ->and(Operation::query()->where('type', 'omr.canonical.physical_challenge')->count())->toBe(3);
});

test('physical challenge returns an in-progress response for a concurrent same-key request', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $key = 'physical-concurrent-key';
    $requestPayload = ['draft_id' => $draft->id, 'mode' => 'quick'];
    Operation::query()->create([
        'idempotency_key' => sprintf('%s:%s:%s:%s', $actor->id, 'omr.canonical.physical_challenge', $draft->id, $key),
        'type' => 'omr.canonical.physical_challenge',
        'status' => Operation::STATUS_PROCESSING,
        'attempts' => 1,
        'payload' => [
            'request_hash' => hash('sha256', json_encode($requestPayload, JSON_THROW_ON_ERROR)),
            ...$requestPayload,
        ],
    ]);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.drafts.challenge.create', $draft), [
            'mode' => 'quick',
            'idempotency_key' => $key,
        ])
        ->assertStatus(409)
        ->assertJson([
            'code' => 'idempotency_in_progress',
        ]);
});

test('failed printed challenge retry resumes capture with the same challenge and no digital job', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $run = physicalChallengeQuickRun($actor, $draft);
    $challenge = $run->challenges()->firstOrFail();

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))
        ->assertStatus(202);
    app(OmrVerificationRunService::class)->fail($run->id, 'capture_analysis_failed', 'legacy failure');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.retry', $run))
        ->assertStatus(202)
        ->assertJson([
            'run_id' => $run->id,
            'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
            'attempt' => 2,
        ]);

    expect($run->fresh()->challenges()->first()->id)->toBe($challenge->id)
        ->and($run->fresh()->challenges()->first()->status)->toBe(OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE);
});

test('camera and upload captures are equivalent: identical bytes produce identical evidence', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $bytes = physicalChallengePng();
    $camera = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('camera.png', $bytes),
        'capture_source' => 'camera',
        'page_no' => 1,
        'idempotency_key' => 'cap-camera', ])->assertStatus(201)->json();

    // The run passed; a second attempt needs a fresh run (repeated capture refusal
    // is covered separately) — here we compare upload on a second run instead.
    $draft2 = physicalChallengeDraft($actor);
    $plan2 = physicalChallengePlanForDraft($draft2->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan2->expectedVector, 'exact'));
    $run2 = physicalChallengeQuickRun($actor, $draft2);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run2), physicalPrintPayload($run2))->assertStatus(202);
    $upload = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run2), [...physicalPrintPayload($run2), 'image' => UploadedFile::fake()->createWithContent('upload.png', $bytes),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-upload', ])->assertStatus(201)->json();

    $cameraCapture = OmrTemplateVerificationCapture::query()->findOrFail($camera['capture_id']);
    $uploadCapture = OmrTemplateVerificationCapture::query()->findOrFail($upload['capture_id']);
    expect($cameraCapture->input_hash)->toBe(hash('sha256', $bytes))
        ->and($uploadCapture->input_hash)->toBe(hash('sha256', $bytes))
        ->and($cameraCapture->input_hash)->toBe($uploadCapture->input_hash)
        ->and($cameraCapture->vector_match)->toBeTrue()
        ->and($uploadCapture->vector_match)->toBeTrue()
        ->and($cameraCapture->summary['capture_source'] ?? null)->toBe('camera')
        ->and($uploadCapture->summary['capture_source'] ?? null)->toBe('upload');

    // Camera/upload equivalence: identical bytes flow through the same
    // normalized detection projection — same item count, labels, expected
    // and detected options, and exact flags. Target identity (draft id,
    // pinned hashes, per-draft region mm) legitimately differs per draft.
    $cameraItems = collect($cameraCapture->summary['evidence']['items'])
        ->map(fn (array $item): array => [$item['native_label'], $item['expected'], $item['detected'], $item['exact']])
        ->all();
    $uploadItems = collect($uploadCapture->summary['evidence']['items'])
        ->map(fn (array $item): array => [$item['native_label'], $item['expected'], $item['detected'], $item['exact']])
        ->all();
    expect($uploadItems)->toBe($cameraItems);
});

test('an exact capture passes its method and stores forensic/aligned/overlay assets', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $bytes = physicalChallengePng();
    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', $bytes),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-exact', ])->assertStatus(201)->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT]);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->finished_at)->toBeNull();
    $capture = $run->captures()->firstOrFail();
    $alignedImage = $capture->layout_result['aligned_image'];
    $overlayImage = $capture->layout_result['overlay_image'];
    expect($capture->status)->toBe(OmrTemplateVerificationCapture::STATUS_PASS)
        ->and($capture->vector_match)->toBeTrue()
        ->and($capture->expected)->toBe('pass')
        ->and($capture->actual)->toBe('pass')
        ->and($capture->input_hash)->toBe(hash('sha256', $bytes))
        ->and($capture->input_asset_id)->not->toBeNull()
        ->and($capture->aligned_asset_id)->not->toBeNull()
        ->and($capture->overlay_asset_id)->not->toBeNull()
        ->and($capture->alignedAsset->metadata)->toMatchArray([
            'width' => $alignedImage['width'],
            'height' => $alignedImage['height'],
        ])
        ->and($capture->overlayAsset->metadata)->toMatchArray([
            'width' => $overlayImage['width'],
            'height' => $overlayImage['height'],
        ])
        ->and($capture->evidence_hash)->toHaveLength(64)
        ->and($capture->detected_vector_hash)->toHaveLength(64)
        ->and($capture->expected_vector_hash)->toBe($run->expected_vector_hash);
    expect($run->challenges()->first()->status)->toBe(OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE);
    expect(AuditEvent::query()->where('event_type', 'omr.challenge.capture_passed')->exists())->toBeTrue();
});
test('a capture with analyzed evidence requires an explicit retake and returns conflict', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [
        ...physicalPrintPayload($run),
        'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-before-retake',
    ])->assertStatus(201);

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [
        ...physicalPrintPayload($run),
        'image' => UploadedFile::fake()->createWithContent('replacement.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-without-retake',
    ])->assertStatus(409)->assertJson([
        'outcome' => 'failed',
        'code' => 'verification_capture_retake_required',
        'detail' => 'This page already has analyzed evidence. Start a retake before uploading another image',
        'page_no' => 1,
    ]);
});

test('keep-original crop policy is accepted and persisted in capture summary', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [
        ...physicalPrintPayload($run),
        'image' => UploadedFile::fake()->createWithContent('straight.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'crop_policy' => 'keep_original',
        'idempotency_key' => 'cap-keep-original',
    ])->assertStatus(201);

    expect($run->fresh()->captures()->firstOrFail()->summary['crop_policy'] ?? null)->toBe('keep_original');
});

test('retaking a selected page clears its effective selection until replacement review is selected', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))
        ->assertAccepted();

    $firstResponse = $this->actingAs($actor)->post(
        route('setup.omr.templates.canonical.verification_runs.capture', $run),
        [
            ...physicalPrintPayload($run),
            'image' => UploadedFile::fake()->createWithContent('selected-first.png', physicalChallengePng()),
            'capture_source' => 'upload',
            'page_no' => 1,
            'idempotency_key' => 'selected-page-first-capture',
        ],
    )->assertCreated();
    $firstCaptureId = (int) $firstResponse->json('capture_id');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.captures.select', [
            'run' => $run,
            'capture' => $firstCaptureId,
        ]), [
            'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            'page_no' => 1,
            'capture_id' => $firstCaptureId,
            'reason' => 'Initial exact capture selected before retake.',
            'idempotency_key' => 'selected-page-first-selection',
        ])
        ->assertOk();

    $method = $run->fresh()->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->firstOrFail();
    $page = $method->pages()->where('page_no', 1)->firstOrFail();
    expect($page->selected_attempt_id)->toBe($firstCaptureId)
        ->and($method->method_state)->toBe(OmrTemplateVerificationMethod::STATE_PASSED);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.pages.retake', [
            'run' => $run,
            'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            'page' => 1,
        ]), [
            'page_version' => $page->page_version,
            'idempotency_key' => 'selected-page-retake',
        ])
        ->assertOk()
        ->assertJson(['page_state' => OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE]);

    $page = $page->fresh();
    $method = $method->fresh();
    expect($page->selected_attempt_id)->toBeNull()
        ->and($page->page_state)->toBe(OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE)
        ->and($method->method_state)->toBe(OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE)
        ->and($run->fresh()->progress_done)->toBe(0)
        ->and($run->fresh()->status)->not->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and(OmrTemplateVerificationCapture::query()->findOrFail($firstCaptureId)->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_PASSED);

    $replacementResponse = $this->actingAs($actor)->post(
        route('setup.omr.templates.canonical.verification_runs.capture', $run),
        [
            ...physicalPrintPayload($run),
            'image' => UploadedFile::fake()->createWithContent('selected-replacement.png', physicalChallengePng()),
            'capture_source' => 'upload',
            'page_no' => 1,
            'idempotency_key' => 'selected-page-replacement-capture',
        ],
    )->assertCreated();
    $replacementCaptureId = (int) $replacementResponse->json('capture_id');

    $page = $page->fresh();
    $method = $method->fresh();
    expect($replacementCaptureId)->not->toBe($firstCaptureId)
        ->and(OmrTemplateVerificationCapture::query()->findOrFail($replacementCaptureId)->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_PASSED)
        ->and($page->selected_attempt_id)->toBeNull()
        ->and($page->page_state)->toBe(OmrTemplateVerificationMethodPage::STATE_NEEDS_REVIEW)
        ->and($method->method_state)->toBe(OmrTemplateVerificationMethod::STATE_NEEDS_REVIEW)
        ->and($run->fresh()->status)->not->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and(OmrTemplateVerificationCapture::query()->where('run_id', $run->id)->count())->toBe(2);
});

test('Run 31 retains a landscape marker mismatch before a portrait pass without qualifying a candidate', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));

    // The first four calls represent the landscape candidate's four quarter
    // turns. They expose all marker observations but fail the layout gate.
    $failedLayout = [
        'marker_matches' => [
            'quarter_1' => 0.35,
            'quarter_2' => 0.35,
            'quarter_3' => 0.35,
            'quarter_4' => 0.35,
        ],
        'gates' => [
            'marker_count' => 'fail',
            'registration' => 'fail',
            'dimensions' => 'pass',
            'shifts' => 'pass',
            'unsafe_blocks' => [],
        ],
    ];
    physicalChallengeFakeWorkerSequence(
        array_fill(0, 8, true),
        array_fill(0, 4, $failedLayout),
    );

    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))
        ->assertStatus(202)
        ->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE]);

    $landscape = physicalChallengeLandscapePng();
    $first = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('run31-landscape.png', $landscape),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'run31-landscape', ])
        ->assertStatus(201)
        ->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE]);

    $failedCapture = OmrTemplateVerificationCapture::query()
        ->where('run_id', $run->id)
        ->where('attempt', 1)
        ->firstOrFail();
    expect($failedCapture->error_code)->toBe('marker_mismatch')
        ->and($failedCapture->actual)->toBe(OmrTemplateVerificationCapture::ACTUAL_FAILED)
        ->and($failedCapture->status)->toBe(OmrTemplateVerificationCapture::STATUS_FAIL)
        ->and($failedCapture->input_hash)->toBe(hash('sha256', $landscape))
        ->and($failedCapture->input_asset_id)->not->toBeNull()
        ->and($failedCapture->aligned_asset_id)->not->toBeNull()
        ->and($failedCapture->overlay_asset_id)->not->toBeNull()
        ->and($failedCapture->summary['prepared_capture']['outcome'] ?? null)->toBe('failed')
        ->and($failedCapture->summary['prepared_capture']['code'] ?? null)->toBe('marker_mismatch')
        ->and($failedCapture->summary['prepared_capture']['orientation_diagnostics']['candidates'] ?? [])->toHaveCount(4)
        ->and($failedCapture->summary['prepared_capture']['orientation_diagnostics']['results'] ?? [])->toHaveCount(4);
    expect($first->json())->toHaveKeys(['run_id', 'capture_id', 'status', 'status_url'])
        ->and($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->fresh()->attempt)->toBe(1)
        ->and($run->fresh()->challenges()->firstOrFail()->status)->toBe(OmrPhysicalChallenge::STATUS_FAILED);

    $failedStatus = $this->actingAs($actor)->getJson(
        route('setup.omr.templates.canonical.verification_runs.status', $run),
    )->assertOk();
    $failedPreShaded = collect($failedStatus->json('methods'))
        ->firstWhere('method', 'pre_shaded');
    expect($failedStatus->json('phase_detail.failed_phase_code'))->toBe(OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION)
        ->and($failedPreShaded['phase_detail']['phase_code'] ?? null)->toBe(OmrTemplateVerificationRun::PHASE_FAILED)
        ->and($failedPreShaded['phase_detail']['failed_phase_code'] ?? null)->toBe(OmrTemplateVerificationRun::PHASE_LAYOUT_VERIFICATION);

    $methodPage = $run->fresh()->methods()->where('method', 'pre_shaded')->firstOrFail()->pages()->where('page_no', 1)->firstOrFail();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.pages.retake', [
        'run' => $run,
        'method' => 'pre_shaded',
        'page' => 1,
    ]), ['page_version' => $methodPage->page_version, 'idempotency_key' => 'retake-run31'])->assertOk();

    $portrait = physicalChallengePortraitPng();
    $second = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('run31-portrait.png', $portrait),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'run31-portrait', ])
        ->assertStatus(201)
        ->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT]);

    $passedCapture = OmrTemplateVerificationCapture::query()
        ->where('run_id', $run->id)
        ->where('attempt_no', 2)
        ->firstOrFail();
    expect($passedCapture->status)->toBe(OmrTemplateVerificationCapture::STATUS_PASS)
        ->and($passedCapture->actual)->toBe(OmrTemplateVerificationCapture::ACTUAL_PASS)
        ->and($passedCapture->vector_match)->toBeTrue()
        ->and($passedCapture->input_hash)->toBe(hash('sha256', $portrait))
        ->and($passedCapture->aligned_asset_id)->not->toBeNull()
        ->and($passedCapture->overlay_asset_id)->not->toBeNull()
        ->and($passedCapture->summary['prepared_capture']['outcome'] ?? null)->toBe('succeeded')
        ->and($passedCapture->summary['prepared_capture']['accepted_orientation'] ?? null)->toBe(270)
        ->and($passedCapture->summary['prepared_capture']['orientation_diagnostics']['candidates'] ?? [])->toHaveCount(4)
        ->and($passedCapture->summary['prepared_capture']['layout']['marker_matches'] ?? [])->toHaveCount(4)
        ->and($passedCapture->summary['prepared_capture']['gates']['marker_count'] ?? null)->toBe('pass');

    $status = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk();
    expect($status->json('status'))->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($status->json('evidence'))->toHaveCount(2)
        ->and($status->json('evidence.0.attempt'))->toBe(1)
        ->and($status->json('evidence.0.error_code'))->toBe('marker_mismatch')
        ->and($status->json('evidence.1.attempt'))->toBe(2)
        ->and($status->json('evidence.1.status'))->toBe(OmrTemplateVerificationCapture::STATUS_PASS)
        ->and($status->json('evidence.0.urls.input'))->toContain('/assets/'.$failedCapture->input_asset_id)
        ->and($status->json('evidence.1.urls.aligned'))->toContain('/assets/'.$passedCapture->aligned_asset_id);

    // The current pass is usable, but this candidate rehearsal remains a
    // non-qualifying evidence run and never changes marker-profile authority.
    $profile = $draft->markerProfile()->firstOrFail();
    $profileStatus = $profile->status;
    $profileEvidenceHash = $profile->qualification_evidence_hash;
    expect(fn () => app(RunOmrPhysicalQualification::class)->handle(
        $run->fresh(),
        $actor,
        [
            'renderer_version' => 'v1',
            'printer' => 'p',
            'device' => 'd',
            'paper' => 'A4',
            'measured_marker_center_mm' => [186, 271],
        ],
    ))->toThrow(RuntimeException::class, 'physical_qualification_mode_invalid');
    expect($run->fresh()->mode)->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE)
        ->and($draft->fresh()->authoring_json['physical_qualification'] ?? null)->toBeNull()
        ->and($profile->fresh()->status)->toBe($profileStatus)
        ->and($profile->fresh()->qualification_evidence_hash)->toBe($profileEvidenceHash);
});

test('an expected-vector mismatch parks the run in needs_review', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $this->withSession(['auth.staff_inactivity_generation' => 'physical-reviewed-acceptance']);
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'mismatch'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-mismatch', ])->assertStatus(201)->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT]);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);
    $capture = $run->captures()->first();
    expect($capture->vector_match)->toBeFalse()
        ->and($capture->actual)->toBe('answer_vector_mismatch')
        ->and($capture->status)->toBe('fail');

    $originalEvidence = $capture->only(['actual', 'vector_match', 'status', 'evidence_hash']);
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.review', $run), [
            'decision' => 'pass', 'capture_id' => $capture->id, 'idempotency_key' => 'rev-1',
        ])
        ->assertStatus(409)
        ->assertJson(['code' => 'verification_capture_exact_recovery_required']);
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.captures.select', ['run' => $run, 'capture' => $capture->id]), [
            'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
            'page_no' => 1,
            'capture_id' => $capture->id,
            'idempotency_key' => 'select-review-pass-1',
        ])
        ->assertStatus(409)
        ->assertJson(['code' => 'verification_capture_exact_recovery_required']);
    expect($capture->fresh()->only(['actual', 'vector_match', 'status', 'evidence_hash']))->toBe($originalEvidence)
        ->and(AuditEvent::query()->where('event_type', 'omr.challenge.capture_reviewed')->exists())->toBeFalse();

    $reviewItem = OmrTemplateVerificationItemEvidence::query()
        ->where('capture_id', $capture->id)
        ->where('group_type', OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA)
        ->where('state', OmrTemplateVerificationItemEvidence::STATE_MISMATCH)
        ->firstOrFail();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $run->id,
        'capture' => $capture->id,
        'item' => $reviewItem->id,
    ]), [
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => (string) $reviewItem->expected,
        'reason_code' => 'human_confirmed',
        'note' => 'The reviewer confirmed the expected mark against the captured sheet.',
        'expected_parent_version' => (int) $capture->lock_version,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => (string) $reviewItem->evidence_hash,
        'idempotency_key' => 'review-mismatch-item',
    ])->assertOk();
    $status = $this->actingAs($actor)->getJson(
        route('setup.omr.templates.canonical.verification_runs.status', $run),
    )->assertOk()->json();
    $methodStatus = collect($status['methods'])->firstWhere('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    $attemptStatus = collect($methodStatus['attempts'])->firstWhere('capture_id', (int) $capture->id);
    expect($attemptStatus['can_accept_reviewed'])->toBeTrue()
        ->and($attemptStatus['machine_needs_review_count'])->toBeGreaterThan(0)
        ->and($attemptStatus['needs_review_count'])->toBe(0);

    app(OmrPhysicalAssuranceCoordinator::class)->reviewCapture(
        $run->fresh(),
        $actor,
        (int) $capture->id,
        true,
        'Accepted after item review.',
    );
    $acceptedCapture = $capture->fresh();
    expect($acceptedCapture->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_PASSED)
        ->and($acceptedCapture->status)->toBe(OmrTemplateVerificationCapture::STATUS_PASS)
        ->and($acceptedCapture->actual)->toBe($originalEvidence['actual'])
        ->and($acceptedCapture->vector_match)->toBe($originalEvidence['vector_match'])
        ->and($acceptedCapture->evidence_hash)->toBe($originalEvidence['evidence_hash'])
        ->and($acceptedCapture->failure_code)->toBeNull()
        ->and($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->fresh()->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->value('method_state'))
        ->toBe(OmrTemplateVerificationMethod::STATE_PASSED)
        ->and($run->fresh()->challenges()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->value('status'))
        ->toBe(OmrPhysicalChallenge::STATUS_PASSED)
        ->and(AuditEvent::query()->where('event_type', 'omr.challenge.capture_reviewed')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('event_type', 'omr.challenge.attempt_selected')->exists())->toBeTrue();

    // A rejected review fails the run with a typed code.
    $draft3 = physicalChallengeDraft($actor);
    $plan3 = physicalChallengePlanForDraft($draft3->fresh(), ProofPurpose::QuickRehearsal);
    $run3 = physicalChallengeQuickRun($actor, $draft3);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run3), physicalPrintPayload($run3))->assertStatus(202);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan3->expectedVector, 'ambiguous'));
    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run3), [...physicalPrintPayload($run3), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'camera',
        'page_no' => 1,
        'idempotency_key' => 'cap-amb', ])->assertStatus(201)->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT]);
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.review', $run3), ['decision' => 'fail', 'note' => 'illegible', 'idempotency_key' => 'rev-2'])
        ->assertOk()
        ->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE]);
    expect($run3->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run3->fresh()->captures()->latest('id')->first()->failure_code)->toBe('review_rejected');
});

test('a fully reviewed gate-valid capture can be selected through the physical attempt selector', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $this->withSession(['auth.staff_inactivity_generation' => 'physical-reviewed-selection']);
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'mismatch'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))
        ->assertStatus(202);
    $this->actingAs($actor)
        ->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [
            ...physicalPrintPayload($run),
            'image' => UploadedFile::fake()->createWithContent('reviewed-selection.png', physicalChallengePng()),
            'capture_source' => 'upload',
            'page_no' => 1,
            'idempotency_key' => 'capture-reviewed-selection',
        ])
        ->assertStatus(201);

    $run = $run->fresh();
    $capture = $run->captures()->latest('id')->firstOrFail();
    $item = OmrTemplateVerificationItemEvidence::query()
        ->where('capture_id', $capture->id)
        ->where('group_type', OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA)
        ->where('state', OmrTemplateVerificationItemEvidence::STATE_MISMATCH)
        ->firstOrFail();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.captures.items.review', [
        'run' => $run->id,
        'capture' => $capture->id,
        'item' => $item->id,
    ]), [
        'decision' => OmrReviewDecision::SetOption->value,
        'selected_option' => (string) $item->expected,
        'reason_code' => 'human_confirmed',
        'note' => 'The reviewer confirmed the expected mark against the captured sheet.',
        'expected_parent_version' => (int) $capture->lock_version,
        'expected_item_revision' => 0,
        'reviewed_machine_evidence_hash' => (string) $item->evidence_hash,
        'idempotency_key' => 'review-reviewed-selection',
    ])->assertOk();

    $method = $run->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->firstOrFail();
    $page = $method->pages()->where('page_no', 1)->firstOrFail();
    $selectedPage = app(OmrPhysicalAssuranceCoordinator::class)->selectAttempt(
        $run,
        $actor,
        (string) $method->method,
        1,
        (int) $capture->id,
        '',
        expectedPageVersion: (int) $page->page_version,
    );
    $promoted = $capture->fresh();

    expect($selectedPage->selected_attempt_id)->toBe($promoted->id)
        ->and($promoted->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_PASSED)
        ->and($promoted->status)->toBe(OmrTemplateVerificationCapture::STATUS_PASS)
        ->and($promoted->actual)->toBe(OmrTemplateVerificationCapture::ACTUAL_ANSWER_VECTOR_MISMATCH)
        ->and($promoted->vector_match)->toBeFalse()
        ->and($promoted->failure_code)->toBeNull()
        ->and($run->fresh()->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->value('method_state'))
        ->toBe(OmrTemplateVerificationMethod::STATE_PASSED)
        ->and($run->fresh()->challenges()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->value('status'))
        ->toBe(OmrPhysicalChallenge::STATUS_PASSED)
        ->and($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);
});

test('an ambiguous or missing answer also parks the run in needs_review', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    foreach (['ambiguous', 'missing'] as $mode) {
        $draft = physicalChallengeDraft($actor);
        $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
        app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, $mode));
        physicalChallengeFakeWorker();
        $run = physicalChallengeQuickRun($actor, $draft);
        $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

        $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
            'capture_source' => 'upload',
            'page_no' => 1,
            'idempotency_key' => 'cap-'.$mode, ])->assertStatus(201)->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT]);
        expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);
    }
});

test('a failing dimension gate rejects the capture instead of offering mark review', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    // Worker returns an aligned image whose dimensions do not match the frame.
    physicalChallengeFakeWorker(true, ['aligned_image' => ['mime' => 'image/png', 'width' => 2000, 'height' => 3000], 'layout_overlay' => ['mime' => 'image/png', 'width' => 2000, 'height' => 3000]]);
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-shift', ])->assertStatus(201);

    $capture = $run->fresh()->captures()->first();
    expect($capture->vector_match)->toBeTrue()
        ->and($capture->actual)->toBe('typed_rejection')
        ->and($capture->failure_code)->toBe('dimensions_mismatch')
        ->and($capture->failure_detail)->toBe('dimensions_mismatch')
        ->and($capture->summary['gates']['dimensions'] ?? null)->toBe('fail');
});

test('a partial page or missing marker hard-fails the run with the typed worker code', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    foreach (['partial_page' => 'marker_missing', 'missing_marker' => 'marker_missing'] as $key => $code) {
        $draft = physicalChallengeDraft($actor);
        $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
        app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
        physicalChallengeFakeWorker(false, ['code' => $code, 'detail' => 'fixture '.$code]);
        $run = physicalChallengeQuickRun($actor, $draft);
        $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

        $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
            'capture_source' => 'upload',
            'page_no' => 1,
            'idempotency_key' => 'cap-'.$key, ])->assertStatus(201)->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE]);

        $run = $run->fresh();
        expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
            ->and($run->error_code)->toBeNull()
            ->and($run->captures()->first()->error_code)->toBe($code)
            ->and($run->captures()->first()->status)->toBe('fail')
            ->and($run->captures()->first()->actual)->toBe('failed');
    }
});

test('a wrong-challenge calibration QR is hard-rejected before analysis', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    // A payload signed for a DIFFERENT challenge code.
    $foreign = app(OmrCalibrationQrPayload::class)->sign('CHAL-DEADBEEF00', 1, 1);

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'camera',
        'page_no' => 1,
        'qr_payload' => $foreign,
        'idempotency_key' => 'cap-wrong-chal', ])->assertStatus(422)->assertJson(['code' => 'wrong_challenge']);

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->fresh()->captures()->count())->toBe(0);
});

test('a blank-instance QR is refused for a calibration challenge capture', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $blankPayload = app(OmrQrPayload::class)->sign('NI-ABCDEFGHJKMNPQRSTVWXYZ1234', 1, 1);

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'camera',
        'page_no' => 1,
        'qr_payload' => $blankPayload,
        'idempotency_key' => 'cap-blank-qr', ])->assertStatus(422)->assertJson(['code' => 'qr_purpose_mismatch']);
});

test('a tampered calibration QR is hard-rejected', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $payload = app(OmrCalibrationQrPayload::class)->sign((string) $run->challenge_code, 1, 1);
    $tampered = substr($payload, 0, -1).($payload[-1] === 'a' ? 'b' : 'a');

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'camera',
        'page_no' => 1,
        'qr_payload' => $tampered,
        'idempotency_key' => 'cap-tampered', ])->assertStatus(422)->assertJson(['code' => 'qr_signature_invalid']);
});

test('repeated capture submissions replay the same immutable attempt', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $payload = [
        ...physicalPrintPayload($run),
        'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-repeat',
    ];
    $first = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), $payload)
        ->assertStatus(201);

    // Same idempotency key returns the original immutable attempt.
    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), $payload)
        ->assertStatus(201)
        ->assertJson(['capture_id' => $first->json('capture_id')]);
    expect($run->fresh()->captures()->count())->toBe(1);
});

test('a multi-page challenge passes its method only after every page recovers exactly', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor, 2);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    expect($plan->pageInstructions)->toHaveCount(2);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);
    expect($run->progress_total)->toBe(4);

    // Page 1 passes but page 2 remains: the run returns to awaiting_capture.
    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('page1.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-p1', ])->assertStatus(201)->assertJson(['status' => 'awaiting_capture']);
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->fresh()->progress_done)->toBe(0);

    // Page 2 completes the challenge.
    $page2Response = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('page2.pdf', physicalChallengeTwoPagePdfBytes()),
        'capture_source' => 'upload',
        'page_no' => 2,
        'idempotency_key' => 'cap-p2', ]);
    $page2Response->assertStatus(201)->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT]);

    $run = $run->fresh();
    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->captures()->where('page_no', 1)->first()->vector_match)->toBeTrue()
        ->and($run->captures()->where('page_no', 2)->first()->vector_match)->toBeTrue()
        ->and($run->captures()->count())->toBe(2);
});

test('a scanned PDF capture is rasterized and analyzed like a camera image', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    $pdfBytes = physicalChallengePdfBytes();
    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('scan.pdf', $pdfBytes),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-pdf', ])->assertStatus(201)->assertJson(['status' => OmrTemplateVerificationRun::STATUS_AWAITING_PRINT]);

    $capture = $run->fresh()->captures()->first();
    expect($capture->status)->toBe('pass')
        ->and($capture->vector_match)->toBeTrue()
        // Forensic asset keeps the ORIGINAL scanned PDF bytes.
        ->and($capture->inputAsset->mime)->toBe('application/pdf')
        ->and($capture->input_hash)->toBe(hash('sha256', $pdfBytes));
});

test('a stale target refuses every physical print transition', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $run = physicalChallengeQuickRun($actor, $draft);

    $draft->forceFill([
        'authoring_json' => [
            ...(array) $draft->authoring_json,
            'canonical' => [...(array) ($draft->authoring_json['canonical'] ?? []), 'artifact_set' => [
                ...(array) ($draft->authoring_json['canonical']['artifact_set'] ?? []),
                'artifact_hash' => str_repeat('x', 64),
            ]],
        ],
    ])->save();

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.prepare_print', $run), [
            'method' => OmrProofPlan::METHOD_PRE_SHADED,
            'idempotency_key' => 'prepare-stale-target',
        ])
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_verification_stale']);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.challenge.regenerate', $run), [
            'idempotency_key' => 'regenerate-stale-target',
        ])
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_verification_stale']);

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))
        ->assertStatus(422)
        ->assertJson(['code' => 'digital_verification_stale']);

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->fresh()->error_code)->toBe('stale_target');
});

test('a stale target refuses capture with plan_target_mismatch', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $run = physicalChallengeQuickRun($actor, $draft);
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run))->assertStatus(202);

    // The draft artifact hash moves AFTER the challenge was pinned.
    $draft->forceFill([
        'authoring_json' => [
            ...(array) $draft->authoring_json,
            'canonical' => [...(array) ($draft->authoring_json['canonical'] ?? []), 'artifact_set' => [
                ...(array) ($draft->authoring_json['canonical']['artifact_set'] ?? []),
                'artifact_hash' => str_repeat('x', 64),
            ]],
        ],
    ])->save();

    $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [
        ...physicalPrintPayload($run),
        'image' => UploadedFile::fake()->createWithContent('sheet.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'page_no' => 1,
        'idempotency_key' => 'cap-stale',
    ])->assertStatus(422)->assertJson(['code' => 'digital_verification_stale']);
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->fresh()->error_code)->toBe('stale_target');
});

test('status projection exposes PDF print_mime, pdf_ready, structured guide, and guide_pdf_url', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $result = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $result['run'];
    $challenge = $result['challenge'];

    $response = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk();

    $challengeData = $response->json('challenge');
    expect($challengeData)->not->toBeNull()
        ->and($challengeData['challenge_id'])->toBe($challenge->id)
        ->and($challengeData['print_mime'])->toBe('application/pdf')
        ->and($challengeData['pdf_ready'])->toBeTrue()
        ->and($challengeData['print_url'])->toContain((string) $challenge->sheet_asset_id)
        ->and($challengeData['guide_pdf_url'])->toContain((string) $challenge->guide_asset_id)
        ->and($challengeData['guide'])->not->toBeNull()
        ->and($challengeData['guide']['schema_version'])->toBe('v2')
        ->and($challengeData['guide']['mode'])->toBe('quick')
        ->and($challengeData['guide']['sections'])->not->toBeEmpty()
        ->and($challengeData['sheet_pages'])->toBeArray()
        ->and($challengeData['sheet_pages'])->not->toBeEmpty()
        ->and($challengeData['sheet_pages'][0]['page_no'])->toBe(1)
        ->and($challengeData['sheet_pages'][0]['mime'])->toBe('image/png')
        ->and($challengeData['sheet_pages'][0]['url'])->toContain('/assets/');

    $methodChallenge = collect($response->json('methods'))->firstWhere('method', 'pre_shaded')['challenge'] ?? null;
    expect($methodChallenge)->not->toBeNull()
        ->and($methodChallenge['challenge_id'])->toBe($challenge->id)
        ->and($methodChallenge['method'])->toBe('pre_shaded')
        ->and($methodChallenge['print_mime'])->toBe('application/pdf')
        ->and($methodChallenge['pdf_ready'])->toBeTrue()
        ->and($methodChallenge['print_url'])->toContain((string) $challenge->sheet_asset_id)
        ->and($methodChallenge['guide_pdf_url'])->toContain((string) $challenge->guide_asset_id)
        ->and($methodChallenge['guide'])->not->toBeNull()
        ->and($methodChallenge['guide']['schema_version'])->toBe('v2')
        ->and($methodChallenge['guide']['mode'])->toBe('quick')
        ->and($methodChallenge['guide']['sections'])->not->toBeEmpty()
        ->and($methodChallenge['sheet_pages'])->toBeArray()
        ->and($methodChallenge['sheet_pages'])->not->toBeEmpty()
        ->and($methodChallenge['sheet_pages'][0]['page_no'])->toBe(1)
        ->and($methodChallenge['sheet_pages'][0]['mime'])->toBe('image/png')
        ->and($methodChallenge['sheet_pages'][0]['url'])->toContain('/assets/');

    $handResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', 'hand_shaded');
    $handChallenge = $handResult['challenge'];

    $responseAfterHand = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk();

    $handMethodChallenge = collect($responseAfterHand->json('methods'))->firstWhere('method', 'hand_shaded')['challenge'] ?? null;
    expect($handMethodChallenge)->not->toBeNull()
        ->and($handMethodChallenge['challenge_id'])->toBe($handChallenge->id)
        ->and($handMethodChallenge['method'])->toBe('hand_shaded')
        ->and($handMethodChallenge['print_mime'])->toBe('application/pdf')
        ->and($handMethodChallenge['pdf_ready'])->toBeTrue()
        ->and($handMethodChallenge['print_url'])->toContain((string) $handChallenge->sheet_asset_id)
        ->and($handMethodChallenge['guide_pdf_url'])->toContain((string) $handChallenge->guide_asset_id)
        ->and($handMethodChallenge['guide'])->not->toBeNull()
        ->and($handMethodChallenge['guide']['schema_version'])->toBe('v2')
        ->and($handMethodChallenge['guide']['mode'])->toBe('quick')
        ->and($handMethodChallenge['guide']['sections'])->not->toBeEmpty()
        ->and($handMethodChallenge['sheet_pages'])->toBeArray()
        ->and($handMethodChallenge['sheet_pages'])->not->toBeEmpty()
        ->and($handMethodChallenge['sheet_pages'][0]['page_no'])->toBe(1)
        ->and($handMethodChallenge['sheet_pages'][0]['mime'])->toBe('image/png')
        ->and($handMethodChallenge['sheet_pages'][0]['url'])->toContain('/assets/');

    $handGuideRows = collect($handMethodChallenge['guide']['sections'])->flatMap(
        static fn (array $section): array => $section['labels'],
    );
    expect($handGuideRows)->not->toBeEmpty()
        ->and($handGuideRows->every(
            static fn (array $row): bool => filled($row['scenario_id'] ?? null)
                && filled($row['instruction'] ?? null)
                && filled($row['expected_result'] ?? null)
                && array_key_exists('answer_condition', $row)
                && array_key_exists('candidates', $row),
        ))->toBeTrue()
        ->and($handMethodChallenge['guide']['unsampled_rows'])->toBe([
            'expected_answer' => null,
            'instruction' => 'Leave blank — intentionally unkeyed control.',
        ]);
});

test('prepare-print converts legacy PNG pack to Actual-Size PDF and updates challenge', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $result = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $result['run'];
    $challenge = $result['challenge'];

    // Simulate legacy PNG pack: change sheet_asset_id to the PNG page asset
    $pageAsset = OmrTemplateAsset::query()
        ->where('authoring_draft_id', $draft->id)
        ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
        ->firstOrFail();
    OmrPhysicalChallenge::allowPreCaptureRegeneration(static function () use ($challenge, $pageAsset): void {
        $challenge->forceFill(['sheet_asset_id' => $pageAsset->id])->save();
    });
    expect($challenge->fresh()->sheetAsset->mime)->toBe('image/png');

    // Status should report pdf_ready false and available action prepare_print
    $statusResponse = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.status', $run))
        ->assertOk();
    expect($statusResponse->json('challenge.pdf_ready'))->toBeFalse()
        ->and($statusResponse->json('challenge.print_mime'))->toBe('image/png')
        ->and($statusResponse->json('challenge.sheet_pages'))->toBeArray()
        ->and($statusResponse->json('challenge.sheet_pages'))->not->toBeEmpty()
        ->and($statusResponse->json('available_actions'))->toContain('prepare_print');

    // Call prepare-print endpoint
    $prepareResponse = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.prepare_print', $run), [
            'idempotency_key' => 'prep-1',
        ])
        ->assertOk()
        ->assertJsonStructure(['run_id', 'challenge_id', 'challenge_code', 'status', 'print_url', 'guide_url', 'status_url']);

    $updatedChallenge = $challenge->fresh();
    expect($updatedChallenge->sheet_asset_id)->not->toBe($pageAsset->id)
        ->and($updatedChallenge->sheetAsset->mime)->toBe('application/pdf')
        ->and($updatedChallenge->sheetAsset->kind)->toBe(OmrTemplateAsset::KIND_PRINT_PDF);

    expect(AuditEvent::query()->where('event_type', 'omr.challenge.print_prepared')->whereJsonContains('payload', ['challenge_id' => $challenge->id])->exists())->toBeTrue();

    // Calling prepare-print again is idempotent
    $replayResponse = $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.prepare_print', $run), [
            'idempotency_key' => 'prep-2',
        ])
        ->assertOk();
    expect($replayResponse->json('print_url'))->toBe($prepareResponse->json('print_url'))
        ->and($challenge->fresh()->sheet_asset_id)->toBe($updatedChallenge->sheet_asset_id);
});

test('prepare-print refuses invalid run status', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $result = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $result['run'];
    $run->forceFill(['status' => OmrTemplateVerificationRun::STATUS_PASSED])->save();

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.prepare_print', $run), [
            'idempotency_key' => 'prep-invalid-status',
        ])
        ->assertStatus(422)
        ->assertJson(['code' => 'challenge_prepare_print_invalid_status']);
});

test('both physical methods complete in reverse order with explicit selection', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $profile = $draft->markerProfile;
    $candidateVersion = (int) (($draft->authoring_json['canonical']['artifact_set']['candidate_version'] ?? 0));
    $materialized = app(CanonicalArtifactMaterializer::class)
        ->materialize($draft->fresh(['markerProfile']), $profile, $candidateVersion > 0 ? $candidateVersion : null);
    $target = new CandidateArtifactDiagnosticTarget($draft->fresh(['markerProfile']), $materialized, $profile);
    $handPlan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::QuickRehearsal, 'hand_shaded');
    $prePlan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::QuickRehearsal, 'pre_shaded');

    // Materialize the hand-shaded pack first, then the pre-shaded pack.
    $hand = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', 'hand_shaded');
    $pre = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', 'pre_shaded');
    $run = $hand['run']->fresh();
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run, 'hand_shaded'))
        ->assertAccepted();

    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($handPlan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $handCapture = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('hand.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'method' => OmrTemplateVerificationCapture::METHOD_HAND_SHADED,
        'challenge_id' => $hand['challenge']->id,
        'page_no' => 1,
        'target_hash' => $handPlan->targetHashes['artifact'],
        'plan_hash' => $handPlan->planHash(),
        'expected_vector_hash' => hash('sha256', json_encode($handPlan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        'idempotency_key' => 'reverse-hand-capture', ])->assertCreated();
    $handCaptureId = (int) $handCapture->json('capture_id');
    expect(OmrTemplateVerificationCapture::query()->findOrFail($handCaptureId)->medium)
        ->toBe(OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL);
    expect($handCapture->json('status'))->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT);

    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.captures.select', ['run' => $run, 'capture' => $handCaptureId]), [
        'method' => OmrTemplateVerificationCapture::METHOD_HAND_SHADED,
        'page_no' => 1,
        'capture_id' => $handCaptureId,
        'reason' => 'Reverse-order hand-shaded review accepted.',
        'idempotency_key' => 'reverse-hand-select',
    ])->assertOk();

    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($prePlan->expectedVector, 'exact'));
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), physicalPrintPayload($run, 'pre_shaded', 'reverse-pre-print'))
        ->assertAccepted();
    $preCapture = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('pre.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'challenge_id' => $pre['challenge']->id,
        'page_no' => 1,
        'target_hash' => $prePlan->targetHashes['artifact'],
        'plan_hash' => $prePlan->planHash(),
        'expected_vector_hash' => hash('sha256', json_encode($prePlan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        'idempotency_key' => 'reverse-pre-capture', ])->assertCreated();
    $preCaptureId = (int) $preCapture->json('capture_id');
    $prePage = $run->fresh()->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->firstOrFail()->pages()->where('page_no', 1)->firstOrFail();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.pages.retake', [
        'run' => $run,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'page' => 1,
    ]), ['page_version' => $prePage->page_version, 'idempotency_key' => 'retake-pre-replacement'])->assertOk();
    $replacementCapture = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('pre-retry.png', physicalChallengePng()),
        'capture_source' => 'upload',
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'challenge_id' => $pre['challenge']->id,
        'page_no' => 1,
        'target_hash' => $prePlan->targetHashes['artifact'],
        'plan_hash' => $prePlan->planHash(),
        'expected_vector_hash' => hash('sha256', json_encode($prePlan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        'idempotency_key' => 'reverse-pre-retry', ])->assertCreated();
    $replacementCaptureId = (int) $replacementCapture->json('capture_id');
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.captures.select', ['run' => $run, 'capture' => $preCaptureId]), [
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'page_no' => 1,
        'capture_id' => $preCaptureId,
        'reason' => 'Reverse-order pre-shaded review accepted.',
        'idempotency_key' => 'reverse-pre-select-first',
    ])->assertOk();
    $this->actingAs($actor)->postJson(route('setup.omr.templates.canonical.verification_runs.captures.select', ['run' => $run, 'capture' => $replacementCaptureId]), [
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'page_no' => 1,
        'capture_id' => $replacementCaptureId,
        'reason' => 'Replacement pre-shaded evidence accepted with operator reason.',
        'idempotency_key' => 'reverse-pre-select-replacement',
    ])->assertOk();

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->fresh()->methods()->where('method', OmrTemplateVerificationCapture::METHOD_HAND_SHADED)->value('method_state'))->toBe('passed')
        ->and($run->fresh()->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->value('method_state'))->toBe('passed')
        ->and($run->fresh()->methods()->count())->toBe(2);
    expect(OmrTemplateVerificationCapture::query()->findOrFail($preCaptureId)->attempt_state)->toBe(OmrTemplateVerificationCapture::ATTEMPT_SUPERSEDED)
        ->and(OmrTemplateVerificationCapture::query()->findOrFail($preCaptureId)->supersede_reason)->toContain('Replacement')
        ->and(OmrTemplateVerificationMethodPage::query()->where('method_id', $run->methods()->where('method', OmrTemplateVerificationCapture::METHOD_PRE_SHADED)->value('id'))->value('selected_attempt_id'))->toBe($replacementCaptureId);
});

test('method-bound print is independently idempotent', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', 'pre_shaded');
    app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', 'hand_shaded');
    $run = OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->latest('id')->firstOrFail();

    $prePayload = physicalPrintPayload($run, 'pre_shaded', 'independent-pre-print');
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), $prePayload)
        ->assertStatus(202)
        ->assertJson(['method' => 'pre_shaded', 'challenge_id' => $prePayload['challenge_id']]);
    $auditCount = AuditEvent::query()->where('event_type', 'omr.challenge.printed')->count();

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), $prePayload)
        ->assertStatus(202)
        ->assertJson(['method' => 'pre_shaded', 'challenge_id' => $prePayload['challenge_id']]);

    expect(AuditEvent::query()->where('event_type', 'omr.challenge.printed')->count())->toBe($auditCount)
        ->and($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($run->fresh()->methods()->where('method', 'pre_shaded')->value('method_state'))->toBe(OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE)
        ->and($run->fresh()->methods()->where('method', 'hand_shaded')->value('method_state'))->toBe(OmrTemplateVerificationMethod::STATE_CHALLENGE_READY);

    $handPayload = physicalPrintPayload($run->fresh(), 'hand_shaded', 'independent-hand-print');
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), $handPayload)
        ->assertStatus(202);
    expect($run->fresh()->methods()->where('method', 'hand_shaded')->value('method_state'))->toBe(OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE);
});

test('cross-method print challenge is rejected without mutation', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', 'pre_shaded');
    app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick', 'hand_shaded');
    $run = OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->latest('id')->firstOrFail();
    $payload = physicalPrintPayload($run, 'pre_shaded', 'cross-method-print');
    $payload['challenge_id'] = (int) $run->challenges()->where('method', 'hand_shaded')->value('id');

    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), $payload)
        ->assertStatus(409)
        ->assertJson(['code' => 'challenge_method_mismatch']);

    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($run->fresh()->challenges()->where('status', OmrPhysicalChallenge::STATUS_CREATED)->count())->toBe(2)
        ->and(AuditEvent::query()->where('event_type', 'omr.challenge.printed')->count())->toBe(0);
});

test('physical capture reuses persisted bundle when runtime materialization drifts', function (): void {
    physicalChallengeSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);
    Storage::fake('local');
    $run = physicalChallengeQuickRun($actor, $draft);
    $print = physicalPrintPayload($run, 'pre_shaded', 'persisted-capture-print');
    $this->actingAs($actor)
        ->postJson(route('setup.omr.templates.canonical.verification_runs.print', $run), $print)
        ->assertStatus(202);

    $plan = physicalChallengePlanForDraft($draft->fresh(), ProofPurpose::QuickRehearsal);
    app()->instance(OmrDetectionPort::class, new PhysicalCaptureDetectorFake($plan->expectedVector, 'exact'));
    physicalChallengeFakeWorker();
    $originalRatio = config('omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio');
    config(['omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio' => 18.0]);
    try {
        $capture = $this->actingAs($actor)->post(route('setup.omr.templates.canonical.verification_runs.capture', $run), [...physicalPrintPayload($run), 'image' => UploadedFile::fake()->createWithContent('persisted-capture.png', physicalChallengePng()),
            'capture_source' => 'upload',
            'method' => 'pre_shaded',
            'challenge_id' => $print['challenge_id'],
            'page_no' => 1,
            'target_hash' => $print['target_hash'],
            'plan_hash' => $print['plan_hash'],
            'expected_vector_hash' => $print['expected_vector_hash'],
            'idempotency_key' => 'persisted-capture', ])->assertCreated();
    } finally {
        config(['omr.capture-policy.v1.fiducial.sheet_to_marker_width_ratio' => $originalRatio]);
    }

    expect($capture->json('status'))->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and(OmrTemplateVerificationCapture::query()->where('run_id', $run->id)->where('status', OmrTemplateVerificationCapture::STATUS_PASS)->count())->toBe(1);
});
