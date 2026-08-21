<?php

use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalComposer;
use App\Assessment\Omr\Canonical\OmrCanonicalCompositionException;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
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
