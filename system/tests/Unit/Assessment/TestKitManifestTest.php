<?php

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\ExactTestKitItemMap;
use App\Assessment\Support\PhysicalSheetInventory;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Models\Assessment\OmrAnswerSheetPage;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletItem;
use App\Models\Assessment\OmrBookletSection;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrBookletSetItem;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateItemMapping;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\OmrTemplateQuestionOption;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use RuntimeException;

function testKitManifestFixture(array $options = ['A', 'B', 'C']): array
{
    $profile = (new TestInstrumentVersion)->forceFill([
        'id' => 300,
        'test_instrument_id' => 301,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_APPROVED,
        'display_name' => 'Exact assessment profile',
        'publisher_name' => 'Publisher',
        'form_code' => 'FORM-1',
        'edition' => '1',
        'language' => 'en',
        'source_reference' => 'catalog/source-1',
        'permitted_use_reference' => 'catalog/permission-1',
        'material_requirement' => 'paper_backed',
        'administration_procedure_reference' => 'SOP-1',
    ]);
    $profile->setRelation('instrument', (new TestInstrument)->forceFill([
        'id' => 301,
        'code' => 'PROFILE',
        'institution_id' => 9,
        'is_synthetic' => true,
    ]));

    $subtestA = (new TestInstrumentSubtest)->forceFill([
        'id' => 311,
        'test_instrument_version_id' => 300,
        'code' => 'VERBAL',
        'name' => 'Verbal',
        'display_order' => 1,
        'response_mode' => 'keyed_items',
        'item_order_policy' => 'within_subtest_permutable',
    ]);
    $subtestB = (new TestInstrumentSubtest)->forceFill([
        'id' => 312,
        'test_instrument_version_id' => 300,
        'code' => 'LOGIC',
        'name' => 'Logic',
        'display_order' => 2,
        'response_mode' => 'keyed_items',
        'item_order_policy' => 'fixed',
    ]);

    $profileItems = [
        testKitProfileItem(321, 311, 'VERBAL_ONE', '1', 1, true, $options),
        testKitProfileItem(322, 311, 'VERBAL_TWO', '2', 2, true, $options),
        testKitProfileItem(323, 311, 'VERBAL_PRACTICE', '3', 3, false, $options),
        testKitProfileItem(324, 312, 'LOGIC_ONE', '1', 1, true, $options),
    ];
    $subtestA->setRelation('items', new Collection(array_slice($profileItems, 0, 3)));
    $subtestB->setRelation('items', new Collection([end($profileItems)]));
    $profile->setRelation('subtests', new Collection([$subtestA, $subtestB]));

    $metricA = testKitCountMetric(331, 311, 'VERBAL_RAW', '2');
    $metricB = testKitCountMetric(332, 312, 'LOGIC_RAW', '1');
    $metricA->setRelation('subtest', $subtestA);
    $metricB->setRelation('subtest', $subtestB);
    $metricA->setRelation('inputs', new Collection);
    $metricB->setRelation('inputs', new Collection);
    $profile->setRelation('metrics', new Collection([$metricA, $metricB]));

    $profileValidator = new TestInstrumentProfileValidator;
    $profile->content_sha256 = $profileValidator->digest($profileValidator->normalizeVersion($profile));

    $reviewHash = str_repeat('d', 64);
    $reviewManifest = [
        'template_code' => 'SHEET-1',
        'template_version' => 4,
        'fields' => [],
        'pages' => [
            ['page_no' => 1, 'page_dimensions' => ['width' => 1000, 'height' => 1400]],
            ['page_no' => 2, 'page_dimensions' => ['width' => 1000, 'height' => 1400]],
        ],
        'identity' => [
            'mode' => 'bubbled_applicant_number',
            'labels' => ['APPLICANT_NUMBER_1'],
            'symbols' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ],
    ];
    $profileMap = [321, 322, 323, 324];
    $pagesByPosition = [1 => 1, 2 => 1, 3 => 2, 4 => 2];
    $labelsByPosition = [1 => 'VERBAL_Q_1', 2 => 'VERBAL_Q_2', 3 => 'VERBAL_Q_3', 4 => 'LOGIC_Q_1'];
    foreach ($profileMap as $index => $itemId) {
        $position = $index + 1;
        foreach ($options as $option) {
            $reviewManifest['fields'][] = [
                'page' => $pagesByPosition[$position],
                'label' => $labelsByPosition[$position],
                'option' => $option,
                'pixel' => ['x' => $position * 10, 'y' => 10, 'w' => 5, 'h' => 5],
            ];
        }
    }
    $reviewManifest['fields'][] = ['page' => 1, 'label' => 'APPLICANT_NUMBER_1', 'option' => '0', 'pixel' => ['x' => 5, 'y' => 5, 'w' => 4, 'h' => 4]];
    $reviewManifest['fields'][] = ['page' => 1, 'label' => 'APPLICANT_NUMBER_1', 'option' => '1', 'pixel' => ['x' => 9, 'y' => 5, 'w' => 4, 'h' => 4]];

    $template = new class extends OmrAnswerSheetTemplateVersion
    {
        public HasMany $itemMappingsRelation;

        public function itemMappings(): HasMany
        {
            return $this->itemMappingsRelation;
        }
    };
    $template->forceFill([
        'id' => 400,
        'template_id' => 401,
        'test_instrument_version_id' => 300,
        'version' => 4,
        'status' => 'compiled',
        'registration_mode' => OmrRegistrationMode::FiducialAnchored,
        'qr_mode' => OmrQrMode::Signed,
        'page_count' => 2,
        'identity_regions_json' => ['applicant_number' => ['x' => 1, 'y' => 2]],
        'print_overlay_json' => ['header' => ['text' => 'EXAM']],
        'authoring_manifest' => ['source_kind' => 'system_designed', 'physical_inventory' => ['sections' => [
            ['printed_section_uid' => '00000000-0000-4000-9000-000000000311', 'name' => 'Verbal', 'page_no' => 1, 'expected_item_count' => 3, 'display_order' => 1],
            ['printed_section_uid' => '00000000-0000-4000-9000-000000000312', 'name' => 'Logic', 'page_no' => 2, 'expected_item_count' => 1, 'display_order' => 2],
        ]]],
        'config_hash' => str_repeat('a', 64),
        'template_json_hash' => str_repeat('b', 64),
        'native_bundle_hash' => str_repeat('c', 64),
        'review_manifest_hash' => $reviewHash,
        'native_bundle_version' => 'native-v5',
        'engine_name' => 'omr-engine',
        'engine_commit' => 'commit-1',
        'patch_identity' => 'patch-1',
        'layout_solver_version' => 'layout-v2',
        'review_manifest_json' => $reviewManifest,
    ]);
    $template->setRelation('template', (new OmrAnswerSheetTemplate)->forceFill([
        'id' => 401,
        'institution_id' => 9,
        'is_synthetic' => true,
    ]));
    $pageRows = [];
    foreach ([1, 2] as $pageNo) {
        $pageRows[] = (new OmrAnswerSheetPage)->forceFill([
            'id' => 410 + $pageNo,
            'template_version_id' => 400,
            'page_no' => $pageNo,
            'width' => 1000,
            'height' => 1400,
            'page_manifest_json' => [...$reviewManifest, 'areas' => [], 'questions' => []],
            'page_manifest_hash' => $reviewHash,
        ]);
    }
    $template->setRelation('pages', new Collection($pageRows));

    $areasBySubtest = [
        311 => (new OmrTemplateArea)->forceFill([
            'id' => 420,
            'template_version_id' => 400,
            'aptitude_area_id' => 99,
            'display_order' => 1,
            'max_items' => 3,
            'region_x' => 0.1,
            'region_y' => 0.2,
            'region_w' => 0.8,
            'region_h' => 0.7,
            'projection_manifest_hash' => $reviewHash,
        ]),
        312 => (new OmrTemplateArea)->forceFill([
            'id' => 421,
            'template_version_id' => 400,
            'aptitude_area_id' => 99,
            'display_order' => 2,
            'max_items' => 1,
            'region_x' => 0.1,
            'region_y' => 0.2,
            'region_w' => 0.8,
            'region_h' => 0.7,
            'projection_manifest_hash' => $reviewHash,
        ]),
    ];
    $questionsBySubtest = [311 => [], 312 => []];
    $profileItemsById = collect($profileItems)->keyBy('id');
    foreach ($profileMap as $index => $itemId) {
        $position = $index + 1;
        $subtestId = $position === 4 ? 312 : 311;
        $profileItem = $profileItemsById->get($itemId);
        $localNumber = (int) $profileItem->local_number;
        $question = (new OmrTemplateQuestion)->forceFill([
            'id' => 430 + $position,
            'template_version_id' => 400,
            'template_area_id' => $areasBySubtest[$subtestId]->id,
            'page_no' => $pagesByPosition[$position],
            'physical_item_uid' => sprintf('00000000-0000-4000-8000-%012d', $position),
            'printed_section_uid' => sprintf('00000000-0000-4000-9000-%012d', $subtestId),
            'native_label' => $labelsByPosition[$position],
            'global_position' => $position,
            'question_no' => $localNumber,
            'region_x' => 0.1 * $localNumber,
            'region_y' => 0.2,
            'region_w' => 0.3,
            'region_h' => 0.4,
            'projection_manifest_hash' => $reviewHash,
        ]);
        $optionRows = [];
        foreach ($options as $optionIndex => $token) {
            $optionRows[] = (new OmrTemplateQuestionOption)->forceFill([
                'id' => 450 + ($position * 10) + $optionIndex,
                'template_question_id' => 430 + $position,
                'code' => $token,
                'display_order' => $optionIndex + 1,
                'region_x' => 0.1 * ($optionIndex + 1),
                'region_y' => 0.3,
                'region_w' => 0.08,
                'region_h' => 0.04,
                'projection_manifest_hash' => $reviewHash,
            ]);
        }
        $question->setRelation('options', new Collection($optionRows));
        $questionsBySubtest[$subtestId][] = $question;
    }
    foreach ($areasBySubtest as $subtestId => $area) {
        $area->setRelation('questions', new Collection($questionsBySubtest[$subtestId]));
    }
    $template->setRelation('areas', new Collection(array_values($areasBySubtest)));

    $profileSubtestsById = collect([$subtestA, $subtestB])->keyBy('id');
    $profileItemsById = collect($profileItems)->keyBy('id');
    $mappings = [];
    foreach ($profileMap as $index => $itemId) {
        $position = $index + 1;
        $subtestId = $position === 4 ? 312 : 311;
        $mapping = (new OmrTemplateItemMapping)->forceFill([
            'id' => 470 + $position,
            'template_version_id' => 400,
            'test_instrument_version_id' => 300,
            'test_instrument_subtest_id' => $subtestId,
            'test_instrument_item_id' => $itemId,
            'native_label' => $labelsByPosition[$position],
            'page_no' => $pagesByPosition[$position],
            'global_position' => $position,
        ]);
        $profileItem = $profileItemsById->get($itemId);
        $profileItem->setRelation('subtest', $profileSubtestsById->get($subtestId));
        $mapping->setRelation('item', $profileItem);
        $mappings[] = $mapping;
    }
    $template->setRelation('itemMappings', new Collection($mappings));
    $template->itemMappingsRelation = Mockery::mock(HasMany::class);
    $template->itemMappingsRelation->shouldReceive('with')
        ->with('item.subtest.items')
        ->andReturnSelf();
    $template->itemMappingsRelation->shouldReceive('orderBy')
        ->with('global_position')
        ->andReturnSelf();
    $template->itemMappingsRelation->shouldReceive('get')
        ->andReturnUsing(fn (): Collection => $template->getRelation('itemMappings')
            ->sortBy('global_position')
            ->values());

    $assets = [
        testKitAsset(481, 400, OmrTemplateAsset::KIND_PRINT_PDF, null, str_repeat('e', 64)),
        testKitAsset(482, 400, OmrTemplateAsset::KIND_REFERENCE_PAGE, 1, str_repeat('f', 64)),
        testKitAsset(483, 400, OmrTemplateAsset::KIND_REFERENCE_PAGE, 2, str_repeat('1', 64)),
    ];
    $template->setRelation('canonicalAssets', new Collection($assets));

    $booklet = (new OmrQuestionBookletVersion)->forceFill([
        'id' => 500,
        'booklet_id' => 501,
        'template_version_id' => 400,
        'test_instrument_version_id' => 300,
        'version' => 2,
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        'allow_multiple_sets' => false,
        'answer_key_hash' => str_repeat('2', 64),
        'content_hash' => hash('sha256', json_encode([], JSON_THROW_ON_ERROR)),
        'finalized_by_user_id' => 1,
        'finalized_at' => '2026-10-08 12:00:00',
    ]);
    $booklet->setRelation('templateVersion', $template);
    $booklet->setRelation('booklet', (new OmrQuestionBooklet)->forceFill([
        'id' => 501,
        'institution_id' => 9,
        'is_synthetic' => true,
    ]));
    $sectionUidsBySubtest = [
        311 => '00000000-0000-4000-9000-000000000411',
        312 => '00000000-0000-4000-9000-000000000412',
    ];
    $bookletSections = [];
    foreach ([311 => ['Verbal', 1, 3], 312 => ['Logic', 2, 1]] as $subtestId => [$name, $order, $count]) {
        $bookletSections[$subtestId] = (new OmrBookletSection)->forceFill([
            'id' => 530 + $subtestId - 311,
            'booklet_version_id' => 500,
            'booklet_section_uid' => $sectionUidsBySubtest[$subtestId],
            'name' => $name,
            'display_order' => $order,
            'expected_count' => $count,
            'option_tokens' => $options,
        ]);
    }
    $intrinsicItems = [];
    foreach ([1121 => [1, 311], 1122 => [2, 311], 1123 => [3, 311], 1124 => [4, 312]] as $bookletItemId => [$position, $subtestId]) {
        $item = (new OmrBookletItem)->forceFill([
            'id' => $bookletItemId,
            'booklet_version_id' => 500,
            'booklet_section_id' => $bookletSections[$subtestId]->id,
            'booklet_item_uid' => sprintf('00000000-0000-4000-8000-%012d', 100 + $position),
            'booklet_section_uid' => $sectionUidsBySubtest[$subtestId],
            'test_instrument_item_id' => null,
            'option_tokens' => $options,
            'key_required' => $bookletItemId !== 1123,
            'authored_position' => $position,
            'correct_option_code' => $bookletItemId === 1124 ? ($options[0] ?? 'A') : null,
        ]);
        if ($bookletItemId === 1123) {
            $item->setRawAttributes([...$item->getAttributes(), 'key_required' => false], true);
        }
        $item->setRelation('section', $bookletSections[$subtestId]);
        $intrinsicItems[] = $item;
    }
    foreach ($bookletSections as $section) {
        $section->setRelation('items', new Collection(array_values(array_filter(
            $intrinsicItems,
            fn (OmrBookletItem $item): bool => $item->booklet_section_id === $section->id,
        ))));
    }
    $booklet->setRelation('sections', new Collection(array_values($bookletSections)));
    $booklet->setRelation('items', new Collection($intrinsicItems));

    $set = (new OmrBookletSet)->forceFill([
        'id' => 510,
        'booklet_version_id' => 500,
        'set_code' => 'A',
        'status' => OmrBookletSet::STATUS_FINALIZED,
        'order_hash' => '',
        'answer_key_hash' => '',
        'source_content_hash' => null,
        'finalized_at' => '2026-10-08 12:00:00',
    ]);
    $set->setRelation('bookletVersion', $booklet);
    $setItems = [
        testKitSetItem(521, 510, 321, 1, $options[0] ?? 'A', 1, 1121),
        testKitSetItem(522, 510, 322, 2, $options[1] ?? 'B', 2, 1122),
        testKitSetItem(524, 510, 323, 3, '', 3, 1123),
        testKitSetItem(523, 510, 324, 4, $options[0] ?? 'A', 4, 1124),
    ];
    foreach ($setItems as $setItem) {
        $setItem->setRelation('bookletItem', collect($intrinsicItems)->firstWhere('id', $setItem->booklet_item_id));
    }
    $set->setRelation('items', new Collection($setItems));
    testKitRefreshSetHashes($set, $booklet->content_hash);
    $booklet->setRelation('sets', new Collection([$set]));
    $compositionRows = [];
    foreach ([321 => [311, 1, 521, 1121], 322 => [311, 2, 522, 1122], 323 => [311, 3, 524, 1123], 324 => [312, 4, 523, 1124]] as $formalItemId => [$subtestId, $position, $setItemId, $bookletItemId]) {
        $compositionRows[] = [
            'template_version_id' => 400,
            'booklet_version_id' => 500,
            'booklet_set_id' => 510,
            'test_instrument_version_id' => 300,
            'disposition' => 'physical',
            'physical_item_uid' => sprintf('00000000-0000-4000-8000-%012d', $position),
            'sheet_item_id' => 430 + $position,
            'booklet_set_item_id' => $setItemId,
            'booklet_item_id' => $bookletItemId,
            'booklet_item_uid' => $bookletItemId === null ? null : sprintf('00000000-0000-4000-8000-%012d', 100 + $position),
            'booklet_section_uid' => $bookletItemId === null ? null : $sectionUidsBySubtest[$subtestId],
            'test_instrument_subtest_id' => $subtestId,
            'test_instrument_item_id' => $formalItemId,
        ];
    }
    $componentVector = [
        'test_kit_id' => 600,
        'institution_id' => 9,
        'is_synthetic' => true,
        'template_version_id' => 400,
        'booklet_version_id' => 500,
        'booklet_set_id' => 510,
        'test_instrument_version_id' => 300,
        'sheet' => [
            'template_id' => 401,
            'version' => 4,
            'native_bundle_hash' => str_repeat('c', 64),
            'template_json_hash' => str_repeat('b', 64),
            'config_hash' => str_repeat('a', 64),
            'review_manifest_hash' => str_repeat('d', 64),
            'layout_blueprint_hash' => null,
            'physical_inventory_sha256' => (new PhysicalSheetInventory)->forVersion($template)['inventory_digest'],
        ],
        'booklet' => [
            'booklet_id' => 501,
            'version' => 2,
            'content_hash' => $booklet->content_hash,
            'answer_key_hash' => $booklet->answer_key_hash,
            'authoring_mode' => $booklet->authoring_mode,
            'set_code' => 'A',
            'set_order_hash' => $set->order_hash,
            'set_answer_key_hash' => $set->answer_key_hash,
        ],
        'profile' => [
            'instrument_id' => 301,
            'version_number' => 1,
            'content_sha256' => $profile->content_sha256,
        ],
    ];

    return [
        'template' => $template,
        'booklet' => $booklet,
        'set' => $set,
        'profile' => $profile,
        'identity' => [
            'test_kit_id' => 600,
            'public_id' => 'kit-public-600',
            'version_number' => 1,
            'provenance_class' => 'synthetic',
        ],
        'composition_rows' => $compositionRows,
        'component_vector' => $componentVector,
    ];
}

