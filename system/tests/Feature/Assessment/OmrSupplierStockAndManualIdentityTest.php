<?php

use App\Assessment\Actions\Omr\AllocateOmrSupplierStockToBatch;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\PrintOmrBlankInstances;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\RecordOmrSupplierStockLot;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalNativeBundleCompiler;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrQuarterTurnDiagnostics;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\OmrPhysicalQualificationDeriver;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrSupplierStockAllocation;
use App\Models\Assessment\OmrSupplierStockAllocationUse;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/OmrAssistedCaptureTestRequests.php';

test('uploaded drafts materialize without print PDF, generate QR-free digital proof, and block reproduction routes', function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');

    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'UPLOADED-NO-PRINT',
        'Uploaded Supplier Sheet',
        OmrTemplateAuthoringDraft::SOURCE_PDF,
        [
            'pages' => [['page_no' => 1, 'confirmed' => true]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'mapping' => [
                'input' => [
                    'page_width' => 2480,
                    'page_height' => 3508,
                    'page_count' => 1,
                    'declared_inventory' => [
                        ['page_no' => 1, 'area_code' => 'GENERAL', 'question_count' => 2, 'option_labels' => ['A', 'B', 'C', 'D']],
                    ],
                    'identity' => [
                        'method' => 'write_in_manual',
                        'regions' => [['page_no' => 1, 'x' => 180, 'y' => 120, 'w' => 520, 'h' => 110]],
                    ],
                ],
                'field_blocks' => [
                    'Questions_1' => [
                        'page_no' => 1,
                        'fieldType' => 'QTYPE_MCQ4',
                        'direction' => 'horizontal',
                        'fieldLabels' => ['GENERAL_Q_1', 'GENERAL_Q_2'],
                        'bubblesGap' => 70,
                        'labelsGap' => 80,
                        'origin' => [220, 900],
                        'bubbleDimensions' => [50, 50],
                    ],
                ],
            ],
        ],
        OmrRegistrationMode::DocumentBound,
        OmrQrMode::Disabled,
        1,
    );

    $materialized = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, 0);
    $artifactSet = $materialized->authoring_json['canonical']['artifact_set'];

    expect($materialized->authoring_json['canonical']['pdf_asset_id'])->toBeNull()
        ->and($artifactSet['pdf_asset_id'])->toBeNull()
        ->and($artifactSet['pdf_hash'])->toBeNull()
        ->and($materialized->assets()->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)->count())->toBe(0)
        ->and($materialized->assets()->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)->count())->toBe(1);

    $rasterAssetId = (int) $artifactSet['raster_assets'][0]['asset_id'];
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.drafts.assets.show', [$materialized, $rasterAssetId]))
        ->assertOk();

    $target = new CandidateArtifactDiagnosticTarget(
        $materialized->fresh(['template', 'markerProfile', 'assets']),
        app(CanonicalArtifactMaterializer::class)->loadPersisted($materialized->fresh(), null),
        null,
    );
    $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::Digital);
    expect($plan->sourceKind)->toBe('uploaded_original')
        ->and($plan->diagnosticQrReservation)->toBe([]);

    $proof = app(OmrDigitalProofGenerator::class)->generate($materialized->fresh(['assets']), $plan);
    expect($proof['source_pdf_asset_id'])->toBe(0)
        ->and($proof['diagnostic_qr_payload_hashes'])->toBe([])
        ->and($proof['source_raster_hashes'])->toHaveCount(1)
        ->and($proof['pages'][0]['diagnostic_qr_payload_hash'])->toBe('');

    $proofAsset = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $materialized,
        OmrTemplateAsset::KIND_DIGITAL_PROOF,
        $proof['pages'][0]['bytes'],
        'image/png',
        1,
        ['proof_hash' => $proof['proof_hash']],
    );
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.drafts.assets.show', [$materialized, $proofAsset->id]))
        ->assertForbidden();

    expect(fn () => app(CreateOmrPhysicalChallenge::class)->handle($materialized->fresh(), $actor, 'quick'))
        ->toThrow(RuntimeException::class, 'uploaded_original_print_forbidden');
});

