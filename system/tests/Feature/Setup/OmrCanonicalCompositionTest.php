<?php

use App\Assessment\Omr\Canonical\CanonicalNativePageRenderer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalComposer;
use App\Assessment\Omr\Canonical\OmrCanonicalCompositionException;
use App\Assessment\Omr\Canonical\OmrCanonicalMappingGuideGeometry;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function omrCanonicalTestPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

test('canonical composition produces deterministic 300 PPI raster and exact-size PDF', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-COMPOSE', 'Compose', 'image');
    $asset = app(OmrTemplateAssetStore::class)->putDraftAsset($draft, OmrTemplateAsset::KIND_INTERMEDIATE_PAGE, omrCanonicalTestPng(), 'image/png', 1, [
        'width_mm' => 216.0,
        'height_mm' => 330.0,
        'source_dpi' => 300,
    ]);
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED,
        'authoring_json' => [
            'pages' => [[
                'page_no' => 1,
                'asset_id' => $asset->id,
                'width_mm' => 216.0,
                'height_mm' => 330.0,
                'confirmed' => true,
            ]],
        ],
    ])->save();

    $result = app(OmrCanonicalComposer::class)->compose($draft->fresh(), [
        'paper_width_mm' => 216,
        'paper_height_mm' => 330,
        'source_dpi' => 300,
        'content_margin_mm' => ['top' => 18, 'right' => 15, 'bottom' => 18, 'left' => 15],
        'alignment' => 'center',
    ]);
    $dimensions = getimagesizefromstring($result->pages[0]['bytes']);

    expect($dimensions[0])->toBe(2551)
        ->and($dimensions[1])->toBe(3898)
        ->and($result->pdfBytes)->toStartWith('%PDF-')
        ->and($result->compositionHash)->toHaveLength(64);
});

test('document-bound composition excludes marker state even when a stale inset is submitted', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'DOCUMENT-BOUND-COMPOSE',
        'Document-bound Compose',
        'preset',
        registrationMode: OmrRegistrationMode::DocumentBound,
        qrMode: OmrQrMode::Disabled,
    );

    $result = app(OmrCanonicalComposer::class)->compose($draft, [
        'paper_width_mm' => 210,
        'paper_height_mm' => 297,
        'parametric' => true,
        'marker_inset_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12],
    ]);

    expect($result->settings)->not->toHaveKey('marker_inset_mm')
        ->and($result->pages[0]['metadata']['markers'])->toBe([]);
});

test('composition refuses unconfirmed pages', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-BLOCK', 'Blocked', 'image');

    expect(fn () => app(OmrCanonicalComposer::class)->compose($draft, []))
        ->toThrow(OmrCanonicalCompositionException::class, 'page_not_confirmed');
});

test('parametric composition produces clean white 300 PPI raster and PDF without uploaded source pages', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'CANONICAL-PARAMETRIC', 'Parametric Blank', 'preset');

    $result = app(OmrCanonicalComposer::class)->compose($draft->fresh(), [
        'paper_width_mm' => 210,
        'paper_height_mm' => 297,
        'parametric' => true,
        'content_margin_mm' => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15],
    ]);
    $dimensions = getimagesizefromstring($result->pages[0]['bytes']);

    expect($dimensions[0])->toBe(2480)
        ->and($dimensions[1])->toBe(3508)
        ->and($result->pdfBytes)->toStartWith('%PDF-')
        ->and($result->pages[0]['metadata']['parametric'])->toBeTrue()
        ->and($result->compositionHash)->toHaveLength(64);
});

test('parametric composition emits exactly the requested ordered pages', function (): void {
    Storage::fake('local');
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'CANONICAL-TWO-PAGE',
        'Two Page',
        'preset',
        requestedPageCount: 2,
    );

    $result = app(OmrCanonicalComposer::class)->compose($draft->fresh(), [
        'paper_width_mm' => 210,
        'paper_height_mm' => 297,
        'parametric' => true,
    ]);

    expect(array_column($result->pages, 'page_no'))->toBe([1, 2])
        ->and($result->pdfBytes)->toStartWith('%PDF-');
});

test('composition rejects uploaded source page count mismatch', function (): void {
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'CANONICAL-MISMATCH',
        'Mismatch',
        'pdf',
        ['pages' => [['page_no' => 1, 'asset_id' => 1, 'confirmed' => true]]],
        requestedPageCount: 2,
    );
    $draft->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED])->save();

    expect(fn () => app(OmrCanonicalComposer::class)->compose($draft->fresh(), []))
        ->toThrow(OmrCanonicalCompositionException::class, 'uploaded_page_count_mismatch');
});

test('canonical examinee number uses write-in boxes above numeric bubble rows', function (): void {
    $html = app(CanonicalNativePageRenderer::class)->renderHtml([
        'pageDimensions' => [2480, 3508],
        'fieldBlocks' => [
            'Examinee_ID' => [
                'fieldType' => 'QTYPE_INT',
                'direction' => 'vertical',
                'fieldLabels' => ['APPLICANT_NUMBER_1..7'],
                'origin' => [200, 300],
                'bubbleDimensions' => [53, 53],
                'bubblesGap' => 68,
                'labelsGap' => 68,
                'displayLabel' => 'Examinee number',
            ],
        ],
    ]);

    expect(substr_count($html, 'class="identity-write-in"'))->toBe(7)
        ->and(substr_count($html, 'class="question"'))->toBe(10)
        ->and($html)->toContain('Examinee number')
        ->and($html)->toContain('>0</div>')
        ->and($html)->toContain('>9</div>')
        ->and($html)->not->toContain('class="option"');
});

test('canonical renderer aligns labels with explicit bubble positions and mapping guides use inset-only translation', function (): void {
    $template = [
        'pageDimensions' => [2480, 3508],
        'fieldBlocks' => [
            'Tilted' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['Q_1..2'],
                'origin' => [100, 100],
                'bubbleDimensions' => [50, 50],
                'bubblesGap' => 80,
                'labelsGap' => 70,
                'bubblePositions' => [
                    [[300, 400], [380, 404], [460, 408], [540, 412]],
                    [[320, 470], [400, 474], [480, 478], [560, 482]],
                ],
                'bubbleValues' => ['A', 'B', 'C', 'D'],
                'showSectionHeader' => false,
            ],
        ],
    ];
    $renderer = app(CanonicalNativePageRenderer::class);
    $html = $renderer->renderHtml($template);
    $svg = $renderer->renderReferenceSvg($template);
    $guides = OmrCanonicalMappingGuideGeometry::resolve($template, ['left' => 30, 'top' => 40]);

    expect($html)->toContain('left:25.4mm;top:33.8667mm')
        ->and($html)->toContain('left:45.72mm;top:34.8827mm')
        ->and($html)->toContain('left:24.6mm;top:29.3667mm;width:5.8333mm;">A</div>')
        ->and($html)->toContain('left:22.0933mm;top:40.10995mm;width:4mm;">2</div>')
        ->and($svg)->toContain('cx="27.51665" cy="35.98335"')
        ->and($guides[0]['bubbles'][0]['x_mm'])->toBe(27.94)
        ->and($guides[0]['bubbles'][0]['y_mm'])->toBe(37.253);
});