function testKitProfileItem(int $id, int $subtestId, string $code, string $localNumber, int $order, bool $scored, array $tokens): TestInstrumentItem
{
    return (new TestInstrumentItem)->forceFill([
        'id' => $id,
        'test_instrument_version_id' => 300,
        'test_instrument_subtest_id' => $subtestId,
        'item_code' => $code,
        'local_number' => $localNumber,
        'display_order' => $order,
        'is_scored' => $scored,
        'option_tokens' => $tokens,
    ]);
}

function testKitCountMetric(int $id, int $subtestId, string $code, string $maximum): TestInstrumentMetric
{
    return (new TestInstrumentMetric)->forceFill([
        'id' => $id,
        'test_instrument_version_id' => 300,
        'code' => $code,
        'name' => $code,
        'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
        'test_instrument_subtest_id' => $subtestId,
        'unit' => 'count',
        'min_raw_value' => '0.000000',
        'max_raw_value' => $maximum.'.000000',
        'precision_scale' => 0,
        'is_required' => true,
        'required_output_scales' => [],
        'procedure_reference' => null,
        'construct_aptitude_area_id' => null,
    ]);
}

function testKitSetItem(int $id, int $setId, int $itemId, int $position, string $correctToken, ?int $globalPosition = null, ?int $bookletItemId = null): OmrBookletSetItem
{
    return (new OmrBookletSetItem)->forceFill([
        'id' => $id,
        'booklet_set_id' => $setId,
        'booklet_item_id' => $bookletItemId,
        'test_instrument_item_id' => $itemId,
        'aptitude_area_id' => 99,
        'area_position' => $position,
        'global_position' => $globalPosition ?? $position,
        'correct_option_code' => $correctToken,
        'content_hash' => null,
    ]);
}

