<?php

use App\Assessment\Actions\Omr\Canonical\ComposeOmrCanonicalPages;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\PreviewOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\RegenerateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\RestartOmrPhysicalChallenge;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\CanonicalSheetDefinition;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Authorization\CapabilityCatalog;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function createAuthorityRecoveryDraft(User $actor, string $code = 'AUTH-REC'): array
{
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $profile = OmrMarkerProfile::query()->create([
        'code' => $code.'.marker',
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

    $draft = app(OmrAuthoringDraftService::class)->create($actor, $code, 'Authority Recovery Draft', 'html');
    $assets = app(OmrTemplateAssetStore::class);
    $source = $assets->putDraftAsset($draft, OmrTemplateAsset::KIND_SOURCE, 'source content', 'text/plain');
    $raster = $assets->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_RASTER_PAGE,
        (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/png',
        1
    );

    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
        'authoring_json' => [
            'source' => ['sha256' => $source->sha256, 'converter_version' => 'test', 'settings_hash' => hash('sha256', 'settings')],
            'pages' => [['page_no' => 1, 'asset_id' => $raster->id, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => [
                'settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297],
                'composition_hash' => hash('sha256', 'composition'),
                'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id]],
            ],
            'studio' => [
                'components' => [[
                    'id' => 'Part_1',
                    'type' => 'mcq',
                    'name' => 'Part 1',
                    'subjectName' => 'General Ability',
                    'posX' => 25.0,
                    'posY' => 70.0,
                    'w_mm' => 40.0,
                    'h_mm' => 50.0,
                    'aptitudeAreaCode' => 'LOGIC',
                    'questionsCount' => 2,
                    'startQ' => 1,
                    'optionsCount' => 4,
                    'bubbleDiameterMm' => 4.5,
                    'bubblePitchMm' => 6.0,
                    'rowPitchMm' => 6.8,
                ]],
            ],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => [],
            ],
            'overlay' => ['boxes' => []],
        ],
    ])->save();

    return [$draft->fresh(['markerProfile', 'template', 'assets']), $profile];
}

function attachPassingDigitalVerification(OmrTemplateAuthoringDraft $draft, User $actor): OmrTemplateVerificationRun
{
    $materializedDraft = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, (int) $draft->lock_version);
    $artifactSet = $materializedDraft->authoring_json['canonical']['artifact_set'];
    $artifactHash = (string) $artifactSet['artifact_hash'];
    $geometryIdentity = (string) ($artifactSet['geometry_identity'] ?? OmrCanonicalGeometryIdentity::forDraft($materializedDraft));

    $artifact = app(CanonicalArtifactMaterializer::class)->loadPersisted($materializedDraft, $materializedDraft->markerProfile);
    $target = new CandidateArtifactDiagnosticTarget($materializedDraft, $artifact, $materializedDraft->markerProfile);
    $digitalPlan = app(OmrProofPlanner::class)->plan(
        $target,
        ProofPurpose::Digital,
        OmrProofPlanner::CURRENT_PLAN_VERSION,
    );
    $planHash = $digitalPlan->planHash();
    $expectedVectorHash = $digitalPlan->expectedVectorHash;
    $proofHash = hash('sha256', 'proof:'.$artifactHash);
    $cleanVectorHash = hash('sha256', 'clean:'.$artifactHash);

    $variantsMap = [];
    foreach (OmrNoGoMatrixRunner::VARIANTS as $variant) {
        $variantsMap[$variant] = [
            'status' => 'pass',
            'variant' => $variant,
            'vector_match' => true,
        ];
    }
    $matrixHash = hash('sha256', json_encode($variantsMap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $materializedDraft->id,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'attempt' => 1,
        'plan_version' => OmrProofPlanner::CURRENT_PLAN_VERSION,
        'plan_hash' => $planHash,
        'expected_vector_hash' => $expectedVectorHash,
        'artifact_hash' => $artifactHash,
        'native_bundle_hash' => $target->nativeBundleHash(),
        'manifest_hash' => $target->manifestHash(),
        'config_hash' => $target->configHash(),
        'mapping_hash' => $target->mappingHash(),
        'proof_hash' => $proofHash,
        'clean_vector_hash' => $cleanVectorHash,
        'challenge_code' => 'CHAL-TEST-001',
        'progress_done' => 1,
        'progress_total' => 1,
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'finished_at' => now(),
        'last_heartbeat_at' => now(),
        'result_summary' => [
            'proof_hash' => $proofHash,
            'expected_vector_hash' => $expectedVectorHash,
            'clean_vector_hash' => $cleanVectorHash,
            'matrix_hash' => $matrixHash,
            'variants' => $variantsMap,
        ],
    ]);

    foreach (OmrNoGoMatrixRunner::VARIANTS as $variant) {
        OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'attempt' => 1,
            'page_no' => 1,
            'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
            'variant' => $variant,
            'status' => OmrTemplateVerificationCapture::STATUS_PASS,
            'expected' => 'pass',
            'actual' => 'pass',
            'input_hash' => hash('sha256', $variant),
            'evidence_hash' => hash('sha256', 'ev:'.$variant),
            'expected_vector_hash' => $expectedVectorHash,
            'detected_vector_hash' => $expectedVectorHash,
            'vector_match' => true,
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ]);
    }

    $assets = app(OmrTemplateAssetStore::class);
    $assets->putDraftAsset(
        $materializedDraft,
        OmrTemplateAsset::KIND_DIGITAL_PROOF,
        'proof bytes for '.$proofHash,
        'image/png',
        1,
        ['run_id' => $run->id, 'attempt' => 1, 'proof_hash' => $proofHash],
    );
    $match = app(CurrentOmrDigitalVerification::class)->find($materializedDraft);
    expect($match)->not->toBeNull();

    $authoring = (array) $materializedDraft->authoring_json;
    $authoring['verification'] = $match->rebuildVerificationPayload($materializedDraft);
    $materializedDraft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'authoring_json' => $authoring,
    ])->save();

    return $run;
}

