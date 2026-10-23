<?php

use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\CaptureOmrPendingPage;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalNativeBundleCompiler;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrQuarterTurnDiagnostics;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Operational\OmrPendingCaptureTarget;
use App\Assessment\Support\KitCompositionContext;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\ScoreRecord;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/OmrAssistedCaptureTestRequests.php';

test('selected page recognition gates pairing and retake without producing a claim or score', function (array $observations, ?string $expectedCode, string $expectedStatus): void {
    Storage::fake('local');
    Storage::fake('sensitive');
    AptitudeArea::query()->create(['code' => 'GENERAL', 'name' => 'General Ability', 'max_items' => 2, 'display_order' => 1, 'is_active' => true]);
    $institution = Institution::query()->create(['code' => 'PAIRING', 'name' => 'Pairing fixture']);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id, 'code' => 'PAIRING-CYCLE', 'name' => 'Pairing cycle',
        'opens_on' => now()->subYear()->toDateString(), 'closes_on' => now()->addYear()->toDateString(),
    ]);
    $actor = User::factory()->create();
    foreach ([CapabilityCatalog::GUIDANCE_SCORING_OMR, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG] as $capability) {
        Permission::findOrCreate($capability, 'web');
        $actor->givePermissionTo($capability);
    }
    $actor->staffProfile()->create([
        'institution_id' => $institution->id, 'office' => 'guidance',
        'active_cycle_code' => $cycle->code, 'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'session_version' => 1,
        'must_change_password' => false,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $blocks = [];
    foreach ([1, 2] as $pageNo) {
        $blocks['Identity_'.$pageNo] = [
            'page_no' => $pageNo, 'fieldType' => 'QTYPE_INT', 'direction' => 'horizontal',
            'fieldLabels' => ['APPLICANT_NUMBER_1..6'], 'bubblesGap' => 65, 'labelsGap' => 80,
            'origin' => [220, 180], 'bubbleDimensions' => [50, 50],
        ];
        $blocks['Questions_'.$pageNo] = [
            'page_no' => $pageNo, 'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal',
            'fieldLabels' => ['GENERAL_Q_'.$pageNo], 'bubblesGap' => 70, 'labelsGap' => 80,
            'origin' => [220, 2100], 'bubbleDimensions' => [50, 50],
        ];
    }
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor, 'PAIRING-DOCUMENT', 'Two-page pairing', 'html', [
            'pages' => [['page_no' => 1, 'confirmed' => true], ['page_no' => 2, 'confirmed' => true]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'mapping' => ['field_blocks' => $blocks],
        ], OmrRegistrationMode::DocumentBound, OmrQrMode::Disabled, 2,
    );
    $saved = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, 0);
    $compiled = app(OmrCanonicalNativeBundleCompiler::class)->compile($saved->fresh(['assets']), null, [
        'registration_mode' => 'document_bound', 'qr_mode' => 'qr_disabled',
        'compiled_pages' => [['page_no' => 1], ['page_no' => 2]],
        'physical_inventory' => ['revision' => 1, 'sections' => array_map(static fn (int $page): array => [
            'printed_section_uid' => (string) Str::uuid(),
            'name' => 'Synthetic page '.$page,
            'page_no' => $page,
            'numbering' => ['start' => $page, 'step' => 1],
            'expected_item_count' => 1,
            'items' => [[
                'physical_item_uid' => (string) Str::uuid(),
                'page_no' => $page, 'printed_number' => $page,
                'native_label' => 'GENERAL_Q_'.$page, 'option_tokens' => ['A', 'B', 'C', 'D'],
                'guide_id' => null,
            ]],
        ], [1, 2])],
    ]);
    $version = $compiled['version'];
    $manifest = $version->authoring_manifest;
    $manifest['artifact_set'] = $saved->authoring_json['canonical']['artifact_set'];
    $version->forceFill(['authoring_manifest' => $manifest])->save();
    $booklet = OmrQuestionBooklet::query()->create(['code' => 'PAIRING-BOOKLET', 'name' => 'Pairing booklet', 'institution_id' => $institution->id, 'is_synthetic' => false]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id, 'template_version_id' => $version->id,
        'version' => 1, 'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => hash('sha256', 'pairing-booklet'),
    ]);
    $run = OmrDiagnosticRun::query()->create([
        'institution_id' => $institution->id, 'academic_cycle_id' => $cycle->id,
        'template_version_id' => $version->id, 'booklet_version_id' => $bookletVersion->id,
        'declared_set_code' => 'A',
        'revision' => 1, 'created_by_user_id' => $actor->id,
    ]);
    $set = app(StartOmrPendingCaptureSet::class)->forDiagnostic($run, $actor, (string) Str::uuid());
    $otherRun = OmrDiagnosticRun::query()->create([
        'institution_id' => $institution->id, 'academic_cycle_id' => $cycle->id,
        'template_version_id' => $version->id, 'booklet_version_id' => $bookletVersion->id,
        'declared_set_code' => 'B', 'revision' => 1, 'created_by_user_id' => $actor->id,
    ]);
    $otherRunCaptureUrl = route('setup.exam-materials.omr-test.runs.capture', $otherRun->public_id);
    $this->actingAs($actor)->post($otherRunCaptureUrl, omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $otherRun->public_id), [
        'image' => UploadedFile::fake()->image('foreign-run.png', 20, 20),
        'pending_capture_id' => $set->public_id,
    ]), ['Accept' => 'application/json'])->assertUnprocessable()
        ->assertJsonPath('code', 'omr_capture_set_page_target_mismatch');
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(0)
        ->and(OmrPendingCapturePageAnalysis::query()->count())->toBe(0);

    $port = new class($observations) implements OmrCaptureAnalysisPort
    {
        private array $proposalsByHash = [];

        public function __construct(private readonly array $observations) {}

        public function analyze(OmrCaptureAnalysisRequest $request): OmrPreparedCaptureEvidence
        {
            $input = $request->capture;
            $hash = $input->sourceHash();
            if (! array_key_exists($hash, $this->proposalsByHash)) {
                $this->proposalsByHash[$hash] = $this->observations[count($this->proposalsByHash)];
            }
            $proposal = $this->proposalsByHash[$hash];
            $success = new OmrDetectionSuccess(
                engineName: 'pairing-test', engineVersion: '1', engineCommit: null,
                inputHash: $hash, configHash: hash('sha256', 'config'),
                applicantNumberProposal: $proposal, applicantNumberConfidence: $proposal === null ? null : 1.0,
                identityFields: [], items: [], issues: [], sheetConfidence: null,
                registrationMode: $request->target->registrationMode()->value,
                templateVersionId: (int) $request->target->targetId(),
                expectedPageNo: $request->pageNo,
            );

            return new OmrPreparedCaptureEvidence(
                outcome: OmrPreparedCaptureEvidence::OUTCOME_SUCCEEDED, code: null, detail: null,
                pageNo: $request->pageNo, inputHash: $hash, acceptedOrientation: null,
                orientationDiagnostics: new OmrQuarterTurnDiagnostics([]), layout: null,
                diagnosticEvidence: null, target: ['id' => $request->target->targetId()],
                hashes: ['input' => $hash], assets: [], gates: null, markerMatches: null,
                fieldBlockShifts: null, items: [], identity: [], timings: [], issues: [],
                providerResult: OmrDetectionResult::succeeded($success),
            );
        }
    };
    app()->instance(OmrCaptureAnalysisPort::class, $port);
    $first = (string) UploadedFile::fake()->image('first.png', 20, 20)->get();
    $second = (string) UploadedFile::fake()->image('second.png', 21, 20)->get();
    $firstQuad = [
        'points' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        'width' => 20,
        'height' => 20,
        'orientation' => 1,
        'input_hash' => hash('sha256', $first),
        'page_no' => 1,
    ];
    $captureUrl = route('setup.exam-materials.omr-test.runs.capture', $run->public_id);
    $firstPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->createWithContent('first.png', $first),
        'client_quad' => $firstQuad,
    ]);
    $firstId = $firstPayload['client_capture_id'];
    $wrongModeSet = clone $set;
    $wrongModeSet->registration_mode = OmrRegistrationMode::FiducialAnchored->value;
    expect(fn () => app(CaptureOmrPendingPage::class)->handle(
        $wrongModeSet,
        $actor,
        1,
        1,
        (string) Str::uuid(),
        $first,
        'image/png',
        clientQuad: $firstQuad,
    ))->toThrow(RuntimeException::class, 'omr_capture_set_client_quad_not_allowed');
    $wrongPageQuad = $firstQuad;
    $wrongPageQuad['page_no'] = 2;
    $wrongPagePayload = $firstPayload;
    $wrongPagePayload['image'] = UploadedFile::fake()->createWithContent('first.png', $first);
    $wrongPagePayload['client_quad'] = $wrongPageQuad;
    $wrongPagePayload['page_no'] = 2;
    $this->post($captureUrl, $wrongPagePayload, ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post($captureUrl, $firstPayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.status', OmrPendingCaptureSet::STATUS_PENDING)
        ->assertJsonPath('context.pending_capture.revision', 2)
        ->assertJsonPath('context.pending_capture.active_page_no', 2)
        ->assertJsonPath('context.pending_capture.pages.0.detected_applicant_number', '123456');
    $firstEvidence = OmrPendingCapturePageEvidence::query()
        ->where('set_id', $set->id)
        ->where('client_capture_id', $firstId)
        ->firstOrFail();
    expect($firstEvidence->client_quad_json)->toBe($firstQuad);
    $replayedFirstPayload = $firstPayload;
    $replayedFirstPayload['image'] = UploadedFile::fake()->createWithContent('first.png', $first);
    $this->post($captureUrl, $replayedFirstPayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.revision', 2);
    $changedQuad = $firstQuad;
    $changedQuad['points'] = [[0.15, 0.1], [0.9, 0.1], [0.9, 0.9], [0.15, 0.9]];
    $changedHintReplay = $firstPayload;
    $changedHintReplay['image'] = UploadedFile::fake()->createWithContent('first.png', $first);
    $changedHintReplay['client_quad'] = $changedQuad;
    $this->post($captureUrl, $changedHintReplay, ['Accept' => 'application/json'])->assertStatus(409)
        ->assertJsonPath('code', 'omr_capture_set_idempotency_conflict');
    $staleQuad = $firstQuad;
    $staleQuad['input_hash'] = str_repeat('0', 64);
    $staleHintRequest = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->createWithContent('first.png', $first),
        'client_quad' => $staleQuad,
    ]);
    $this->post($captureUrl, $staleHintRequest, ['Accept' => 'application/json'])->assertUnprocessable();
    $secondPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->createWithContent('second.png', $second),
        'page_no' => 2,
    ]);
    $secondId = $secondPayload['client_capture_id'];
    $pairedResponse = $this->post($captureUrl, $secondPayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.status', $expectedStatus)
        ->assertJsonPath('context.pending_capture.revision', 4)
        ->assertJsonPath('context.pending_capture.pairing_code', $expectedCode)
        ->assertJsonPath('context.pending_capture.pages.0.detected_applicant_number', '123456')
        ->assertJsonPath('context.pending_capture.pages.1.detected_applicant_number', $observations[1]);
    expect(OmrPendingCapturePageAnalysis::query()->count())->toBe(2)
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and(OmrBlankInstancePageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
    $replayPayload = $secondPayload;
    $replayPayload['image'] = UploadedFile::fake()->createWithContent('second.png', $second);
    $this->post($captureUrl, $replayPayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.revision', 4);
    $staleSecondPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->image('stale.png'),
        'page_no' => 2,
        'pending_capture_revision' => 2,
    ]);
    $this->post($captureUrl, $staleSecondPayload, ['Accept' => 'application/json'])->assertStatus(409)
        ->assertJsonPath('code', 'omr_capture_set_revision_stale');
    $target = app(OmrPendingCaptureTarget::class)->forDiagnostic($run, $actor);
    expect($target['pairing_code'])->toBe($expectedCode)
        ->and($target['pages'][0]['detected_applicant_number'])->toBe('123456')
        ->and($target['pages'][1]['detected_applicant_number'])->toBe($observations[1])
        ->and($target['pages'][1]['capture_id'])->not->toBeNull();

    if ($expectedCode !== null) {
        $retake = (string) UploadedFile::fake()->image('retake.png', 22, 20)->get();
        $retakePayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
            'image' => UploadedFile::fake()->createWithContent('retake.png', $retake),
            'page_no' => 2,
            'replacement_reason' => 'Identity grid was unreadable',
        ]);
        $recovered = $this->post($captureUrl, $retakePayload, ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('context.pending_capture.status', OmrPendingCaptureSet::STATUS_READY)
            ->assertJsonPath('context.pending_capture.pairing_code', null)
            ->assertJsonPath('context.pending_capture.pages.1.capture_count', 2);
        expect($recovered->json('context.pending_capture.revision'))->toBe(6)
            ->and(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(3)
            ->and(OmrPendingCapturePageAnalysis::query()->count())->toBe(3)
            ->and(OmrCaptureSheet::query()->count())->toBe(0);
    }
})->with([
    'matching' => [['123456', '123456'], null, OmrPendingCaptureSet::STATUS_READY],
    'missing then retaken' => [['123456', null, '123456'], 'pairing_missing', OmrPendingCaptureSet::STATUS_CAPTURED],
    'conflicting then retaken' => [['123456', '654321', '123456'], 'pairing_conflict', OmrPendingCaptureSet::STATUS_CAPTURED],
]);
test('Kit-pinned geometry follows saved composition when intrinsic Set rows have no legacy aptitude area', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::current();
    $fixture = testKitAcceptanceFixture($actor, $institution);
    $kitContext = app(KitCompositionContext::class)->resolveVersion($fixture['version']);
    $set = $fixture['set']->fresh('items');

    expect($set->items->every(static fn ($item): bool => $item->aptitude_area_id === null))->toBeTrue();
    $kitContext['item_map']['bindings'] = array_reverse($kitContext['item_map']['bindings']);
    $kitContext['item_map']['composition_rows'] = array_reverse($kitContext['item_map']['composition_rows']);

    $pin = OmrPinnedSnapshot::pin(
        $fixture['sheet'],
        $fixture['booklet'],
        $set,
        $kitContext,
    );
    $bindingsByPosition = collect($kitContext['item_map']['bindings'])->keyBy('global_position');
    $rowsByLabel = collect($kitContext['item_map']['composition_rows'])->keyBy('native_label');

    expect($pin->items)->toHaveCount($bindingsByPosition->count());
    foreach ($pin->items as $item) {
        $binding = $bindingsByPosition->get($item->position);
        $row = $rowsByLabel->get($item->nativeLabel);
        expect($binding)->not->toBeNull()
            ->and($row)->not->toBeNull()
            ->and($item->physicalItemUid)->toBe($row['physical_item_uid'])
            ->and($item->nativeLabel)->toBe($binding['native_label'])
            ->and($item->pageNo)->toBe($binding['page_no'])
            ->and($item->position)->toBe($binding['global_position']);
    }
});
