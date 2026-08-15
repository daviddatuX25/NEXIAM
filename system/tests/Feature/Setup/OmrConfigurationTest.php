<?php

use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\RegisterNativeBundle;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Foundation\AptitudeArea;

test('bundle intake registers a draft version in the canonical store', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $code = 'REAL-TEST-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);

    try {
        $base = base_path('app/Assessment/Omr/NativeBundles/SYN-OMR-ONE-PAGE/v3');
        $files = [
            'template.json' => file_get_contents($base.'/template.json'),
            'config.json' => file_get_contents($base.'/config.json'),
            'manifest.json' => file_get_contents($base.'/manifest.json'),
            'reference-page-1.svg' => file_get_contents($base.'/reference-page-1.svg'),
            'reference-page-2.svg' => file_get_contents($base.'/reference-page-2.svg'),
        ];

        $manifest = json_decode($files['manifest.json'], true);
        $manifest['template_code'] = $code;
        $files['manifest.json'] = json_encode($manifest, JSON_UNESCAPED_SLASHES);

        $version = app(RegisterNativeBundle::class)->handle($code, $files, $guidance);

        expect($version->status)->toBe('draft')
            ->and($version->version)->toBe(1)
            ->and($version->engine_name)->toBe('OMRChecker')
            ->and(is_dir($bundleDir.'/v1'))->toBeTrue();

        // duplicate registration is refused
        expect(fn () => app(RegisterNativeBundle::class)->handle($code, $files, $guidance))
            ->toThrow(RuntimeException::class, 'already exists');
    } finally {
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('omr-bundles/'.$code);
    }
});

test('authored booklet questions activate instead of being key-generated', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $code = 'REAL-TEST-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);

    try {
        // Intake a real bundle as a fresh draft template version.
        $base = base_path('app/Assessment/Omr/NativeBundles/SYN-OMR-ONE-PAGE/v3');
        $files = [
            'template.json' => file_get_contents($base.'/template.json'),
            'config.json' => file_get_contents($base.'/config.json'),
            'manifest.json' => file_get_contents($base.'/manifest.json'),
            'reference-page-1.svg' => file_get_contents($base.'/reference-page-1.svg'),
            'reference-page-2.svg' => file_get_contents($base.'/reference-page-2.svg'),
        ];
        $manifest = json_decode($files['manifest.json'], true);
        $manifest['template_code'] = $code;
        $files['manifest.json'] = json_encode($manifest, JSON_UNESCAPED_SLASHES);

        $templateVersion = app(RegisterNativeBundle::class)->handle($code, $files, $guidance);
        expect($templateVersion->status)->toBe('draft');

        // Author a booklet draft with complete keys (global positions 1..200).
        $booklet = App\Models\Assessment\OmrQuestionBooklet::query()->create([
            'code' => 'REAL-BOOKLET-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 6)),
            'name' => 'Authored demo booklet',
        ]);
        $draft = App\Models\Assessment\OmrQuestionBookletVersion::query()->create([
            'booklet_id' => $booklet->id,
            'version' => 1,
            'status' => 'draft',
            'answer_key_hash' => hash('sha256', 'authored-key'),
        ]);

        $areas = AptitudeArea::query()->where('is_active', true)->orderBy('code')->get();
        $position = 1;
        foreach ($areas as $area) {
            for ($i = 1; $i <= (int) $area->max_items; $i++) {
                $draft->questions()->create([
                    'aptitude_area_id' => $area->id,
                    'question_position' => $position++,
                    'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4],
                ]);
            }
        }

        $activated = app(ActivateOmrConfiguration::class)->handle($templateVersion, $draft, $guidance);

        expect($activated->status)->toBe('active')
            ->and($draft->fresh()->questions()->count())->toBe(200)
            ->and($draft->fresh()->questions()->whereNull('template_question_id')->count())->toBe(0)
            ->and($draft->fresh()->questions()->where('correct_option_code', 'B')->count())->toBe(50);
    } finally {
        exec('rm -rf '.escapeshellarg($bundleDir));
    }
});