function testKitAsset(int $id, int $templateId, string $kind, ?int $pageNo, string $sha256): OmrTemplateAsset
{
    return (new OmrTemplateAsset)->forceFill([
        'id' => $id,
        'template_version_id' => $templateId,
        'kind' => $kind,
        'page_no' => $pageNo,
        'sha256' => $sha256,
        'mime' => 'application/pdf',
        'bytes' => 1024,
    ]);
}

function testKitRefreshSetHashes(OmrBookletSet $set, ?string $bookletContentHash): void
{
    $identityRows = $set->items->sortBy('global_position')->map(static fn (OmrBookletSetItem $item): array => [
        'test_instrument_item_id' => (int) $item->test_instrument_item_id,
        'aptitude_area_id' => (int) $item->aptitude_area_id,
        'area_position' => (int) $item->area_position,
        'global_position' => (int) $item->global_position,
        'correct_option_code' => (string) $item->correct_option_code,
    ])->values()->all();
    $set->answer_key_hash = hash('sha256', json_encode($identityRows, JSON_THROW_ON_ERROR));
    $set->order_hash = hash('sha256', json_encode(array_map(
        static fn (array $row): array => array_diff_key($row, ['correct_option_code' => true]),
        $identityRows,
    ), JSON_THROW_ON_ERROR));
    $set->source_content_hash = $bookletContentHash;
}
function testKitManifestRefreshVector(array &$fixture): void
{
    $fixture['component_vector']['booklet']['content_hash'] = $fixture['booklet']->content_hash;
    $fixture['component_vector']['booklet']['answer_key_hash'] = $fixture['booklet']->answer_key_hash;
    $fixture['component_vector']['booklet']['set_order_hash'] = $fixture['set']->order_hash;
    $fixture['component_vector']['booklet']['set_answer_key_hash'] = $fixture['set']->answer_key_hash;
    $fixture['component_vector']['profile']['content_sha256'] = $fixture['profile']->content_sha256;
}