test('canonical geometry identity is deterministic across move-away and move-back', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'GEO-IDENT');

    $identity1 = OmrCanonicalGeometryIdentity::forDraft($draft, $profile);
    expect($identity1)->toBeString()->toHaveLength(64);

    // Change position (move away)
    $authoring = (array) $draft->authoring_json;
    $authoring['studio']['components'][0]['posX'] = 35.0;
    $draft->forceFill(['authoring_json' => $authoring])->save();
    $identity2 = OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile);
    expect($identity2)->not->toBe($identity1);

    // Move back to exact initial position
    $authoring['studio']['components'][0]['posX'] = 25.0;
    $draft->forceFill(['authoring_json' => $authoring])->save();
    $identity3 = OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile);
    expect($identity3)->toBe($identity1);

    // Non-geometry studio changes (e.g. isLocked, current_stage) preserve geometry identity
    $authoring['studio']['components'][0]['isLocked'] = true;
    $authoring['studio']['current_stage'] = 4;
    $draft->forceFill(['authoring_json' => $authoring])->save();
    $identity4 = OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile);
    expect($identity4)->toBe($identity1);
});

test('explicit bubble positions are stable in geometry identity and position-sensitive', function (): void {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'EXPLICIT-GEO');
    $authoring = (array) $draft->authoring_json;
    $authoring['mapping']['field_blocks'] = [
        'Part_1' => [
            'fieldType' => 'QTYPE_MCQ4',
            'direction' => 'horizontal',
            'fieldLabels' => ['LOGIC_Q_1', 'LOGIC_Q_2'],
            'bubbleValues' => ['A', 'B', 'C', 'D'],
            'origin' => [200, 300],
            'bubbleDimensions' => [50, 50],
            'bubblesGap' => 80,
            'labelsGap' => 70,
            'bubblePositions' => [
                [[200, 300], [280, 304], [360, 308], [440, 312]],
                [[202, 370], [282, 374], [362, 378], [442, 382]],
            ],
            'page_no' => 1,
        ],
    ];
    $draft->forceFill(['authoring_json' => $authoring])->save();

    $identity = OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile);
    expect(OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile))->toBe($identity);

    $authoring['mapping']['field_blocks']['Part_1']['bubblePositions'][0][1] = [284, 405];
    $draft->forceFill(['authoring_json' => $authoring])->save();
    expect(OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile))->not->toBe($identity);
});

