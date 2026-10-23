<?php

use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\GenerateBookletSets;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Assessment\Omr\Queries\BookletContractQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateItemMapping;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    seedE2Foundation();
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $this->actor->givePermissionTo([
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
    ]);
});
function profileMappingRasterBytes(): string
{
    $image = imagecreate(1000, 1400);
    if ($image === false) {
        throw new RuntimeException('Unable to create the profile-mapping preview raster.');
    }

    $background = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $background);
    ob_start();
    try {
        if (! imagepng($image)) {
            throw new RuntimeException('Unable to encode the profile-mapping preview raster.');
        }

        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
        imagedestroy($image);
    }
}

function createApprovedProfileWithSubtests(
    User $actor,
    string $code,
    ?array $firstItemOptions = null,
): TestInstrumentVersion {
    $instrument = new TestInstrument;
    $instrument->forceFill([
        'institution_id' => $actor->staffProfile?->institution_id ?? Institution::current()->id ?? 1,
        'code' => $code,
        'is_synthetic' => true,
    ]);
    $instrument->save();
    $version = TestInstrumentVersion::query()->create([
        'test_instrument_id' => $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'display_name' => 'Synthetic approved fixed-order profile',
        'publisher_name' => 'Synthetic software test fixture',
        'form_code' => 'FORM-1',
        'edition' => 'Synthetic test fixture',
        'language' => 'Test-only',
        'source_reference' => 'test-fixture://synthetic-approved-source',
        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
        'administration_procedure_reference' => 'Synthetic profile-mapping procedure',
        'permitted_use_reference' => 'test-fixture://synthetic-permitted-use',
        'lock_version' => 1,
        'created_by_user_id' => $actor->id,
    ]);

    foreach (['VERBAL', 'QUANT'] as $sectionIndex => $subtestCode) {
        $subtest = TestInstrumentSubtest::query()->create([
            'test_instrument_version_id' => $version->id,
            'code' => $subtestCode,
            'name' => 'Subtest '.($sectionIndex + 1),
            'display_order' => $sectionIndex + 1,
            'response_mode' => 'keyed_items',
            'item_order_policy' => 'fixed',
        ]);

        foreach ([1, 2] as $itemIndex) {
            TestInstrumentItem::query()->create([
                'test_instrument_version_id' => $version->id,
                'test_instrument_subtest_id' => $subtest->id,
                'item_code' => $subtestCode.'_ITEM_'.$itemIndex,
                'local_number' => (string) $itemIndex,
                'display_order' => $itemIndex,
                'is_scored' => true,
                'option_tokens' => $sectionIndex === 0 && $itemIndex === 1
                    ? ($firstItemOptions ?? ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'])
                    : ['A', 'B', 'C', 'D'],
            ]);
        }
        TestInstrumentItem::query()->create([
            'test_instrument_version_id' => $version->id,
            'test_instrument_subtest_id' => $subtest->id,
            'item_code' => $subtestCode.'_PRACTICE',
            'local_number' => 'P',
            'display_order' => 3,
            'is_scored' => false,
            'option_tokens' => ['A', 'B', 'C', 'D'],
        ]);

        TestInstrumentMetric::query()->create([
            'test_instrument_version_id' => $version->id,
            'test_instrument_subtest_id' => $subtest->id,
            'code' => $subtestCode.'_RAW',
            'name' => $subtest->name.' correct item count',
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'unit' => 'count',
            'min_raw_value' => '0',
            'max_raw_value' => '2',
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
        ]);
    }

    return app(ApproveTestInstrumentVersion::class)->handle(
        $actor,
        (int) $instrument->id,
        (int) $version->id,
        (int) $version->lock_version,
        (string) Str::uuid(),
        true,
        true,
    );
}

function profileMappingTemplateFixture(
    TestInstrumentVersion $profile,
    string $status = OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
): OmrAnswerSheetTemplateVersion {
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => Institution::current()->id,
        'is_synthetic' => true,
        'code' => 'PROFILE-MAPPING-'.$profile->id,
        'name' => 'Profile mapping test',
    ]);
    $templateVersion = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'test_instrument_version_id' => $profile->id,
        'page_width' => 1000,
        'page_height' => 1400,
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'page_count' => 1,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'profile-mapping-'.$profile->id),
        'native_bundle_json' => [
            'template' => [
                'pages' => [[
                    'page_no' => 1,
                    'pageDimensions' => [1000, 1400],
                    'fieldBlocks' => [],
                ]],
            ],
            'manifest' => ['registration_mode' => 'document_bound'],
        ],
        'native_bundle_hash' => hash('sha256', 'profile-mapping-native-'.$profile->id),
        'native_bundle_version' => 'test-v1',
    ]);

    $fields = [];
    $fieldBlocks = [];
    $mappingRows = [];
    $globalPosition = 0;
    $subtests = $profile->subtests()->with('items')->orderBy('display_order')->get();
    foreach ($subtests as $subtest) {
        foreach ($subtest->items->where('is_scored', true)->sortBy('display_order') as $item) {
            $globalPosition++;
            $nativeLabel = $subtest->code.'_Q_'.str_pad((string) $item->local_number, 2, '0', STR_PAD_LEFT);
            $bubblePositions = [];
            foreach ($item->option_tokens as $optionIndex => $optionCode) {
                $x = 50 + (($globalPosition - 1) * 100) + ($optionIndex * 12);
                $y = 250 + ($globalPosition * 25);
                $bubblePositions[] = [$x, $y];
                $fields[] = [
                    'page' => 1,
                    'label' => $nativeLabel,
                    'option' => $optionCode,
                    'region' => [
                        'x' => round($x / 1000, 6),
                        'y' => round($y / 1400, 6),
                        'w' => 0.01,
                        'h' => round(10 / 1400, 6),
                    ],
                    'pixel' => ['x' => $x, 'y' => $y, 'w' => 10, 'h' => 10],
                ];
            }
            $fieldBlocks[$nativeLabel] = [
                'fieldType' => 'QTYPE_MCQ6',
                'fieldLabels' => [$nativeLabel],
                'bubbleValues' => array_values($item->option_tokens),
                'bubbleDimensions' => [10, 10],
                'bubblePositions' => [$bubblePositions],
            ];
            $mappingRows[] = [
                'test_instrument_version_id' => $profile->id,
                'test_instrument_subtest_id' => $subtest->id,
                'test_instrument_item_id' => $item->id,
                'native_label' => $nativeLabel,
                'page_no' => 1,
                'global_position' => $globalPosition,
            ];
        }
    }
    $reviewManifest = [
        'template_code' => $template->code,
        'template_version' => 1,
        'fields' => $fields,
        'pages' => [['page_no' => 1, 'page_dimensions' => ['width' => 1000, 'height' => 1400]]],
    ];
    $nativeBundle = [
        'template' => [
            'pages' => [[
                'page_no' => 1,
                'pageDimensions' => [1000, 1400],
                'fieldBlocks' => $fieldBlocks,
                'bubbleDimensions' => [10, 10],
            ]],
            'bubbleDimensions' => [10, 10],
        ],
        'manifest' => ['registration_mode' => 'document_bound'],
    ];
    $templateVersion->update([
        'review_manifest_json' => $reviewManifest,
        'native_bundle_json' => $nativeBundle,
        'native_bundle_hash' => hash('sha256', json_encode($nativeBundle, JSON_THROW_ON_ERROR)),
    ]);
    app(OmrTemplateGeometryProjector::class)->project($templateVersion, $reviewManifest);
    foreach ($mappingRows as $mapping) {
        $templateVersion->itemMappings()->create($mapping);
    }
    $bytes = profileMappingRasterBytes();
    $path = 'profile-mapping/page-'.$profile->id.'.png';
    Storage::disk('local')->put($path, $bytes);
    OmrTemplateAsset::query()->create([
        'template_version_id' => $templateVersion->id,
        'kind' => OmrTemplateAsset::KIND_RASTER_PAGE,
        'page_no' => 1,
        'disk' => 'local',
        'path' => $path,
        'mime' => 'image/png',
        'bytes' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
        'metadata' => ['width_px' => 1000, 'height_px' => 1400],
    ]);
    $templateVersion->update(['status' => $status]);

    return $templateVersion;
}

