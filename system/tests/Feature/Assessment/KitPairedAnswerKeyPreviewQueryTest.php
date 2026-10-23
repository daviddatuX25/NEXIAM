<?php

use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Queries\KitPairedAnswerKeyPreviewQuery;
use App\Models\Assessment\OmrAnswerSheetPage;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\OmrTemplateQuestionOption;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
});

/** @return array{sheet:OmrAnswerSheetTemplateVersion,items:list<array<string,mixed>>} */
function makeKitPairedPreviewSheet(): array
{
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => Institution::current()->id,
        'code' => 'KIT-PAIRED-PREVIEW-'.strtoupper(bin2hex(random_bytes(4))),
        'name' => 'Isolated Kit paired preview sheet',
        'is_synthetic' => true,
    ]);
    $physicalItems = [
        ['uid' => 'physical-location-page-one', 'section' => 'printed-section-one', 'native' => 'VERBAL_Q_1', 'page' => 1, 'global' => 1, 'area' => 'LOGIC', 'token' => 'A-B', 'x' => 120, 'y' => 230],
        ['uid' => 'physical-location-page-two', 'section' => 'printed-section-two', 'native' => 'VERBAL_Q_1', 'page' => 2, 'global' => 2, 'area' => 'SPATIAL', 'token' => 'C-D', 'x' => 410, 'y' => 530],
    ];
    $pages = [];
    $reviewFields = [];
    foreach ($physicalItems as $item) {
        $pages[$item['page']] = [
            'page_no' => $item['page'],
            'pageDimensions' => [1000, 1000],
            'fieldBlocks' => [
                'block-'.$item['page'] => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'fieldLabels' => [$item['native']],
                    'bubbleValues' => ['A-B', 'B-C', 'C-D'],
                    'bubbleDimensions' => [10, 10],
                ],
            ],
        ];
        foreach ([['A-B', 0], ['B-C', 1], ['C-D', 2]] as [$token, $offset]) {
            $reviewFields[] = [
                'page' => $item['page'],
                'label' => $item['native'],
                'option' => $token,
                'pixel' => ['x' => $item['x'] + ($offset * 20), 'y' => $item['y'], 'w' => 10, 'h' => 10],
            ];
        }
    }
    ksort($pages);
    $reviewManifest = [
        'fields' => $reviewFields,
        'pages' => [
            ['page_no' => 1, 'page_dimensions' => ['width' => 1000, 'height' => 1000]],
            ['page_no' => 2, 'page_dimensions' => ['width' => 1000, 'height' => 1000]],
        ],
    ];
    $sectionInventory = [
        ['printed_section_uid' => 'printed-section-one', 'name' => 'Verbal', 'page_no' => 1, 'expected_item_count' => 1, 'display_order' => 1],
        ['printed_section_uid' => 'printed-section-two', 'name' => 'Spatial', 'page_no' => 2, 'expected_item_count' => 1, 'display_order' => 2],
    ];
    $sheet = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'page_count' => 2,
        'page_width' => 1000,
        'page_height' => 1000,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'kit-paired-preview-config'),
        'review_manifest_json' => $reviewManifest,
        'review_manifest_hash' => hash('sha256', 'kit-paired-preview-review'),
        'native_bundle_json' => [
            'template' => ['pages' => array_values($pages), 'bubbleDimensions' => [10, 10]],
            'manifest' => ['registration_mode' => OmrRegistrationMode::DocumentBound->value],
        ],
        'native_bundle_hash' => hash('sha256', 'kit-paired-preview-native'),
        'native_bundle_version' => 'isolated-test',
        'authoring_manifest' => ['physical_inventory' => ['sections' => $sectionInventory]],
    ]);
    $projectionHash = hash('sha256', 'kit-paired-projection');

    foreach ($sectionInventory as $section) {
        OmrAnswerSheetPage::query()->create([
            'template_version_id' => $sheet->id,
            'page_no' => $section['page_no'],
            'width' => 1000,
            'height' => 1000,
            'page_manifest_json' => $reviewManifest,
            'page_manifest_hash' => $projectionHash,
        ]);
        $area = OmrTemplateArea::query()->create([
            'template_version_id' => $sheet->id,
            'aptitude_area_id' => AptitudeArea::query()->where('code', $section['page_no'] === 1 ? 'LOGIC' : 'SPATIAL')->value('id'),
            'display_order' => $section['page_no'],
            'max_items' => 1,
            'region_x' => 0.1,
            'region_y' => 0.1,
            'region_w' => 0.8,
            'region_h' => 0.8,
            'projection_manifest_hash' => $projectionHash,
        ]);
        $item = $physicalItems[$section['page_no'] - 1];
        $question = OmrTemplateQuestion::query()->create([
            'template_area_id' => $area->id,
            'template_version_id' => $sheet->id,
            'page_no' => $item['page'],
            'question_no' => 1,
            'physical_item_uid' => $item['uid'],
            'printed_section_uid' => $item['section'],
            'native_label' => $item['native'],
            'global_position' => $item['global'],
            'region_x' => 0.1,
            'region_y' => 0.2,
            'region_w' => 0.4,
            'region_h' => 0.05,
            'projection_manifest_hash' => $projectionHash,
        ]);
        foreach (['A-B', 'B-C', 'C-D'] as $order => $token) {
            OmrTemplateQuestionOption::query()->create([
                'template_question_id' => $question->id,
                'code' => $token,
                'display_order' => $order + 1,
                'region_x' => 0.1 + ($order * 0.1),
                'region_y' => 0.2,
                'region_w' => 0.05,
                'region_h' => 0.05,
                'projection_manifest_hash' => $projectionHash,
            ]);
        }
    }

    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l6QAAAAASUVORK5CYII=');
    foreach ([1, 2] as $pageNo) {
        $path = 'kit-preview/page-'.$pageNo.'.png';
        Storage::disk('local')->put($path, $bytes);
        OmrTemplateAsset::query()->create([
            'template_version_id' => $sheet->id,
            'kind' => OmrTemplateAsset::KIND_RASTER_PAGE,
            'page_no' => $pageNo,
            'disk' => 'local',
            'path' => $path,
            'mime' => 'image/png',
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'metadata' => ['width_px' => 1000, 'height_px' => 1000],
        ]);
    }
    $sheet->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED]);

    return ['sheet' => $sheet->fresh(), 'items' => $physicalItems];
}

