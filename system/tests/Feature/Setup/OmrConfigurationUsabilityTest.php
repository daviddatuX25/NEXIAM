<?php

use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\CopyBookletVersionToDraft;
use App\Assessment\Actions\Omr\RegisterNativeBundle;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\AptitudeArea;
use App\Printing\BundleRenderer;
use App\Printing\RenderBundlePdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

test('template preview returns the shared-renderer PDF with private headers', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $clerk = App\Models\User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $version = OmrAnswerSheetTemplateVersion::query()
        ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->where('version', 4)
        ->whereHas('template', fn ($query) => $query->where('code', 'SYN-OMR-ONE-PAGE'))
        ->firstOrFail();

    $response = $this->actingAs($guidance)
        ->get(route('setup.omr.templates.preview', $version));
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs($clerk)
        ->get(route('setup.omr.templates.preview', $version))
        ->assertForbidden();
});

test('preview-draft renders unsaved geometry without persisting', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $templateJson = [
        'bubbleDimensions' => [56, 56],
        'pageDimensions' => [2060, 3144],
        'pages' => [[
            'page_no' => 1,
            'pageDimensions' => [2060, 3144],
            'fieldBlocks' => [
                'ApplicantNumber' => [
                    'fieldType' => 'QTYPE_INT',
                    'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
                    'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [200, 260],
                ],
            ],
            'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]],
        ]],
    ];

    $response = $this->actingAs($guidance)
        ->post(route('setup.omr.templates.preview_draft'), [
            'template_json' => json_encode($templateJson),
        ]);
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and(OmrAnswerSheetTemplateVersion::query()->where('template_json_hash', hash('sha256', json_encode($templateJson)))->exists())->toBeFalse();

    $this->actingAs($guidance)
        ->post(route('setup.omr.templates.preview_draft'), ['template_json' => '{not-json'])
        ->assertStatus(422);
});

test('overlays render identically through renderer and are render-only', function (): void {
    $overlay = [
        'title' => 'DEMO EXAM SHEET',
        'header_lines' => ['EX-DEMO-1 · MAIN HALL 101'],
        'footer_lines' => ['Shade completely.'],
        'name_box_label' => 'EXAMINEE (PRINT):',
        'per_page' => [['page_no' => 1, 'note' => 'Answer only in this booklet.']],
    ];

    $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', 4);
    $html = app(BundleRenderer::class)->renderHtml($bundle->template, ['overlay' => $overlay]);

    expect($html)->toContain('DEMO EXAM SHEET')
        ->and($html)->toContain('EX-DEMO-1 · MAIN HALL 101')
        ->and($html)->toContain('Shade completely.')
        ->and($html)->toContain('Answer only in this booklet.')
        ->and($html)->not->toContain('SYNTHETIC NEXIAM OMR ANSWER SHEET');

    // Margin-safety rule: a full header stack uses the safe upper-right
    // fallback instead of suppressing the manual name field.
    expect($html)->toContain('EXAMINEE (PRINT):')
        ->and($html)->toContain('left:155.6mm;top:29.5mm;width:50mm;height:8mm;border:0.3mm solid #000;');
    // The frame must clear the full header stack instead of using the raw 80px top inset.
    expect($html)->not->toContain('left:8.4mm;top:8.4mm;width:199.2mm;height:313.2mm;border:0.7mm solid #000;')
        ->and($html)->toMatch('/left:8\.4mm;top:2[23]\.\d+mm;width:199\.2mm;height:298\.\d+mm;border:0.7mm solid #000/');

    // A lower field block keeps the preferred left-band placement.
    // The same label remains available when the top band is otherwise clear.
    $lowered = $bundle->template;
    $lowered['pages'][0]['fieldBlocks']['ApplicantNumber']['origin'] = [200, 520];
    $sparseHtml = app(BundleRenderer::class)->renderHtml($lowered, [
        'overlay' => ['name_box_label' => 'EXAMINEE (PRINT):'],
    ]);
    expect($sparseHtml)->toContain('EXAMINEE (PRINT):');

    // Null overlay = today's defaults.
    $defaultHtml = app(BundleRenderer::class)->renderHtml($bundle->template);
    expect($defaultHtml)->toContain('SYNTHETIC NEXIAM OMR ANSWER SHEET');

    // The service renders PDF bytes for the same input (preview == print).
    $bytes = app(RenderBundlePdf::class)->renderTemplate($bundle->template, $overlay);
    expect(substr($bytes, 0, 4))->toBe('%PDF');
});