function profileMappingSetKeys(OmrQuestionBookletVersion $booklet): array
{
    $positions = app(BookletContractQuery::class)->forBooklet($booklet)['instrument_item_positions'];

    return $booklet->templateVersion->itemMappings()
        ->with('item')
        ->orderBy('global_position')
        ->get()
        ->map(function (OmrTemplateItemMapping $mapping) use ($positions): array {
            $position = $positions[(int) $mapping->test_instrument_item_id];

            return [
                'test_instrument_item_id' => (int) $mapping->test_instrument_item_id,
                'aptitude_area_id' => (int) $position['aptitude_area_id'],
                'area_position' => (int) $position['area_position'],
                'global_position' => (int) $position['global_position'],
                'correct_option_code' => $mapping->item->option_tokens[0],
            ];
        })
        ->all();
}

test('profile-bound template item mappings persist subtest and item relationships', function (): void {
    $profileVersion = createApprovedProfileWithSubtests($this->actor, 'PROFILE-MAPPING-TEST');
    $templateVersion = profileMappingTemplateFixture($profileVersion, OmrAnswerSheetTemplateVersion::STATUS_DRAFT);
    $subtests = $profileVersion->subtests()->with('items')->orderBy('display_order')->get();
    $mappings = $templateVersion->itemMappings()->with(['templateVersion', 'instrumentVersion', 'subtest', 'item'])
        ->orderBy('global_position')->get();

    expect($mappings)->toHaveCount(4)
        ->and($mappings->pluck('test_instrument_item_id')->sort()->values()->all())
        ->toBe($subtests->flatMap(fn ($subtest) => $subtest->items->where('is_scored', true))->pluck('id')->sort()->values()->all());
    foreach ($mappings as $index => $mapping) {
        $subtest = $subtests[intdiv($index, 2)];
        $item = $subtest->items[$index % 2];
        expect($mapping->templateVersion->is($templateVersion))->toBeTrue()
            ->and($mapping->instrumentVersion->is($profileVersion))->toBeTrue()
            ->and($mapping->subtest->is($subtest))->toBeTrue()
            ->and($mapping->item->is($item))->toBeTrue()
            ->and($mapping->page_no)->toBe(1)
            ->and($mapping->global_position)->toBe($index + 1);
    }
    $practiceItem = $subtests[0]->items->firstWhere('is_scored', false);
    expect($practiceItem)->not->toBeNull()
        ->and($mappings->pluck('test_instrument_item_id'))->not->toContain($practiceItem->id);
    OmrTemplateItemMapping::query()->create([
        'template_version_id' => $templateVersion->id,
        'test_instrument_version_id' => $profileVersion->id,
        'test_instrument_subtest_id' => $subtests[0]->id,
        'test_instrument_item_id' => $practiceItem->id,
        'native_label' => 'VERBAL_Q_PRACTICE',
        'page_no' => 1,
        'global_position' => 5,
    ]);
    $booklet = new OmrQuestionBookletVersion;
    $booklet->forceFill([
        'template_version_id' => $templateVersion->id,
        'test_instrument_version_id' => $profileVersion->id,
    ]);
    $booklet->setRelation('templateVersion', $templateVersion);
    expect(fn () => app(BookletContractQuery::class)->forBooklet($booklet))
        ->toThrow(RuntimeException::class, 'invalid test instrument item mapping');
    expect(fn () => OmrTemplateItemMapping::query()->create([
        'template_version_id' => $templateVersion->id,
        'test_instrument_version_id' => $profileVersion->id,
        'test_instrument_subtest_id' => $subtests[0]->id,
        'test_instrument_item_id' => $practiceItem->id,
        'native_label' => 'UNIQUE-POSITION',
        'page_no' => 1,
        'global_position' => 1,
    ]))->toThrow(QueryException::class);
});