test('manifest canonicalization is stable across relation ordering', function (): void {
    $fixture = testKitManifestFixture();
    $builder = new TestKitManifestBuilder;
    $first = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    $fixture['template']->setRelation('pages', $fixture['template']->pages->reverse()->values());
    $fixture['template']->setRelation('areas', $fixture['template']->areas->reverse()->values());
    $fixture['template']->setRelation('itemMappings', $fixture['template']->itemMappings->reverse()->values());
    $fixture['template']->setRelation('canonicalAssets', $fixture['template']->canonicalAssets->reverse()->values());
    $fixture['set']->setRelation('items', $fixture['set']->items->reverse()->values());

    $second = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    expect($second['manifest'])->toBe($first['manifest'])
        ->and($second['sha256'])->toBe($first['sha256']);
});

test('schema two rebuild preserves historical composition row shape when exact pins are hydrated', function (): void {
    $fixture = testKitManifestFixture();
    $historicalRows = array_map(static function (array $row): array {
        unset(
            $row['template_version_id'],
            $row['booklet_version_id'],
            $row['booklet_set_id'],
            $row['test_instrument_version_id'],
        );

        return $row;
    }, $fixture['composition_rows']);
    $builder = new TestKitManifestBuilder;
    $buildHistorical = fn (): array => $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $historicalRows,
        $fixture['component_vector'],
    );
    $firstHistorical = $buildHistorical();
    $rebuiltHistorical = $buildHistorical();
    $current = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    expect($firstHistorical['manifest']['manifest_schema_version'])->toBe(2)
        ->and($firstHistorical['manifest']['composition']['rows'][0])->not->toHaveKeys([
            'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id',
        ])
        ->and($rebuiltHistorical['manifest'])->toBe($firstHistorical['manifest'])
        ->and($rebuiltHistorical['sha256'])->toBe($firstHistorical['sha256'])
        ->and($current['manifest'])->toBe($firstHistorical['manifest'])
        ->and($current['sha256'])->toBe($firstHistorical['sha256']);
});

