<?php

use App\Assessment\Actions\Omr\Canonical\ActivateOmrCanonicalTemplate;
use App\Assessment\Actions\Omr\Canonical\RunOmrPhysicalQualification;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\SelectOmrPhysicalAttempt;
use App\Assessment\Actions\Omr\Verification\SubmitOmrPhysicalCapture;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrMarkerProfileException;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Foundation\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('activation requires trusted physical provenance and preserves the previous active version', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = physicalChallengeActor();
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'activation.marker', 'version' => 1, 'family' => 'test', 'asset_path' => 'marker.jpg', 'asset_sha256' => hash('sha256', 'marker'),
        'size_mm' => 12, 'quiet_zone_mm' => 3, 'matching_config' => [], 'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'qualification_evidence_hash' => hash('sha256', 'calibration'), 'created_by_user_id' => $actor->id, 'qualified_by_user_id' => $actor->id, 'qualified_at' => now(),
    ]);

    // The candidate must qualify through a real full-challenge run; a bare
    // manual evidence array is no longer accepted (AD-7). Start from the
    // shared compiled fixture and add a PREVIOUS active version on the same
    // template so the atomic activation swap can retire it.
    $fixtureVersion = physicalChallengeVersion($actor);
    $previous = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $fixtureVersion->template_id,
        'version' => 0,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'page_width' => 2196,
        'page_height' => 3201,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => $fixtureVersion->native_bundle_json,
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'review'),
        'activated_by_user_id' => $actor->id,
        'activated_at' => now(),
    ]);
    $version = $fixtureVersion;

    expect(fn () => app(ActivateOmrCanonicalTemplate::class)->handle($version, $actor))->toThrow(RuntimeException::class, 'activation_gate_missing');
    // The old manual checkbox path is gone: outcomes arrays are meaningless now.
    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($version, $actor, [
        'renderer_version' => 'canonical-renderer-v1', 'printer' => 'printer', 'device' => 'camera', 'paper' => 'A4',
        'measured_marker_center_mm' => [186, 271], 'outcomes' => ['pass'],
    ]))->toThrow(TypeError::class);

    // Swap only the profile snapshot; keep the fixture's printed A4 geometry pinned.
    $fixtureVersion->forceFill([
        'authoring_manifest' => [
            ...(array) $fixtureVersion->authoring_manifest,
            'marker_profile' => ['id' => $profile->id, 'code' => $profile->code, 'version' => $profile->version, 'asset_sha256' => $profile->asset_sha256],

        ],
    ])->save();

    $draft = app(OmrAuthoringDraftService::class)->create($actor, (string) $fixtureVersion->template?->code, 'Activation draft', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'compiled_version_id' => $fixtureVersion->id,
        'authoring_json' => [
            'canonical' => ['artifact_set' => ['artifact_hash' => (string) ($fixtureVersion->authoring_manifest['artifact_set']['artifact_hash'] ?? str_repeat('a', 64))]],
            'verification' => null,
        ],
    ])->save();
    physicalChallengeSeedDigitalPass($draft->fresh(), $actor, mappingHashOverride: (string) ($fixtureVersion->authoring_manifest['mapping_hash'] ?? ''));
    $digitalMatch = app(CurrentOmrDigitalVerification::class)->find($draft->fresh());
    if ($digitalMatch === null) {
        throw new RuntimeException('activation_fixture_digital_pass_invalid');
    }
    $manifest = (array) $fixtureVersion->authoring_manifest;
    $manifest['verification_hashes'] = [
        'digital_verification' => $digitalMatch->verificationHash,
        'matrix_hash' => $digitalMatch->matrixHash,
        'proof_hash' => $digitalMatch->proofHash,
    ];
    $fixtureVersion->forceFill(['authoring_manifest' => $manifest])->save();
    $pre = app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, 'full', OmrTemplateVerificationCapture::METHOD_PRE_SHADED);
    $hand = app(CreateOmrPhysicalChallenge::class)->handle($draft->fresh(), $actor, 'full', OmrTemplateVerificationCapture::METHOD_HAND_SHADED);
    $run = $pre['run']->fresh();
    $target = new CompiledVersionDiagnosticTarget($fixtureVersion->fresh());
    foreach ([
        [$pre, OmrTemplateVerificationCapture::METHOD_PRE_SHADED, null],
        [$hand, OmrTemplateVerificationCapture::METHOD_HAND_SHADED, OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL],
    ] as [$pack, $method, $medium]) {
        $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::FullQualification, 'v3', $method);
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
            targetHash: (string) $plan->targetHashes['artifact'],
            planHash: $plan->planHash(),
            expectedVectorHash: hash('sha256', json_encode($plan->expectedVector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            medium: $medium,
            idempotencyKey: 'activation-'.$method,
            autoSelect: false,
        );
        app(SelectOmrPhysicalAttempt::class)->handle(
            run: $run->fresh(),
            actor: $actor,
            method: $method,
            pageNo: 1,
            captureId: (int) $submitted['capture']->id,
            reason: 'Activation qualification '.$method.' capture selected.',
        );
    }
    expect($run->fresh()->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED);

    expect(fn () => app(RunOmrPhysicalQualification::class)->handle($run->fresh(), $actor, [
        'renderer_version' => 'canonical-renderer-v1',
        'printer' => 'printer',
        'device' => 'camera',
        'paper' => 'A4 80gsm',
        'measured_marker_center_mm' => [186.2, 270.8],
    ]))->toThrow(RuntimeException::class, 'physical_provenance_unavailable');
    expect($fixtureVersion->fresh()->authoring_manifest['physical_qualification'] ?? null)->toBeNull()
        ->and(AuditEvent::query()->where('event_type', 'omr.canonical.physical_qualified')->count())->toBe(0);
    expect(fn () => app(ActivateOmrCanonicalTemplate::class)->handle($fixtureVersion->fresh(), $actor))
        ->toThrow(RuntimeException::class, 'activation_gate_missing');
    $activationVersion = $fixtureVersion->fresh();
    $target = new CompiledVersionDiagnosticTarget($activationVersion);
    $manifest = (array) $activationVersion->authoring_manifest;
    $manifest['physical_qualification'] = [
        'status' => 'physical_qualified',
        'registration_mode' => $target->registrationMode()->value,
        'qr_mode' => $target->qrMode()->value,
        'compiled_pages' => $target->compiledPages(),
    ];
    $activationVersion->forceFill(['authoring_manifest' => $manifest])->save();
    expect(fn () => app(ActivateOmrCanonicalTemplate::class)->handle($activationVersion->fresh(), $actor))
        ->toThrow(RuntimeException::class, 'physical_provenance_unavailable');
    expect($previous->fresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->and(AuditEvent::query()->where('event_type', 'omr.canonical.physical_qualified')->count())->toBe(0);
});
test('activation locks and rechecks the marker profile after locking the version', function (): void {
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $target = new CompiledVersionDiagnosticTarget($version);
    $profileId = (int) $version->authoring_manifest['marker_profile']['id'];
    $profile = OmrMarkerProfile::query()->findOrFail($profileId);
    $manifest = (array) $version->authoring_manifest;
    $manifest['physical_qualification'] = [
        'status' => 'physical_qualified',
        'registration_mode' => $target->registrationMode()->value,
        'qr_mode' => $target->qrMode()->value,
        'compiled_pages' => $target->compiledPages(),
    ];
    $version->forceFill(['authoring_manifest' => $manifest])->save();

    $downgradedAfterVersionLock = false;
    DB::listen(function ($query) use (&$downgradedAfterVersionLock, $profileId): void {
        if ($downgradedAfterVersionLock
            || DB::connection()->transactionLevel() === 0
            || ! str_contains(strtolower($query->sql), 'omr_answer_sheet_template_versions')) {
            return;
        }
        $downgradedAfterVersionLock = true;
        DB::table('omr_marker_profiles')
            ->where('id', $profileId)
            ->update(['status' => OmrMarkerProfile::STATUS_CANDIDATE]);
    });

    expect(fn () => app(ActivateOmrCanonicalTemplate::class)->handle($version, $actor))
        ->toThrow(OmrMarkerProfileException::class, 'marker_profile_unqualified');
    expect($downgradedAfterVersionLock)->toBeTrue()
        ->and($version->fresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_DRAFT)
        ->and($profile->fresh()->status)->toBe(OmrMarkerProfile::STATUS_QUALIFIED)
        ->and(AuditEvent::query()->where('event_type', 'omr.canonical.physical_qualified')->count())->toBe(0);
});
test('activation refuses legacy qualification metadata without physical provenance', function (): void {
    Storage::fake('local');
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);
    $target = new CompiledVersionDiagnosticTarget($version);
    $manifest = (array) $version->authoring_manifest;
    $manifest['physical_qualification'] = [
        'status' => 'physical_qualified',
        'registration_mode' => $target->registrationMode()->value,
        'qr_mode' => $target->qrMode()->value,
        'compiled_pages' => $target->compiledPages(),
    ];
    $version->forceFill(['authoring_manifest' => $manifest])->save();

    expect(fn () => app(ActivateOmrCanonicalTemplate::class)->handle($version, $actor))
        ->toThrow(RuntimeException::class, 'physical_provenance_unavailable');
    expect($version->fresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_DRAFT)
        ->and(AuditEvent::query()->where('event_type', 'omr.canonical.physical_qualified')->count())->toBe(0);
});

test('authorized staff can activate compiled template version retiring previous active version', function (): void {
    $actor = physicalChallengeActor();
    $version = physicalChallengeVersion($actor);

    $previous = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $version->template_id,
        'version' => 0,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'page_width' => 2196,
        'page_height' => 3201,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'prev-config'),
        'template_json_hash' => hash('sha256', 'prev-template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => $version->native_bundle_json,
        'native_bundle_hash' => hash('sha256', 'prev-bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'prev-review'),
        'activated_by_user_id' => $actor->id,
        'activated_at' => now(),
    ]);

    $activated = app(ActivateOmrCanonicalTemplate::class)->handle($version, $actor, confirmActivation: true);

    expect($activated->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->and($version->fresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->and($previous->fresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_RETIRED);

    expect(AuditEvent::query()->where('event_type', 'omr.canonical.template_activated')->count())->toBe(1);
});
