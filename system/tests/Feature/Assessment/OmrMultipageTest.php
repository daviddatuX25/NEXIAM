<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;

test('omr page exposes sheet and per-page image urls with both route params', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'multipage-urls-'.uniqid(),
    );

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();
    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);
    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 2);
    $sheet = OmrCaptureSheet::query()->where('batch_id', $batch->id)->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('grading.sessions.omr', $grading))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Omr/Capture')
            ->has('batch.sheets', 1)
            ->has('batch.sheets.0.pages', 2, fn ($pageProps) => $pageProps
                ->where('image_url', fn (string $url) => str_contains($url, '/sheets/'.$sheet->id.'/pages/'))
                ->etc()
            )
        );
});

test('merge records per-page birthdate conflicts with value evidence', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
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
        $draft = app(\App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft::class)->handle($code, 'Merge birthdate demo', $templateJson, $guidance);
        $quant = \App\Models\Foundation\AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $verbal = \App\Models\Foundation\AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
        $questions = [];
        foreach ([[$verbal, 1], [$quant, 101]] as [$area, $offset]) {
            foreach (range(1, (int) $area->max_items) as $i) {
                $questions[] = ['area_id' => $area->id, 'position' => $offset + $i - 1, 'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4]];
            }
        }
        $bkVersion = app(\App\Assessment\Actions\Omr\SaveAuthoredBookletVersion::class)->handle('DOB-BK-'.$code, 'Merge birthdate booklet', $questions, $guidance);
        $pair = app(\App\Assessment\Actions\Omr\ActivateOmrConfiguration::class)->handle($draft, $bkVersion, $guidance);

        $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $guidance, (int) $pair->template_version_id, (int) $pair->booklet_version_id, 'dob-conflict-'.uniqid());
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
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});

test('invalid merged birthdate is flagged as a review issue', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
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
        $draft = app(\App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft::class)->handle($code, 'Invalid birthdate demo', $templateJson, $guidance);
        $quant = \App\Models\Foundation\AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $questions = [];
        foreach (range(1, (int) $quant->max_items) as $i) {
            $questions[] = ['area_id' => $quant->id, 'position' => $i, 'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4]];
        }
        $bkVersion = app(\App\Assessment\Actions\Omr\SaveAuthoredBookletVersion::class)->handle('DOB-BK-'.$code, 'Invalid birthdate booklet', $questions, $guidance);
        $pair = app(\App\Assessment\Actions\Omr\ActivateOmrConfiguration::class)->handle($draft, $bkVersion, $guidance);

        $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $guidance, (int) $pair->template_version_id, (int) $pair->booklet_version_id, 'dob-invalid-'.uniqid());
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
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});




test('two-page capture merges into one reviewed sheet', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'multipage-'.uniqid(),
    );

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
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'multipage-dup-'.uniqid(),
    );

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();

    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);

    expect(fn () => app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1))
        ->toThrow(RuntimeException::class, 'already uploaded');
});

test('incomplete two-page set never analyzes', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'multipage-half-'.uniqid(),
    );

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, (string) Str::uuid(), $guidance, null, 1);
    $sheet->refresh();

    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_PENDING_PAGES)
        ->and($sheet->processingRuns()->count())->toBe(0);
});

test('captured page can be replaced before the set is enqueued', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'multipage-replace-'.uniqid(),
    );

    $original = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $replacement = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAQAAABFaP0WAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $original, 'image/png', OmrCaptureSheet::MODE_AUTO, (string) Str::uuid(), $guidance, null, 1);
    $page = $sheet->pages()->where('page_no', 1)->firstOrFail();
    $oldPath = (string) $page->asset_path;

    app(\App\Assessment\Actions\Omr\ReplaceOmrSheetPage::class)->handle($sheet, 1, $replacement, 'image/png', $guidance);
    $page->refresh();

    expect($page->asset_path)->not->toBe($oldPath)
        ->and((int) $page->original_bytes)->toBe(strlen($replacement))
        ->and(Storage::disk('sensitive')->exists($page->asset_path))->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($oldPath))->toBeFalse()
        ->and(DB::table('audit_events')->where('event_type', 'grading.omr_sheet_page_replaced')->exists())->toBeTrue();
});

test('replacement is refused once the set is enqueued', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'multipage-replace-locked-'.uniqid(),
    );

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();
    app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);
    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 2);

    expect(in_array($sheet->status, [OmrCaptureSheet::STATUS_QUEUED, OmrCaptureSheet::STATUS_PROCESSING, OmrCaptureSheet::STATUS_NEEDS_REVIEW], true))->toBeTrue()
        ->and(fn () => app(\App\Assessment\Actions\Omr\ReplaceOmrSheetPage::class)->handle($sheet, 1, $bytes, 'image/png', $guidance))
        ->toThrow(RuntimeException::class, 'not-yet-enqueued');
});

test('solver-generated two-page bundle runs through capture and merge', function (): void {
    Storage::fake('sensitive');
    $fixture = buildGradingFixture($this);
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
        $draft = app(\App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft::class)->handle($code, 'Solver recognition sheet', [], $guidance, null, false, $blueprint);
        expect($draft->template_json_hash)->toBeString()
            ->and($draft->layout_solver_version)->toBe(\App\Assessment\Omr\Layout\OmrTemplateLayoutSolver::SOLVER_VERSION);

        $verbal = \App\Models\Foundation\AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
        $quant = \App\Models\Foundation\AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $questions = [];
        foreach ([[$verbal, 1], [$quant, 101]] as [$area, $offset]) {
            foreach (range(1, (int) $area->max_items) as $i) {
                $questions[] = ['area_id' => $area->id, 'position' => $offset + $i - 1, 'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4]];
            }
        }
        $bk = app(\App\Assessment\Actions\Omr\SaveAuthoredBookletVersion::class)->handle('SLV-BK-'.$code, 'Solver recognition booklet', $questions, $guidance);
        $pair = app(\App\Assessment\Actions\Omr\ActivateOmrConfiguration::class)->handle($draft, $bk, $guidance);

        $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $guidance, (int) $pair->template_version_id, (int) $pair->booklet_version_id, 'solver-sheet-'.uniqid());
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
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});
