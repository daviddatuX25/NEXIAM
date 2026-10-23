<?php

use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\ReplaceOmrSheetPage;
use App\Assessment\Actions\Omr\RetryOmrCaptureSheet;
use App\Assessment\Actions\Omr\RunOmrSheetAnalysis;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Assessment\Omr\Layout\OmrTemplateLayoutSolver;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrCaptureSheetPageRevision;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Foundation\AptitudeArea;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('omr page exposes sheet and per-page image urls with both route params', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'multipage-urls-'.uniqid());

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();
    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);
    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 2);
    $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('scoring.sessions.omr', $grading->examSession))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Omr/Capture')
            ->has('batch.sheets', 1)
            ->has('batch.sheets.0.pages', 2, fn ($pageProps) => $pageProps
                ->where('image_url', fn (string $url) => str_contains($url, '/sheets/'.$sheet->id.'/pages/'))
                ->etc()
            )
        );

    $this->actingAs($guidance)
        ->getJson(route('scoring.sessions.omr.batches.status', [
            'examSession' => $grading->examSession,
            'batch' => $batch,
        ]))
        ->assertOk()
        ->assertJsonPath('session_id', $grading->id)
        ->assertJsonPath('run_id', $batch->id)
        ->assertJsonPath('mode', 'production')
        ->assertJsonPath('progress.total', 2)
        ->assertJsonPath('progress.completed', 2)
        ->assertJsonPath('counts.total', 1)
        ->assertJsonPath('sheets.0.id', $sheet->id)
        ->assertJsonPath('sheets.0.pages.0.page_no', 1)
        ->assertJsonStructure([
            'revision',
            'observed_at',
            'stage',
            'counts',
            'actions',
            'needs_attention',
        ]);

    $this->actingAs($guidance)
        ->getJson(route('scoring.sessions.omr.batches.status', [
            'examSession' => $grading->examSession->id + 99999,
            'batch' => $batch,
        ]))
        ->assertNotFound();

    expect(fn () => app(ConfirmOmrCaptureBatch::class)->handle(
        $batch,
        $guidance,
        1,
    ))->toThrow(RuntimeException::class, 'changed after validation');

    $this->actingAs($guidance)
        ->get(route('scoring.sessions.omr', [
            'examSession' => $grading->examSession,
            'mode' => 'dry_run',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Omr/Capture')
            ->missing('execution_mode')
            ->has('roster', 1)
            ->has('batch.sheets', 1)
            ->where('run_status.mode', 'production')
        );
});

test('merge records per-page birthdate conflicts with value evidence', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $code = 'DOB-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);
    $bands = fn (string $prefix, int $start): array => [
        'PartA' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => [sprintf('%s_Q_%03d..%03d', $prefix, $start, $start + 24)], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [180, 900]],
        'PartB' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => [sprintf('%s_Q_%03d..%03d', $prefix, $start + 25, $start + 49)], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [620, 900]],
        'PartC' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => [sprintf('%s_Q_%03d..%03d', $prefix, $start + 50, $start + 74)], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [1060, 900]],
        'PartD' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => [sprintf('%s_Q_%03d..%03d', $prefix, $start + 75, $start + 99)], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [1500, 900]],
    ];
    $identity = [
        'ApplicantNumber' => ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['APPLICANT_NUMBER_1..10'], 'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [200, 260]],
        'Birthdate' => ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['BIRTHDATE_1..6'], 'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [1000, 260]],
    ];

    try {
        $templateJson = [
            'bubbleDimensions' => [56, 56],
            'pageDimensions' => [2060, 3144],
            'pages' => [
                ['page_no' => 1, 'pageDimensions' => [2060, 3144], 'fieldBlocks' => [...$identity, ...$bands('QUANT', 1)], 'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]]],
                ['page_no' => 2, 'pageDimensions' => [2060, 3144], 'fieldBlocks' => [...$identity, ...$bands('VERBAL', 1)], 'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]]],
            ],
        ];
        $draft = app(SaveAuthoredTemplateDraft::class)->handle($code, 'Merge birthdate demo', $templateJson, $guidance);
        $quant = AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $verbal = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
        $questions = [];
        foreach ([[$verbal, 1], [$quant, 101]] as [$area, $offset]) {
            foreach (range(1, (int) $area->max_items) as $i) {
                $questions[] = ['area_id' => $area->id, 'position' => $offset + $i - 1, 'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4]];
            }
        }
        $bkVersion = app(SaveAuthoredBookletVersion::class)->handle('DOB-BK-'.$code, 'Merge birthdate booklet', $questions, $guidance);
        $pair = app(ActivateOmrConfiguration::class)->handle($draft, $bkVersion, $guidance);

        $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $guidance, 'dob-conflict-'.uniqid());
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $setId = (string) Str::uuid();

        config(['omr.fake.birthdate_by_page' => [1 => '01012010', 2 => '02022010']]);
        app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);
        app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 2);

        $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->firstOrFail()->refresh();
        expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
            ->and($sheet->detected_birthdate)->toBe('01012010');

        $conflict = $sheet->issues()->where('issue_code', 'identity_unresolved')->get()
            ->first(fn ($issue) => isset($issue->evidence_json['per_page_birthdates']));
        expect($conflict)->not->toBeNull()
            ->and($conflict->evidence_json['per_page_birthdates'])
            ->toBe(['page_1' => '01012010', 'page_2' => '02022010'])
            ->and($conflict->evidence_json['per_page_applicant_numbers'])
            ->toBe(['page_1' => null, 'page_2' => null]);
    } finally {
        Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});

