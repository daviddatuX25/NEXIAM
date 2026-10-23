<?php

declare(strict_types=1);

use App\Assessment\Actions\Omr\Camera\OmrCameraFrameFinalization;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\CaptureOmrPendingPage;
use App\Assessment\Actions\Omr\Operational\CaptureOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompleteOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\DiscardOmrDiagnosticCapture;
use App\Assessment\Actions\Omr\Operational\RetryOmrDiagnosticCapture;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalNativeBundleCompiler;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Operational\DiagnosticOmrAuthorityAdapter;
use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Assessment\Support\TestKitCurrentEvidence;
use App\Assessment\Support\TestKitMockSession;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../../Support/OmrAssistedCaptureTestRequests.php';

function diagnosticQrDisabledBridgeFixture(string $suffix): array
{
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->findOrFail($actor->staffProfile->institution_id);
    OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'code' => 'QRD-'.$suffix,
        'name' => 'Two-page diagnostic fixture',
    ]);
    $blocks = [];
    foreach ([1, 2] as $pageNo) {
        $blocks['Questions_'.$pageNo] = [
            'page_no' => $pageNo,
            'fieldType' => 'QTYPE_MCQ4',
            'direction' => 'horizontal',
            'fieldLabels' => ['LOGIC_Q_'.$pageNo],
            'bubblesGap' => 70,
            'labelsGap' => 80,
            'origin' => [220, 1200],
            'bubbleDimensions' => [50, 50],
        ];
    }
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'QRD-'.$suffix,
        'Two-page diagnostic fixture',
        OmrTemplateAuthoringDraft::SOURCE_PDF,
        [
            'pages' => [['page_no' => 1, 'confirmed' => true], ['page_no' => 2, 'confirmed' => true]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'mapping' => [
                'input' => [
                    'page_width' => 2480,
                    'page_height' => 3508,
                    'page_count' => 2,
                    'declared_inventory' => [
                        ['page_no' => 1, 'area_code' => 'LOGIC', 'question_count' => 1, 'option_labels' => ['A', 'B', 'C', 'D']],
                        ['page_no' => 2, 'area_code' => 'LOGIC', 'question_count' => 1, 'option_labels' => ['A', 'B', 'C', 'D']],
                    ],
                    'identity' => [
                        'method' => 'write_in_manual',
                        'regions' => [
                            ['page_no' => 1, 'x' => 140, 'y' => 110, 'w' => 480, 'h' => 96],
                            ['page_no' => 2, 'x' => 140, 'y' => 110, 'w' => 480, 'h' => 96],
                        ],
                    ],
                ],
                'field_blocks' => $blocks,
            ],
        ],
        OmrRegistrationMode::DocumentBound,
        OmrQrMode::Disabled,
        2,
    );
    $savedDraft = app(MaterializeOmrCanonicalDraft::class)
        ->handle($draft, $actor, 0);
    $physicalInventory = [
        'revision' => 1,
        'sections' => [],
    ];
    foreach ([1, 2] as $pageNo) {
        $sectionUid = (string) Str::uuid();
        $physicalInventory['sections'][] = [
            'printed_section_uid' => $sectionUid,
            'name' => 'Synthetic page '.$pageNo,
            'page_no' => $pageNo,
            'numbering' => ['start' => 1, 'step' => 1],
            'expected_item_count' => 1,
            'items' => [[
                'physical_item_uid' => (string) Str::uuid(),
                'native_label' => 'LOGIC_Q_'.$pageNo,
                'page_no' => $pageNo,
                'printed_number' => 1,
                'option_tokens' => ['A', 'B', 'C', 'D'],
                'guide_id' => 'QRD_'.$pageNo,
            ]],
        ];
    }
    $compiled = app(OmrCanonicalNativeBundleCompiler::class)->compile(
        $savedDraft->fresh(['assets']),
        null,
        [
            'registration_mode' => OmrRegistrationMode::DocumentBound->value,
            'qr_mode' => OmrQrMode::Disabled->value,
            'source_kind' => 'uploaded_original',
            'declared_inventory' => $savedDraft->authoring_json['mapping']['input']['declared_inventory'],
            'identity_method' => $savedDraft->authoring_json['mapping']['input']['identity'],
            'physical_inventory' => $physicalInventory,
            'compiled_pages' => [['page_no' => 1], ['page_no' => 2]],
        ],
    );
    $version = $compiled['version'];
    $authoringManifest = $version->authoring_manifest;
    $authoringManifest['artifact_set'] = $savedDraft->authoring_json['canonical']['artifact_set'];
    $version->forceFill([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
        'authoring_manifest' => $authoringManifest,
    ])->save();

    return [
        'institution' => $institution,
        'actor' => $actor,
        'version' => $version,
    ];
}

function diagnosticBridgeAcceptanceRun(
    array $fixture,
    OmrAnswerSheetTemplateVersion $templateVersion,
    array $itemBindings,
    array $cases,
): OmrDiagnosticRun {
    $now = now();
    $actor = $fixture['actor'];
    $institution = $fixture['institution'];
    $cycle = AcademicCycle::query()
        ->where('institution_id', $actor->staffProfile->institution_id)
        ->where('code', $actor->staffProfile->active_cycle_code)
        ->firstOrFail();

    $suffix = Str::upper(Str::random(10));
    $instrumentId = DB::table('test_instruments')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'INST-'.$suffix,
        'is_synthetic' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $profileHash = hash('sha256', 'profile-'.$suffix);
    $profileVersionId = DB::table('test_instrument_versions')->insertGetId([
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'status' => 'approved',
        'display_name' => 'Profile '.$suffix,
        'publisher_name' => 'Private fixture',
        'form_code' => 'FORM-'.$suffix,
        'edition' => 'Fixture edition',
        'language' => 'en',
        'source_reference' => 'test-source-'.$suffix,
        'permitted_use_reference' => 'Testing only',
        'source_reviewed_by_user_id' => $actor->id,
        'source_reviewed_at' => $now,
        'approved_by_user_id' => $actor->id,
        'approved_at' => $now,
        'lock_version' => 1,
        'content_sha256' => $profileHash,
        'created_by_user_id' => $actor->id,
        'material_requirement' => 'paper_backed',
        'administration_procedure_reference' => 'Synthetic test procedure',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $booklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $actor->staffProfile->institution_id,
        'is_synthetic' => true,
        'code' => 'BOOKLET-'.$suffix,
        'name' => 'Diagnostic bridge booklet '.$suffix,
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'template_version_id' => $templateVersion->id,
        'test_instrument_version_id' => $profileVersionId,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'answer_key_hash' => hash('sha256', 'diagnostic-bridge-'.$suffix),
    ]);

    $setId = DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $bookletVersion->id,
        'set_code' => 'A',
        'status' => 'draft',
        'generation_version' => 'test-kit-evidence-v1',
        'order_hash' => hash('sha256', 'set-order-'.$suffix),
        'answer_key_hash' => hash('sha256', 'set-key-'.$suffix),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'code' => 'KIT-'.$suffix,
        'name' => 'Bridge Test Kit '.$suffix,
        'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);
    $manifest = ['fixture' => 'diagnostic-bridge', 'suffix' => $suffix];
    $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $version = TestKitVersion::query()->create([
        'test_kit_id' => $kit->id,
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'template_id' => $templateVersion->template_id,
        'booklet_id' => $booklet->id,
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'template_version_id' => $templateVersion->id,
        'booklet_version_id' => $bookletVersion->id,
        'booklet_set_id' => $setId,
        'test_instrument_version_id' => $profileVersionId,
        'booklet_set_code' => 'A',
        'booklet_set_order_hash' => hash('sha256', 'set-order-'.$suffix),
        'booklet_set_answer_key_hash' => hash('sha256', 'set-key-'.$suffix),
        'manifest_schema_version' => 1,
        'canonicalizer_version' => 'test-kit-canonicalizer-v1',
        'manifest_json' => $manifest,
        'manifest_sha256' => $manifestHash,
        'status' => TestKitVersion::STATUS_CANDIDATE,
        'revision' => 1,
        'created_by_user_id' => $actor->id,
    ]);

    $plan = [
        'schema_version' => 'test-kit-acceptance-plan-v1',
        'item_bindings' => $itemBindings,
        'cases' => $cases,
    ];
    $planHash = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $acceptancePlans = app(TestKitAcceptancePlan::class);
    $protocolIdentity = $acceptancePlans->protocolIdentity();
    $workerIdentity = $acceptancePlans->expectedWorkerIdentity();

    $planRecord = TestKitAcceptancePlanRecord::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $manifestHash,
        'protocol_schema_version' => $protocolIdentity['schema_version'],
        'protocol_sha256' => $protocolIdentity['sha256'],
        'plan_schema_version' => 'test-kit-acceptance-plan-v1',
        'plan_sha256' => $planHash,
        'plan_payload' => $plan,
        'source_key' => 'diagnostic-qr-disabled-bridge-'.$suffix,
        'authored_source_sha256' => hash('sha256', 'source-'.$suffix),
        'author_reference' => 'Synthetic software-only diagnostic bridge fixture.',
        'truth_source_references_json' => [[
            'reference' => 'tests/Feature/Assessment/OmrDiagnosticQrDisabledBridgeTest.php#diagnosticBridgeAcceptanceRun',
            'sha256' => $planHash,
        ]],
        'review_evidence_reference' => 'test-only://synthetic-diagnostic-plan-review',
        'review_evidence_sha256' => hash('sha256', 'review-'.$suffix),
        'provenance_kind' => 'synthetic_software_fixture',
        'created_by_user_id' => $actor->id,
        'created_at' => $now,
    ]);
    $planId = (int) $planRecord->getKey();

    $run = new OmrDiagnosticRun;
    $run->forceFill([
        'execution_kind' => OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC,
        'diagnostic_purpose' => OmrDiagnosticRun::PURPOSE_KIT_ACCEPTANCE,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'template_version_id' => $templateVersion->id,
        'booklet_version_id' => $bookletVersion->id,
        'declared_set_code' => 'A',
        'status' => OmrDiagnosticRun::STATUS_OPEN,
        'phase' => OmrDiagnosticRun::PHASE_CAPTURE,
        'revision' => 1,
        'created_by_user_id' => $actor->id,
        'test_kit_id' => $kit->id,
        'test_kit_version_id' => $version->id,
        'acceptance_plan_id' => $planId,
        'booklet_set_id' => $setId,
        'manifest_sha256' => $manifestHash,
        'test_kit_manifest_sha256' => $manifestHash,
        'test_instrument_version_id' => $profileVersionId,
        'profile_content_sha256' => $profileHash,
        'acceptance_protocol_schema_version' => $protocolIdentity['schema_version'],
        'acceptance_protocol_sha256' => $protocolIdentity['sha256'],
        'acceptance_plan_schema_version' => 'test-kit-acceptance-plan-v1',
        'acceptance_plan_sha256' => $planHash,
        'scoring_kernel_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
        'worker_commit' => $workerIdentity['engine_commit'],
        'expected_worker_identity_json' => $workerIdentity,
        'acceptance_plan_json' => $plan,
        'encrypted_acceptance_plan_snapshot' => $plan,
    ]);
    $run->saveOrFail();

    return $run;
}