it('shades exact opaque physical identities and literal composite options across both pages', function (): void {
    ['sheet' => $sheet, 'items' => $items] = makeKitPairedPreviewSheet();
    $rows = array_map(static fn (array $item): array => [
        'physical_item_uid' => $item['uid'],
        'booklet_item_id' => 101 + $item['page'],
        'formal_subtest_id' => 201 + $item['page'],
        'formal_item_id' => 301 + $item['page'],
        'option_tokens' => ['A-B', 'B-C', 'C-D'],
        'disposition' => 'physical',
    ], $items);
    $keys = [
        ['physical_item_uid' => $items[0]['uid'], 'correct_option_code' => 'A-B'],
        ['physical_item_uid' => $items[1]['uid'], 'correct_option_code' => 'C-D'],
    ];
    $preview = app(KitPairedAnswerKeyPreviewQuery::class)->forKit(
        $sheet,
        $rows,
        $keys,
        ['map_complete' => true, 'preparation_revision' => 8, 'composition_revision' => 3, 'component_vector' => ['sheet' => $sheet->id]],
        static fn (int $pageNo): string => '/kit-preview/pages/'.$pageNo,
    );

    expect($preview['state'])->toBe('ready')
        ->and($preview['page_count'])->toBe(2)
        ->and($preview['pages'][0]['bubbles'][0]['physical_item_uid'])->toBe($items[0]['uid'])
        ->and($preview['pages'][0]['bubbles'][0]['options']['A-B'])->toMatchArray(['cx' => 125.0, 'cy' => 235.0, 'r' => 5.0])
        ->and($preview['pages'][1]['bubbles'][0]['physical_item_uid'])->toBe($items[1]['uid'])
        ->and($preview['pages'][1]['bubbles'][0]['options']['C-D'])->toMatchArray(['cx' => 455.0, 'cy' => 535.0, 'r' => 5.0])
        ->and($preview['answers'])->toBe([
            ['physical_item_uid' => $items[0]['uid'], 'correct_option_code' => 'A-B'],
            ['physical_item_uid' => $items[1]['uid'], 'correct_option_code' => 'C-D'],
        ]);
});

it('keeps an incomplete map or missing key unfilled rather than defaulting a bubble', function (): void {
    ['sheet' => $sheet, 'items' => $items] = makeKitPairedPreviewSheet();
    $rows = [['physical_item_uid' => $items[0]['uid'], 'booklet_item_id' => 102, 'formal_subtest_id' => 202, 'formal_item_id' => 302, 'option_tokens' => ['A-B', 'B-C', 'C-D'], 'disposition' => 'physical']];
    $previewQuery = app(KitPairedAnswerKeyPreviewQuery::class);
    $rasterUrl = static fn (int $pageNo): string => '/kit-preview/pages/'.$pageNo;

    $partialMap = $previewQuery->forKit(
        $sheet,
        $rows,
        [['physical_item_uid' => $items[0]['uid'], 'correct_option_code' => 'A-B']],
        ['map_complete' => false, 'preparation_revision' => 8, 'composition_revision' => 3],
        $rasterUrl,
    );
    expect($partialMap['state'])->toBe('incomplete')
        ->and($partialMap['answers'])->toBe([])
        ->and($partialMap['pages'][0]['bubbles'])->toHaveCount(1)
        ->and($partialMap['issues'])->toContain(['code' => 'composition_incomplete', 'message' => 'Complete the explicit physical, Booklet, and Test Setup matches before key shading is available.']);

    $missingKey = $previewQuery->forKit(
        $sheet,
        array_map(static fn (array $item): array => ['physical_item_uid' => $item['uid']], $items),
        [],
        ['map_complete' => true, 'preparation_revision' => 8, 'composition_revision' => 3],
        $rasterUrl,
    );
    expect($missingKey['state'])->toBe('incomplete')
        ->and($missingKey['answers'])->toBe([])
        ->and($missingKey['issues'])->toContain(['code' => 'answer_key_incomplete', 'message' => 'A valid saved answer is missing for one or more mapped items.']);
});