test('invalid merged birthdate is flagged as a review issue', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $code = 'DOB-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);

    try {
        $templateJson = [
            'bubbleDimensions' => [56, 56],
            'pageDimensions' => [2060, 3144],
            'pages' => [[
                'page_no' => 1,
                'pageDimensions' => [2060, 3144],
                'fieldBlocks' => [
                    'ApplicantNumber' => ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['APPLICANT_NUMBER_1..10'], 'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [200, 260]],
                    'Birthdate' => ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['BIRTHDATE_1..6'], 'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [1000, 260]],
                    'QuantPartA' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['QUANT_Q_001..025'], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [180, 900]],
                    'QuantPartB' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['QUANT_Q_026..050'], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [620, 900]],
                    'QuantPartC' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['QUANT_Q_051..075'], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [1060, 900]],
                    'QuantPartD' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['QUANT_Q_076..100'], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [1500, 900]],
                ],
                'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]],
            ]],
        ];
        $draft = app(SaveAuthoredTemplateDraft::class)->handle($code, 'Invalid birthdate demo', $templateJson, $guidance);
        $quant = AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $questions = [];
        foreach (range(1, (int) $quant->max_items) as $i) {
            $questions[] = ['area_id' => $quant->id, 'position' => $i, 'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4]];
        }
        $bkVersion = app(SaveAuthoredBookletVersion::class)->handle('DOB-BK-'.$code, 'Invalid birthdate booklet', $questions, $guidance);
        $pair = app(ActivateOmrConfiguration::class)->handle($draft, $bkVersion, $guidance);

        $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $guidance, 'dob-invalid-'.uniqid());
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $setId = (string) Str::uuid();

        config(['omr.fake.birthdate' => '13322010']);
        app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);

        $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->firstOrFail()->refresh();
        expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
            ->and($sheet->detected_birthdate)->toBe('13322010');

        $issue = $sheet->issues()->where('issue_code', 'birthdate_invalid')->firstOrFail();
        expect($issue->evidence_json['detected_birthdate'])->toBe('13322010');
    } finally {
        Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});

test('two-page capture merges into one reviewed sheet', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'multipage-'.uniqid());

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();

    // Page 2 first — arrival order must not matter.
    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 2);
    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);
    $sheet->refresh();

    expect((int) $sheet->expected_page_count)->toBe(2)
        ->and($sheet->pages()->count())->toBe(2)
        ->and($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($sheet->pages()->where('page_no', 1)->first()->status)->toBe(OmrCaptureSheetPage::STATUS_SUCCEEDED)
        ->and($sheet->pages()->where('page_no', 2)->first()->status)->toBe(OmrCaptureSheetPage::STATUS_SUCCEEDED)
        ->and(count($sheet->proposal_json['items'] ?? []))->toBe(200)
        ->and($sheet->proposal_json['pages'])->toBe(2);
});

test('duplicate page upload is refused', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'multipage-dup-'.uniqid());

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();

    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);

    expect(fn () => app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1))
        ->toThrow(RuntimeException::class, 'already uploaded');
});

test('incomplete two-page set never analyzes', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'multipage-half-'.uniqid());

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, (string) Str::uuid(), $guidance, null, 1);
    $sheet->refresh();

    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_PENDING_PAGES)
        ->and($sheet->processingRuns()->count())->toBe(0);
});