test('supplier stock custody, per-page write-in manual identity, retake invalidation, and adoption resolve without print issuance', function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');

    seedE2Foundation();
    seedE2OmrFoundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $institution = Institution::query()->findOrFail($cycle->institution_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $area = AptitudeArea::query()->where('code', 'LOGIC')->firstOrFail();

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
        'SUPPLIER-WRITEIN-2P',
        'Two-page supplier write-in sheet',
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

    $saved = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, 0);
    $compiled = app(OmrCanonicalNativeBundleCompiler::class)->compile($saved->fresh(['assets']), null, [
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'source_kind' => 'uploaded_original',
        'declared_inventory' => $saved->authoring_json['mapping']['input']['declared_inventory'],
        'identity_method' => $saved->authoring_json['mapping']['input']['identity'],
        'compiled_pages' => [['page_no' => 1], ['page_no' => 2]],
    ]);
    $version = $compiled['version'];
    $manifest = $version->authoring_manifest;
    $manifest['artifact_set'] = $saved->authoring_json['canonical']['artifact_set'];
    $version->forceFill([
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'authoring_manifest' => $manifest,
    ])->save();
    $saved->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'compiled_version_id' => $version->id,
    ])->save();

    expect(fn () => app(PrintOmrBlankInstances::class)->handle($version->fresh(), $actor, 5, (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'uploaded_original_print_forbidden');

    $bookletVersion = app(CreateBookletDraft::class)->handle(
        'Supplier Booklet '.uniqid(),
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $actor,
        $version->fresh('areas'),
    );
    $bookletVersion = app(SetBookletSetsMode::class)->handle($bookletVersion, $actor, true, $bookletVersion->lock_version, false);
    $keys = [
        ['aptitude_area_id' => (int) $area->id, 'area_position' => 1, 'global_position' => 1, 'correct_option_code' => 'B'],
        ['aptitude_area_id' => (int) $area->id, 'area_position' => 2, 'global_position' => 2, 'correct_option_code' => 'C'],
    ];
    foreach (OmrBookletSet::SET_CODES as $setCode) {
        app(SaveBookletSetKeys::class)->handle($bookletVersion, $setCode, $keys, $actor);
    }
    $bookletVersion = app(FinalizeBookletVersion::class)->handle($bookletVersion, $actor);
    $pair = OmrTemplateBookletPair::query()->create([
        'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
        'approved_by_user_id' => $actor->id,
        'approved_at' => now(),
    ]);

    $arrangement = SeatingArrangement::query()->firstOrCreate(
        ['normalized_name' => 'supplier uniform'],
        [
            'name' => 'Supplier Uniform',
            'rows' => 5,
            'columns' => 5,
            'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
            'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
            'is_active' => true,
            'lock_version' => 1,
        ],
    );

    $attempts = [];
    for ($i = 1; $i <= 2; $i++) {
        $result = submitSyntheticApplicant("supplier.{$i}.".uniqid().'@example.test', "Supplier {$i}");
        $attempt = $result['attempt'];
        $this->actingAs($registrar)
            ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
            ->assertRedirect();
        $attempts[$i] = $attempt->fresh();
    }

    $this->actingAs($actor)->post(route('exams.sessions.store'), [
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'booklet_version_id' => $pair->booklet_version_id,
        'seating_arrangement_id' => $arrangement->id,
        'code' => 'EX-SUP-'.uniqid(),
        'starts_at' => now()->subHours(2)->toDateTimeString(),
        'effective_end_at' => now()->addMinutes(30)->toDateTimeString(),
    ])->assertRedirect();
    $examSession = ExamSession::query()->orderByDesc('id')->firstOrFail();
    foreach ($attempts as $attempt) {
        $this->actingAs($actor)
            ->post(route('exams.sessions.assign', $examSession), ['admission_attempt_id' => $attempt->id])
            ->assertRedirect();
    }
    $this->actingAs($actor)
        ->post(route('exams.sessions.publish', $examSession), ['expected_version' => 1])
        ->assertRedirect();
    foreach ($examSession->fresh()->assignments as $assignment) {
        app(RecordExamAttendance::class)->handle($assignment, $actor, ExamAttendanceRecord::OUTCOME_PRESENT, 'att-sup-'.$assignment->id);
        app(RecordExamSubmission::class)->handle($assignment, $actor, 'sub-sup-'.$assignment->id);
    }
    $this->actingAs($actor)
        ->post(route('exams.sessions.close', $examSession->fresh()), ['expected_version' => 2])
        ->assertRedirect();

    $gradingSession = GradingSession::query()->where('exam_session_id', $examSession->id)->firstOrFail();
    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $gradingSession->id,
        'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 1,
        'opened_by_user_id' => $actor->id,
    ]);
    $appNo1 = (string) $attempts[1]->reference_code;
    $appNo2 = (string) $attempts[2]->reference_code;

    // Printed issuance is rejected for uploaded_original template versions
    $fakeIssuance = OmrQrDisabledIssuance::query()->create([
        'template_version_id' => $version->id,
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'issuance_code' => 'QDI-FAKE',
        'page_count' => 2,
        'copy_policy' => 'unique_instance',
        'intended_copy_count' => 1,
        'artifact_manifest_hash' => str_repeat('b', 64),
        'status' => OmrQrDisabledIssuance::STATUS_PRINTED,
        'created_by_user_id' => $actor->id,
        'printed_at' => now(),
    ]);
    expect(fn () => app(StartOmrPendingCaptureSet::class)->forProduction($gradingSession, $batch, $fakeIssuance, $actor, (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'omr_capture_set_supplier_stock_required');

    // Record supplier stock lot and allocate 1 set to the batch
    $lot = app(RecordOmrSupplierStockLot::class)->handle(
        $version,
        $actor,
        'Psychometric Press Inc.',
        'INV-2026-9901',
        10,
        ['delivery_receipt' => 'DR-9901'],
    );
    expect($lot->availablePageSets())->toBe(10);

    $allocation = app(AllocateOmrSupplierStockToBatch::class)->handle(
        $gradingSession,
        $batch,
        $lot,
        $actor,
        1,
        (string) Str::uuid(),
    );
    expect($allocation->remainingQuantity())->toBe(1)
        ->and($lot->fresh()->availablePageSets())->toBe(9);

    // Fake analysis port: Page 1 has blank answer (unanswered), Page 2 has ambiguous mark
    $port = new class implements OmrCaptureAnalysisPort
    {
        public function analyze(OmrCaptureAnalysisRequest $request): OmrPreparedCaptureEvidence
        {
            $hash = $request->capture->sourceHash();
            $pageNo = $request->pageNo;
            $detectedPayload = $pageNo === 1
                ? [
                    'position' => 1,
                    'page_no' => 1,
                    'area_code' => 'LOGIC',
                    'question_no' => 1,
                    'detected' => [
                        'state' => OmrDetectedItem::STATE_BLANK,
                        'selected_option' => null,
                        'observed_options' => [],
                        'option_observations' => [
                            ['code' => 'A', 'fill_ratio' => 0.01, 'marked' => false],
                            ['code' => 'B', 'fill_ratio' => 0.02, 'marked' => false],
                            ['code' => 'C', 'fill_ratio' => 0.01, 'marked' => false],
                            ['code' => 'D', 'fill_ratio' => 0.01, 'marked' => false],
                        ],
                        'confidence' => 0.99,
                        'issue_codes' => [],
                    ],
                ]
                : [
                    'position' => 2,
                    'page_no' => 2,
                    'area_code' => 'LOGIC',
                    'question_no' => 2,
                    'detected' => [
                        'state' => OmrDetectedItem::STATE_AMBIGUOUS,
                        'selected_option' => null,
                        'observed_options' => ['B', 'C'],
                        'option_observations' => [
                            ['code' => 'A', 'fill_ratio' => 0.01, 'marked' => false],
                            ['code' => 'B', 'fill_ratio' => 0.85, 'marked' => true],
                            ['code' => 'C', 'fill_ratio' => 0.82, 'marked' => true],
                            ['code' => 'D', 'fill_ratio' => 0.01, 'marked' => false],
                        ],
                        'confidence' => 0.45,
                        'issue_codes' => ['multiple_marks'],
                    ],
                ];
            $issues = $pageNo === 2
                ? [['code' => 'multiple_marks', 'severity' => 'required', 'message' => 'Multiple options shaded.', 'area_code' => 'LOGIC', 'question_no' => 2]]
                : [];

            $success = new OmrDetectionSuccess(
                engineName: 'supplier-test',
                engineVersion: '1',
                engineCommit: null,
                inputHash: $hash,
                configHash: hash('sha256', 'config'),
                applicantNumberProposal: null,
                applicantNumberConfidence: null,
                identityFields: [],
                items: [],
                issues: [],
                sheetConfidence: 0.95,
                registrationMode: $request->target->registrationMode()->value,
                templateVersionId: (int) $request->target->targetId(),
                expectedPageNo: $pageNo,
            );

            return new OmrPreparedCaptureEvidence(
                outcome: OmrPreparedCaptureEvidence::OUTCOME_SUCCEEDED,
                code: null,
                detail: null,
                pageNo: $pageNo,
                inputHash: $hash,
                acceptedOrientation: 0,
                orientationDiagnostics: new OmrQuarterTurnDiagnostics([]),
                layout: null,
                diagnosticEvidence: null,
                target: ['id' => $request->target->targetId()],
                hashes: ['input' => $hash],
                assets: [],
                gates: null,
                markerMatches: null,
                fieldBlockShifts: null,
                items: [$detectedPayload],
                identity: [],
                timings: [],
                issues: $issues,
                providerResult: OmrDetectionResult::succeeded($success),
            );
        }
    };
    app()->instance(OmrCaptureAnalysisPort::class, $port);

    // Start pending capture set via supplier_stock_allocation_id
    $startResponse = $this->actingAs($actor)->postJson(
        route('scoring.sessions.omr.batches.workbench.pending-capture.start', [$gradingSession->examSession, $batch]),
        [
            'supplier_stock_allocation_id' => $allocation->id,
            'idempotency_key' => (string) Str::uuid(),
        ],
    )->assertOk();

    $pendingId = $startResponse->json('context.pending_capture.id');
    expect($startResponse->json('context.pending_capture.identity_mode'))->toBe('write_in_manual')
        ->and($startResponse->json('context.pending_capture.supplier_stock_allocation_id'))->toBe($allocation->id);

    $captureUrl = route('scoring.sessions.omr.batches.workbench.capture_start', [$gradingSession->examSession, $batch]);
    $workbenchUrl = route('scoring.sessions.omr.batches.workbench', [$gradingSession->examSession, $batch]);
    $page1Response = $this->actingAs($actor)->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('page1.png', 24, 24),
        'capture_mode' => 'manual',
    ]), ['Accept' => 'application/json'])->assertOk();

    $afterPage2 = $this->actingAs($actor)->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('page2.png', 24, 24),
        'page_no' => 2,
        'capture_mode' => 'manual',
    ]), ['Accept' => 'application/json'])->assertOk();

    expect(OmrPendingCapturePageAnalysis::query()->orderBy('id')->get()->map(fn ($a) => $a->evidence_json['error_detail'] ?? null)->all())->toBe([null, null]);
    expect($afterPage2->json('context.pending_capture.status'))->toBe('captured')
        ->and($afterPage2->json('context.pending_capture.pairing_code'))->toBe('identity_review_required')
        ->and(OmrPendingCapturePageAnalysis::query()->whereNotNull('detected_applicant_number')->count())->toBe(0);

    $revisionAfterCapture = (int) $afterPage2->json('context.pending_capture.revision');

    // Conflicting per-page manual selections keep status='captured' with pairing_conflict
    $conflictResponse = $this->actingAs($actor)->postJson(
        route('scoring.sessions.omr.batches.workbench.pending-capture.identity', [$gradingSession->examSession, $batch]),
        [
            'pending_capture_id' => $pendingId,
            'pending_capture_revision' => $revisionAfterCapture,
            'idempotency_key' => (string) Str::uuid(),
            'selections' => [
                ['page_no' => 1, 'applicant_number' => $appNo1],
                ['page_no' => 2, 'applicant_number' => $appNo2],
            ],
        ],
    )->assertOk();

    expect($conflictResponse->json('context.pending_capture.status'))->toBe('captured')
        ->and($conflictResponse->json('context.pending_capture.pairing_code'))->toBe('pairing_conflict');

    // Matching per-page manual selections transition set to ready
    $readyResponse = $this->actingAs($actor)->postJson(
        route('scoring.sessions.omr.batches.workbench.pending-capture.identity', [$gradingSession->examSession, $batch]),
        [
            'pending_capture_id' => $pendingId,
            'pending_capture_revision' => (int) $conflictResponse->json('context.pending_capture.revision'),
            'idempotency_key' => (string) Str::uuid(),
            'selections' => [
                ['page_no' => 1, 'applicant_number' => $appNo1],
                ['page_no' => 2, 'applicant_number' => $appNo1],
            ],
        ],
    )->assertOk();

    expect($readyResponse->json('context.pending_capture.status'))->toBe('ready')
        ->and($readyResponse->json('context.pending_capture.pairing_code'))->toBeNull();

    $pageTwoRetakeBytes = (string) UploadedFile::fake()->image('page2-retake.png', 24, 24)->get();
    $pageTwoRetakeQuad = [
        'points' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        'width' => 24,
        'height' => 24,
        'orientation' => 1,
        'input_hash' => hash('sha256', $pageTwoRetakeBytes),
        'page_no' => 2,
    ];
    $retakeResponse = $this->actingAs($actor)->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->createWithContent('page2-retake.png', $pageTwoRetakeBytes),
        'page_no' => 2,
        'capture_mode' => 'manual',
        'crop_policy' => 'keep_original',
        'client_quad' => $pageTwoRetakeQuad,
    ]), ['Accept' => 'application/json'])->assertOk();

    expect($retakeResponse->json('context.pending_capture.status'))->toBe('captured')
        ->and($retakeResponse->json('context.pending_capture.pairing_code'))->toBe('identity_review_required');

    // Re-confirm matching manual identity on the new Page 2 evidence
    $reconfirmed = $this->actingAs($actor)->postJson(
        route('scoring.sessions.omr.batches.workbench.pending-capture.identity', [$gradingSession->examSession, $batch]),
        [
            'pending_capture_id' => $pendingId,
            'pending_capture_revision' => (int) $retakeResponse->json('context.pending_capture.revision'),
            'idempotency_key' => (string) Str::uuid(),
            'selections' => [
                ['page_no' => 1, 'applicant_number' => $appNo1],
                ['page_no' => 2, 'applicant_number' => $appNo1],
            ],
        ],
    )->assertOk();

    expect($reconfirmed->json('context.pending_capture.status'))->toBe('ready');

    // Adopt into reviewable production sheet
    $adoptedResponse = $this->actingAs($actor)->postJson(
        route('scoring.sessions.omr.batches.workbench.pending-capture.adopt', [$gradingSession->examSession, $batch]),
        [
            'pending_capture_id' => $pendingId,
            'pending_capture_revision' => (int) $reconfirmed->json('context.pending_capture.revision'),
        ],
    )->assertOk();

    $sheetId = (int) $adoptedResponse->json('context.pending_capture.adopted_sheet_id');
    expect($sheetId)->toBeGreaterThan(0);

    $sheet = OmrCaptureSheet::query()->with(['pages', 'issues'])->findOrFail($sheetId);
    $pageTwo = $sheet->pages->firstWhere('page_no', 2);
    expect($pageTwo?->crop_policy)->toBe('keep_original')
        ->and($pageTwo?->client_quad_json)->toMatchArray($pageTwoRetakeQuad);
    $claims = OmrQrDisabledPageClaim::query()->where('set_id', OmrPendingCaptureSet::query()->where('public_id', $pendingId)->value('id'))->orderBy('page_no')->get();

    expect($claims)->toHaveCount(2)
        ->and($claims->pluck('issuance_id')->all())->toBe([null, null])
        ->and($claims->pluck('supplier_stock_allocation_id')->all())->toBe([$allocation->id, $allocation->id])
        ->and(OmrSupplierStockAllocationUse::query()->where('capture_sheet_id', $sheet->id)->exists())->toBeTrue()
        ->and($allocation->fresh()->used_quantity)->toBe(1)
        ->and($allocation->fresh()->status)->toBe(OmrSupplierStockAllocation::STATUS_EXHAUSTED);

    $resolved = app(OmrCaptureResolver::class)->resolveSheet($sheet);
    expect($resolved->isResolved())->toBeTrue()
        ->and($resolved->attempt?->reference_code)->toBe($appNo1)
        ->and($sheet->proposal_json['items'][0]['state'])->toBe(OmrDetectedItem::STATE_BLANK)
        ->and($sheet->proposal_json['items'][0]['detected_answer'])->toBeNull()
        ->and($sheet->proposal_json['items'][1]['state'])->toBe(OmrDetectedItem::STATE_AMBIGUOUS)
        ->and($sheet->issues->pluck('issue_code')->all())->toContain('multiple_marks');

    // Deriver reports supplier-specific physical qualification blockers for uploaded versions
    $deriver = app(OmrPhysicalQualificationDeriver::class)->derive(null, $version->fresh());
    expect(collect($deriver['blockers'])->pluck('code')->all())
        ->toContain('supplier_physical_capture_required');
});