test('manifest rejects a well-formed but incorrect approved profile digest', function (): void {
    $fixture = testKitManifestFixture();
    $fixture['profile']->content_sha256 = str_repeat('0', 64);

    expect(fn () => (new TestKitManifestBuilder)->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    ))->toThrow(RuntimeException::class, 'The approved profile content hash does not match its exact profile and scoring rules.');
});

test('material changes alter the manifest without needing physical qualification', function (): void {
    $fixture = testKitManifestFixture();
    $builder = new TestKitManifestBuilder;
    $first = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    $fixture['template']->engine_commit = 'commit-2';
    $second = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    expect($second['sha256'])->not->toBe($first['sha256'])
        ->and($second['manifest']['kit']['provenance_class'])->toBe('synthetic')
        ->and(array_key_exists('physical_qualification', $second['manifest']))->toBeFalse();
});

test('source kind, print overlay, and live row geometry change material identity', function (): void {
    $fixture = testKitManifestFixture();
    $builder = new TestKitManifestBuilder;
    $build = function () use ($builder, &$fixture): array {
        $componentVector = $fixture['component_vector'];
        $componentVector['sheet']['physical_inventory_sha256'] = (new PhysicalSheetInventory)
            ->forVersion($fixture['template'])['inventory_digest'];

        return $builder->build(
            $fixture['template'],
            $fixture['booklet'],
            $fixture['set'],
            $fixture['profile'],
            9,
            $fixture['identity'],
            $fixture['composition_rows'],
            $componentVector,
        );
    };
    $baseline = $build();

    $fixture['template']->authoring_manifest = ['source_kind' => 'uploaded_original'];
    $uploaded = $build();
    $fixture['template']->authoring_manifest = ['source_kind' => 'system_designed'];
    $overlayJson = $fixture['template']->print_overlay_json;
    $overlayJson['header']['text'] = 'UPDATED';
    $fixture['template']->print_overlay_json = $overlayJson;
    $overlay = $build();
    $fixture['template']->print_overlay_json = ['header' => ['text' => 'EXAM']];
    $fixture['template']->areas[0]->questions[0]->region_x = 0.75;
    $geometry = $build();

    expect($uploaded['sha256'])->not->toBe($baseline['sha256'])
        ->and($overlay['sha256'])->not->toBe($baseline['sha256'])
        ->and($geometry['sha256'])->not->toBe($baseline['sha256'])
        ->and($baseline['manifest']['sheet']['source_kind'])->toBe('system_designed')
        ->and($baseline['manifest']['sheet']['live_geometry']['projection_rows'][0]['questions'][0]['region']['x'])->toBe(0.1);
});