test('booklet Set keys preserve fixed profile order and item identity', function (): void {
    $profile = createApprovedProfileWithSubtests($this->actor, 'PROFILE-FIXED-ORDER');
    $templateVersion = profileMappingTemplateFixture($profile);
    $bookletVersion = app(CreateBookletDraft::class)->handle(
        'Fixed order profile booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        $templateVersion,
        'FIXED-ORDER-'.Str::upper(Str::random(6)),
    );
    $bookletVersion = app(SetBookletSetsMode::class)->handle(
        $bookletVersion, $this->actor, true, $bookletVersion->lock_version, false,
    );

    $keys = profileMappingSetKeys($bookletVersion);
    $set = app(SaveBookletSetKeys::class)->handle(
        $bookletVersion, 'A', $keys, $this->actor, $bookletVersion->lock_version, true,
    );
    $setKeys = $set->items()->orderBy('global_position')->get()
        ->map(fn ($item): array => [
            'test_instrument_item_id' => (int) $item->test_instrument_item_id,
            'area_position' => (int) $item->area_position,
            'global_position' => (int) $item->global_position,
            'correct_option_code' => $item->correct_option_code,
        ])->all();
    $expectedKeys = collect($keys)->map(fn (array $item): array => [
        'test_instrument_item_id' => $item['test_instrument_item_id'],
        'area_position' => $item['area_position'],
        'global_position' => $item['global_position'],
        'correct_option_code' => $item['correct_option_code'],
    ])->all();

    expect($setKeys)->toBe($expectedKeys);
});

