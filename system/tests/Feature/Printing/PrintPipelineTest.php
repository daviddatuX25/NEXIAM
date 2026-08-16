<?php

use App\Assessment\Omr\NativeBundle;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Printing\RenderPrintJob;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Printing\PrintJob;
use App\Printing\Actions\RenderBlankOmrSheets;
use App\Printing\BundleRenderer;
use App\Printing\DomPdfPrintRenderer;
use App\Printing\PrintRenderer;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('blank sheet action queues a job with pinned inputs and dedupes duplicates', function (): void {
    Queue::fake();
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];

    $templateVersion = OmrAnswerSheetTemplateVersion::query()
        ->where('version', 4)
        ->firstOrFail();

    $first = app(RenderBlankOmrSheets::class)->handle($templateVersion, $guidance);
    $second = app(RenderBlankOmrSheets::class)->handle($templateVersion, $guidance);

    expect($first->id)->toBe($second->id)
        ->and($first->artifact_type)->toBe(PrintJob::ARTIFACT_BLANK_SHEET)
        ->and($first->pinned_input_json['template_version_id'])->toBe($templateVersion->id)
        ->and($first->pinned_input_json['bundle_version'])->toBe(4)
        ->and($first->pinned_input_json['bundle_hash'])->toBe($templateVersion->native_bundle_hash);

    Queue::assertPushed(RenderPrintJob::class, 1);
});

test('render job produces a ready pdf for the long-bond bundle', function (): void {
    Storage::fake('local');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];

    $templateVersion = OmrAnswerSheetTemplateVersion::query()->where('version', 4)->firstOrFail();
    $job = app(RenderBlankOmrSheets::class)->handle($templateVersion, $guidance);

    (new RenderPrintJob($job->id))->handle(
        app(DomPdfPrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();

    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($job->file_path)->not->toBeNull()
        ->and($job->file_checksum)->toMatch('/^[a-f0-9]{64}$/')
        ->and(Storage::disk('local')->exists((string) $job->file_path))->toBeTrue();

    $bytes = Storage::disk('local')->get((string) $job->file_path);
    expect(str_starts_with($bytes, '%PDF'))->toBeTrue();

    $download = $this->actingAs($guidance)
        ->get(route('printing.jobs.download', $job));

    $download->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Disposition', 'inline; filename="print-'.$job->id.'.pdf"');

    expect($download->getContent())->toBe($bytes);
});
test('render job uses the selected DB snapshot and print overlay', function (): void {
    Queue::fake();
    Storage::fake('local');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];

    $templateVersion = OmrAnswerSheetTemplateVersion::query()->where('version', 4)->firstOrFail();
    $templateVersion->update(['print_overlay_json' => ['title' => 'DOWNLOAD PROOF']]);
    $job = app(RenderBlankOmrSheets::class)->handle($templateVersion, $guidance);

    $renderer = new class implements PrintRenderer
    {
        public string $html = '';

        public function render(string $html, array $options = []): string
        {
            $this->html = $html;

            return '%PDF-1.7 print-proof';
        }
    };

    (new RenderPrintJob($job->id))->handle(
        $renderer,
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    expect($renderer->html)->toContain('DOWNLOAD PROOF')
        ->and($job->fresh()->status)->toBe(PrintJob::STATUS_READY);
});

test('download refuses non-ready jobs and denies without capability', function (): void {
    Storage::fake('local');
    $fixture = buildOmrGradingFixture($this);
    $guidance = $fixture['guidance'];
    $registrar = $fixture['registrar'];

    $templateVersion = OmrAnswerSheetTemplateVersion::query()->where('version', 4)->firstOrFail();
    $job = PrintJob::query()->create([
        'artifact_type' => PrintJob::ARTIFACT_BLANK_SHEET,
        'scope_type' => 'template_version',
        'scope_id' => $templateVersion->id,
        'requested_by_user_id' => $guidance->id,
        'pinned_input_json' => ['bundle_code' => 'SYN-OMR-ONE-PAGE', 'bundle_version' => 4],
        'progress_total' => 1,
        'progress_done' => 0,
    ]);

    $this->actingAs($guidance)
        ->get(route('printing.jobs.download', $job))
        ->assertStatus(409);

    $this->actingAs($registrar)
        ->get(route('printing.jobs.download', $job))
        ->assertStatus(403);
});

test('renderer centres option letters on bubble centres and never scales mismatched paper', function (): void {
    $renderer = app(BundleRenderer::class);
    $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', 4);

    config(['print.paper' => 'long']);
    $html = $renderer->renderHtml($bundle->template);

    // Letter centre coords equal bubble centre coords by construction: check one
    // rendered label position matches the corresponding bubble position.
    expect($html)->toContain('PAGE 1 OF 2')
        ->and($html)->toContain('Print at 100% scale')
        ->and($html)->toContain('.bubble{box-sizing:border-box;position:absolute;');

    // A4 bundle vs long-bond paper must refuse, never scale.
    $a4 = NativeBundle::load('SYN-OMR-ONE-PAGE', 3);
    expect(fn () => $renderer->renderHtml($a4->template))
        ->toThrow(RuntimeException::class, 'never scaled');
});

test('renderer keeps vertical integer grids aligned with native manifest geometry', function (): void {
    $template = [
        'bubbleDimensions' => [56, 56],
        'pageDimensions' => [2060, 3144],
        'fieldBlocks' => [
            'Birthdate' => [
                'fieldType' => 'QTYPE_INT',
                'fieldLabels' => ['BIRTHDATE_1..6'],
                'origin' => [1000, 260],
                'bubblesGap' => 60,
                'labelsGap' => 66,
            ],
        ],
    ];

    $html = app(BundleRenderer::class)->renderHtml($template);

    expect(substr_count($html, 'class="bubble"'))->toBe(60)
        ->and($html)->toContain('left:104.96mm;top:27.29mm')
        ->and($html)->toContain('left:139.6mm;top:83.97mm');
});

test('renderer leaves option labels above empty shade bubbles', function (): void {
    $template = [
        'bubbleDimensions' => [56, 56],
        'pageDimensions' => [2060, 3144],
        'fieldBlocks' => [
            'VERBAL_Q_001..025' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['VERBAL_Q_001..025'],
                'origin' => [180, 900],
                'bubblesGap' => 66,
                'labelsGap' => 80,
            ],
        ],
    ];

    $html = app(BundleRenderer::class)->renderHtml($template);

    expect($html)->toContain('top:90.45mm;width:6mm;height:3.175mm;line-height:3.175mm;')
        ->and($html)->toContain('top:95.4mm;width:5mm;')
        ->and($html)->not->toContain('top:94.63mm;width:6mm;height:3.175mm;line-height:3.175mm;');
});

