<?php

use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Assessment\Omr\OmrTemplateProjectionException;
use App\Assessment\Omr\Queries\OmrReviewGeometryQuery;
use App\Assessment\Omr\Review\GradingOmrReviewContextResolver;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Assessment\ScoreRecord;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OmrDiagnosticDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('canonical demo seeder recreates Draft 1 digital verification and non-qualifying rehearsal baseline', function (): void {
    config([
        'omr.qr-keyring.current' => 'dev-v1',
        'omr.qr-keyring.keys' => ['dev-v1' => 'canonical-seeder-test-key'],
    ]);
    $scoreCountBefore = ScoreRecord::query()->count();

    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    (new OmrDiagnosticDemoSeeder)->run();

    $draft = OmrTemplateAuthoringDraft::query()->where('code', 'CANONICAL-HTML')->firstOrFail();
    $draftOne = OmrTemplateAuthoringDraft::query()->orderBy('id')->firstOrFail();
    $digital = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draft->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->firstOrFail();
    $quick = OmrTemplateVerificationRun::query()
        ->whereHas('draft', fn ($query) => $query->where('code', 'like', 'CANONICAL-HTML-REHEARSAL%'))
        ->where('mode', OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->firstOrFail();
    $challenge = OmrPhysicalChallenge::query()->where('run_id', $quick->id)->firstOrFail();
    $sheet = $challenge->sheetAsset()->firstOrFail();
    $guide = $challenge->guideAsset()->firstOrFail();
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
        ->whereHas('bookletVersion.booklet', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-KEY'))
        ->with(['templateVersion', 'bookletVersion'])
        ->firstOrFail();
    $grading = GradingSession::query()
        ->whereHas('examSession', fn ($query) => $query->where('code', 'EX-OMR-CANONICAL'))
        ->firstOrFail();
    $instance = OmrBlankInstance::query()
        ->where('template_version_id', $pair->template_version_id)
        ->where('status', OmrBlankInstance::STATUS_PRINTED)
        ->with('pages')
        ->firstOrFail();
    $verification = (array) (($draft->authoring_json['verification'] ?? null) ?: []);
    $profiles = OmrMarkerProfile::query()->orderBy('code')->get();
    $physicalQualifiedVersionCount = OmrAnswerSheetTemplateVersion::query()->get()->filter(
        static fn (OmrAnswerSheetTemplateVersion $version): bool => data_get($version->authoring_manifest, 'physical_qualification.status') === 'physical_qualified',
    )->count();
    $activeSupplierVersionCount = OmrAnswerSheetTemplateVersion::query()
        ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->whereHas('template', fn ($query) => $query->where('is_synthetic', false))
        ->count();
    $activeSupplierPairCount = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('is_synthetic', false))
        ->count();

    $challengePageCount = (int) $quick->methods()
        ->where('method', $challenge->method)
        ->value('required_page_count');
    $calibrationPayload = app(OmrCalibrationQrPayload::class)->sign(
        $challenge->challenge_code,
        1,
        $challengePageCount,
    );
    $blankPayload = app(OmrQrPayload::class)->sign(
        $instance->instance_code,
        1,
        (int) $instance->page_count,
        $instance->signing_key_id,
    );
    $methods = $quick->methods()->with('pages')->orderBy('method')->get()->keyBy('method');
    $physicalCaptures = OmrTemplateVerificationCapture::query()->where('run_id', $quick->id)->orderBy('id')->get();
    $selectedPassIds = $methods->flatMap(fn ($method) => $method->pages->pluck('selected_attempt_id'))->filter()->values();
    $selectedVerificationCapture = $physicalCaptures->firstWhere('id', (int) $selectedPassIds->first());
    $verificationDemoEvidence = DB::table('omr_template_verification_item_evidence')
        ->where('capture_id', $selectedVerificationCapture?->id)
        ->orderBy('area_position')
        ->orderBy('question_no')
        ->get();
    $gradingBatch = OmrCaptureBatch::query()
        ->where('grading_session_id', $grading->id)
        ->where('status', OmrCaptureBatch::STATUS_OPEN)
        ->firstOrFail();
    $gradingSheet = $gradingBatch->sheets()
        ->where('client_capture_id', '00000000-0000-4000-8000-000000000131')
        ->firstOrFail();
    $gradingDemoItems = collect((array) ($gradingSheet->proposal_json['items'] ?? []))
        ->sortBy('position')
        ->values();
    $gradingStaleReview = OmrItemReviewRevision::query()
        ->where('capture_sheet_id', $gradingSheet->id)
        ->where('item_key', 'PROFILE_VERBAL_Q_7')
        ->firstOrFail();

    expect($draft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($draftOne->id)->toBe($draft->id)
        ->and($draftOne->code)->toBe('CANONICAL-HTML')
        ->and($verification['status'] ?? null)->toBe('passed')
        ->and($verification['seeded_demo'] ?? false)->toBeFalse()
        ->and($selectedPassIds)->toHaveCount(2)
        ->and($verificationDemoEvidence)->toHaveCount(150)
        ->and($verificationDemoEvidence->take(7)->pluck('state')->all())->toBe([
            'match',
            'mismatch',
            'missing',
            'ambiguous',
            'ambiguous',
            'unreadable',
            'match',
        ])
        ->and($verificationDemoEvidence->get(4)->expected)->toBeNull()
        ->and($gradingSheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($gradingSheet->proposal_json['envelope'] ?? null)->toBe(OmrDetectionRequest::ENVELOPE_VERSION)
        ->and($gradingSheet->proposal_json['demo_evidence']['contract'] ?? null)->toBe('omr-evidence-visual-review')
        ->and($gradingDemoItems)->toHaveCount(150)
        ->and($gradingDemoItems->take(7)->pluck('machine.state')->all())->toBe([
            'marked',
            'marked',
            'blank',
            'ambiguous',
            'ambiguous',
            'unreadable',
            'marked',
        ])
        ->and($gradingDemoItems->get(4)['machine']['expected_available'] ?? null)->toBeFalse()
        ->and(app(GradingOmrReviewContextResolver::class)->resolve($gradingSheet, 'PROFILE_VERBAL_Q_4')->optionSet)->toBe(['A', 'B', 'C', 'D'])
        ->and($gradingStaleReview->reviewed_machine_evidence_hash)
        ->not->toBe($gradingDemoItems->get(6)['machine']['evidence_hash'])
        ->and(DB::table('omr_template_verification_item_evidence')->whereIn('capture_id', $selectedPassIds->all())->count())->toBe(300)
        ->and($profiles)->toHaveCount(2)
        ->and($profiles->pluck('status')->all())->toBe([
            OmrMarkerProfile::STATUS_CANDIDATE,
            OmrMarkerProfile::STATUS_CANDIDATE,
        ])
        ->and($digital->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($digital->result_summary['threshold_status'] ?? null)->toBe('Unqualified')
        ->and($quick->mode)->toBe(OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->and($quick->status)->toBe(OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE)
        ->and($challenge->mode)->toBe(OmrPhysicalChallenge::MODE_QUICK)
        ->and($challenge->status)->toBe(OmrPhysicalChallenge::STATUS_PASSED)
        ->and($methods)->toHaveCount(2)
        ->and($methods['pre_shaded']->method_state)->toBe('passed')
        ->and($methods['hand_shaded']->method_state)->toBe('passed')
        ->and($physicalCaptures)->toHaveCount(4)
        ->and($physicalCaptures->where('attempt_state', 'failed'))->toHaveCount(2)
        ->and($physicalCaptures->where('attempt_state', 'passed'))->toHaveCount(2)
        ->and($physicalCaptures->where('accepted_orientation', 270))->toHaveCount(2)
        ->and($challenge->plan_hash)->toBe($quick->plan_hash)
        ->and($challenge->qr_payload_hash)->toBe(hash('sha256', $calibrationPayload))
        ->and($sheet->metadata['role'] ?? null)->toBe('challenge_sheet')
        ->and($sheet->metadata['sheet_hash'] ?? null)->toBeString()
        ->and($guide->metadata['role'] ?? null)->toBe('challenge_guide_pdf')
        ->and($guide->metadata['guide_mode'] ?? null)->toBe('quick')
        ->and($pair->isUsable())->toBeTrue()
        ->and($pair->templateVersion->native_bundle_hash)->not->toBeNull()
        ->and($pair->bookletVersion->answer_key_hash)->not->toBeNull()
        ->and($grading->status)->not->toBe(GradingSession::STATUS_FINALIZED)
        ->and($instance->template_version_id)->toBe($pair->template_version_id)
        ->and($instance->status)->toBe(OmrBlankInstance::STATUS_PRINTED)
        ->and($instance->pages)->toHaveCount($instance->page_count)
        ->and($instance->pages->first()->qr_payload_hash)->toBe(hash('sha256', $blankPayload))
        ->and(OmrTemplateVerificationRun::query()->where('mode', OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION)->count())->toBe(0)
        ->and($physicalQualifiedVersionCount)->toBe(0)
        ->and($activeSupplierVersionCount)->toBe(0)
        ->and($activeSupplierPairCount)->toBe(0)
        ->and($scoreCountBefore)->toBe(ScoreRecord::query()->count());

    $countsBefore = [
        'drafts' => OmrTemplateAuthoringDraft::query()->count(),
        'runs' => OmrTemplateVerificationRun::query()->count(),
        'challenges' => OmrPhysicalChallenge::query()->count(),
        'pairs' => OmrTemplateBookletPair::query()->count(),
        'instances' => OmrBlankInstance::query()->count(),
    ];
    $rehearsalIdentity = [
        'draft_id' => $draft->id,
        'digital_run_id' => $digital->id,
        'quick_run_id' => $quick->id,
        'challenge_id' => $challenge->id,
        'sheet_asset_id' => $sheet->id,
        'guide_asset_id' => $guide->id,
        'capture_ids' => $physicalCaptures->pluck('id')->all(),
    ];

    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    (new OmrDiagnosticDemoSeeder)->run();

    expect([
        'drafts' => OmrTemplateAuthoringDraft::query()->count(),
        'runs' => OmrTemplateVerificationRun::query()->count(),
        'challenges' => OmrPhysicalChallenge::query()->count(),
        'pairs' => OmrTemplateBookletPair::query()->count(),
        'instances' => OmrBlankInstance::query()->count(),
    ])->toBe($countsBefore);
    $draftAfter = OmrTemplateAuthoringDraft::query()->where('code', 'CANONICAL-HTML')->firstOrFail();
    $digitalAfter = OmrTemplateVerificationRun::query()
        ->where('draft_id', $draftAfter->id)
        ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
        ->firstOrFail();
    $quickAfter = OmrTemplateVerificationRun::query()
        ->whereHas('draft', fn ($query) => $query->where('code', 'like', 'CANONICAL-HTML-REHEARSAL%'))
        ->where('mode', OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
        ->firstOrFail();
    $challengeAfter = OmrPhysicalChallenge::query()
        ->where('run_id', $quickAfter->id)
        ->where('method', $challenge->method)
        ->firstOrFail();
    $captureIdsAfter = OmrTemplateVerificationCapture::query()
        ->where('run_id', $quickAfter->id)
        ->orderBy('id')
        ->pluck('id')
        ->all();
    expect([
        'draft_id' => $draftAfter->id,
        'digital_run_id' => $digitalAfter->id,
        'quick_run_id' => $quickAfter->id,
        'challenge_id' => $challengeAfter->id,
        'sheet_asset_id' => $challengeAfter->sheet_asset_id,
        'guide_asset_id' => $challengeAfter->guide_asset_id,
        'capture_ids' => $captureIdsAfter,
    ])->toBe($rehearsalIdentity);
});

test('canonical scanner reseed retains review history while replacing a stale pair', function (): void {
    config([
        'omr.qr-keyring.current' => 'dev-v1',
        'omr.qr-keyring.keys' => ['dev-v1' => 'canonical-seeder-test-key'],
    ]);
    $this->withoutVite();
    Storage::fake('sensitive');

    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    (new OmrDiagnosticDemoSeeder)->run();

    $oldPair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
        ->whereHas('bookletVersion.booklet', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-KEY'))
        ->with(['templateVersion.template', 'bookletVersion.questions.aptitudeArea'])
        ->firstOrFail();
    $oldVersion = $oldPair->templateVersion;
    $oldBooklet = $oldPair->bookletVersion;
    $oldArea = $oldVersion->areas()->orderBy('display_order')->firstOrFail();
    $oldInstance = OmrBlankInstance::query()
        ->where('template_version_id', $oldVersion->id)
        ->where('status', OmrBlankInstance::STATUS_PRINTED)
        ->with('pages')
        ->firstOrFail();
    $oldBatch = OmrCaptureBatch::query()
        ->where('grading_session_id', GradingSession::query()
            ->whereHas('examSession', fn ($query) => $query->where('code', 'EX-OMR-CANONICAL'))
            ->value('id'))
        ->where('template_version_id', $oldVersion->id)
        ->firstOrFail();
    $oldDemoSheet = $oldBatch->sheets()
        ->where('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->orderBy('id')
        ->firstOrFail();
    $oldDemoReview = OmrItemReviewRevision::query()
        ->where('capture_sheet_id', $oldDemoSheet->id)
        ->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $assetStore = app(SensitiveAssetStore::class);
    $bytes = 'stale-demo-capture';
    $sheetAssetPath = $assetStore->store($bytes, 'omr/demo/stale-sheet.img');
    $staleSheet = OmrCaptureSheet::query()->create([
        'batch_id' => $oldBatch->id,
        'status' => OmrCaptureSheet::STATUS_QUEUED,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'expected_page_count' => 1,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => $sheetAssetPath,
        'asset_hmac' => $assetStore->hmac($bytes),
        'asset_hmac_key_id' => 'omr-demo-v1',
        'asset_format_version' => 'v1',
        'asset_key_id' => 'omr-demo-v1',
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => strlen($bytes),
        'captured_by_user_id' => $guidance->id,
        'captured_at' => now(),
    ]);
    $pageAssetPath = $assetStore->store($bytes, 'omr/demo/stale-page.img');
    $stalePage = OmrCaptureSheetPage::query()->create([
        'sheet_id' => $staleSheet->id,
        'page_no' => 1,
        'status' => OmrCaptureSheetPage::STATUS_QUEUED,
        'asset_path' => $pageAssetPath,
        'asset_hmac' => $assetStore->hmac($bytes),
        'asset_hmac_key_id' => 'omr-demo-v1',
        'asset_key_id' => 'omr-demo-v1',
        'asset_format_version' => 'v1',
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => strlen($bytes),
    ]);
    $staleRun = $staleSheet->processingRuns()->create([
        'actor_id' => $guidance->id,
        'run_no' => 1,
        'run_uuid' => (string) Str::uuid(),
        'status' => OmrProcessingRun::STATUS_PENDING,
        'provider_name' => 'nexiam.fake',
        'provider_version' => '1',
        'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'expected_sheet_version' => 0,
        'input_hash' => hash('sha256', $bytes),
        'template_hash' => $oldVersion->native_bundle_hash,
        'booklet_hash' => $oldBooklet->answer_key_hash,
        'native_bundle_hash' => $oldVersion->native_bundle_hash,
        'manifest_hash' => $oldVersion->review_manifest_hash,
        'config_hash' => $oldVersion->config_hash,
        'patch_identity' => $oldVersion->patch_identity,
    ]);
    $staleSheet->forceFill(['current_processing_run_id' => $staleRun->id])->save();

    DB::table('omr_template_areas')
        ->where('id', $oldArea->id)
        ->update(['projection_manifest_hash' => 'legacy-stale']);
    $disk = Storage::disk('local');
    $orphanDir = 'omr-bundles/SYN-OMR-PROFILE-150/v3';
    foreach (['template.json', 'config.json', 'reference-page-1.svg'] as $name) {
        $disk->put(
            $orphanDir.'/'.$name,
            $disk->get('omr-bundles/SYN-OMR-PROFILE-150/v1/'.$name),
        );
    }
    $orphanManifest = json_decode(
        $disk->get('omr-bundles/SYN-OMR-PROFILE-150/v1/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $orphanManifest['template_version'] = 3;
    unset($orphanManifest['files']['reference-page-1.svg']);
    $disk->put($orphanDir.'/manifest.json', json_encode($orphanManifest, JSON_THROW_ON_ERROR));

    expect($disk->exists($orphanDir.'/manifest.json'))->toBeTrue();

    $scoreCountBefore = ScoreRecord::query()->count();

    (new OmrDiagnosticDemoSeeder)->run();
    expect($disk->exists($orphanDir.'/manifest.json'))->toBeFalse();

    $freshPair = OmrTemplateBookletPair::query()
        ->where('id', '>', $oldPair->id)
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
        ->whereHas('bookletVersion.booklet', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-KEY'))
        ->with(['templateVersion.template', 'bookletVersion.questions.aptitudeArea'])
        ->orderByDesc('id')
        ->firstOrFail();
    $freshVersion = $freshPair->templateVersion;
    $freshBooklet = $freshPair->bookletVersion;
    $freshBundle = NativeBundle::load('SYN-OMR-PROFILE-150', (int) $freshVersion->version);
    $items = app(OmrReviewGeometryQuery::class)->itemsFor($freshVersion, $freshBooklet);
    $projectionQuestion = $freshVersion->areas()
        ->whereHas('aptitudeArea', fn ($query) => $query->where('code', $items[0]['area_code']))
        ->with('questions.options')
        ->firstOrFail()
        ->questions()
        ->where('question_no', $items[0]['question_no'])
        ->with('options')
        ->firstOrFail();
    $expectedOptions = $projectionQuestion->options
        ->sortBy('display_order')
        ->values()
        ->map(static fn ($option): array => [
            'code' => (string) $option->code,
            'region' => [
                'x' => (float) $option->region_x,
                'y' => (float) $option->region_y,
                'w' => (float) $option->region_w,
                'h' => (float) $option->region_h,
            ],
        ])
        ->all();
    $freshBatch = OmrCaptureBatch::query()
        ->where('grading_session_id', $oldBatch->grading_session_id)
        ->where('template_version_id', $freshVersion->id)
        ->where('booklet_version_id', $freshBooklet->id)
        ->where('status', OmrCaptureBatch::STATUS_OPEN)
        ->firstOrFail();
    $freshDemoSheet = $freshBatch->sheets()
        ->where('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->orderBy('id')
        ->firstOrFail();
    $freshDemoReview = OmrItemReviewRevision::query()
        ->where('capture_sheet_id', $freshDemoSheet->id)
        ->firstOrFail();
    $freshInstance = OmrBlankInstance::query()
        ->where('template_version_id', $freshVersion->id)
        ->where('status', OmrBlankInstance::STATUS_PRINTED)
        ->with('pages')
        ->firstOrFail();
    $staleError = null;
    try {
        app(OmrReviewGeometryQuery::class)->itemsFor(
            $oldVersion->fresh(['template']),
            $oldBooklet->fresh(['questions.aptitudeArea']),
        );
    } catch (OmrTemplateProjectionException $exception) {
        $staleError = $exception->errorCode;
    }

    expect($freshVersion->id)->not->toBe($oldVersion->id)
        ->and($freshBooklet->id)->not->toBe($oldBooklet->id)
        ->and(OmrCaptureBatch::query()->whereKey($oldBatch->id)->value('status'))->toBe(OmrCaptureBatch::STATUS_REJECTED)
        ->and(OmrCaptureSheet::query()->whereKey($oldDemoSheet->id)->exists())->toBeTrue()
        ->and(OmrItemReviewRevision::query()->whereKey($oldDemoReview->id)->exists())->toBeTrue()
        ->and(OmrCaptureSheet::query()->whereKey($staleSheet->id)->exists())->toBeTrue()
        ->and(OmrCaptureSheetPage::query()->whereKey($stalePage->id)->exists())->toBeTrue()
        ->and(OmrProcessingRun::query()->whereKey($staleRun->id)->exists())->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($sheetAssetPath))->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($pageAssetPath))->toBeTrue()
        ->and(OmrCaptureBatch::query()
            ->where('grading_session_id', $oldBatch->grading_session_id)
            ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
            ->count())->toBe(1)
        ->and($freshDemoReview->id)->not->toBe($oldDemoReview->id)
        ->and(OmrBlankInstance::query()->whereKey($oldInstance->id)->where('template_version_id', $oldVersion->id)->exists())->toBeTrue()
        ->and($freshBatch->template_version_id)->toBe($freshVersion->id)
        ->and($freshBatch->booklet_version_id)->toBe($freshBooklet->id)
        ->and($freshInstance->template_version_id)->toBe($freshVersion->id)
        ->and($freshInstance->pages->first()->qr_payload_hash)->toBe(hash('sha256', app(OmrQrPayload::class)->sign(
            $freshInstance->instance_code,
            1,
            (int) $freshInstance->page_count,
            $freshInstance->signing_key_id,
        )))
        ->and($scoreCountBefore)->toBe(ScoreRecord::query()->count());
    expect($freshVersion->version)->toBe(2)
        ->and(array_keys($freshBundle->manifest['files']))->toBe([
            'template.json',
            'config.json',
            'reference-page-1.svg',
        ]);
    foreach ($freshBundle->manifest['files'] as $name => $hash) {
        expect($hash)->toBe($freshBundle->fileHashes[$name]);
    }

    foreach ($items as $item) {
        expect($item['options'])->toHaveCount(4)
            ->and(array_column($item['options'], 'code'))->toBe(['A', 'B', 'C', 'D']);
        foreach ($item['options'] as $option) {
            expect($option['region']['w'])->toBeGreaterThan(0.0)
                ->and($option['region']['h'])->toBeGreaterThan(0.0);
        }
    }
    $freshVersion->load(['pages', 'areas.questions.options']);
    $projectionHash = app(OmrTemplateGeometryProjector::class)->currentFor($freshVersion)->projectionHash;
    foreach ($freshVersion->pages as $page) {
        expect($page->page_manifest_hash)->toBe($projectionHash);
    }
    foreach ($freshVersion->areas as $area) {
        expect($area->projection_manifest_hash)->toBe($projectionHash);
        foreach ($area->questions as $question) {
            expect($question->projection_manifest_hash)->toBe($projectionHash);
            foreach ($question->options as $option) {
                expect($option->projection_manifest_hash)->toBe($projectionHash);
            }
        }
    }

    $response = $this->actingAs($guidance)
        ->get(route('scoring.sessions.omr', $oldBatch->gradingSession->examSession));
    expect($response->status())->toBe(200);

    $countsAfterRepair = [
        'drafts' => OmrTemplateAuthoringDraft::query()->count(),
        'runs' => OmrTemplateVerificationRun::query()->count(),
        'challenges' => OmrPhysicalChallenge::query()->count(),
        'pairs' => OmrTemplateBookletPair::query()->count(),
        'instances' => OmrBlankInstance::query()->count(),
        'batches' => OmrCaptureBatch::query()->count(),
    ];
    (new OmrDiagnosticDemoSeeder)->run();
    expect([
        'drafts' => OmrTemplateAuthoringDraft::query()->count(),
        'runs' => OmrTemplateVerificationRun::query()->count(),
        'challenges' => OmrPhysicalChallenge::query()->count(),
        'pairs' => OmrTemplateBookletPair::query()->count(),
        'instances' => OmrBlankInstance::query()->count(),
        'batches' => OmrCaptureBatch::query()->count(),
    ])->toBe($countsAfterRepair);
});