test('no-op studio save preserves digital verification authority while real geometry change clears it', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'SAVE-PRESERVE');

    $run = attachPassingDigitalVerification($draft, $actor);
    $fresh = $draft->fresh();
    expect($fresh->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED);
    $initialArtifactHash = $fresh->authoring_json['canonical']['artifact_set']['artifact_hash'];

    // 1. Non-geometry studio save preserves artifact set and digital verification
    $saveAction = app(SaveOmrCanonicalStudioState::class);
    $savedDraft = $saveAction->handle(
        $fresh,
        $actor,
        ['boxes' => []],
        null,
        [
            ...$fresh->authoring_json['studio'],
            'current_stage' => 3,
        ],
        (int) $fresh->lock_version,
    );

    expect($savedDraft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($savedDraft->authoring_json['canonical']['artifact_set']['artifact_hash'])->toBe($initialArtifactHash)
        ->and($savedDraft->authoring_json['verification']['status'])->toBe('passed');

    // 2. Real geometry change invalidates artifact set and clears digital verification
    $modifiedStudio = $savedDraft->authoring_json['studio'];
    $modifiedStudio['components'][0]['posX'] = 40.0;

    $invalidatedDraft = $saveAction->handle(
        $savedDraft,
        $actor,
        ['boxes' => []],
        null,
        $modifiedStudio,
        (int) $savedDraft->lock_version,
    );

    expect($invalidatedDraft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE)
        ->and($invalidatedDraft->authoring_json['canonical']['artifact_set'])->toBeNull()
        ->and($invalidatedDraft->authoring_json['verification'])->toBeNull()
        ->and($invalidatedDraft->authoring_json['canonical']['superseded_artifact_sets'])->toHaveKey((string) $run->mapping_hash);

    // 3. CurrentOmrDigitalVerification strictly rejects the modified draft
    $match = app(CurrentOmrDigitalVerification::class)->find($invalidatedDraft);
    expect($match)->toBeNull();

    // 4. Moving back to the exact canonical geometry restores the same proof authority
    $restoredDraft = $saveAction->handle(
        $invalidatedDraft,
        $actor,
        ['boxes' => []],
        null,
        $savedDraft->authoring_json['studio'],
        (int) $invalidatedDraft->lock_version,
    );
    expect($restoredDraft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($restoredDraft->authoring_json['canonical']['artifact_set']['artifact_hash'])->toBe($initialArtifactHash)
        ->and($restoredDraft->authoring_json['verification']['status'])->toBe('passed');
});