test('acceptance protocol, plan, proof, and review identity do not change material identity', function (): void {
    $fixture = testKitManifestFixture();
    $builder = new TestKitManifestBuilder;
    $baseline = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    $reviewManifest = $fixture['template']->review_manifest_json;
    $reviewManifest['acceptance_protocol_id'] = 'protocol-next';
    $reviewManifest['acceptance_plan_id'] = 'plan-next';
    $fixture['template']->review_manifest_json = $reviewManifest;
    $fixture['template']->setRelation('canonicalAssets', $fixture['template']->canonicalAssets->concat([
        testKitAsset(484, 400, 'digital_proof', null, str_repeat('9', 64)),
    ]));
    $protocolOnly = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    expect($protocolOnly['sha256'])->toBe($baseline['sha256']);
});

test('profile rules and the exact Set A key participate in material identity', function (): void {
    $fixture = testKitManifestFixture();
    $builder = new TestKitManifestBuilder;
    $baseline = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    $metric = $fixture['profile']->metrics[0];
    $metric->procedure_reference = 'SOP-RAW-REV-2';
    $profileValidator = new TestInstrumentProfileValidator;
    $fixture['profile']->content_sha256 = $profileValidator->digest($profileValidator->normalizeVersion($fixture['profile']));
    testKitManifestRefreshVector($fixture);
    $rulesChanged = $builder->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    $keyFixture = testKitManifestFixture();
    $keyBaseline = $builder->build(
        $keyFixture['template'],
        $keyFixture['booklet'],
        $keyFixture['set'],
        $keyFixture['profile'],
        9,
        $keyFixture['identity'],
        $keyFixture['composition_rows'],
        $keyFixture['component_vector'],
    );
    $keyFixture['set']->items[0]->correct_option_code = 'B';
    testKitRefreshSetHashes($keyFixture['set'], $keyFixture['booklet']->content_hash);
    testKitManifestRefreshVector($keyFixture);
    $keyChanged = $builder->build(
        $keyFixture['template'],
        $keyFixture['booklet'],
        $keyFixture['set'],
        $keyFixture['profile'],
        9,
        $keyFixture['identity'],
        $keyFixture['composition_rows'],
        $keyFixture['component_vector'],
    );

    expect($rulesChanged['sha256'])->not->toBe($baseline['sha256'])
        ->and($keyChanged['sha256'])->not->toBe($keyBaseline['sha256'])
        ->and($baseline['manifest']['scoring']['raw_evaluator_identity'])->toBe(AssessmentRawEvaluator::KERNEL_VERSION);
});