test('renderer preserves the compact profile name line above identity grids', function (): void {
    $template = [
        'bubbleDimensions' => [40, 40],
        'pageDimensions' => [2060, 3144],
        'fieldBlocks' => [
            'ApplicantNumber' => [
                'fieldType' => 'QTYPE_INT',
                'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
                'origin' => [200, 315],
                'bubblesGap' => 52,
                'labelsGap' => 66,
            ],
            'Birthdate' => [
                'fieldType' => 'QTYPE_INT',
                'fieldLabels' => ['BIRTHDATE_1..6'],
                'origin' => [1000, 315],
                'bubblesGap' => 52,
                'labelsGap' => 66,
            ],
        ],
    ];

    $html = app(BundleRenderer::class)->renderHtml($template, [
        'overlay' => ['name_box_label' => 'NAME (PRINT):'],
    ]);

    expect(substr_count($html, 'class="bubble"'))->toBe(160)
        ->and($html)->toContain('NAME (PRINT):')
        ->and($html)->toContain('left:155.6mm;top:23.71mm;width:50mm;height:8mm;border:0.3mm solid #000;')
        ->and($html)->not->toContain('top:23.71mm;width:80mm;height:8mm;border:0.3mm solid #000;');
});

test('renderer places reserved EDQ content clear of native identity grids', function (): void {
    $template = [
        'bubbleDimensions' => [40, 40],
        'pageDimensions' => [2060, 3144],
        'fieldBlocks' => [
            'ApplicantNumber' => [
                'fieldType' => 'QTYPE_INT',
                'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
                'origin' => [200, 300],
                'bubblesGap' => 52,
                'labelsGap' => 66,
            ],
        ],
    ];

    $html = app(BundleRenderer::class)->renderHtml($template, [
        'overlay' => [
            'edq_header' => true,
            'edq_layout' => ['columns' => 3, 'gap_mm' => 4, 'reserve_native_identity' => true],
            'exam_information_lines' => ['EXAM: __________'],
            'instructions_lines' => ['Shade one answer per question.'],
        ],
    ]);

    expect($html)->toContain('left:91.54mm;top:20mm;width:')
        ->and($html)->toContain('EXAM INFORMATION')
        ->and($html)->toContain('IDENTITY');
});

test('renderer keeps a manual name field on every v4 and v5 page', function (): void {
    $renderer = app(BundleRenderer::class);

    foreach ([4, 5] as $version) {
        $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', $version);
        $html = $renderer->renderHtml($bundle->template);

        expect(substr_count($html, 'NAME (PRINT):'))->toBe(2)
            ->and($html)->toContain('width:50mm;height:8mm;border:0.3mm solid #000;');
    }
});