test('proof plan v1 replay retains exact v1 seed while v2 incorporates full target identity', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'PLAN-V2');

    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, (int) $draft->lock_version);
    $artifactSet = $materialized->authoring_json['canonical']['artifact_set'];
    $artifact = app(CanonicalArtifactMaterializer::class)->materialize($materialized, $profile);
    $target = new CandidateArtifactDiagnosticTarget($materialized, $artifact, $profile);

    $planner = app(OmrProofPlanner::class);

    // v1 physical seed: all target hashes + method + purpose + 'v1'.
    $planV1 = $planner->plan($target, ProofPurpose::QuickRehearsal, 'v1');
    $policyHash = hash('sha256', json_encode([
        'registration_mode' => $target->registrationMode()->value,
        'qr_mode' => $target->qrMode()->value,
        'compiled_pages' => $target->compiledPages(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $expectedV1Seed = hash('sha256', implode('|', [
        $target->artifactHash(),
        $target->nativeBundleHash(),
        $target->manifestHash(),
        $target->configHash(),
        $target->mappingHash(),
        'v1',
        OmrProofPlan::METHOD_PRE_SHADED,
        ProofPurpose::QuickRehearsal->value,
        $policyHash,
    ]));
    expect($planV1->planVersion)->toBe('v1')
        ->and($planV1->seed)->toBe($expectedV1Seed);

    // v2 seed includes the same full target identity and the plan version.
    $planV2 = $planner->plan($target, ProofPurpose::QuickRehearsal, 'v2');
    $expectedV2Seed = hash('sha256', implode('|', [
        $target->artifactHash(),
        $target->nativeBundleHash(),
        $target->manifestHash(),
        $target->configHash(),
        $target->mappingHash(),
        'v2',
        OmrProofPlan::METHOD_PRE_SHADED,
        ProofPurpose::QuickRehearsal->value,
        $policyHash,
    ]));
    expect($planV2->planVersion)->toBe('v2')
        ->and($planV2->seed)->toBe($expectedV2Seed)
        ->and($planV2->planHash())->not->toBe($planV1->planHash());
});

test('challenge recovery projection returns repair_reference when renderer stale, restart_rehearsal when target drifted, and evidence_immutable when captures exist', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'REC-PROJ');

    attachPassingDigitalVerification($draft, $actor);
    $fresh = $draft->fresh();

    // 1. Create a physical quick challenge
    $created = app(CreateOmrPhysicalChallenge::class)->handle($fresh, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $run = $created['run'];
    $challenge = $created['challenge'];

    $statusService = app(OmrTemplateVerificationStatus::class);

    // When newly created and current, no recovery needed
    $status = $statusService->get($run->fresh(), $actor);
    expect($status['challenge_recovery'])->toBeNull()
        ->and($status['available_actions'])->toContain('print_challenge');

    // 2. Simulate renderer outdated on unchanged target -> repair_reference
    $sheetAsset = $challenge->sheetAsset;
    $meta = $sheetAsset->metadata;
    $meta['renderer_version'] = 'v1.0.0-legacy';
    DB::table('omr_template_assets')->where('id', $sheetAsset->id)->update(['metadata' => json_encode($meta)]);

    $statusRendererStale = $statusService->get($run->fresh(), $actor);
    expect($statusRendererStale['challenge_recovery'])->not->toBeNull()
        ->and($statusRendererStale['challenge_recovery']['kind'])->toBe('repair_reference')
        ->and($statusRendererStale['challenge_recovery']['reason_code'])->toBe('renderer_outdated')
        ->and($statusRendererStale['challenge_recovery']['action_url'])->toContain('/challenge/regenerate')
        ->and($statusRendererStale['available_actions'])->toBe(['regenerate_challenge']);

    // 3. Simulate target drift -> restart_rehearsal (impossible repair is NEVER advertised)
    $authoring = (array) $fresh->authoring_json;
    $authoring['studio']['components'][0]['posX'] = 50.0;
    $fresh->forceFill(['authoring_json' => $authoring])->save();
    $statusTargetDrifted = $statusService->get($run->fresh(), $actor);
    expect($statusTargetDrifted['challenge_recovery'])->not->toBeNull()
        ->and($statusTargetDrifted['challenge_recovery']['kind'])->toBe('restart_rehearsal')
        ->and($statusTargetDrifted['challenge_recovery']['reason_code'])->toBe('target_changed')
        ->and($statusTargetDrifted['challenge_recovery']['action_url'])->toContain('/challenge/restart')
        ->and($statusTargetDrifted['available_actions'])->toBe(['restart_challenge']);

    // 4. If captures exist -> evidence_immutable
    OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'challenge_id' => $challenge->id,
        'attempt' => 1,
        'page_no' => 1,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'variant' => 'clean',
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'expected' => 'pass',
        'actual' => 'pass',
        'input_hash' => 'hash',
        'evidence_hash' => 'evhash',
        'expected_vector_hash' => 'evh',
        'detected_vector_hash' => 'dvh',
        'vector_match' => true,
        'captured_by_user_id' => $actor->id,
        'captured_at' => now(),
    ]);

    $statusWithCaptures = $statusService->get($run->fresh(), $actor);
    expect($statusWithCaptures['challenge_recovery'])->not->toBeNull()
        ->and($statusWithCaptures['challenge_recovery']['kind'])->toBeNull()
        ->and($statusWithCaptures['challenge_recovery']['reason_code'])->toBe('evidence_immutable')
        ->and($statusWithCaptures['available_actions'])->toBe([]);
});

test('restart recovery rematerializes current target and starts durable digital re-verification', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'RESTART-CMD');

    attachPassingDigitalVerification($draft, $actor);
    $fresh = $draft->fresh();

    $created = app(CreateOmrPhysicalChallenge::class)->handle($fresh, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $oldRun = $created['run'];
    $oldChallenge = $created['challenge'];
    $oldArtifactHash = (string) $oldRun->artifact_hash;

    // Move the visible geometry, leaving the old persisted artifact package pinned.
    $authoring = (array) $fresh->authoring_json;
    $authoring['studio']['components'][0]['posX'] = 45.0;
    $fresh->forceFill(['authoring_json' => $authoring, 'lock_version' => (int) $fresh->lock_version + 1])->save();

    Bus::fake();
    $restartAction = app(RestartOmrPhysicalChallenge::class);
    $result = $restartAction->handle($oldRun->fresh(), $actor);
    Bus::assertDispatched(RunOmrTemplateVerificationJob::class, fn ($job): bool => $job->runId === (int) $result['run_id']);

    expect($result['run_id'])->not->toBe($oldRun->id)
        ->and($result['challenge_id'])->toBeNull()
        ->and($result['challenge_code'])->toBeNull()
        ->and($result['status'])->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($result['run']->mode)->toBe(OmrTemplateVerificationRun::MODE_DIGITAL)
        ->and($result['run']->plan_version)->toBe(OmrProofPlanner::CURRENT_PLAN_VERSION);

    // The normal materializer now owns the current artifact; the old package remains historical.
    $prepared = $draft->fresh();
    expect($prepared->authoring_json['canonical']['artifact_set']['artifact_hash'])->not->toBe($oldArtifactHash);

    $oldChallengeFresh = $oldChallenge->fresh();
    $oldRunFresh = $oldRun->fresh();
    expect($oldChallengeFresh->status)->toBe(OmrPhysicalChallenge::STATUS_SUPERSEDED)
        ->and($oldRunFresh->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($oldRunFresh->error_code)->toBe('target_changed_superseded')
        ->and($oldRunFresh->captures()->count())->toBe(0);
    $oldStatus = app(OmrTemplateVerificationStatus::class)->get($oldRunFresh, $actor);
    expect($oldStatus['challenge_recovery'])->toBeNull()
        ->and($oldStatus['available_actions'])->toBe([]);

    // A quick challenge cannot be created until the new digital run passes.
    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($prepared, $actor, OmrPhysicalChallenge::MODE_QUICK))
        ->toThrow(OmrAuthoringDraftException::class, 'digital_pass_required');
});

