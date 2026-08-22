<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Assessment\Omr\NativeBundle;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Printing\BundleRenderer;
use Database\Seeders\OmrDemoBatchSeeder;
use Database\Seeders\OmrDemoMultiStateBatchSeeder;
use Database\Seeders\OmrDemoSeeder;
use App\Jobs\Assessment\AnalyzeOmrCaptureSheetJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('profile pair persists one bounded page and a matching 150-question key', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];

    $template = OmrAnswerSheetTemplate::query()->where('code', 'SYN-OMR-PROFILE-150')->firstOrFail();
    $version = $template->versions()->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)->firstOrFail();
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->where('template_version_id', $version->id)
        ->whereHas('bookletVersion.booklet', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-KEY'))
        ->firstOrFail();
    $bookletVersion = OmrQuestionBookletVersion::query()->with('questions.aptitudeArea')->findOrFail($pair->booklet_version_id);

    $native = NativeBundle::load('SYN-OMR-PROFILE-150', (int) $version->version);
    $page = $native->template['pages'][0];
    $questionBlocks = array_values(array_filter(
        $page['fieldBlocks'],
        static fn (array $block): bool => $block['fieldType'] === 'QTYPE_MCQ4',
    ));
    usort($questionBlocks, static fn (array $left, array $right): int => $left['origin'][0] <=> $right['origin'][0]);
    $areaCodes = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];

    expect(OmrTemplateBookletPair::query()
        ->where('template_version_id', $version->id)
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->count())->toBe(1)
        ->and($native->template['pageDimensions'])->toBe([2060, 3144])
        ->and($native->template['bubbleDimensions'])->toBe([40, 40])
        ->and($page['pageDimensions'])->toBe([2060, 3144])
        ->and($questionBlocks)->toHaveCount(6)
        ->and(array_map(static fn (array $block): string => $block['fieldLabels'][0], $questionBlocks))->toBe([
            'PROFILE_VERBAL_Q_001..025',
            'PROFILE_QUANT_Q_001..025',
            'LOGIC_Q_001..025',
            'SPATIAL_Q_001..025',
            'MEMORY_Q_001..025',
            'LANGUAGE_Q_001..025',
        ])
        ->and($page['fieldBlocks']['ApplicantNumber']['fieldLabels'])->toBe(['APPLICANT_NUMBER_1..10'])
        ->and($page['fieldBlocks']['ApplicantNumber']['origin'])->toBe([200, 300])
        ->and($questionBlocks[0]['origin'])->toBe([120, 940])
        ->and($page['fieldBlocks']['ApplicantNumber']['origin'][1] + (9 * 52) + 40)->toBeLessThan($questionBlocks[0]['origin'][1])
        ->and(array_values(array_filter($page['fieldBlocks'], static fn (array $block): bool => $block['fieldType'] === 'QTYPE_INT')))->toHaveCount(1)
        ->and($version->layout_blueprint_json['columns'])->toBe(6)
        ->and($version->layout_blueprint_json['identity']['birthdate'])->toBeFalse()
        ->and($version->print_overlay_json['name_box_label'])->toBe('NAME (PRINT):')
        ->and($version->print_overlay_json['edq_header'])->toBeTrue()
        ->and($version->print_overlay_json['instructions_lines'])->toBe(['Shade one answer per question.'])
        ->and($version->print_overlay_json['birthdate_label'])->toBe('BIRTHDATE:')
        ->and($version->print_overlay_json['show_calibration'])->toBeFalse()
        ->and($bookletVersion->questions)->toHaveCount(150)
        ->and($bookletVersion->questions->whereNull('template_question_id'))->toHaveCount(0);

    foreach ($questionBlocks as $block) {
        expect($block['bubblesGap'])->toBe(52)
            ->and($block['labelsGap'])->toBe(80)
            ->and($block['origin'][0] + (3 * $block['bubblesGap']) + 40)->toBeLessThanOrEqual(2060 - 80)
            ->and($block['origin'][1] + (24 * $block['labelsGap']) + 40)->toBeLessThanOrEqual(3144 - 80);
    }

    $grouped = $bookletVersion->questions->sortBy('question_position')->groupBy(fn ($question): string => $question->aptitudeArea->code);
    expect($grouped->keys()->all())->toBe($areaCodes);
    foreach ($areaCodes as $areaCode) {
        $areaQuestions = $grouped->get($areaCode);
        expect($areaQuestions)->toHaveCount(25)
            ->and($areaQuestions->pluck('question_position')->map(fn ($position): int => (int) $position)->all())
            ->toBe(range(1 + (array_search($areaCode, $areaCodes, true) * 25), 25 + (array_search($areaCode, $areaCodes, true) * 25)));
    }

    $expectedOptions = array_map(static fn (int $position): string => ['A', 'B', 'C', 'D'][($position - 1) % 4], range(1, 150));
    expect($bookletVersion->questions->sortBy('question_position')->pluck('correct_option_code')->all())->toBe($expectedOptions);

    $html = app(BundleRenderer::class)->renderHtml($native->template, ['overlay' => $version->print_overlay_json]);
    expect(substr_count($html, 'class="bubble"'))->toBe(700)
        ->and($html)->toContain('PAGE 1 OF 1')
        ->and($html)->toContain('NEXIAM ANSWER SHEET')
        ->and($html)->toContain('EDQ / HEADER ZONE')
        ->and($html)->toContain('BIRTHDATE:')
        ->and($html)->toContain('NAME (PRINT):')
        ->and($html)->not->toContain('BIRTHDATE_')
        ->and($html)->not->toContain('Print at 100% scale')
        ->and($html)->not->toContain('Shade completely. Erasures void the sheet.');

    $templateVersionId = $version->id;
    $bookletVersionId = $bookletVersion->id;
    $pairId = $pair->id;
    (new OmrDemoSeeder)->run();

    expect($template->fresh()->versions()->count())->toBe(1)
        ->and(OmrQuestionBookletVersion::query()->where('booklet_id', $bookletVersion->booklet_id)->count())->toBe(1)
        ->and(OmrTemplateBookletPair::query()->whereKey($pairId)->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->exists())->toBeTrue()
        ->and(OmrAnswerSheetTemplateVersion::query()->whereKey($templateVersionId)->exists())->toBeTrue()
        ->and(OmrQuestionBookletVersion::query()->whereKey($bookletVersionId)->exists())->toBeTrue();

    $this->actingAs($guidance)
        ->get(route('setup.omr.templates.preview', $version))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('profile capture seed exposes all review states without fake confidence', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];

    (new OmrDemoMultiStateBatchSeeder)->run();

    $batch = OmrCaptureBatch::query()
        ->where('grading_session_id', $fixture['grading']->id)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
        ->firstOrFail();
    $sheets = $batch->sheets()->with('pages')->get();

    expect($sheets)->toHaveCount(3)
        ->and($sheets->where('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW))->toHaveCount(1)
        ->and($sheets->where('status', OmrCaptureSheet::STATUS_PENDING_PAGES))->toHaveCount(1)
        ->and($sheets->where('status', OmrCaptureSheet::STATUS_FAILED_TERMINAL))->toHaveCount(1)
        ->and($sheets->whereNull('sheet_confidence'))->toHaveCount(3)
        ->and($sheets->every(fn (OmrCaptureSheet $sheet): bool => $sheet->pages->every(
            fn ($page): bool => Storage::disk('sensitive')->exists((string) $page->asset_path),
        )))->toBeTrue();

    $review = $sheets->firstWhere('status', OmrCaptureSheet::STATUS_NEEDS_REVIEW);
    expect($review->detected_applicant_confidence)->toBeNull()
        ->and($review->detected_birthdate)->toBeNull()
        ->and($review->proposal_json['applicant_number_confidence'])->toBeNull()
        ->and($review->proposal_json['birthdate_proposal'])->toBeNull()
        ->and($review->proposal_json['sheet_confidence'])->toBeNull()
        ->and($review->pages)->toHaveCount(1);

    (new OmrDemoMultiStateBatchSeeder)->run();
    expect($batch->fresh()->sheets()->count())->toBe(3);

    $this->actingAs($guidance)
        ->get(route('grading.sessions.omr', $fixture['grading']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Omr/Capture')
            ->where('page_state', 'ready')
            ->where('batch.template.page_count', 1)
            ->has('batch.sheets', 3)
        );
});

test('default batch seed is a focused single-examinee dataset', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];

    (new OmrDemoBatchSeeder)->run();

    $batch = OmrCaptureBatch::query()
        ->where('grading_session_id', $fixture['grading']->id)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
        ->firstOrFail();
    $sheets = $batch->sheets()->with('pages')->get();

    expect($sheets)->toHaveCount(1)
        ->and($sheets->first()->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($sheets->first()->admission_attempt_id)->not->toBeNull()
        ->and($sheets->first()->detected_applicant_number)->toBe('1234567890')
        ->and($sheets->first()->pages)->toHaveCount(1);

    (new OmrDemoBatchSeeder)->run();
    expect($batch->fresh()->sheets()->count())->toBe(1);

    $this->actingAs($guidance)
        ->get(route('grading.sessions.omr', $fixture['grading']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Omr/Capture')
            ->where('page_state', 'ready')
            ->where('batch.template.page_count', 1)
            ->has('batch.sheets', 1)
        );
});

test('single-page profile upload carries the asset mirror and queues analysis', function (): void {
    Storage::fake('sensitive');
    Queue::fake();
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];

    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
        ->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'profile-upload-'.uniqid(),
    );

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $setId = (string) Str::uuid();
    $sheet = app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1);

    // Regression: `omr_capture_sheets_asset_check` requires the asset mirror
    // on the sheet row at insert time for single-page bundles.
    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_QUEUED)
        ->and($sheet->asset_path)->not->toBeNull()
        ->and($sheet->pages()->count())->toBe(1)
        ->and($sheet->pages()->first()->status)->toBe(OmrCaptureSheetPage::STATUS_QUEUED)
        ->and($sheet->processingRuns()->count())->toBe(1);

    Queue::assertPushed(AnalyzeOmrCaptureSheetJob::class, fn ($job) => $job->runId === $sheet->processingRuns()->first()->id);

    // Same client capture id is rejected as already uploaded.
    expect(fn () => app(UploadOmrCaptureSheet::class)->handle($batch, $bytes, 'image/png', OmrCaptureSheet::MODE_AUTO, $setId, $guidance, null, 1))
        ->toThrow(RuntimeException::class);
});

test('capture reports the configured live OMR provider', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];

    Config::set([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => 'test-commit',
    ]);

    $this->actingAs($guidance)
        ->get(route('grading.sessions.omr', $fixture['grading']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('provider.name', 'OMRChecker')
            ->where('provider.version', 'test-commit')
            ->where('provider.synthetic', false)
        );
});