test('captured page can be replaced before the set is enqueued with immutable history', function (): void {
    Storage::fake('sensitive');
    Queue::fake();
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'multipage-replace-'.uniqid());
    $original = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $replacement = base64_decode('iVBORw0KGgoAAAACCAQAAABFaP0WAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $original, 'image/png', OmrCaptureSheet::MODE_AUTO, (string) Str::uuid(), $guidance, null, 1);
    $page = $sheet->pages()->where('page_no', 1)->firstOrFail();
    $oldPath = (string) $page->asset_path;
    $oldHmac = (string) $page->asset_hmac;

    app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        (int) $page->current_revision,
        $replacement,
        'image/png',
        $guidance,
        'The original page was misframed.',
        (string) Str::uuid(),
    );
    $page->refresh();

    expect($sheet->fresh()->status)->toBe(OmrCaptureSheet::STATUS_PENDING_PAGES)
        ->and((int) $page->current_revision)->toBe(2)
        ->and($page->revisions()->count())->toBe(2)
        ->and(Storage::disk('sensitive')->exists($oldPath))->toBeTrue()
        ->and(app(SensitiveAssetStore::class)->read($oldPath, $oldHmac))->toBe($original)
        ->and(Storage::disk('sensitive')->exists((string) $page->asset_path))->toBeTrue()
        ->and($page->currentRevision?->replacement_reason)->toBe('The original page was misframed.')
        ->and($page->crop_policy)->toBe('auto_document_quad');
});

test('accepted analysis page can be redone while enqueued and stale processing remains fenced', function (): void {
    Storage::fake('sensitive');
    Queue::fake();
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();
    $batch = app(CreateOmrCaptureBatch::class)->handle($grading, $guidance, 'multipage-replace-locked-'.uniqid());

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();
    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);
    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 2);
    $page = $sheet->pages()->where('page_no', 1)->firstOrFail();
    $sibling = $sheet->pages()->where('page_no', 2)->firstOrFail();
    $oldRun = $page->currentProcessingRun()->firstOrFail();
    $siblingRun = $sibling->currentProcessingRun()->firstOrFail();
    $oldPath = (string) $page->asset_path;
    $oldHmac = (string) $page->asset_hmac;

    $oldRun->forceFill([
        'status' => OmrProcessingRun::STATUS_CLAIMED,
        'claim_token' => (string) Str::uuid(),
        'claimed_at' => now(),
        'claim_expires_at' => now()->addMinute(),
        'attempts' => 1,
    ])->save();
    $page->forceFill(['status' => OmrCaptureSheetPage::STATUS_PROCESSING])->save();
    $sheet->forceFill(['status' => OmrCaptureSheet::STATUS_PROCESSING])->save();
    $reason = 'The accepted page was captured at an angle.';

    app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        (int) $page->current_revision,
        $bytes,
        'image/png',
        $guidance,
        $reason,
        (string) Str::uuid(),
    );

    $sheet->refresh();

    $page->refresh();
    $revisionTwoRun = $page->currentProcessingRun()->firstOrFail();
    $firstReplacementPath = (string) $page->asset_path;
    $firstReplacementHmac = (string) $page->asset_hmac;
    $revisionTwoProposal = [
        'input_hash' => (string) $revisionTwoRun->input_hash,
        'page_no' => 1,
        'items' => [],
    ];
    $revisionTwoRun->forceFill([
        'status' => OmrProcessingRun::STATUS_SUCCEEDED,
        'claim_token' => null,
        'claim_expires_at' => null,
        'output_json' => ['outcome' => 'succeeded'],
    ])->save();
    $page->forceFill([
        'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
        'proposal_json' => $revisionTwoProposal,
    ])->save();
    $analysisEvent = OmrCaptureSheetPageRevision::capture(
        $page,
        OmrCaptureSheetPageRevision::KIND_ANALYSIS,
        sourceRevisionId: (int) $page->current_revision_id,
        actorUserId: (int) $guidance->id,
    );
    $page->forceFill(['current_revision_id' => $analysisEvent->id])->save();

    $revisionTwoAnalysis = $analysisEvent;

    $lateRevisionTwoRun = $sheet->processingRuns()->create([
        'actor_id' => $guidance->id,
        'run_no' => ((int) $sheet->processingRuns()->max('run_no')) + 1,
        'run_uuid' => (string) Str::uuid(),
        'status' => OmrProcessingRun::STATUS_CLAIMED,
        'page_no' => 1,
        'page_revision' => 2,
        'provider_name' => $revisionTwoRun->provider_name,
        'provider_version' => $revisionTwoRun->provider_version,
        'provider_commit' => $revisionTwoRun->provider_commit,
        'request_version' => $revisionTwoRun->request_version,
        'expected_sheet_version' => (int) $sheet->version,
        'input_hash' => (string) $revisionTwoRun->input_hash,
        'template_hash' => $revisionTwoRun->template_hash,
        'booklet_hash' => $revisionTwoRun->booklet_hash,
        'native_bundle_hash' => $revisionTwoRun->native_bundle_hash,
        'manifest_hash' => $revisionTwoRun->manifest_hash,
        'config_hash' => $revisionTwoRun->config_hash,
        'patch_identity' => $revisionTwoRun->patch_identity,
        'claim_token' => (string) Str::uuid(),
        'claimed_at' => now(),
        'claim_expires_at' => now()->addMinute(),
        'attempts' => 1,
    ]);
    $page->forceFill([
        'status' => OmrCaptureSheetPage::STATUS_PROCESSING,
        'current_processing_run_id' => $lateRevisionTwoRun->id,
    ])->save();
    $sheet->forceFill(['status' => OmrCaptureSheet::STATUS_PROCESSING])->save();

    $secondReason = 'The second scan supersedes the analyzed revision.';
    app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        2,
        $replacement,
        'image/png',
        $guidance,
        $secondReason,
        (string) Str::uuid(),
    );

    $page->refresh();
    $newRun = $page->currentProcessingRun()->firstOrFail();
    $oldRun->refresh();
    $lateRevisionTwoRun->refresh();
    $siblingRun->refresh();
    app(RunOmrSheetAnalysis::class)->handle((int) $oldRun->id);
    app(RunOmrSheetAnalysis::class)->handle((int) $lateRevisionTwoRun->id);
    $oldRun->refresh();
    $lateRevisionTwoRun->refresh();

    expect($oldRun->status)->toBe(OmrProcessingRun::STATUS_SUPERSEDED)
        ->and($revisionTwoRun->fresh()->status)->toBe(OmrProcessingRun::STATUS_SUCCEEDED)
        ->and($lateRevisionTwoRun->status)->toBe(OmrProcessingRun::STATUS_SUPERSEDED)
        ->and($newRun->status)->toBe(OmrProcessingRun::STATUS_PENDING)
        ->and((int) $newRun->page_revision)->toBe(3)
        ->and((int) $page->current_revision)->toBe(3)
        ->and($page->proposal_json)->toBeNull()
        ->and((int) $siblingRun->expected_sheet_version)->toBe((int) $sheet->fresh()->version)
        ->and($revisionTwoAnalysis->proposal_json['input_hash'])->toBe((string) $revisionTwoRun->input_hash)
        ->and(Storage::disk('sensitive')->exists($oldPath))->toBeTrue()
        ->and(app(SensitiveAssetStore::class)->read($oldPath, $oldHmac))->toBe($bytes)
        ->and(Storage::disk('sensitive')->exists($firstReplacementPath))->toBeTrue()
        ->and(app(SensitiveAssetStore::class)->read($firstReplacementPath, $firstReplacementHmac))->toBe($bytes)
        ->and($page->currentRevision?->replacement_reason)->toBe($secondReason)
        ->and(app(SensitiveAssetStore::class)->read((string) $page->asset_path, (string) $page->asset_hmac))->toBe($replacement);

    $newRun->forceFill([
        'status' => OmrProcessingRun::STATUS_TERMINAL_FAILED,
        'diagnostic_code' => 'provider_failed',
    ])->save();
    $sheet->forceFill(['status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW])->save();
    $retryRun = app(RetryOmrCaptureSheet::class)->handle($sheet, $guidance, 1);

    expect($retryRun->status)->toBe(OmrProcessingRun::STATUS_PENDING)
        ->and((int) $retryRun->page_revision)->toBe(3)
        ->and((int) $page->fresh()->currentProcessingRun?->page_revision)->toBe(3);
});