test('typed Set keys resolve physical positions from exact mapped geometry and preserve opaque option tokens', function (): void {
    $profile = createApprovedProfileWithSubtests($this->actor, 'PROFILE-EXACT-PHYSICAL-KEYS');
    $templateVersion = profileMappingTemplateFixture($profile);
    $bookletVersion = app(CreateBookletDraft::class)->handle(
        'Exact physical key fixture',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        $templateVersion,
        'EXACT-PHYSICAL-'.Str::upper(Str::random(6)),
    );
    $items = array_map(fn (array $item): array => [
        'test_instrument_item_id' => $item['test_instrument_item_id'],
        'global_position' => $item['global_position'],
        'correct_option_code' => $item['correct_option_code'],
    ], profileMappingSetKeys($bookletVersion));

    $set = app(SaveBookletSetKeys::class)->handle(
        $bookletVersion,
        'A',
        $items,
        $this->actor,
        $bookletVersion->lock_version,
        true,
    );
    $firstRow = $set->items()->where('global_position', 1)->firstOrFail();
    $verbalArea = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
    $physicalArea = $templateVersion->areas()->where('aptitude_area_id', $verbalArea->id)->firstOrFail();
    $physicalQuestion = $physicalArea->questions()->where('page_no', 1)->where('question_no', 1)->firstOrFail();
    $expectedAreaId = (int) $physicalArea->aptitude_area_id;
    $expectedAreaPosition = (int) $physicalQuestion->question_no;

    expect($firstRow->correct_option_code)->toBe('A-B')
        ->and((int) $firstRow->aptitude_area_id)->toBe((int) $expectedAreaId)
        ->and((int) $firstRow->area_position)->toBe((int) $expectedAreaPosition);

    $conflictingItems = $items;
    $conflictingItems[0]['aptitude_area_id'] = AptitudeArea::query()
        ->where('id', '!=', $expectedAreaId)
        ->value('id');
    $freshBooklet = $bookletVersion->fresh();
    expect(fn () => app(SaveBookletSetKeys::class)->handle(
        $freshBooklet,
        'A',
        $conflictingItems,
        $this->actor,
        $freshBooklet->lock_version,
        true,
    ))->toThrow(RuntimeException::class, 'cannot claim another physical aptitude position');
    $freshBooklet = $bookletVersion->fresh();
    $multiSetBooklet = app(SetBookletSetsMode::class)->handle(
        $freshBooklet,
        $this->actor,
        true,
        $freshBooklet->lock_version,
        false,
    );
    $sourceItemId = (int) $templateVersion->itemMappings()->where('global_position', 2)->value('test_instrument_item_id');
    expect(fn () => app(SaveBookletSetKeys::class)->handle(
        $multiSetBooklet,
        'B',
        [[
            'test_instrument_item_id' => $sourceItemId,
            'global_position' => 3,
            'correct_option_code' => 'A',
        ]],
        $this->actor,
        $multiSetBooklet->lock_version,
    ))->toThrow(RuntimeException::class, 'destination must remain in its exact subtest');

});