test('overlay save edits drafts in place and copies active versions to a draft', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $code = 'OVL-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);

    $templateJson = [
        'bubbleDimensions' => [56, 56],
        'pageDimensions' => [2060, 3144],
        'pages' => [[
            'page_no' => 1,
            'pageDimensions' => [2060, 3144],
            'fieldBlocks' => [
                'ApplicantNumber' => ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['APPLICANT_NUMBER_1..10'], 'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [200, 260]],
                'QuantPart' => ['fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['QUANT_Q_001..010'], 'bubblesGap' => 66, 'labelsGap' => 80, 'origin' => [180, 900]],
            ],
            'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]],
        ]],
    ];

    try {
        $draft = app(SaveAuthoredTemplateDraft::class)->handle($code, 'Overlay demo', $templateJson, $guidance);

        // Draft: overlay updates in place without a new version.
        $this->actingAs($guidance)
            ->post(route('setup.omr.templates.overlay', $draft), [
                'version_id' => $draft->id,
                'overlay' => ['title' => 'EDITED TITLE', 'header_lines' => ['HDR'], 'footer_lines' => [], 'name_box_label' => '', 'per_page' => []],
            ])
            ->assertRedirect();
        expect($draft->refresh()->print_overlay_json['title'])->toBe('EDITED TITLE')
            ->and($draft->refresh()->version)->toBe($draft->version);

        // Activate $draft using a matching booklet so it becomes active.
        $quant = AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $bkVersion = app(SaveAuthoredBookletVersion::class)->handle('OVL-BK-'.$code, 'Overlay booklet', [[
            'area_id' => $quant->id, 'position' => 1, 'correct_option_code' => 'A',
        ]], $guidance);
        // Mock scorable area count on template question or pair directly
        $draft->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE, 'activated_by_user_id' => $guidance->id, 'activated_at' => now()]);

        // Active: overlay save copies the bundle into a new draft.
        $this->actingAs($guidance)
            ->post(route('setup.omr.templates.overlay', $draft), [
                'version_id' => $draft->id,
                'overlay' => ['title' => 'COPIED TITLE', 'header_lines' => ['H2'], 'footer_lines' => [], 'name_box_label' => '', 'per_page' => []],
            ])
            ->assertRedirect();

        $newDraft = OmrAnswerSheetTemplateVersion::query()
            ->where('template_id', $draft->template_id)
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_DRAFT)
            ->where('print_overlay_json->title', 'COPIED TITLE')
            ->orderByDesc('version')
            ->first();
        expect($newDraft)->not->toBeNull()
            ->and($newDraft->id)->not->toBe($draft->id);
    } finally {
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('omr-bundles/OVL-BK-'.$code);
    }
});