test('manifest excludes acceptance protocol, proof, and plaintext key bindings', function (): void {
    $fixture = testKitManifestFixture();
    $result = (new TestKitManifestBuilder)->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $fixture['identity'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );
    $manifest = $result['manifest'];

    expect(array_key_exists('protocol', $manifest))->toBeFalse()
        ->and(array_key_exists('plan', $manifest))->toBeFalse()
        ->and(array_key_exists('proof', $manifest))->toBeFalse()
        ->and(array_key_exists('correct_token', $manifest['item_map']['bindings'][0]))->toBeFalse()
        ->and(array_key_exists('acceptance_receipt_id', $manifest))->toBeFalse();
    expect(array_column($manifest['item_map']['bindings'], 'native_label'))->not->toContain('APPLICANT_NUMBER_1');
});

test('manifest rejects unknown identity data instead of hashing protocol or proof', function (): void {
    $fixture = testKitManifestFixture();
    $identity = [...$fixture['identity'], 'protocol_hash' => str_repeat('a', 64)];

    expect(fn () => (new TestKitManifestBuilder)->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        9,
        $identity,
        $fixture['composition_rows'],
        $fixture['component_vector'],
    ))->toThrow(RuntimeException::class);
});

test('item map accepts only an exact within-subtest Set A permutation and preserves custom tokens', function (): void {
    $tokens = ['a-B', 'A-c', 'b-C'];
    $fixture = testKitManifestFixture($tokens);
    $setRows = $fixture['set']->items->all();
    $setRows[0]->test_instrument_item_id = 322;
    $setRows[0]->correct_option_code = 'A-c';
    $setRows[1]->test_instrument_item_id = 321;
    $setRows[1]->correct_option_code = 'a-B';
    $fixture['set']->setRelation('items', new Collection($setRows));
    testKitRefreshSetHashes($fixture['set'], $fixture['booklet']->content_hash);
    $fixture['composition_rows'][0]['test_instrument_item_id'] = 322;
    $fixture['composition_rows'][0]['formal_item_id'] = 322;
    $fixture['composition_rows'][1]['test_instrument_item_id'] = 321;
    $fixture['composition_rows'][1]['formal_item_id'] = 321;
    testKitManifestRefreshVector($fixture);

    $contract = (new ExactTestKitItemMap)->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    );

    expect($contract['bindings'][0]['sheet_item_id'])->toBe(431)
        ->and($contract['bindings'][0]['item_id'])->toBe(322)
        ->and($contract['bindings'][0]['correct_token'])->toBe('A-c')
        ->and($contract['bindings'][0]['option_tokens'])->toBe($tokens)
        ->and($contract['bindings'][1]['item_id'])->toBe(321)
        ->and($contract['bindings'][2]['correct_token'])->toBeNull()
        ->and($contract['items'])->toHaveCount(4);
});
test('item map excludes every declared identity field even when its native label is custom', function (): void {
    $fixture = testKitManifestFixture();
    $reviewManifest = $fixture['template']->review_manifest_json;
    $reviewManifest['identity']['labels'][] = 'CAMPUS_RECORD_TOKEN';
    $fixture['template']->review_manifest_json = $reviewManifest;
    $fixture['template']->itemMappings[0]->native_label = 'CAMPUS_RECORD_TOKEN';

    expect(fn () => (new ExactTestKitItemMap)->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    ))->toThrow(RuntimeException::class);
});