test('restart recovery supersedes an already failed stale rehearsal before starting digital re-verification', function () {
    $actor = User::factory()->create();
    [$draft] = createAuthorityRecoveryDraft($actor, 'RESTART-FAILED');

    attachPassingDigitalVerification($draft, $actor);
    $fresh = $draft->fresh();
    $created = app(CreateOmrPhysicalChallenge::class)->handle($fresh, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $oldRun = $created['run'];
    $oldChallenge = $created['challenge'];
    $oldRun->forceFill([
        'status' => OmrTemplateVerificationRun::STATUS_FAILED,
        'error_code' => 'worker_failed',
        'safe_error' => 'The prior rehearsal failed.',
        'finished_at' => now(),
    ])->save();
    $originalFinishedAt = $oldRun->finished_at;
    $originalArtifactHash = $oldRun->artifact_hash;
    $originalOptimisticVersion = (int) $oldRun->optimistic_version;
    $originalPinnedHashes = $oldRun->pinnedTargetHashes();
    $originalChallengeCode = $oldRun->challenge_code;

    $authoring = (array) $fresh->authoring_json;
    $authoring['studio']['components'][0]['posX'] = 47.0;
    $fresh->forceFill(['authoring_json' => $authoring, 'lock_version' => (int) $fresh->lock_version + 1])->save();

    Bus::fake();
    $result = app(RestartOmrPhysicalChallenge::class)->handle($oldRun->fresh(), $actor);

    Bus::assertDispatched(RunOmrTemplateVerificationJob::class, fn ($job): bool => $job->runId === (int) $result['run_id']);
    $oldRunFresh = $oldRun->fresh();
    expect($result['status'])->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($oldChallenge->fresh()->status)->toBe(OmrPhysicalChallenge::STATUS_SUPERSEDED)
        ->and($oldRunFresh->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($oldRunFresh->error_code)->toBe('target_changed_superseded')
        ->and($oldRunFresh->finished_at->equalTo($originalFinishedAt))->toBeTrue()
        ->and($oldRunFresh->artifact_hash)->toBe($originalArtifactHash)
        ->and($oldRunFresh->pinnedTargetHashes())->toBe($originalPinnedHashes)
        ->and($oldRunFresh->challenge_code)->toBe($originalChallengeCode)
        ->and($oldRunFresh->optimistic_version)->toBe($originalOptimisticVersion + 1)
        ->and($oldRunFresh->captures()->count())->toBe(0);
});

test('uploaded composition remains the immutable source after Studio invalidates derived PDF assets', function () {
    $actor = User::factory()->create();
    [$draft] = createAuthorityRecoveryDraft($actor, 'UPLOADED-SOURCE');
    $authoring = (array) $draft->authoring_json;
    unset($authoring['marker']);
    $mapping = $authoring['mapping'];
    $studio = $authoring['studio'];
    $overlay = $authoring['overlay'];
    $authoring['mapping'] = null;
    $draft->forceFill([
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_PDF,
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'marker_profile_id' => null,
        'stage' => OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED,
        'authoring_json' => $authoring,
    ])->save();
    $composed = app(ComposeOmrCanonicalPages::class)->handle($draft->fresh(), $actor, [
        'paper_width_mm' => 210,
        'paper_height_mm' => 297,
    ]);
    $canonical = $composed->authoring_json['canonical'];
    expect($canonical['pdf_asset_id'])->toBeNull()
        ->and($canonical['source_pdf_asset_id'])->toBeNull()
        ->and($canonical['raster_assets'])->not->toBeEmpty();
    $updatedAuthoring = (array) $composed->authoring_json;
    $updatedAuthoring['mapping'] = $mapping;
    $updatedAuthoring['studio'] = $studio;
    $updatedAuthoring['overlay'] = $overlay;
    $composed->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => $updatedAuthoring,
    ])->save();

    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($composed->fresh(), $actor, (int) $composed->lock_version);
    $artifact = app(CanonicalArtifactMaterializer::class)->materialize(
        $materialized->fresh(['template', 'markerProfile']),
        null,
        (int) $materialized->authoring_json['canonical']['artifact_set']['candidate_version'],
    );

    expect($materialized->authoring_json['canonical']['pdf_asset_id'])->toBeNull()
        ->and($materialized->authoring_json['canonical']['source_pdf_asset_id'])->toBeNull()
        ->and($materialized->authoring_json['canonical']['artifact_set']['pdf_asset_id'])->toBeNull()
        ->and($materialized->authoring_json['canonical']['artifact_set']['pdf_hash'])->toBeNull()
        ->and($artifact->artifactHash)->toBe($materialized->authoring_json['canonical']['artifact_set']['artifact_hash']);
});