test('solver-generated two-page bundle runs through capture and merge', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $code = 'SLV-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));

    $blueprint = [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 4,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'sections' => [
            ['area_code' => 'VERBAL', 'question_from' => 1, 'question_to' => 100, 'option_count' => 4, 'break_before' => null],
            ['area_code' => 'QUANT', 'question_from' => 1, 'question_to' => 100, 'option_count' => 4, 'break_before' => 'next_page'],
        ],
    ];

    try {
        $draft = app(SaveAuthoredTemplateDraft::class)->handle($code, 'Solver recognition sheet', [], $guidance, null, false, $blueprint);
        expect($draft->template_json_hash)->toBeString()
            ->and($draft->layout_solver_version)->toBe(OmrTemplateLayoutSolver::SOLVER_VERSION);

        $verbal = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
        $quant = AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $questions = [];
        foreach ([[$verbal, 1], [$quant, 101]] as [$area, $offset]) {
            foreach (range(1, (int) $area->max_items) as $i) {
                $questions[] = ['area_id' => $area->id, 'position' => $offset + $i - 1, 'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4]];
            }
        }
        $bk = app(SaveAuthoredBookletVersion::class)->handle('SLV-BK-'.$code, 'Solver recognition booklet', $questions, $guidance);
        $pair = app(ActivateOmrConfiguration::class)->handle($draft, $bk, $guidance);

        $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $guidance, 'solver-sheet-'.uniqid());
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $setId = (string) Str::uuid();
        app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);
        app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 2);

        $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->firstOrFail()->refresh();
        expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
            ->and($sheet->pages()->count())->toBe(2)
            ->and(count($sheet->proposal_json['items'] ?? []))->toBe(200)
            ->and($sheet->proposal_json['pages'])->toBe(2);
    } finally {
        Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});