test('numeric opaque option tokens stay strings through intrinsic Booklet persistence and Set key saving', function (): void {
    $booklet = app(CreateBookletDraft::class)->handle(
        'Numeric opaque token fixture',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        isSynthetic: true,
    );
    $sectionUid = (string) Str::uuid();
    $itemUid = (string) Str::uuid();
    $saved = app(SaveAuthoredBookletVersion::class)->handle($booklet, [[
        'booklet_item_uid' => $itemUid,
        'booklet_section_uid' => $sectionUid,
        'section_name' => 'Numeric options',
        'section_display_order' => 1,
        'section_expected_count' => 1,
        'option_tokens' => ['0', '1'],
        'key_required' => true,
        'authored_position' => 1,
        'content' => ['prompt' => '', 'choices' => []],
    ]], $this->actor, $booklet->lock_version);
    $generated = app(GenerateBookletSets::class)->handle($saved, $this->actor, $saved->lock_version);
    $set = $generated->sets()->where('set_code', 'A')->firstOrFail();
    $setItem = $set->items()->firstOrFail();
    $keys = [[
        'booklet_item_uid' => $itemUid,
        'booklet_section_uid' => $sectionUid,
        'global_position' => 1,
        'selected_token' => '0',
    ]];
    $savedSet = app(SaveBookletSetKeys::class)->handle($generated, 'A', $keys, $this->actor, $generated->lock_version);

    expect($saved->items()->firstOrFail()->option_tokens)->toBe(['0', '1'])
        ->and($savedSet->items()->firstOrFail()->correct_option_code)->toBe('0');
});