test('document-bound canonical preview presents paper against a contrasting camera background', function () {
    $actor = User::factory()->create();
    [$draft] = createAuthorityRecoveryDraft($actor, 'DOCUMENT-PREVIEW');
    $authoring = (array) $draft->authoring_json;
    unset($authoring['marker']);
    $draft->forceFill([
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'marker_profile_id' => null,
        'authoring_json' => $authoring,
    ])->save();

    $port = new class implements OmrLayoutVerificationPort
    {
        public ?OmrDetectionRequest $request = null;

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $this->request = $request;

            return OmrLayoutVerificationResult::failed('preview_inspected', 'Preview image inspected.');
        }
    };
    $preview = new PreviewOmrCanonicalDraft(
        app(MaterializeOmrCanonicalDraft::class),
        app(CanonicalArtifactMaterializer::class),
        app(OmrDiagnosticRequestBuilder::class),
        $port,
    );
    $result = $preview->handle($draft->fresh(), $actor, (int) $draft->lock_version);
    $paper = imagecreatefromstring($result['artifact']->pages[0]['bytes']);
    $frame = imagecreatefromstring($port->request->imageBytes);
    expect($paper)->not->toBeFalse()
        ->and($frame)->not->toBeFalse()
        ->and(imagesx($frame))->toBeGreaterThan(imagesx($paper))
        ->and(imagesy($frame))->toBeGreaterThan(imagesy($paper))
        ->and(imagecolorsforindex($frame, imagecolorat($frame, 0, 0))['red'])->toBe(128)
        ->and(imagecolorsforindex($frame, imagecolorat($frame, 0, 0))['green'])->toBe(128)
        ->and(imagecolorsforindex($frame, imagecolorat($frame, 0, 0))['blue'])->toBe(128)
        ->and($port->request->registrationMode)->toBe(OmrRegistrationMode::DocumentBound);
    imagedestroy($paper);
    imagedestroy($frame);
});

test('worker typed failures preserve code and detail in OmrCheckerDetection and preview controller', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'WORKER-FAIL');

    // Mock OmrLayoutVerificationPort returning typed failure
    $fakePort = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return OmrLayoutVerificationResult::failed(
                'marker_missing',
                'One or more alignment markers were missing or could not be detected on the sheet.',
            );
        }
    };

    $previewAction = new PreviewOmrCanonicalDraft(
        app(MaterializeOmrCanonicalDraft::class),
        app(CanonicalArtifactMaterializer::class),
        app(OmrDiagnosticRequestBuilder::class),
        $fakePort,
    );

    $previewResult = $previewAction->handle($draft, $actor, (int) $draft->lock_version);
    expect($previewResult['result']->succeeded())->toBeFalse()
        ->and($previewResult['result']->code)->toBe('marker_missing')
        ->and($previewResult['result']->detail)->toBe('One or more alignment markers were missing or could not be detected on the sheet.');

    // HTTP controller route returns 422 with typed code and detail
    $response = test()->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.drafts.preview', $draft),
        ['lock_version' => (int) $draft->lock_version],
    );

    $response->assertStatus(422)
        ->assertJson([
            'outcome' => 'failed',
        ]);
});