test('authored booklet save validates contiguity, digests the key, and edits drafts in place', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $areas = AptitudeArea::query()->orderBy('id')->limit(2)->get();

    $questions = [
        ['area_id' => $areas[0]->id, 'position' => 1, 'correct_option_code' => 'A'],
        ['area_id' => $areas[1]->id, 'position' => 2, 'correct_option_code' => 'B'],
    ];

    $code = 'BKLT-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));

    $version = app(\App\Assessment\Actions\Omr\SaveAuthoredBookletVersion::class)
        ->handle($code, 'Authored booklet', $questions, $guidance);

    expect($version->status)->toBe('draft')
        ->and($version->questions()->count())->toBe(2)
        ->and($version->answer_key_hash)->toHaveLength(64)
        ->and($version->answer_key_hash)->toBe(hash('sha256', "1|{$areas[0]->id}|A\n2|{$areas[1]->id}|B"));

    // gaps are refused
    expect(fn () => app(\App\Assessment\Actions\Omr\SaveAuthoredBookletVersion::class)
        ->handle($code, 'Authored booklet', [
            ['area_id' => $areas[0]->id, 'position' => 1, 'correct_option_code' => 'A'],
            ['area_id' => $areas[0]->id, 'position' => 3, 'correct_option_code' => 'B'],
        ], $guidance))
        ->toThrow(RuntimeException::class, 'contiguous');

    // editing the draft rewrites the same version instead of appending
    $edited = app(\App\Assessment\Actions\Omr\SaveAuthoredBookletVersion::class)
        ->handle($code, 'Authored booklet', [
            ['area_id' => $areas[0]->id, 'position' => 1, 'correct_option_code' => 'C'],
        ], $guidance);

    expect($edited->version)->toBe($version->version)
        ->and($edited->questions()->count())->toBe(1);
});

test('retirement flips pairs, cascades through versions, and audits', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->firstOrFail();

    app(\App\Assessment\Actions\Omr\RetireOmrConfiguration::class)
        ->handle('pair', $pair->id, $guidance);

    expect($pair->refresh()->status)->toBe(OmrTemplateBookletPair::STATUS_RETIRED)
        ->and(DB::table('audit_events')->where('event_type', 'grading.omr_pair_retired')->exists())->toBeTrue();

    // retiring an active template version cascades to its pairs
    $activeTemplate = OmrAnswerSheetTemplateVersion::query()
        ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
        ->whereHas('pairs', fn ($query) => $query->where('status', OmrTemplateBookletPair::STATUS_ACTIVE))
        ->firstOrFail();
    $activePairIds = $activeTemplate->pairs()->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->pluck('id')->all();

    app(\App\Assessment\Actions\Omr\RetireOmrConfiguration::class)
        ->handle('template_version', $activeTemplate->id, $guidance);
    expect($activeTemplate->refresh()->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_RETIRED)
        ->and(OmrTemplateBookletPair::query()->whereIn('id', $activePairIds)->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->exists())->toBeFalse()
        ->and(DB::table('audit_events')->where('event_type', 'grading.omr_template_retired')->exists())->toBeTrue();
});

test('authored template builder output registers through the unchanged verify path', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $code = 'BLDR-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code);

    $templateJson = [
        'bubbleDimensions' => [56, 56],
        'pageDimensions' => [2060, 3144],
        'pages' => [
            [
                'page_no' => 1,
                'pageDimensions' => [2060, 3144],
                'fieldBlocks' => [
                    'ApplicantNumber' => [
                        'fieldType' => 'QTYPE_INT',
                        'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
                        'bubblesGap' => 60,
                        'labelsGap' => 66,
                        'origin' => [200, 260],
                    ],
                    'VerbalPart' => [
                        'fieldType' => 'QTYPE_MCQ4',
                        'direction' => 'horizontal',
                        'fieldLabels' => ['VERBAL_Q_001..010'],
                        'bubblesGap' => 66,
                        'labelsGap' => 80,
                        'origin' => [180, 900],
                    ],
                ],
                'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]],
            ],
        ],
    ];

    try {
        $version = app(\App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft::class)
            ->handle($code, 'Builder output', $templateJson, $guidance);

        expect($version->status)->toBe('draft')
            ->and($version->version)->toBe(1)
            ->and($version->engine_name)->toBe('OMRChecker')
            ->and($version->native_bundle_hash)->not->toBeNull()
            // Artifacts are staged immutably on the private durable disk, and
            // the load path resolves them through the unchanged NativeBundle.
            ->and(\Illuminate\Support\Facades\Storage::disk('local')->exists('omr-bundles/'.$code.'/v1/template.json'))->toBeTrue()
            ->and(\Illuminate\Support\Facades\Storage::disk('local')->exists('omr-bundles/'.$code.'/v1/reference-page-1.svg'))->toBeTrue()
            ->and(\App\Assessment\Omr\NativeBundle::load($code, 1)->template['pages'])->toHaveCount(1);

        // invalid geometry (no pages) is refused before anything is written
        expect(fn () => app(\App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft::class)
            ->handle('BLDR-BAD-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 4)), 'Bad', ['bubbleDimensions' => [120, 120]], $guidance))
            ->toThrow(RuntimeException::class, 'at least one page');
    } finally {
        exec('rm -rf '.escapeshellarg($bundleDir));
    }
});