test('discarding Page 1 preserves Page 2 evidence and its active pending set', function (): void {
    Storage::fake('sensitive');
    $fixture = diagnosticQrDisabledBridgeFixture('DISCARD-PAGE');
    $itemBindings = [
        ['page_no' => 1, 'native_label' => 'DISCARD_Q1', 'global_position' => 1, 'sheet_item_id' => 101, 'item_id' => 201, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
        ['page_no' => 2, 'native_label' => 'DISCARD_Q2', 'global_position' => 2, 'sheet_item_id' => 102, 'item_id' => 202, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
    ];
    $cases = [[
        'case_id' => 'case-discard',
        'coverage' => ['correct'],
        'pages' => [
            ['page_no' => 1, 'responses' => [['native_label' => 'DISCARD_Q1', 'token' => 'A']]],
            ['page_no' => 2, 'responses' => [['native_label' => 'DISCARD_Q2', 'token' => 'B']]],
        ],
        'expected_metrics' => [],
        'recorded_measures' => [],
    ]];
    $run = diagnosticBridgeAcceptanceRun($fixture, $fixture['version'], $itemBindings, $cases);
    $actor = $fixture['actor'];
    $set = app(StartOmrPendingCaptureSet::class)->forDiagnostic($run, $actor, (string) Str::uuid());
    $draft = OmrTemplateAuthoringDraft::query()
        ->where('template_id', $fixture['version']->template_id)
        ->latest('id')
        ->firstOrFail();
    $asset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => 'test/discard-input.png',
        'mime' => 'image/png',
        'bytes' => 0,
        'sha256' => hash('sha256', 'discard-input'),
    ]);
    $pageOne = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $run->id,
        'client_capture_id' => (string) Str::uuid(),
        'status' => OmrDiagnosticCapture::STATUS_FAILED,
        'failure_code' => 'analysis_failed',
        'failure_detail' => 'Recognition failed after accepted upload.',
        'completed_at' => now(),
        'input_asset_id' => $asset->id,
        'input_asset_hash' => $asset->sha256,
        'created_by_user_id' => $actor->id,
    ]);
    $pageTwo = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $run->id,
        'client_capture_id' => (string) Str::uuid(),
        'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
        'input_asset_id' => $asset->id,
        'input_asset_hash' => $asset->sha256,
        'created_by_user_id' => $actor->id,
    ]);
    $set->forceFill([
        'active_page_no' => 2,
        'status' => OmrPendingCaptureSet::STATUS_CAPTURED,
        'pairing_code' => 'pairing_missing',
        'revision' => 2,
    ])->saveOrFail();
    $unrelatedSet = app(StartOmrPendingCaptureSet::class)->forDiagnostic($run, $actor, (string) Str::uuid());
    $pageOneEvidenceId = DB::table('omr_pending_capture_page_evidence')->insertGetId([
        'set_id' => $set->id,
        'page_no' => 1,
        'client_capture_id' => $pageOne->client_capture_id,
        'input_hash' => hash('sha256', 'pending-page-one'),
        'asset_path' => 'test/pending-page-one.png',
        'mime_type' => 'image/png',
        'byte_count' => 0,
        'captured_by_user_id' => $actor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $pageTwoEvidenceId = DB::table('omr_pending_capture_page_evidence')->insertGetId([
        'set_id' => $set->id,
        'page_no' => 2,
        'client_capture_id' => $pageTwo->client_capture_id,
        'input_hash' => hash('sha256', 'pending-page-two'),
        'asset_path' => 'test/pending-page-two.png',
        'mime_type' => 'image/png',
        'byte_count' => 0,
        'captured_by_user_id' => $actor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $pageOne->forceFill(['evidence_json' => ['pending_page_link' => [
        'capture_set_id' => $set->id,
        'pending_page_evidence_id' => $pageOneEvidenceId,
        'page_no' => 1,
        'input_hash' => hash('sha256', 'pending-page-one'),
    ]]])->saveOrFail();
    $pageTwo->forceFill(['evidence_json' => ['pending_page_link' => [
        'capture_set_id' => $set->id,
        'pending_page_evidence_id' => $pageTwoEvidenceId,
        'page_no' => 2,
        'input_hash' => hash('sha256', 'pending-page-two'),
    ]]])->saveOrFail();
    foreach ([
        ['page_no' => 1, 'capture' => $pageOne, 'page_evidence_id' => $pageOneEvidenceId, 'input_hash' => hash('sha256', 'pending-page-one'), 'binding' => $itemBindings[0]],
        ['page_no' => 2, 'capture' => $pageTwo, 'page_evidence_id' => $pageTwoEvidenceId, 'input_hash' => hash('sha256', 'pending-page-two'), 'binding' => $itemBindings[1]],
    ] as $page) {
        $attemptId = DB::table('test_kit_diagnostic_page_attempts')->insertGetId([
            'diagnostic_run_id' => $run->id,
            'case_id' => 'case-discard',
            'page_no' => $page['page_no'],
            'diagnostic_capture_id' => $page['capture']->id,
            'pending_set_id' => $set->id,
            'pending_page_evidence_id' => $page['page_evidence_id'],
            'input_sha256' => $page['input_hash'],
            'predecessor_attempt_id' => null,
            'item_bindings_json' => json_encode([$page['binding']], JSON_THROW_ON_ERROR),
            'created_by_user_id' => $actor->id,
            'created_at' => now(),
        ]);
        DB::table('test_kit_diagnostic_page_selections')->insert([
            'diagnostic_run_id' => $run->id,
            'case_id' => 'case-discard',
            'page_no' => $page['page_no'],
            'selected_attempt_id' => $attemptId,
            'revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    $workbenchId = 'diagnostic:'.$run->public_id;
    $sourceOperationId = (string) Str::uuid();
    $sourceTargetKey = json_encode([
        $workbenchId,
        $pageOne->client_capture_id,
        1,
        1,
        null,
        null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $sourceTarget = [
        'workbenchId' => $workbenchId,
        'clientCaptureId' => $pageOne->client_capture_id,
        'pageNo' => 1,
        'captureRevision' => 1,
        'instanceId' => null,
        'sheetId' => null,
        'targetKey' => $sourceTargetKey,
        'run_id' => $run->id,
        'template_version_id' => $fixture['version']->id,
        'booklet_version_id' => $run->booklet_version_id,
        'page_count' => 2,
        'material_identity' => [
            'template_version_id' => $fixture['version']->id,
            'booklet_version_id' => $run->booklet_version_id,
            'mock_manifest_sha256' => $run->mock_manifest_sha256,
        ],
        'execution_kind' => 'diagnostic',
        'diagnostic_purpose' => $run->diagnostic_purpose,
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'pending_capture_id' => $set->public_id,
        'pending_capture_revision' => 1,
    ];
    DB::table('omr_camera_commands')->insert([
        'operation_id' => $sourceOperationId,
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => hash('sha256', $workbenchId),
        'actor_id' => $actor->id,
        'receipt_key' => hash('sha256', 'discard-source-'.$sourceOperationId),
        'payload_json' => json_encode(['operation_id' => $sourceOperationId], JSON_THROW_ON_ERROR),
        'payload_fingerprint' => hash('sha256', 'discard-source-payload'),
        'target_json' => json_encode($sourceTarget, JSON_THROW_ON_ERROR),
        'issued_at' => now(),
        'domain_outcome' => 'accepted',
        'domain_receipt_json' => json_encode([
            'status' => 'accepted',
            'operation_id' => $sourceOperationId,
            'target_key' => $sourceTargetKey,
            'capture_id' => $set->id,
            'diagnostic_capture_id' => $pageOne->id,
            'page_id' => $pageOneEvidenceId,
            'page_no' => 1,
            'pending_capture_id' => $set->public_id,
            'pending_capture_revision' => 1,
            'client_capture_id' => $pageOne->client_capture_id,
        ], JSON_THROW_ON_ERROR),
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $pageTwoOperationId = (string) Str::uuid();
    $pageTwoTargetKey = json_encode([
        $workbenchId,
        $pageTwo->client_capture_id,
        2,
        2,
        null,
        null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $pageTwoTarget = [
        ...$sourceTarget,
        'clientCaptureId' => $pageTwo->client_capture_id,
        'pageNo' => 2,
        'captureRevision' => 2,
        'targetKey' => $pageTwoTargetKey,
        'target_key' => $pageTwoTargetKey,
        'pending_capture_revision' => 2,
    ];
    DB::table('omr_camera_commands')->insert([
        'operation_id' => $pageTwoOperationId,
        'command_id' => (string) Str::uuid(),
        'issuer_role' => 'controller',
        'kind' => 'CAPTURE_RECEIPT',
        'workbench_key' => hash('sha256', $workbenchId),
        'actor_id' => $actor->id,
        'receipt_key' => hash('sha256', 'page-two-source-'.$pageTwoOperationId),
        'payload_json' => json_encode(['operation_id' => $pageTwoOperationId], JSON_THROW_ON_ERROR),
        'payload_fingerprint' => hash('sha256', 'page-two-source-payload'),
        'target_json' => json_encode($pageTwoTarget, JSON_THROW_ON_ERROR),
        'issued_at' => now(),
        'domain_outcome' => 'accepted',
        'domain_receipt_json' => json_encode([
            'status' => 'accepted',
            'operation_id' => $pageTwoOperationId,
            'target_key' => $pageTwoTargetKey,
            'capture_id' => $set->id,
            'diagnostic_capture_id' => $pageTwo->id,
            'page_id' => $pageTwoEvidenceId,
            'page_no' => 2,
            'pending_capture_id' => $set->public_id,
            'pending_capture_revision' => 2,
            'client_capture_id' => $pageTwo->client_capture_id,
        ], JSON_THROW_ON_ERROR),
        'completed_at' => now()->addSecond(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $showUrl = route('setup.exam-materials.omr-test.runs.show', $run->public_id);
    $shown = $this->actingAs($actor)->getJson($showUrl)->assertOk();
    $sourceRow = collect($shown->json('sheets'))->firstWhere('id', $pageOne->id);
    $retakeGrant = $sourceRow['capture_retake'] ?? null;
    expect($retakeGrant)->toBeArray();
    $retakeActionUrl = route('setup.exam-materials.omr-test.runs.capture-actions', $run->public_id);
    $retakeActionPayload = [
        'action' => 'capture_retake',
        'expected_revision' => $retakeGrant['expected_revision'],
        'authorization_id' => $retakeGrant['authorization_id'],
        'idempotency_key' => $retakeGrant['authorization_id'],
        'capture_id' => $retakeGrant['capture_id'],
        'expected_capture_version' => $retakeGrant['expected_capture_version'],
        'page_no' => $retakeGrant['page_no'],
        'source_page_evidence_id' => $retakeGrant['source_page_evidence_id'],
        'pending_capture_id' => $retakeGrant['pending_capture_id'],
        'pending_capture_revision' => $retakeGrant['pending_capture_revision'],
        'replacement_reason' => 'Retake the selected Page 1 after Page 2',
        'target' => $retakeGrant['target'],
    ];
    $retakeResponse = $this->postJson($retakeActionUrl, $retakeActionPayload);
    $retakeResponse->assertOk();
    $retakeGrantCount = DB::table('omr_camera_commands')->where('kind', 'CAPTURE_TARGET_GRANT')->count();
    $this->postJson($retakeActionUrl, $retakeActionPayload)
        ->assertOk()
        ->assertJsonPath('context.capture_target.replaces_capture_id', $pageOne->id);
    expect(DB::table('omr_camera_commands')->where('kind', 'CAPTURE_TARGET_GRANT')->count())
        ->toBe($retakeGrantCount);
    $changedRetakeAction = [...$retakeActionPayload, 'replacement_reason' => 'Changed retry reason'];
    $this->postJson($retakeActionUrl, $changedRetakeAction)->assertConflict();
    $issuedRetakeTarget = json_decode(
        (string) DB::table('omr_camera_commands')->where('kind', 'CAPTURE_TARGET_GRANT')->latest('id')->value('target_json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    expect($issuedRetakeTarget['replaces_capture_id'] ?? null)->toBe($pageOne->id)
        ->and($issuedRetakeTarget['redo_source_page_id'] ?? null)->toBe($pageOneEvidenceId);
    expect($retakeResponse->json('recovery.outcome'))->toBeNull();
    $retakeResponse->assertJsonPath('context.capture_target.replaces_capture_id', $pageOne->id)
        ->assertJsonPath('context.capture_target.page_no', 1)
        ->assertJsonPath('context.capture_receipt.operation_id', $sourceOperationId);

    $discard = app(DiscardOmrDiagnosticCapture::class);
    $pageTwo->forceFill([
        'status' => OmrDiagnosticCapture::STATUS_PROCESSING,
        'version' => (int) $pageTwo->version + 1,
    ])->saveOrFail();
    expect(fn () => $discard->handle(
        $run->fresh(),
        $pageOne,
        $actor,
        (int) $run->fresh()->revision,
        (int) $pageOne->version,
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_capture_outcome_unknown');
    $pageTwo->forceFill([
        'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
        'version' => (int) $pageTwo->version + 1,
    ])->saveOrFail();

    $terminalRevision = (int) $run->fresh()->revision + 1;
    DB::table('omr_diagnostic_runs')->where('id', $run->id)->update([
        'status' => OmrDiagnosticRun::STATUS_FAILED,
        'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
        'completed_at' => now(),
        'revision' => $terminalRevision,
    ]);
    expect(fn () => $discard->handle(
        $run->fresh(),
        $pageOne,
        $actor,
        $terminalRevision,
        (int) $pageOne->version,
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_run_is_terminal');
    DB::table('omr_diagnostic_runs')->where('id', $run->id)->update([
        'status' => OmrDiagnosticRun::STATUS_OPEN,
        'phase' => OmrDiagnosticRun::PHASE_CAPTURE,
        'completed_at' => null,
        'revision' => $terminalRevision + 1,
    ]);
    $discardRunRevision = (int) $run->fresh()->revision;
    expect(fn () => $discard->handle(
        $run->fresh(),
        $pageOne,
        $actor,
        $discardRunRevision,
        (int) $pageOne->version + 1,
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_capture_version_stale');
    $discardPayload = [
        'expected_revision' => $discardRunRevision,
        'expected_capture_version' => (int) $pageOne->version,
        'reason' => 'Discarded by operator',
    ];
    $discardUrl = route('setup.exam-materials.omr-test.runs.captures.discard', [
        'run' => $run->public_id,
        'capture' => $pageOne->id,
    ]);
    $this->actingAs($actor)->postJson($discardUrl, $discardPayload)
        ->assertOk()
        ->assertJsonPath('context.capture_target.replaces_capture_id', $pageOne->id)
        ->assertJsonPath('context.capture_target.page_no', 1)
        ->assertJsonMissingPath('context.capture_receipt');
    $this->postJson($discardUrl, $discardPayload)->assertOk();

    $retakeTarget = json_decode(
        (string) DB::table('omr_camera_commands')->where('kind', 'CAPTURE_TARGET_GRANT')->latest('id')->value('target_json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $discardedCapture = $pageOne->fresh();
    expect($pageOne->fresh()->failure_code)->toBe('discarded_by_operator')
        ->and($pageOne->fresh()->evidence_json['operator_disposition']['prior_recognition']['status'])->toBe(OmrDiagnosticCapture::STATUS_FAILED)
        ->and($pageOne->fresh()->evidence_json['operator_disposition']['prior_recognition']['failure_code'])->toBe('analysis_failed')
        ->and($discardedCapture->evidence_json['operator_disposition']['prior_recognition']['evidence_hash'])->toBeNull()
        ->and($discardedCapture->evidence_hash)->toBe(hash(
            'sha256',
            json_encode($discardedCapture->evidence_json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ))
        ->and($pageTwo->fresh()->status)->toBe(OmrDiagnosticCapture::STATUS_NEEDS_REVIEW)
        ->and(DB::table('omr_pending_capture_page_evidence')->whereIn('id', [$pageOneEvidenceId, $pageTwoEvidenceId])->count())->toBe(2)
        ->and($set->fresh()->status)->toBe(OmrPendingCaptureSet::STATUS_CAPTURED)
        ->and($set->fresh()->active_page_no)->toBe(1)
        ->and($set->fresh()->revision)->toBe(4)
        ->and($unrelatedSet->fresh()->status)->toBe(OmrPendingCaptureSet::STATUS_PENDING)
        ->and($retakeTarget['replaces_capture_id'] ?? null)->toBe($pageOne->id)
        ->and($retakeTarget['redo_source_page_id'] ?? null)->toBe($pageOneEvidenceId)
        ->and($retakeTarget['redo_reason'] ?? null)->toBe('Discarded by operator')
        ->and(AuditEvent::query()->where('event_type', 'omr.diagnostic_capture.discarded')->latest('id')->value('actor'))
        ->toBe((string) $actor->getAuthIdentifier());
    $pageOne->fresh()->forceFill([
        'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
        'completed_at' => null,
        'version' => (int) $pageOne->version + 1,
    ])->saveOrFail();
    $currentEvidence = app(TestKitCurrentEvidence::class)->forRun($run->fresh());
    expect(collect($currentEvidence['errors'])->contains(
        fn (array $error): bool => ($error['code'] ?? null) === 'capture_discarded_by_operator'
            && (int) ($error['page_no'] ?? 0) === 1,
    ))->toBeTrue();
    expect(app(TestKitMockSession::class)->currentCaptureIds($run->fresh()))
        ->toBe([(int) $pageTwo->id]);
    $pageTwo->fresh()->forceFill([
        'status' => OmrDiagnosticCapture::STATUS_FAILED,
        'failure_code' => 'analysis_failed',
        'failure_detail' => 'Second page analysis failed.',
        'completed_at' => now(),
        'version' => (int) $pageTwo->fresh()->version + 1,
    ])->saveOrFail();
    $snapshot = app(DiagnosticOmrAuthorityAdapter::class)
        ->project('diagnostic:'.$run->public_id, (int) $actor->id)
        ->toSafeArray();
    expect($snapshot['commands'])->toContain('capture_retry')
        ->and(fn () => app(RetryOmrDiagnosticCapture::class)->handle(
            $run->fresh(),
            $pageOne->fresh(),
            $actor,
            (int) $run->fresh()->revision,
            'retry-discarded-'.$pageOne->id,
            (string) Str::uuid(),
        ))->toThrow(RuntimeException::class, 'omr_diagnostic_capture_discarded');
});

test('diagnostic discard fences terminal completion and in-flight recognition under PostgreSQL row locks', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('This row-lock race proof requires isolated PostgreSQL.');
    }

    Storage::fake('sensitive');
    $fixture = diagnosticQrDisabledBridgeFixture('DISCARD-RACE');
    $case = [[
        'case_id' => 'case-discard-race',
        'coverage' => ['correct'],
        'pages' => [],
        'expected_metrics' => [],
        'recorded_measures' => [],
    ]];
    $terminalRun = diagnosticBridgeAcceptanceRun($fixture, $fixture['version'], [], $case);
    $processingRun = diagnosticBridgeAcceptanceRun($fixture, $fixture['version'], [], $case);
    $draft = OmrTemplateAuthoringDraft::query()
        ->where('template_id', $fixture['version']->template_id)
        ->latest('id')
        ->firstOrFail();
    $asset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => 'test/discard-race-input.png',
        'mime' => 'image/png',
        'bytes' => 0,
        'sha256' => hash('sha256', 'discard-race-input'),
    ]);
    $terminalCapture = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $terminalRun->id,
        'client_capture_id' => (string) Str::uuid(),
        'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
        'input_asset_id' => $asset->id,
        'input_asset_hash' => $asset->sha256,
        'created_by_user_id' => $fixture['actor']->id,
    ]);
    $processingCapture = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $processingRun->id,
        'client_capture_id' => (string) Str::uuid(),
        'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
        'input_asset_id' => $asset->id,
        'input_asset_hash' => $asset->sha256,
        'created_by_user_id' => $fixture['actor']->id,
    ]);

    $database = (string) config('database.connections.pgsql.database');
    $worker = static function (
        OmrDiagnosticRun $run,
        OmrDiagnosticCapture $capture,
        int $expectedRunRevision,
        int $expectedCaptureVersion,
        string $appName,
    ) use ($database, $fixture): array {
        $configCache = sys_get_temp_dir().'/omr-discard-race-'.$appName.'-config.php';
        $input = [
            'database' => $database,
            'application_name' => $appName,
            'config_cache' => $configCache,
            'actor_id' => (int) $fixture['actor']->id,
            'run_id' => (int) $run->id,
            'capture_id' => (int) $capture->id,
            'expected_revision' => $expectedRunRevision,
            'expected_capture_version' => $expectedCaptureVersion,
        ];
        $script = <<<'PHP'
try {
    $input = json_decode(base64_decode((string) $argv[1], true), true, 512, JSON_THROW_ON_ERROR);
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => $input['database'],
        'DB_HOST' => 'postgres',
        'DB_PORT' => '5432',
        'DB_USERNAME' => 'nexiam',
        'DB_PASSWORD' => 'nexiam-local-only',
        'DB_URL' => '',
        'APP_CONFIG_CACHE' => $input['config_cache'],
        'NEXIAM_TEST_PIN_SQLITE' => '0',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
    foreach ($environment as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection = Illuminate\Support\Facades\DB::connection();
    if (! app()->environment('testing')
        || $connection->getName() !== 'pgsql'
        || $connection->getDatabaseName() !== $input['database']
        || (string) $connection->scalar('SELECT current_database()') !== $input['database']) {
        throw new RuntimeException('worker_effective_database_mismatch');
    }
    $connection->select('SELECT set_config(?, ?, false)', ['application_name', $input['application_name']]);
    $actor = App\Models\User::query()->findOrFail($input['actor_id']);
    $run = App\Models\Assessment\OmrDiagnosticRun::query()->findOrFail($input['run_id']);
    $capture = App\Models\Assessment\OmrDiagnosticCapture::query()->findOrFail($input['capture_id']);
    $pid = (int) $connection->scalar('SELECT pg_backend_pid()');
    fwrite(STDOUT, "READY ".$pid."\n");
    fflush(STDOUT);
    try {
        app(App\Assessment\Actions\Omr\Operational\DiscardOmrDiagnosticCapture::class)->handle(
            $run,
            $capture,
            $actor,
            $input['expected_revision'],
            $input['expected_capture_version'],
            'Concurrent terminal fence test',
        );
        $result = ['outcome' => 'accepted'];
    } catch (RuntimeException $exception) {
        $result = ['outcome' => 'rejected', 'code' => $exception->getMessage()];
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['error' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}
PHP;
        $environment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => $database,
            'DB_HOST' => 'postgres',
            'DB_PORT' => '5432',
            'DB_USERNAME' => 'nexiam',
            'DB_PASSWORD' => 'nexiam-local-only',
            'DB_URL' => '',
            'APP_CONFIG_CACHE' => $configCache,
            'NEXIAM_TEST_PIN_SQLITE' => '0',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];
        $process = new Process([
            PHP_BINARY,
            '-r',
            $script,
            base64_encode(json_encode($input, JSON_THROW_ON_ERROR)),
        ], base_path(), $environment);
        $process->setTimeout(45);
        $process->start();

        $workerPid = null;
        $waiting = false;
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $process->isRunning();
            foreach (explode("\n", $process->getOutput()) as $outputLine) {
                if (str_starts_with($outputLine, 'READY ')) {
                    $workerPid = (int) trim(substr($outputLine, 6));
                    break;
                }
            }
            if ($workerPid !== null && $workerPid > 0) {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $activity = DB::selectOne(
                    'SELECT wait_event_type, wait_event, cardinality(pg_blocking_pids(pid)) AS blockers FROM pg_stat_activity WHERE pid = ?',
                    [$workerPid],
                );
                $waiting = $activity !== null
                    && $activity->wait_event_type === 'Lock'
                    && (int) $activity->blockers > 0;
                if ($waiting) {
                    break;
                }
            }
            if (! $process->isRunning()) {
                test()->fail('The discard worker exited before waiting on the locked run: '.$process->getErrorOutput().$process->getOutput());
            }
            usleep(25_000);
        }
        if (! $waiting) {
            $process->stop();
            test()->fail('The discard worker never blocked on the parent-held PostgreSQL run lock. pid='
                .json_encode($workerPid, JSON_THROW_ON_ERROR).' stdout='.$process->getOutput().' stderr='.$process->getErrorOutput());
        }

        DB::commit();
        $process->wait();
        if (! $process->isSuccessful()) {
            test()->fail('The discard worker failed: '.$process->getErrorOutput());
        }
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
        DB::beginTransaction();

        return $result;
    };

    $connection = DB::connection();
    expect($connection->getDriverName())->toBe('pgsql')
        ->and($connection->getDatabaseName())->toBe(OMR_PAGE_DISPOSITION_POSTGRES_DATABASE);
    $connection->commit();
    $connection->beginTransaction();

    $terminalRevision = (int) $terminalRun->revision;
    DB::table('omr_diagnostic_runs')->where('id', $terminalRun->id)->lockForUpdate()->first();
    DB::table('omr_diagnostic_runs')->where('id', $terminalRun->id)->update([
        'status' => OmrDiagnosticRun::STATUS_FAILED,
        'phase' => OmrDiagnosticRun::PHASE_COMPLETE,
        'completed_at' => now(),
        'revision' => $terminalRevision + 1,
    ]);
    $terminalResult = $worker(
        $terminalRun,
        $terminalCapture,
        $terminalRevision,
        (int) $terminalCapture->version,
        'omr-discard-terminal-'.substr(str_replace('-', '', (string) Str::uuid()), 0, 12),
    );
    expect($terminalResult)->toBe([
        'outcome' => 'rejected',
        'code' => 'omr_diagnostic_run_is_terminal',
    ])->and($terminalCapture->fresh()->failure_code)->toBeNull();

    $processingRevision = (int) $processingRun->revision + 1;
    DB::table('omr_diagnostic_runs')->where('id', $processingRun->id)->lockForUpdate()->first();
    DB::table('omr_diagnostic_captures')->where('id', $processingCapture->id)->update([
        'status' => OmrDiagnosticCapture::STATUS_PROCESSING,
        'version' => (int) $processingCapture->version + 1,
    ]);
    DB::table('omr_diagnostic_runs')->where('id', $processingRun->id)->update([
        'status' => OmrDiagnosticRun::STATUS_PROCESSING,
        'phase' => OmrDiagnosticRun::PHASE_PROCESSING,
        'revision' => $processingRevision,
    ]);
    $processingResult = $worker(
        $processingRun,
        $processingCapture,
        $processingRevision,
        (int) $processingCapture->version + 1,
        'omr-discard-processing-'.substr(str_replace('-', '', (string) Str::uuid()), 0, 12),
    );
    expect($processingResult)->toBe([
        'outcome' => 'rejected',
        'code' => 'omr_diagnostic_capture_outcome_unknown',
    ])->and($processingCapture->fresh()->status)->toBe(OmrDiagnosticCapture::STATUS_PROCESSING)
        ->and($processingCapture->fresh()->failure_code)->toBeNull();
});

test('QR-disabled diagnostic page evidence bridges to diagnostic capture and retake supersedes comparison without production claims', function (): void {
    Storage::fake('sensitive');
    $fixture = diagnosticQrDisabledBridgeFixture('DIAGNOSTIC-BRIDGE');
    $actor = $fixture['actor'];
    $cycle = AcademicCycle::query()
        ->where('institution_id', $actor->staffProfile->institution_id)
        ->where('code', $actor->staffProfile->active_cycle_code)
        ->firstOrFail();
    $itemBindings = [
        ['page_no' => 1, 'native_label' => 'QR_DISABLED_PAGE_1_ITEM_1', 'global_position' => 1, 'sheet_item_id' => 101, 'item_id' => 201, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
        ['page_no' => 2, 'native_label' => 'QR_DISABLED_PAGE_2_ITEM_1', 'global_position' => 2, 'sheet_item_id' => 102, 'item_id' => 202, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
    ];
    $cases = [
        ['case_id' => 'case-1', 'coverage' => ['correct'], 'pages' => [
            ['page_no' => 1, 'responses' => [['native_label' => 'QR_DISABLED_PAGE_1_ITEM_1', 'token' => 'A']]],
            ['page_no' => 2, 'responses' => [['native_label' => 'QR_DISABLED_PAGE_2_ITEM_1', 'token' => 'B']]],
        ], 'expected_metrics' => [], 'recorded_measures' => []],
    ];

    $analysisPort = app(OmrCaptureAnalysisPort::class);
    $observingAnalysisPort = new class($analysisPort) implements OmrCaptureAnalysisPort
    {
        public array $observedCropPolicies = [];

        public function __construct(private readonly OmrCaptureAnalysisPort $inner) {}

        public function analyze(OmrCaptureAnalysisRequest $request): OmrPreparedCaptureEvidence
        {
            $this->observedCropPolicies[] = $request->capture->cropPolicy;

            return $this->inner->analyze($request);
        }
    };
    app()->instance(OmrCaptureAnalysisPort::class, $observingAnalysisPort);
    $run = diagnosticBridgeAcceptanceRun($fixture, $fixture['version'], $itemBindings, $cases);
    $set = app(StartOmrPendingCaptureSet::class)->forDiagnostic($run, $actor, (string) Str::uuid());
    $captureUrl = route('setup.exam-materials.omr-test.runs.capture', $run->public_id);
    $anchoredSet = clone $set;
    $anchoredSet->registration_mode = OmrRegistrationMode::FiducialAnchored->value;
    expect(fn () => app(CaptureOmrPendingPage::class)->handle(
        $anchoredSet,
        $actor,
        1,
        1,
        (string) Str::uuid(),
        'direct-action-input',
        'image/png',
        cropPolicy: 'keep_original',
    ))->toThrow(RuntimeException::class, 'omr_capture_set_crop_policy_not_allowed');
    $pageOne = physicalChallengePng();
    $showUrl = route('setup.exam-materials.omr-test.runs.show', $run->public_id);
    $this->actingAs($actor);
    $invalidPolicyPayload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('invalid-policy.png', $pageOne, 'image/png'),
        'crop_policy' => 'manual_quad',
    ]);
    $this->post($captureUrl, $invalidPolicyPayload, ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['crop_policy']);
    $pageOnePayload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('page-one.png', $pageOne, 'image/png'),
        'crop_policy' => 'keep_original',
    ]);
    $oneResponse = $this->post($captureUrl, $pageOnePayload, ['Accept' => 'application/json']);
    $oneResponse->assertOk();

    $oneEvidence = OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->where('page_no', 1)->latest('id')->firstOrFail();
    $oneCapture = OmrDiagnosticCapture::query()
        ->where('diagnostic_run_id', $run->id)
        ->where('client_capture_id', $oneEvidence->client_capture_id)
        ->firstOrFail();
    expect($oneEvidence->crop_policy)->toBe('keep_original')
        ->and($oneCapture->evidence_json['crop_policy'] ?? null)->toBe('keep_original')
        ->and($oneCapture->evidence_json['pending_page_link']['pending_page_evidence_id'])->toBe($oneEvidence->id)
        ->and($oneCapture->evidence_json['pending_page_link']['input_hash'])->toBe($oneEvidence->input_hash)
        ->and($oneEvidence->input_hash)->toBe(hash('sha256', $pageOne))
        ->and($oneEvidence->byte_count)->toBe(strlen($pageOne))
        ->and($oneCapture->input_asset_hash)->toBe($oneEvidence->input_hash)
        ->and($oneResponse->json('context.capture_receipt.status'))->toBe('accepted')
        ->and($oneResponse->json('context.capture_receipt.operation_id'))->toBe($pageOnePayload['operation_id'])
        ->and($oneResponse->json('context.capture_receipt.page_id'))->toBe($oneEvidence->id)
        ->and($oneResponse->json('context.capture_receipt.pending_capture_id'))->toBe($set->public_id)
        ->and($observingAnalysisPort->observedCropPolicies)->toContain('keep_original');
    $this->getJson($showUrl)->assertOk();
    $this->post($captureUrl, [
        ...$pageOnePayload,
        'image' => UploadedFile::fake()->createWithContent('page-one.png', $pageOne, 'image/png'),
    ], ['Accept' => 'application/json'])->assertOk();
    $changedPolicy = $this->post($captureUrl, [
        ...$pageOnePayload,
        'image' => UploadedFile::fake()->createWithContent('page-one.png', $pageOne, 'image/png'),
        'crop_policy' => 'auto_document_quad',
    ], ['Accept' => 'application/json']);
    expect($changedPolicy->status())->toBe(409);

    $oneCapture->forceFill(['projected_score_json' => ['comparison' => ['passed' => true]]])->saveOrFail();
    $current = $this->getJson($showUrl)->assertOk();
    $captureReceipt = $current->json('context.capture_receipt');
    $redoAction = $current->json('context.capture_actions.redo_last');
    expect($captureReceipt['status'] ?? null)->toBe('accepted')
        ->and($captureReceipt['operation_id'] ?? null)->toBe($pageOnePayload['operation_id'])
        ->and($redoAction)->toBeArray();
    $revision = (int) $current->json('revision');
    $reason = 'Retake blurred page';
    $actionResp = $this->postJson(route('setup.exam-materials.omr-test.runs.capture-actions', $run->public_id), [
        'action' => 'redo_last',
        'expected_revision' => $revision,
        'authorization_id' => $redoAction['authorizationId'],
        'accepted_operation_id' => $captureReceipt['operation_id'],
        'accepted_target_key' => $redoAction['acceptedTargetKey'],
        'idempotency_key' => $redoAction['authorizationId'],
        'replacement_reason' => $reason,
    ]);
    $actionResp->assertOk();
    $redoTarget = $this->getJson($showUrl)->assertOk()->json('context.capture_target');
    expect($redoTarget['redo_source_page_id'] ?? null)->toBe($oneEvidence->id)
        ->and($redoTarget['redo_source_page_revision'] ?? 0)->toBeGreaterThan(0);
    $retakePayload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('retake.png', physicalChallengePng(), 'image/png'),
        'replacement_reason' => $reason,
        'crop_policy' => 'keep_original',
    ]);
    $retakeId = $retakePayload['client_capture_id'];
    $retakeResp = $this->post($captureUrl, $retakePayload, ['Accept' => 'application/json']);
    $retakeResp->assertOk();

    $newEvidence = OmrPendingCapturePageEvidence::query()
        ->where('set_id', $set->id)->where('client_capture_id', $retakeId)->firstOrFail();
    $newCapture = OmrDiagnosticCapture::query()
        ->where('diagnostic_run_id', $run->id)->where('client_capture_id', $retakeId)->firstOrFail();
    expect($newEvidence->replacement_reason)->toBe($reason)
        ->and($newEvidence->supersedes_id)->toBe($oneEvidence->id)
        ->and($newCapture->evidence_json['pending_page_link']['supersedes_pending_page_evidence_id'])->toBe($oneEvidence->id)
        ->and($oneCapture->fresh()->evidence_json['pending_page_link']['pending_page_evidence_id'])->toBe($oneEvidence->id)
        ->and($oneCapture->fresh()->projected_score_json)->toBeNull()
        ->and($run->fresh()->revision)->toBeGreaterThan($revision)
        ->and(OmrPendingCapturePageEvidence::query()->whereKey($oneEvidence->id)->exists())->toBeTrue()
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and(OmrQrDisabledPageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
    $afterRetake = $this->getJson($showUrl.'?capture_page_no=2')->assertOk();
    $afterRetakeTarget = $afterRetake->json('context.capture_target');
    expect($afterRetakeTarget['page_no'] ?? $afterRetakeTarget['pageNo'] ?? null)->toBe(2)
        ->and($afterRetakeTarget['client_capture_id'] ?? $afterRetakeTarget['clientCaptureId'] ?? null)->not->toBe($retakeId);
    $staleRunPayload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('stale-run.png', physicalChallengePng(), 'image/png'),
        'expected_revision' => $revision,
        'page_no' => 2,
    ]);
    expect($this->post($captureUrl, $staleRunPayload, ['Accept' => 'application/json'])->status())->toBeGreaterThanOrEqual(400);
    $staleSetPayload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('stale-set.png', physicalChallengePng(), 'image/png'),
        'page_no' => 2,
        'pending_capture_revision' => $pageOnePayload['pending_capture_revision'],
    ]);
    expect($this->post($captureUrl, $staleSetPayload, ['Accept' => 'application/json'])->status())->toBeGreaterThanOrEqual(400);
    $pageTwoBytes = physicalChallengePng();
    $pageTwoPayload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('page-two.png', $pageTwoBytes, 'image/png'),
        'page_no' => 2,
        'crop_policy' => 'keep_original',
    ]);
    $pageTwoId = $pageTwoPayload['client_capture_id'];
    $this->post($captureUrl, $pageTwoPayload, ['Accept' => 'application/json'])->assertOk();
    $twoEvidence = OmrPendingCapturePageEvidence::query()
        ->where('set_id', $set->id)
        ->where('client_capture_id', $pageTwoId)
        ->firstOrFail();
    expect($twoEvidence->crop_policy)->toBe('keep_original')
        ->and($observingAnalysisPort->observedCropPolicies)->toContain('keep_original');
    $discarded = $this->postJson(route('setup.exam-materials.omr-test.runs.captures.discard', [
        'run' => $run->public_id,
        'capture' => $newCapture->id,
    ]), [
        'expected_revision' => $run->fresh()->revision,
        'expected_capture_version' => $newCapture->fresh()->version,
        'reason' => 'Remove the selected Page 1 attempt',
    ]);
    $discarded->assertOk();
    expect($set->fresh()->status)->not->toBe(OmrPendingCaptureSet::STATUS_CANCELLED)
        ->and($twoEvidence->fresh())->not->toBeNull()
        ->and($newCapture->fresh()->status)->toBe(OmrDiagnosticCapture::STATUS_FAILED);

    $item = OmrDiagnosticItemEvidence::query()->create([
        'diagnostic_capture_id' => $oneCapture->id,
        'item_key' => 'QR_DISABLED_PAGE_1_ITEM_1',
        'page_no' => 1,
        'evidence_hash' => hash('sha256', 'qr-disabled-page-item'),
    ]);
    expect($item->capture->evidence_json['pending_page_link']['pending_page_evidence_id'])->toBe($oneEvidence->id);

    // Assert QR-disabled attempt and selection storage and supersession
    $oneAttempt = DB::table('test_kit_diagnostic_page_attempts')->where('diagnostic_capture_id', $oneCapture->id)->first();
    expect($oneAttempt)->not->toBeNull()
        ->and($oneAttempt->pending_set_id)->toBe($set->id)
        ->and($oneAttempt->pending_page_evidence_id)->toBe($oneEvidence->id)
        ->and($oneAttempt->case_id)->toBe('case-1')
        ->and($oneAttempt->page_no)->toBe(1)
        ->and($oneAttempt->predecessor_attempt_id)->toBeNull();

    $newAttempt = DB::table('test_kit_diagnostic_page_attempts')->where('diagnostic_capture_id', $newCapture->id)->first();
    expect($newAttempt)->not->toBeNull()
        ->and($newAttempt->pending_set_id)->toBe($set->id)
        ->and($newAttempt->pending_page_evidence_id)->toBe($newEvidence->id)
        ->and($newAttempt->case_id)->toBe('case-1')
        ->and($newAttempt->page_no)->toBe(1)
        ->and($newAttempt->predecessor_attempt_id)->toBe($oneAttempt->id);

    $selection = DB::table('test_kit_diagnostic_page_selections')->where('diagnostic_run_id', $run->id)->where('page_no', 1)->first();
    expect($selection)->not->toBeNull()
        ->and($selection->selected_attempt_id)->toBe($newAttempt->id)
        ->and($selection->revision)->toBe(2);

});

test('QR-enabled diagnostic page captures record attempts and advance selection revisions on retake', function (): void {
    Storage::fake('sensitive');
    $fixture = diagnosticQrDisabledBridgeFixture('QR-ENABLED-BRIDGE');
    $actor = $fixture['actor'];
    $cycle = AcademicCycle::query()
        ->where('institution_id', $actor->staffProfile->institution_id)
        ->where('code', $actor->staffProfile->active_cycle_code)
        ->firstOrFail();

    $enabledTemplate = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $fixture['institution']->id,
        'is_synthetic' => true,
        'code' => 'QRE-'.Str::upper(Str::random(6)),
        'name' => 'QR-enabled print fixture',
    ]);
    $enabledVersion = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $enabledTemplate->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Signed,
        'page_count' => 1,
        'page_width' => 2551,
        'page_height' => 3898,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config-enabled'),
        'template_json_hash' => hash('sha256', 'template-enabled'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => [
            'template' => ['pages' => [['page_no' => 1, 'pageDimensions' => [2551, 3898], 'fieldBlocks' => []]]],
            'config' => [],
            'manifest' => [
                'registration_mode' => OmrRegistrationMode::DocumentBound->value,
                'qr_mode' => OmrQrMode::Signed->value,
                'compiled_pages' => [['page_no' => 1]],
            ],
        ],
        'native_bundle_hash' => hash('sha256', 'bundle-enabled'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'commit-enabled',
        'patch_identity' => 'patch-enabled',
        'review_manifest_json' => [
            'artifact_set' => ['artifact_hash' => hash('sha256', 'art-enabled'), 'pdf_hash' => hash('sha256', 'pdf-enabled')],
            'pages' => [['page_no' => 1]],
        ],
        'review_manifest_hash' => hash('sha256', 'review-enabled'),
        'authoring_manifest' => [
            'registration_mode' => OmrRegistrationMode::DocumentBound->value,
            'qr_mode' => OmrQrMode::Signed->value,
            'compiled_pages' => [['page_no' => 1]],
            'artifact_set' => ['artifact_hash' => hash('sha256', 'art-enabled'), 'pdf_hash' => hash('sha256', 'pdf-enabled')],
        ],
    ]);

    $itemBindings = [
        ['page_no' => 1, 'native_label' => 'Q1', 'global_position' => 1, 'sheet_item_id' => 101, 'item_id' => 201, 'subtest_id' => 301, 'option_tokens' => ['A', 'B']],
    ];
    $cases = [
        ['case_id' => 'case-enabled', 'coverage' => ['correct'], 'pages' => [
            ['page_no' => 1, 'responses' => [['native_label' => 'Q1', 'token' => 'A']]],
        ], 'expected_metrics' => [], 'recorded_measures' => []],
    ];

    $run = diagnosticBridgeAcceptanceRun($fixture, $enabledVersion, $itemBindings, $cases);

    $action = app(CaptureOmrDiagnosticRun::class);

    expect(fn () => $action->handle(
        $run,
        'invalid-input',
        'image/png',
        (string) Str::uuid(),
        $actor,
        1,
        cropPolicy: 'manual_quad',
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_crop_policy_invalid');
    // Initial capture
    $captureOneId = (string) Str::uuid();
    $captureOneKey = (string) Str::uuid();
    $captureOneBytes = physicalChallengePng();
    $result1 = $action->handle(
        $run,
        $captureOneBytes,
        'image/png',
        $captureOneId,
        $actor,
        1,
        $captureOneKey,
        1,
        null,
        'case-enabled',
        cropPolicy: 'keep_original',
    );
    $capture1 = $result1['capture'];
    expect($capture1->evidence_json['crop_policy'] ?? null)->toBe('keep_original');
    expect(fn () => $action->handle(
        $run->fresh(),
        $captureOneBytes,
        'image/png',
        $captureOneId,
        $actor,
        1,
        $captureOneKey,
        1,
        null,
        'case-enabled',
        cropPolicy: 'auto_document_quad',
    ))->toThrow(RuntimeException::class);
    expect(OmrDiagnosticCapture::query()->where('diagnostic_run_id', $run->id)->count())->toBe(1)
        ->and($capture1->fresh()->evidence_json['crop_policy'] ?? null)->toBe('keep_original');

    $attempt1 = DB::table('test_kit_diagnostic_page_attempts')->where('diagnostic_capture_id', $capture1->id)->first();
    expect($attempt1)->not->toBeNull()
        ->and($attempt1->pending_set_id)->toBeNull()
        ->and($attempt1->pending_page_evidence_id)->toBeNull()
        ->and($attempt1->case_id)->toBe('case-enabled')
        ->and($attempt1->page_no)->toBe(1)
        ->and($attempt1->predecessor_attempt_id)->toBeNull();

    $selection1 = DB::table('test_kit_diagnostic_page_selections')->where('diagnostic_run_id', $run->id)->where('page_no', 1)->first();
    expect($selection1)->not->toBeNull()
        ->and($selection1->selected_attempt_id)->toBe($attempt1->id)
        ->and($selection1->revision)->toBe(1);

    // Retake capture
    $captureTwoId = (string) Str::uuid();
    $currentRevision = (int) $run->fresh()->revision;
    $result2 = $action->handle(
        $run->fresh(),
        physicalChallengePng(),
        'image/png',
        $captureTwoId,
        $actor,
        $currentRevision,
        (string) Str::uuid(),
        1,
        null,
        'case-enabled',
    );
    $capture2 = $result2['capture'];

    $attempt2 = DB::table('test_kit_diagnostic_page_attempts')->where('diagnostic_capture_id', $capture2->id)->first();
    expect($attempt2)->not->toBeNull()
        ->and($attempt2->pending_set_id)->toBeNull()
        ->and($attempt2->pending_page_evidence_id)->toBeNull()
        ->and($attempt2->predecessor_attempt_id)->toBe($attempt1->id);

    $selection2 = DB::table('test_kit_diagnostic_page_selections')->where('diagnostic_run_id', $run->id)->where('page_no', 1)->first();
    expect($selection2)->not->toBeNull()
        ->and($selection2->selected_attempt_id)->toBe($attempt2->id)
        ->and($selection2->revision)->toBe(2);

    // Stale revision fence
    expect(fn () => $action->handle(
        $run->fresh(),
        physicalChallengePng(),
        'image/png',
        (string) Str::uuid(),
        $actor,
        1, // stale revision
        (string) Str::uuid(),
        1,
        null,
        'case-enabled',
    ))->toThrow(RuntimeException::class);
});
function diagnosticPendingStorageRun(array $fixture, string $setCode): OmrDiagnosticRun
{
    $actor = $fixture['actor'];
    $booklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $fixture['institution']->id,
        'is_synthetic' => true,
        'code' => 'PATH-'.$setCode.'-'.Str::upper(Str::random(6)),
        'name' => 'Pending storage isolation '.$setCode,
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'template_version_id' => $fixture['version']->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => hash('sha256', 'pending-path-'.$setCode),
    ]);
    $cycle = AcademicCycle::query()
        ->where('institution_id', $fixture['institution']->id)
        ->where('code', $actor->staffProfile->active_cycle_code)
        ->firstOrFail();
    $run = new OmrDiagnosticRun;
    $run->forceFill([
        'institution_id' => $fixture['institution']->id,
        'academic_cycle_id' => $cycle->id,
        'template_version_id' => $fixture['version']->id,
        'booklet_version_id' => $bookletVersion->id,
        'declared_set_code' => $setCode,
        'status' => OmrDiagnosticRun::STATUS_OPEN,
        'phase' => OmrDiagnosticRun::PHASE_CAPTURE,
        'revision' => 1,
        'created_by_user_id' => $actor->id,
    ])->saveOrFail();

    return $run;
}

test('pending camera originals stay isolated when separate workbenches reuse an operation UUID', function (): void {
    Storage::fake('sensitive');
    $fixture = diagnosticQrDisabledBridgeFixture('PATH-SCOPE');
    $actor = $fixture['actor'];
    $runs = [
        diagnosticPendingStorageRun($fixture, 'A'),
        diagnosticPendingStorageRun($fixture, 'B'),
    ];
    $sets = array_map(
        fn (OmrDiagnosticRun $run): OmrPendingCaptureSet => app(StartOmrPendingCaptureSet::class)
            ->forDiagnostic($run, $actor, (string) Str::uuid()),
        $runs,
    );
    $operationId = (string) Str::uuid();
    $idempotencyKey = (string) Str::uuid();
    $clientCaptureId = (string) Str::uuid();
    $bytesByRun = [
        (string) UploadedFile::fake()->image('workbench-a.png', 24, 24)->get(),
        (string) UploadedFile::fake()->image('workbench-b.png', 25, 24)->get(),
    ];
    $expectedPaths = [];
    $evidence = [];
    foreach ($runs as $index => $run) {
        $workbenchId = 'diagnostic:'.$run->public_id;
        $targetKey = 'pending-storage-'.$run->public_id;
        $reservation = app(OmrCameraFrameFinalization::class)->reserveOperation(
            $actor,
            hash('sha256', $workbenchId),
            ['workbench_id' => $workbenchId, 'diagnostic_run_id' => (int) $run->id, 'revision' => 1],
            [
                'workbenchId' => $workbenchId,
                'clientCaptureId' => $clientCaptureId,
                'pageNo' => 1,
                'captureRevision' => 1,
                'pending_capture_id' => (string) $sets[$index]->public_id,
                'pending_capture_revision' => 1,
                'targetKey' => $targetKey,
            ],
            $operationId,
            $idempotencyKey,
            hash('sha256', $bytesByRun[$index]),
            'keep_original',
            null,
        );
        $expectedPaths[] = 'omr/pending-pages/camera-'.(int) $reservation->operation->id.'.bin';
        app(CaptureOmrPendingPage::class)->handle(
            $sets[$index],
            $actor,
            1,
            1,
            $clientCaptureId,
            $bytesByRun[$index],
            'image/png',
            cropPolicy: 'keep_original',
            cameraReservation: $reservation,
        );
        $evidence[] = OmrPendingCapturePageEvidence::query()
            ->where('set_id', $sets[$index]->id)
            ->where('client_capture_id', $clientCaptureId)
            ->firstOrFail();
    }

    expect($evidence[0]->asset_path)->toBe($expectedPaths[0])
        ->and($evidence[1]->asset_path)->toBe($expectedPaths[1])
        ->and($evidence[0]->asset_path)->not->toBe($evidence[1]->asset_path)
        ->and(app(SensitiveAssetStore::class)->read($evidence[0]->asset_path))->toBe($bytesByRun[0])
        ->and(app(SensitiveAssetStore::class)->read($evidence[1]->asset_path))->toBe($bytesByRun[1]);
});

test('diagnostic camera exact replay completes an interrupted pending-page bridge before accepting its receipt', function (): void {
    Storage::fake('sensitive');
    $fixture = diagnosticQrDisabledBridgeFixture('BRIDGE-REPLAY');
    $actor = $fixture['actor'];
    $itemBindings = [[
        'page_no' => 1,
        'native_label' => 'BRIDGE_REPLAY_PAGE_1',
        'global_position' => 1,
        'sheet_item_id' => 901,
        'item_id' => 902,
        'subtest_id' => 903,
        'option_tokens' => ['A', 'B'],
    ]];
    $cases = [[
        'case_id' => 'case-1',
        'coverage' => ['correct'],
        'pages' => [
            ['page_no' => 1, 'responses' => [['native_label' => 'BRIDGE_REPLAY_PAGE_1', 'token' => 'A']]],
            ['page_no' => 2, 'responses' => []],
        ],
        'expected_metrics' => [],
        'recorded_measures' => [],
    ]];
    $run = diagnosticBridgeAcceptanceRun($fixture, $fixture['version'], $itemBindings, $cases);
    $this->actingAs($actor);
    $set = app(StartOmrPendingCaptureSet::class)->forDiagnostic($run, $actor, (string) Str::uuid());
    $showUrl = route('setup.exam-materials.omr-test.runs.show', $run->public_id);
    $captureUrl = route('setup.exam-materials.omr-test.runs.capture', $run->public_id);
    $bytes = physicalChallengePng();
    $payload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('bridge-replay.png', $bytes, 'image/png'),
        'crop_policy' => 'keep_original',
    ]);
    $interruptingAudit = new class extends RecordAuditEvent
    {
        public bool $interruptBridge = true;

        public function handle(string $eventType, array $payload, string $actor = 'system'): AuditEvent
        {
            if ($eventType === 'omr.diagnostic.pending_page_bridged' && $this->interruptBridge) {
                $this->interruptBridge = false;
                throw new RuntimeException('injected_pending_bridge_interruption');
            }

            return parent::handle($eventType, $payload, $actor);
        }
    };
    app()->instance(RecordAuditEvent::class, $interruptingAudit);

    $this->actingAs($actor)->post($captureUrl, $payload, ['Accept' => 'application/json']);

    $pendingEvidence = OmrPendingCapturePageEvidence::query()
        ->where('set_id', $set->id)
        ->where('client_capture_id', $payload['client_capture_id'])
        ->firstOrFail();
    $diagnosticCapture = OmrDiagnosticCapture::query()
        ->where('diagnostic_run_id', $run->id)
        ->where('client_capture_id', $payload['client_capture_id'])
        ->firstOrFail();
    $cameraReceipt = OmrCameraCommand::query()
        ->where('operation_id', $payload['operation_id'])
        ->where('kind', 'CAPTURE_RECEIPT')
        ->firstOrFail();
    expect($interruptingAudit->interruptBridge)->toBeFalse()
        ->and($diagnosticCapture->evidence_json['pending_page_link'] ?? null)->toBeNull()
        ->and(DB::table('test_kit_diagnostic_page_attempts')->where('diagnostic_capture_id', $diagnosticCapture->id)->exists())->toBeFalse()
        ->and($cameraReceipt->domain_outcome)->toBe('reserved');
    expect(fn () => app(CompleteOmrDiagnosticRun::class)->handle(
        $run->fresh(),
        $actor,
        (int) $run->fresh()->revision,
        'interrupted-bridge-complete-'.Str::uuid(),
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_capture_outcome_unknown');
    expect(fn () => app(DiscardOmrDiagnosticCapture::class)->handle(
        $run->fresh(),
        $diagnosticCapture,
        $actor,
        (int) $run->fresh()->revision,
        (int) $diagnosticCapture->version,
        'Attempt to remove unresolved bridge',
    ))->toThrow(RuntimeException::class, 'omr_diagnostic_capture_outcome_unknown')
        ->and($diagnosticCapture->fresh()->failure_code)->not->toBe('discarded_by_operator')
        ->and($cameraReceipt->fresh()->domain_outcome)->toBe('reserved');

    $replayResponse = $this->post($captureUrl, [
        ...$payload,
        'image' => UploadedFile::fake()->createWithContent('bridge-replay.png', $bytes, 'image/png'),
    ], ['Accept' => 'application/json']);
    expect($replayResponse->status())->toBe(200, 'Diagnostic replay response: '.$replayResponse->getContent());

    $diagnosticCapture->refresh();
    $cameraReceipt->refresh();
    $attempt = DB::table('test_kit_diagnostic_page_attempts')
        ->where('diagnostic_run_id', $run->id)
        ->where('pending_page_evidence_id', $pendingEvidence->id)
        ->first();
    $selection = DB::table('test_kit_diagnostic_page_selections')
        ->where('diagnostic_run_id', $run->id)
        ->where('case_id', 'case-1')
        ->where('page_no', 1)
        ->first();

    expect($diagnosticCapture->evidence_json['pending_page_link']['pending_page_evidence_id'])->toBe($pendingEvidence->id)
        ->and($attempt)->not->toBeNull()
        ->and($attempt->diagnostic_capture_id)->toBe($diagnosticCapture->id)
        ->and($selection?->selected_attempt_id)->toBe($attempt?->id)
        ->and($cameraReceipt->domain_outcome)->toBe('accepted')
        ->and(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(1)
        ->and(OmrDiagnosticCapture::query()->where('diagnostic_run_id', $run->id)->count())->toBe(1);
});