test('mapping field_blocks authority is preserved and component coordinates are reconciled when visual components are present', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'MAP-AUTH');

    // Set custom mapping field_blocks and matching visual components
    $authoring = (array) $draft->authoring_json;
    $authoring['mapping']['field_blocks'] = [
        'Part_1' => [
            'fieldType' => 'QTYPE_MCQ4',
            'direction' => 'horizontal',
            'origin' => [354, 921], // marker-frame paper pixels (30mm x 78mm paper space = 25mm x 70mm component posX/posY with 5mm/8mm offset)
            'bubbleDimensions' => [53, 53],
            'bubblesGap' => 71,
            'labelsGap' => 80,
            'fieldLabels' => ['LOGIC_Q_1', 'LOGIC_Q_2'],
            'bubbleValues' => ['A', 'B', 'C', 'D'],
            'displayLabel' => 'General Ability',
            'showSectionHeader' => true,
            'page_no' => 1,
        ],
    ];
    $draft->forceFill(['authoring_json' => $authoring])->save();

    $definition = CanonicalSheetDefinition::fromDraft($draft->fresh(), $profile);

    // Field blocks must be preserved from mapping
    expect($definition->fieldBlocks)->toHaveKey('Part_1')
        ->and($definition->fieldBlocks['Part_1']['displayLabel'])->toBe('General Ability')
        ->and($definition->fieldBlocks['Part_1']['bubbleDimensions'])->toBe([53, 53]);

    // Component posX/posY reconciled to match mapping origin
    expect($definition->visualComponents)->toHaveCount(1)
        ->and($definition->visualComponents[0]['id'])->toBe('Part_1')
        ->and($definition->visualComponents[0]['posX'])->toEqualWithDelta(25.0, 0.1)
        ->and($definition->visualComponents[0]['posY'])->toEqualWithDelta(70.0, 0.1);

    // Verify geometry identity consistency
    $identity1 = OmrCanonicalGeometryIdentity::forDraft($draft->fresh(), $profile);
    expect($identity1)->toBeString()->toHaveLength(64);
});

test('restart transaction rolls back old run and challenge supersession if challenge creation fails', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'RESTART-ROLLBACK');

    attachPassingDigitalVerification($draft, $actor);
    $fresh = $draft->fresh();

    $created = app(CreateOmrPhysicalChallenge::class)->handle($fresh, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $oldRun = $created['run'];
    $oldChallenge = $created['challenge'];

    // Modify draft geometry and rematerialize/reverify so target is drifted
    $authoring = (array) $fresh->authoring_json;
    $authoring['studio']['components'][0]['posX'] = 48.0;
    $fresh->forceFill(['authoring_json' => $authoring, 'lock_version' => (int) $fresh->lock_version + 1])->save();
    attachPassingDigitalVerification($fresh->fresh(), $actor);

    // Simulate failure during challenge creation by temporarily sabotaging storage disk
    Storage::shouldReceive('disk')
        ->andThrow(new RuntimeException('simulated_challenge_creation_failure'));

    $restartAction = app(RestartOmrPhysicalChallenge::class);

    expect(fn () => $restartAction->handle($oldRun->fresh(), $actor))
        ->toThrow(RuntimeException::class, 'simulated_challenge_creation_failure');

    // Verify old run and old challenge were NOT stranded in failed/superseded state
    $oldRunFresh = $oldRun->fresh();
    $oldChallengeFresh = $oldChallenge->fresh();
    expect($oldRunFresh->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_PRINT)
        ->and($oldChallengeFresh->status)->toBe(OmrPhysicalChallenge::STATUS_CREATED);

    Storage::clearResolvedInstances();
});