test('uploaded original verification run allows challenge guide pdf and pinned reference rasters while rejecting full sheet reproduction', function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');

    $institution = Institution::query()->first() ?? Institution::query()->create(['code' => 'TEST-INST', 'name' => 'Test Institution', 'is_active' => true]);
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    $raster1Bytes = 'raster-page-1-bytes';
    $raster1Sha = hash('sha256', $raster1Bytes);
    $raster1Path = 'omr/rasters/page-1.png';
    Storage::disk('local')->put($raster1Path, $raster1Bytes);

    $raster2Bytes = 'raster-page-2-bytes';
    $raster2Sha = hash('sha256', $raster2Bytes);
    $raster2Path = 'omr/rasters/page-2.png';
    Storage::disk('local')->put($raster2Path, $raster2Bytes);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'UPLOADED-VERIFY-'.uniqid(),
        'name' => 'Uploaded verification sheet',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_PDF,
        'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'requested_page_count' => 2,
        'owner_user_id' => $actor->id,
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'authoring_json' => [
            'canonical' => [
                'artifact_set' => [
                    'artifact_hash' => str_repeat('a', 64),
                    'raster_assets' => [
                        ['page_no' => 1, 'asset_id' => null, 'sha256' => $raster1Sha],
                        ['page_no' => 2, 'asset_id' => null, 'sha256' => $raster2Sha],
                    ],
                    'manifest' => [
                        'pages' => [
                            ['page_no' => 1, 'page_dimensions' => ['width' => 2480, 'height' => 3508]],
                            ['page_no' => 2, 'page_dimensions' => ['width' => 2480, 'height' => 3508]],
                        ],
                        'fields' => [
                            [
                                'page' => 1,
                                'label' => 'Q_1',
                                'option' => 'A',
                                'pixel' => ['x' => 100, 'y' => 200, 'w' => 35, 'h' => 35],
                            ],
                            [
                                'page' => 2,
                                'label' => 'Q_2',
                                'option' => 'B',
                                'pixel' => ['x' => 150, 'y' => 250, 'w' => 35, 'h' => 35],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $raster1Asset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_RASTER_PAGE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => $raster1Path,
        'mime' => 'image/png',
        'bytes' => strlen($raster1Bytes),
        'sha256' => $raster1Sha,
        'metadata' => [],
    ]);

    $raster2Asset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_RASTER_PAGE,
        'page_no' => 2,
        'disk' => 'local',
        'path' => $raster2Path,
        'mime' => 'image/png',
        'bytes' => strlen($raster2Bytes),
        'sha256' => $raster2Sha,
        'metadata' => [],
    ]);

    $authoring = $draft->authoring_json;
    $authoring['canonical']['artifact_set']['raster_assets'][0]['asset_id'] = $raster1Asset->id;
    $authoring['canonical']['artifact_set']['raster_assets'][1]['asset_id'] = $raster2Asset->id;
    $draft->forceFill(['authoring_json' => $authoring])->save();

    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
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
    ]);

    // Legacy challenge guide PDF (kind = print_pdf, role = challenge_guide_pdf like live Asset #138)
    $legacyGuideBytes = "%PDF-1.4\n%legacy-guide-pdf";
    $legacyGuideSha = hash('sha256', $legacyGuideBytes);
    $legacyGuidePath = 'omr/evidence/legacy-guide.pdf';
    Storage::disk('local')->put($legacyGuidePath, $legacyGuideBytes);

    $legacyGuideAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_PRINT_PDF,
        'page_no' => null,
        'disk' => 'local',
        'path' => $legacyGuidePath,
        'mime' => 'application/pdf',
        'bytes' => strlen($legacyGuideBytes),
        'sha256' => $legacyGuideSha,
        'metadata' => [
            'run_id' => $run->id,
            'role' => 'challenge_guide_pdf',
        ],
    ]);

    // New challenge guide PDF (kind = evidence, role = challenge_guide_pdf)
    $evidenceGuideBytes = "%PDF-1.4\n%evidence-guide-pdf";
    $evidenceGuideSha = hash('sha256', $evidenceGuideBytes);
    $evidenceGuidePath = 'omr/evidence/evidence-guide.pdf';
    Storage::disk('local')->put($evidenceGuidePath, $evidenceGuideBytes);

    $evidenceGuideAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => null,
        'disk' => 'local',
        'path' => $evidenceGuidePath,
        'mime' => 'application/pdf',
        'bytes' => strlen($evidenceGuideBytes),
        'sha256' => $evidenceGuideSha,
        'metadata' => [
            'run_id' => $run->id,
            'role' => 'challenge_guide_pdf',
        ],
    ]);

    // Non-guide print PDF (forbidden reproduction)
    $forbiddenPrintBytes = "%PDF-1.4\n%forbidden-print-master";
    $forbiddenPrintSha = hash('sha256', $forbiddenPrintBytes);
    $forbiddenPrintPath = 'omr/print/forbidden-sheet.pdf';
    Storage::disk('local')->put($forbiddenPrintPath, $forbiddenPrintBytes);

    $forbiddenPrintAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_PRINT_PDF,
        'page_no' => null,
        'disk' => 'local',
        'path' => $forbiddenPrintPath,
        'mime' => 'application/pdf',
        'bytes' => strlen($forbiddenPrintBytes),
        'sha256' => $forbiddenPrintSha,
        'metadata' => [
            'run_id' => $run->id,
            'role' => 'master_print_sheet',
        ],
    ]);

    // Sheet asset associated with challenge (forbidden reproduction)
    $sheetPdfBytes = "%PDF-1.4\n%challenge-sheet-pdf";
    $sheetPdfSha = hash('sha256', $sheetPdfBytes);
    $sheetPdfPath = 'omr/evidence/sheet.pdf';
    Storage::disk('local')->put($sheetPdfPath, $sheetPdfBytes);

    $sheetAsset = OmrTemplateAsset::query()->create([
        'authoring_draft_id' => $draft->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => null,
        'disk' => 'local',
        'path' => $sheetPdfPath,
        'mime' => 'application/pdf',
        'bytes' => strlen($sheetPdfBytes),
        'sha256' => $sheetPdfSha,
        'metadata' => [
            'run_id' => $run->id,
            'role' => 'challenge_sheet',
        ],
    ]);

    $challenge = OmrPhysicalChallenge::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'created_by_user_id' => $actor->id,
        'challenge_code' => 'CHAL-UPLOADED-REF',
        'mode' => 'quick',
        'method' => 'hand_shaded',
        'status' => OmrPhysicalChallenge::STATUS_CREATED,
        'plan_hash' => str_repeat('p', 64),
        'expected_vector_hash' => str_repeat('e', 64),
        'sheet_asset_id' => $sheetAsset->id,
        'guide_asset_id' => $legacyGuideAsset->id,
        'renderer_version' => 'supplier_original',
        'guide_json' => [
            'sections' => [
                [
                    'page' => 1,
                    'items' => [
                        ['field' => 'Q_1', 'option' => 'A'],
                    ],
                ],
                [
                    'page' => 2,
                    'items' => [
                        ['field' => 'Q_2', 'option' => 'B'],
                    ],
                ],
            ],
        ],
    ]);

    // 1. Legacy guide PDF (kind = print_pdf) streams with 200 OK
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', [
            'run' => $run->id,
            'asset' => $legacyGuideAsset->id,
            'sha256' => $legacyGuideAsset->sha256,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // 2. Newly stored guide PDF (kind = evidence) streams with 200 OK
    OmrPhysicalChallenge::query()->whereKey($challenge->id)->update(['guide_asset_id' => $evidenceGuideAsset->id]);
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', [
            'run' => $run->id,
            'asset' => $evidenceGuideAsset->id,
            'sha256' => $evidenceGuideAsset->sha256,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // 3. Non-guide print PDF is rejected with 403 uploaded_original_print_forbidden
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', [
            'run' => $run->id,
            'asset' => $forbiddenPrintAsset->id,
            'sha256' => $forbiddenPrintAsset->sha256,
        ]))
        ->assertForbidden();

    // 4. Sheet asset is rejected with 403 uploaded_original_print_forbidden
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', [
            'run' => $run->id,
            'asset' => $sheetAsset->id,
            'sha256' => $sheetAsset->sha256,
        ]))
        ->assertForbidden();

    // 5. Pinned reference raster assets stream with 200 OK
    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', [
            'run' => $run->id,
            'asset' => $raster1Asset->id,
            'sha256' => $raster1Asset->sha256,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.assets.show', [
            'run' => $run->id,
            'asset' => $raster2Asset->id,
            'sha256' => $raster2Asset->sha256,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    // 6. Projected into sheet_pages with reference_only: true and bubbles
    $status = app(OmrTemplateVerificationStatus::class)->get($run->fresh(), $actor);
    $sheetPages = $status['challenge']['sheet_pages'] ?? [];

    expect($sheetPages)->toHaveCount(2)
        ->and($sheetPages[0]['page_no'])->toBe(1)
        ->and($sheetPages[0]['reference_only'])->toBeTrue()
        ->and($sheetPages[0]['sha256'])->toBe($raster1Sha)
        ->and($sheetPages[0]['target_count'])->toBe(1)
        ->and($sheetPages[0]['bubbles'])->not->toBeEmpty()
        ->and($sheetPages[0]['bubbles'][0]['label'])->toBe('Q_1')
        ->and($sheetPages[0]['bubbles'][0]['option'])->toBe('A')
        ->and($sheetPages[0]['bubbles'][0]['shaded'])->toBeTrue()
        ->and($sheetPages[1]['page_no'])->toBe(2)
        ->and($sheetPages[1]['reference_only'])->toBeTrue()
        ->and($sheetPages[1]['sha256'])->toBe($raster2Sha)
        ->and($sheetPages[1]['target_count'])->toBe(1)
        ->and($sheetPages[1]['bubbles'])->not->toBeEmpty()
        ->and($sheetPages[1]['bubbles'][0]['label'])->toBe('Q_2')
        ->and($sheetPages[1]['bubbles'][0]['option'])->toBe('B')
        ->and($sheetPages[1]['bubbles'][0]['shaded'])->toBeTrue();
});
