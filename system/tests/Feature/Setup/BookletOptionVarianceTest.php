<?php

use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());

    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
});

/** @return array{version: OmrAnswerSheetTemplateVersion, area: AptitudeArea} */
function bookletOptionVarianceAnswerSheet(): array
{
    $area = AptitudeArea::query()->where('is_active', true)->orderBy('id')->firstOrFail();
    $tokens = ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'];
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => Institution::current()->id,
        'is_synthetic' => false,
        'code' => 'PAIR-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8)),
        'name' => 'Paired option variance sheet',
    ]);
    $nativeLabel = $area->code.'_Q_1';
    $positions = [];
    $fields = [];
    foreach ($tokens as $index => $token) {
        $x = 100 + ($index * 30);
        $y = 200;
        $positions[] = [$x, $y];
        $fields[] = [
            'page' => 1,
            'label' => $nativeLabel,
            'option' => $token,
            'region' => [
                'x' => round($x / 1000, 6),
                'y' => round($y / 1400, 6),
                'w' => 0.01,
                'h' => round(10 / 1400, 6),
            ],
            'pixel' => ['x' => $x, 'y' => $y, 'w' => 10, 'h' => 10],
        ];
    }
    $reviewManifest = [
        'template_code' => $template->code,
        'template_version' => 1,
        'fields' => $fields,
        'pages' => [['page_no' => 1, 'page_dimensions' => ['width' => 1000, 'height' => 1400]]],
    ];
    $version = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'page_width' => 1000,
        'page_height' => 1400,
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'page_count' => 1,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => str_repeat('a', 64),
        'review_manifest_json' => $reviewManifest,
        'native_bundle_json' => [
            'template' => [
                'pages' => [[
                    'page_no' => 1,
                    'pageDimensions' => [1000, 1400],
                    'fieldBlocks' => [
                        'PairedChoice' => [
                            'fieldType' => 'QTYPE_MCQ6',
                            'fieldLabels' => [$nativeLabel],
                            'bubbleValues' => $tokens,
                            'bubbleDimensions' => [10, 10],
                            'bubblePositions' => [$positions],
                        ],
                    ],
                ]],
                'bubbleDimensions' => [10, 10],
            ],
            'manifest' => ['registration_mode' => 'document_bound'],
        ],
        'native_bundle_hash' => hash('sha256', 'option-variance-'.$template->code),
        'native_bundle_version' => 'test-v1',
    ]);
    app(OmrTemplateGeometryProjector::class)->project($version, $reviewManifest);
    $version->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED]);

    return ['version' => $version->fresh('areas.aptitudeArea'), 'area' => $area];
}

function bookletOptionVarianceDraft(User $actor, OmrAnswerSheetTemplateVersion $sheet): OmrQuestionBookletVersion
{
    return app(CreateBookletDraft::class)->handle(
        'Paired option variance booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $actor,
        $sheet,
    );
}

it('saves paired comparison tokens as booklet set answer keys', function (): void {
    ['version' => $sheet, 'area' => $area] = bookletOptionVarianceAnswerSheet();
    $booklet = bookletOptionVarianceDraft($this->actor, $sheet);
    $items = [[
        'aptitude_area_id' => $area->id,
        'area_position' => 1,
        'global_position' => 1,
        'correct_option_code' => 'A-B',
    ]];

    $response = $this->actingAs($this->actor)
        ->followingRedirects()
        ->post(route('setup.exam-materials.booklets.sets.keys', [$booklet->id, 'A']), [
            'items' => $items,
            'lock_version' => $booklet->lock_version,
            'complete' => true,
        ]);

    $response->assertOk();
    $setId = DB::table('omr_booklet_sets')->where('booklet_version_id', $booklet->id)->where('set_code', 'A')->value('id');
    expect($setId)->not->toBeNull();
    $this->assertDatabaseHas('omr_booklet_set_items', [
        'booklet_set_id' => $setId,
        'global_position' => 1,
        'correct_option_code' => 'A-B',
    ]);
});

it('rejects an overlength answer key option code', function (string $optionCode): void {
    ['version' => $sheet, 'area' => $area] = bookletOptionVarianceAnswerSheet();
    $booklet = bookletOptionVarianceDraft($this->actor, $sheet);

    $this->actingAs($this->actor)
        ->postJson(route('setup.exam-materials.booklets.sets.keys', [$booklet->id, 'A']), [
            'items' => [[
                'aptitude_area_id' => $area->id,
                'area_position' => 1,
                'global_position' => 1,
                'correct_option_code' => $optionCode,
            ]],
            'lock_version' => $booklet->lock_version,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.correct_option_code');
})->with([
    'overlength token' => [str_repeat('X', 33)],
]);