test('item map rejects a missing sealed composition item', function (): void {
    $fixture = testKitManifestFixture();
    array_pop($fixture['composition_rows']);

    expect(fn () => (new ExactTestKitItemMap)->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    ))->toThrow(RuntimeException::class, 'explicitly account for every physical location');
});

test('item map rejects a native page mismatch and an option-token mismatch', function (): void {
    $pageMismatch = testKitManifestFixture();
    $pageMismatch['template']->itemMappings[0]->page_no = 2;

    $labelMismatch = testKitManifestFixture();
    $labelMismatch['template']->itemMappings[0]->native_label = 'UNDECLARED_Q_1';

    $optionMismatch = testKitManifestFixture();
    $reviewManifest = $optionMismatch['template']->review_manifest_json;
    $reviewManifest['fields'][0]['option'] = 'WRONG';
    $optionMismatch['template']->review_manifest_json = $reviewManifest;
    foreach ($optionMismatch['template']->pages as $page) {
        $pageManifest = $page->page_manifest_json;
        $pageManifest['fields'][0]['option'] = 'WRONG';
        $page->page_manifest_json = $pageManifest;
    }
    expect(fn () => (new ExactTestKitItemMap)->build(
        $pageMismatch['template'],
        $pageMismatch['booklet'],
        $pageMismatch['set'],
        $pageMismatch['profile'],
        $pageMismatch['composition_rows'],
        $pageMismatch['component_vector'],
    ))->toThrow(RuntimeException::class)
        ->and(fn () => (new ExactTestKitItemMap)->build(
            $labelMismatch['template'],
            $labelMismatch['booklet'],
            $labelMismatch['set'],
            $labelMismatch['profile'],
            $labelMismatch['composition_rows'],
            $labelMismatch['component_vector'],
        ))->toThrow(RuntimeException::class)
        ->and(fn () => (new ExactTestKitItemMap)->build(
            $optionMismatch['template'],
            $optionMismatch['booklet'],
            $optionMismatch['set'],
            $optionMismatch['profile'],
            $optionMismatch['composition_rows'],
            $optionMismatch['component_vector'],
        ))->toThrow(RuntimeException::class);
});

test('item map rejects a stale Set A answer-key hash', function (): void {
    $fixture = testKitManifestFixture();
    $fixture['set']->answer_key_hash = str_repeat('9', 64);

    expect(fn () => (new ExactTestKitItemMap)->build(
        $fixture['template'],
        $fixture['booklet'],
        $fixture['set'],
        $fixture['profile'],
        $fixture['composition_rows'],
        $fixture['component_vector'],
    ))->toThrow(RuntimeException::class);
});

test('item map rejects a stale native page hash, duplicate Set position, and cross-subtest swap', function (): void {
    $hashMismatch = testKitManifestFixture();
    $hashMismatch['template']->pages[0]->page_manifest_hash = str_repeat('9', 64);

    $duplicatePosition = testKitManifestFixture();
    $duplicatePosition['set']->setRelation('items', $duplicatePosition['set']->items->concat([
        testKitSetItem(524, 510, 322, 1, 'A'),
    ]));

    $crossSubtestSwap = testKitManifestFixture();
    $crossSubtestSwap['set']->items[0]->test_instrument_item_id = 324;
    testKitRefreshSetHashes($crossSubtestSwap['set'], $crossSubtestSwap['booklet']->content_hash);

    expect(fn () => (new ExactTestKitItemMap)->build(
        $hashMismatch['template'],
        $hashMismatch['booklet'],
        $hashMismatch['set'],
        $hashMismatch['profile'],
        $hashMismatch['composition_rows'],
        $hashMismatch['component_vector'],
    ))->toThrow(RuntimeException::class)
        ->and(fn () => (new ExactTestKitItemMap)->build(
            $duplicatePosition['template'],
            $duplicatePosition['booklet'],
            $duplicatePosition['set'],
            $duplicatePosition['profile'],
            $duplicatePosition['composition_rows'],
            $duplicatePosition['component_vector'],
        ))->toThrow(RuntimeException::class)
        ->and(fn () => (new ExactTestKitItemMap)->build(
            $crossSubtestSwap['template'],
            $crossSubtestSwap['booklet'],
            $crossSubtestSwap['set'],
            $crossSubtestSwap['profile'],
            $crossSubtestSwap['composition_rows'],
            $crossSubtestSwap['component_vector'],
        ))->toThrow(RuntimeException::class);
});