test('booklet copy duplicates the key into a draft and is audited', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $areas = AptitudeArea::query()->orderBy('id')->limit(1)->get();

    $code = 'COPY-BK-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $version = app(SaveAuthoredBookletVersion::class)->handle($code, 'Copy demo', [
        ['area_id' => $areas[0]->id, 'position' => 1, 'correct_option_code' => 'A'],
        ['area_id' => $areas[0]->id, 'position' => 2, 'correct_option_code' => 'B'],
    ], $guidance);
    $version->update(['status' => OmrQuestionBookletVersion::STATUS_ACTIVE, 'activated_by_user_id' => $guidance->id, 'activated_at' => now()]);

    $copy = app(CopyBookletVersionToDraft::class)->handle($version, $guidance);

    expect($copy->id)->not->toBe($version->id)
        ->and($copy->status)->toBe(OmrQuestionBookletVersion::STATUS_DRAFT)
        ->and($copy->questions()->count())->toBe(2)
        ->and($copy->answer_key_hash)->toBe($version->answer_key_hash)
        ->and(DB::table('audit_events')->where('event_type', 'grading.omr_booklet_copied')->exists())->toBeTrue();

    // A second copy is refused while a draft exists.
    expect(fn () => app(CopyBookletVersionToDraft::class)->handle($version, $guidance))
        ->toThrow(RuntimeException::class, 'draft already exists');
});

test('bulk key entry parses rows and reports line numbers', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $bulk = "# key for demo\n1 VERBAL A\n2 VERBAL B\n3 VERBAL C\n4 VERBAL D\n";
    $this->actingAs($guidance)
        ->post(route('setup.omr.booklets.store'), [
            'code' => 'BULK-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8)),
            'name' => 'Bulk demo',
            'questions' => [],
            'bulk' => $bulk,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $booklet = OmrQuestionBooklet::query()->where('name', 'Bulk demo')->firstOrFail();
    expect($booklet->versions()->first()->questions()->count())->toBe(4);

    $this->actingAs($guidance)
        ->post(route('setup.omr.booklets.store'), [
            'code' => 'BULK-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8)),
            'name' => 'Bulk bad',
            'questions' => [],
            'bulk' => "1 VERBAL A\n2 NOPE B\n3 VERBAL Z\n",
        ])
        ->assertSessionHasErrors('omr_booklet');
});

test('builder hydrates drafts and refuses activated versions', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $draft = OmrAnswerSheetTemplateVersion::query()
        ->where('status', OmrAnswerSheetTemplateVersion::STATUS_DRAFT)
        ->first();
    if ($draft === null) {
        $this->markTestSkipped('No draft version seeded for builder hydration.');
    }

    $response = $this->actingAs($guidance)->get(route('setup.omr.templates.builder', ['version' => $draft->id]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/OmrConfiguration/Templates/Builder')
            ->where('draft_version.id', $draft->id));

    $active = OmrAnswerSheetTemplateVersion::query()
        ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->firstOrFail();
    $response = $this->actingAs($guidance)->get(route('setup.omr.templates.builder', ['version' => $active->id]));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('draft_refusal', 'Only draft versions can be edited. Open the show page to preview activated versions.'));
});