test('HTTP Set key clearing persists an exact unanswered destination, finalization blocks it, and reentry restores the token', function (): void {
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    $profile = createApprovedProfileWithSubtests($this->actor, 'PROFILE-HTTP-CLEAR-KEY');
    $templateVersion = profileMappingTemplateFixture($profile);
    $bookletVersion = app(CreateBookletDraft::class)->handle(
        'HTTP blank answer fixture',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        $templateVersion,
        'HTTP-CLEAR-'.Str::upper(Str::random(6)),
    );
    $bookletVersion = app(SetBookletSetsMode::class)->handle(
        $bookletVersion,
        $this->actor,
        true,
        $bookletVersion->lock_version,
        false,
    );
    $showUrl = route('setup.exam-materials.booklets.show', $bookletVersion->booklet_id);
    $aKeys = profileMappingSetKeys($bookletVersion);
    $this->actingAs($this->actor)
        ->from($showUrl)
        ->post(route('setup.exam-materials.booklets.sets.keys', [$bookletVersion->id, 'A']), [
            'items' => $aKeys,
            'lock_version' => $bookletVersion->lock_version,
            'complete' => true,
        ])
        ->assertRedirect($showUrl)
        ->assertSessionHas('status', 'Set A keys saved.');
    $bookletVersion = $bookletVersion->fresh();

    $firstMapping = $templateVersion->itemMappings()->where('global_position', 1)->firstOrFail();
    $bPayload = [[
        'test_instrument_item_id' => (int) $firstMapping->test_instrument_item_id,
        'global_position' => (int) $firstMapping->global_position,
        'selected_token' => 'A-B',
    ]];
    $this->from($showUrl)
        ->post(route('setup.exam-materials.booklets.sets.keys', [$bookletVersion->id, 'B']), [
            'items' => $bPayload,
            'lock_version' => $bookletVersion->lock_version,
        ])
        ->assertRedirect($showUrl);
    $bookletVersion = $bookletVersion->fresh();

    $missingTokenPayload = $bPayload;
    unset($missingTokenPayload[0]['selected_token']);
    $this->from($showUrl)
        ->post(route('setup.exam-materials.booklets.sets.keys', [$bookletVersion->id, 'B']), [
            'items' => $missingTokenPayload,
            'lock_version' => $bookletVersion->lock_version,
        ])
        ->assertRedirect($showUrl)
        ->assertSessionHasErrors('booklet');
    expect($bookletVersion->sets()->where('set_code', 'B')->firstOrFail()->items()->value('correct_option_code'))
        ->toBe('A-B');
    $bookletVersion = $bookletVersion->fresh();

    $bPayload[0]['selected_token'] = '';
    $this->from($showUrl)
        ->post(route('setup.exam-materials.booklets.sets.keys', [$bookletVersion->id, 'B']), [
            'items' => $bPayload,
            'lock_version' => $bookletVersion->lock_version,
        ])
        ->assertRedirect($showUrl)
        ->assertSessionHas('status', 'Set B keys saved.');
    $bookletVersion = $bookletVersion->fresh();
    $workspaceProps = $this->get($showUrl)->assertOk()->viewData('page')['props'];
    $workspace = collect($workspaceProps['booklets'])->firstWhere('id', $bookletVersion->booklet_id)['workspace'];
    $setB = collect($workspace['sets'])->firstWhere('set_code', 'B');
    $firstSection = collect($setB['areas'])->firstWhere('booklet_section_uid', $setB['items'][0]['booklet_section_uid']);

    expect(array_key_exists('answer_sheet_preview', $workspace))->toBeFalse()
        ->and($setB['items'][0]['correct_option_code'])->toBeNull()
        ->and($firstSection['completed_count'])->toBe(0)
        ->and($firstSection['missing_positions'])->toContain(1);

    $this->from($showUrl)
        ->post(route('setup.exam-materials.booklets.sets.keys', [$bookletVersion->id, 'B']), [
            'items' => $bPayload,
            'lock_version' => $bookletVersion->lock_version,
            'complete' => true,
        ])
        ->assertRedirect($showUrl)
        ->assertSessionHasErrors('booklet');
    $this->from($showUrl)
        ->post(route('setup.exam-materials.booklets.finalize', $bookletVersion->id), [
            'lock_version' => $bookletVersion->lock_version,
        ])
        ->assertRedirect($showUrl)
        ->assertSessionHasErrors('booklet');

    $bookletVersion = $bookletVersion->fresh();
    $bPayload[0]['selected_token'] = 'B-D';
    $this->from($showUrl)
        ->post(route('setup.exam-materials.booklets.sets.keys', [$bookletVersion->id, 'B']), [
            'items' => $bPayload,
            'lock_version' => $bookletVersion->lock_version,
        ])
        ->assertRedirect($showUrl);
    $workspaceProps = $this->get($showUrl)->assertOk()->viewData('page')['props'];
    $workspace = collect($workspaceProps['booklets'])->firstWhere('id', $bookletVersion->booklet_id)['workspace'];
    $setB = collect($workspace['sets'])->firstWhere('set_code', 'B');

    expect($setB['items'][0]['correct_option_code'])->toBe('B-D')
        ->and(collect($setB['areas'])->firstWhere('aptitude_area_id', $setB['items'][0]['aptitude_area_id'])['missing_positions'])
        ->toContain(2);
});

test('finalizing booklet version propagates test_instrument_version_id', function (): void {
    $profile = createApprovedProfileWithSubtests($this->actor, 'PROFILE-FINALIZE-BINDING');
    $templateVersion = profileMappingTemplateFixture($profile);
    $bookletVersion = app(CreateBookletDraft::class)->handle(
        'Profile-bound booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        $templateVersion,
        'PROFILE-BOUND-'.Str::upper(Str::random(6)),
    );
    app(SaveBookletSetKeys::class)->handle(
        $bookletVersion,
        'A',
        profileMappingSetKeys($bookletVersion),
        $this->actor,
        $bookletVersion->lock_version,
        true,
    );
    $bookletVersion = $bookletVersion->fresh();
    app(FinalizeBookletVersion::class)->handle($bookletVersion, $this->actor);

    expect($bookletVersion->fresh()->test_instrument_version_id)
        ->toBe($templateVersion->test_instrument_version_id);
});