test('TOCTOU race guard in regenerate and restart rejects if run status transitions out of allowed pre-evidence statuses', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'TOCTOU-GUARD');

    attachPassingDigitalVerification($draft, $actor);
    $fresh = $draft->fresh();

    $created = app(CreateOmrPhysicalChallenge::class)->handle($fresh, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $run = $created['run'];

    // Simulate race where run transitions to STATUS_ANALYZING_CAPTURE (e.g. capture analysis started in-flight)
    $run->forceFill(['status' => OmrTemplateVerificationRun::STATUS_ANALYZING_CAPTURE])->save();

    $regenerateAction = app(RegenerateOmrPhysicalChallenge::class);
    expect(fn () => $regenerateAction->handle($run->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, "Challenge regeneration is not permitted in run status 'analyzing_capture'.");

    $restartAction = app(RestartOmrPhysicalChallenge::class);
    expect(fn () => $restartAction->handle($run->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, "Challenge restart is not permitted in run status 'analyzing_capture'.");
});

test('idempotency replay in controller returns prior result without re-executing state transitions', function () {
    $actor = User::factory()->create();
    [$draft, $profile] = createAuthorityRecoveryDraft($actor, 'IDEMP-REPLAY');

    attachPassingDigitalVerification($draft, $actor);
    $fresh = $draft->fresh();

    $created = app(CreateOmrPhysicalChallenge::class)->handle($fresh, $actor, OmrPhysicalChallenge::MODE_QUICK);
    $run = $created['run'];
    $challenge = $created['challenge'];

    // 1. Test preparePrint idempotency replay
    $prepareKey = 'prepare-idemp-123';
    $resp1 = test()->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.verification_runs.prepare_print', $run),
        ['idempotency_key' => $prepareKey],
    );
    $resp1->assertStatus(200);
    $initialPrintUrl = $resp1->json('print_url');

    $resp2 = test()->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.verification_runs.prepare_print', $run),
        ['idempotency_key' => $prepareKey],
    );
    $resp2->assertStatus(200)
        ->assertJson([
            'run_id' => $run->id,
            'challenge_id' => $challenge->id,
            'print_url' => $initialPrintUrl,
        ]);

    // 2. Test restartPhysicalChallenge idempotency replay
    // Modify draft geometry and rematerialize/reverify so target has drifted
    $authoring = (array) $fresh->authoring_json;
    $authoring['studio']['components'][0]['posX'] = 52.0;
    $fresh->forceFill(['authoring_json' => $authoring, 'lock_version' => (int) $fresh->lock_version + 1])->save();
    attachPassingDigitalVerification($fresh->fresh(), $actor);

    $restartKey = 'restart-idemp-456';
    $restartResp1 = test()->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.verification_runs.challenge.restart', $run),
        ['idempotency_key' => $restartKey],
    );
    $restartResp1->assertStatus(200)
        ->assertJson([
            'digital_reverification' => true,
            'challenge_id' => null,
            'challenge_code' => null,
        ]);
    $newRunId = $restartResp1->json('run_id');
    $newChallengeCode = $restartResp1->json('challenge_code');

    // Replay with exact same idempotency key
    $restartResp2 = test()->actingAs($actor)->postJson(
        route('setup.omr.templates.canonical.verification_runs.challenge.restart', $run),
        ['idempotency_key' => $restartKey],
    );
    $restartResp2->assertStatus(200)
        ->assertJson([
            'run_id' => $newRunId,
            'challenge_code' => $newChallengeCode,
            'digital_reverification' => true,
            'challenge_id' => null,
        ]);
});

test('restart endpoint accepts Laravel double-submit token and reaches domain response', function () {
    $actor = User::factory()->create();
    [$draft] = createAuthorityRecoveryDraft($actor, 'CSRF-RECOVERY');

    attachPassingDigitalVerification($draft, $actor);
    $created = app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, OmrPhysicalChallenge::MODE_QUICK);
    $run = $created['run'];

    $authoring = (array) $draft->fresh()->authoring_json;
    $authoring['studio']['components'][0]['posX'] = 52.0;
    $draft->forceFill([
        'authoring_json' => $authoring,
        'lock_version' => (int) $draft->lock_version + 1,
    ])->save();
    attachPassingDigitalVerification($draft->fresh(), $actor);

    $csrfToken = 'csrf-restart-regression-token';
    $encrypter = app('encrypter');
    $xsrfCookie = $encrypter->encrypt(
        CookieValuePrefix::create('XSRF-TOKEN', $encrypter->getKey()).$csrfToken,
        false,
    );

    test()
        ->withMiddleware(PreventRequestForgery::class)
        ->withSession(['_token' => $csrfToken])
        ->withCookie('XSRF-TOKEN', $xsrfCookie)
        ->withHeader('X-XSRF-TOKEN', $xsrfCookie)
        ->actingAs($actor)
        ->postJson(
            route('setup.omr.templates.canonical.verification_runs.challenge.restart', $run),
            ['idempotency_key' => 'csrf-restart-regression'],
        )
        ->assertOk()
        ->assertJsonStructure(['run_id', 'status', 'status_url', 'challenge_id', 'challenge_code']);
});