test('birthdate grid derives and projects identity regions at activation', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $code = 'DOB-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);

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

    try {
        $draft = app(SaveAuthoredTemplateDraft::class)->handle($code, 'Birthdate demo', $templateJson, $guidance);

        // Activation with a matching booklet persists birthdate identity regions.
        $quant = AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $booklet = OmrQuestionBooklet::query()->firstOrCreate(['code' => 'DOB-BK-'.$code], ['name' => 'Birthdate booklet']);
        $bkVersion = app(SaveAuthoredBookletVersion::class)->handle('DOB-BK-'.$code, 'Birthdate booklet', array_map(
            fn ($i) => ['area_id' => $quant->id, 'position' => $i, 'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4]],
            range(1, (int) $quant->max_items),
        ), $guidance);

        $pair = app(ActivateOmrConfiguration::class)->handle($draft, $bkVersion, $guidance);
        expect($pair->status)->toBe('active');

        $regions = $draft->refresh()->identity_regions_json;
        expect(isset($regions[1]['applicant_number']))->toBeTrue()
            ->and(isset($regions[1]['birthdate']))->toBeTrue()
            ->and($regions[1]['birthdate']['x'])->toBeGreaterThan(0.05);

        // Six positions required; fewer fail derivation.
        $bad = $templateJson;
        $bad['pages'][0]['fieldBlocks']['Birthdate']['fieldLabels'] = ['BIRTHDATE_1..5'];
        expect(fn () => app(SaveAuthoredTemplateDraft::class)->handle('DOB-BAD-'.$code, 'Bad birthdate', $bad, $guidance))
            ->toThrow(RuntimeException::class, 'six positions');
    } finally {
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});

test('blueprint save solves server-side and persists layout metadata', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $code = 'LAY-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));

    $blueprint = [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 4,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'sections' => [['area_code' => 'VERBAL', 'question_from' => 1, 'question_to' => 100, 'option_count' => 4, 'break_before' => null]],
    ];

    $solved = app(\App\Assessment\Actions\Omr\SolveOmrTemplateLayout::class)->handle($blueprint);

    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        $code,
        'Blueprint demo',
        [],
        $guidance,
        null,
        false,
        $blueprint,
    );

    expect($version->layout_blueprint_json)->not->toBeNull()
        ->and($version->layout_blueprint_hash)->toBe($solved->blueprintHash)
        ->and($version->layout_solver_version)->toBe(\App\Assessment\Omr\Layout\OmrTemplateLayoutSolver::SOLVER_VERSION)
        ->and($version->template_json_hash)->toBe($solved->templateJsonHash)
        ->and($version->native_bundle_json['template']['pages'])->toHaveCount(1);

    $blocks = $version->native_bundle_json['template']['pages'][0]['fieldBlocks'];
    $questionLabels = array_values(array_filter($blocks, fn (array $b): bool => ($b['fieldType'] ?? null) === 'QTYPE_MCQ4'));
    expect($questionLabels[0]['fieldLabels'][0])->toBe('VERBAL_Q_001..025');

    // The staged bundle is readable through the durable store.
    $loaded = \App\Assessment\Omr\NativeBundle::load($code, 1);
    expect($loaded->template['pages'])->toHaveCount(1);
});

test('layout preview hash equals saved hash and preview never persists', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $code = 'LAY-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));

    $blueprint = [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 3,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'sections' => [
            ['area_code' => 'VERBAL', 'question_from' => 1, 'question_to' => 30, 'option_count' => 4, 'break_before' => null],
        ],
    ];

    $before = OmrAnswerSheetTemplateVersion::query()->count();

    $preview = $this->actingAs($guidance)
        ->postJson(route('setup.omr.layout.preview'), ['layout_blueprint_json' => json_encode($blueprint)])
        ->assertOk()
        ->json();
    expect($preview['layout']['page_count'])->toBe(1)
        ->and($preview['diagnostics'])->toBe([])
        ->and($preview['template_json_hash'])->toBeString();

    $this->actingAs($guidance)
        ->postJson(route('setup.omr.templates.preview_draft'), ['layout_blueprint_json' => json_encode($blueprint)])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(OmrAnswerSheetTemplateVersion::query()->count())->toBe($before); // previews never persist

    $version = app(SaveAuthoredTemplateDraft::class)->handle($code, 'Preview parity demo', [], $guidance, null, false, $blueprint);
    expect($version->template_json_hash)->toBe($preview['template_json_hash']);
});

test('layout preview returns typed diagnostics for an unsolvable blueprint', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $blueprint = [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 2,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'sections' => [
            ['area_code' => 'VERBAL', 'question_from' => 1, 'question_to' => 60, 'option_count' => 4, 'break_before' => null],
            ['area_code' => 'VERBAL', 'question_from' => 41, 'question_to' => 80, 'option_count' => 4, 'break_before' => null],
        ],
    ];

    $this->actingAs($guidance)
        ->postJson(route('setup.omr.layout.preview'), ['layout_blueprint_json' => json_encode($blueprint)])
        ->assertStatus(422)
        ->assertJsonPath('diagnostics.0.code', 'duplicate_question_range');
});
