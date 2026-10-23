<?php

declare(strict_types=1);

use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalMapping;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\SaveTestInstrumentDraft;
use App\Assessment\Actions\SaveTestKitComposition;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Support\KitCompositionContext;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function prepareTestKitAcceptanceFixtureEnvironment(): void
{
    static $root = null;
    if (app()->environment() !== 'testing') {
        throw new RuntimeException('Test-kit acceptance fixtures may only write in the testing environment.');
    }

    $connection = (string) config('database.default');
    $database = config("database.connections.{$connection}.database");
    $driver = (string) config("database.connections.{$connection}.driver");
    $safeDedicatedOmrCameraPostgres = $driver === 'pgsql'
        && $database === 'nexiam_omr_assisted_verify_20261019_a4c8'
        && defined('OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE')
        && constant('OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE') === 'nexiam_omr_assisted_verify_20261019_a4c8'
        && getenv('APP_ENV') === 'testing'
        && getenv('DB_CONNECTION') === 'pgsql'
        && getenv('DB_DATABASE') === 'nexiam_omr_assisted_verify_20261019_a4c8'
        && getenv('DB_URL') === ''
        && getenv('NEXIAM_TEST_PIN_SQLITE') === '0'
        && config('database.default') === 'pgsql'
        && (string) config('database.connections.pgsql.database') === 'nexiam_omr_assisted_verify_20261019_a4c8'
        && (string) config('database.connections.pgsql.url') === ''
        && (string) DB::connection()->scalar('SELECT current_database()') === 'nexiam_omr_assisted_verify_20261019_a4c8';
    $safeDatabase = ($driver === 'sqlite' && $database === ':memory:')
        || ($driver === 'pgsql' && is_string($database) && str_starts_with($database, 'nexiam_test_kit_acceptance_'))
        || $safeDedicatedOmrCameraPostgres;
    if (! $safeDatabase) {
        throw new RuntimeException('Test-kit acceptance fixtures require SQLite :memory:, a nexiam_test_kit_acceptance_* PostgreSQL database, or the exact guarded OMR camera PostgreSQL acceptance database.');
    }

    if ($root === null) {
        $root = storage_path('framework/testing/assessment-composition/'.Str::uuid());
        if (! mkdir($root.'/sensitive', 0700, true) || ! mkdir($root.'/local', 0700, true)) {
            throw new RuntimeException('Unable to create private acceptance-fixture storage.');
        }
        register_shutdown_function(static function () use ($root): void {
            (new Filesystem)->deleteDirectory($root);
        });
    }
    Storage::set('sensitive', Storage::build([
        'driver' => 'local',
        'root' => $root.'/sensitive',
        'throw' => true,
    ]));
    Storage::set('local', Storage::build([
        'driver' => 'local',
        'root' => $root.'/local',
        'throw' => true,
    ]));
}

function testKitAcceptanceFixture(User $actor, Institution $institution, bool $assemble = true, string $scenario = 'native'): array
{
    require_once __DIR__.'/PhysicalChallengeHelpers.php';
    prepareTestKitAcceptanceFixtureEnvironment();
    if (! in_array($scenario, ['native', 'opaque-swapped', 'optional-attachment'], true)) {
        throw new RuntimeException('Unknown synthetic acceptance fixture scenario.');
    }
    $opaqueSwapped = $scenario === 'opaque-swapped';
    $optionalAttachment = $scenario === 'optional-attachment';
    $suffix = Str::upper(Str::random(8));
    $area = AptitudeArea::query()->updateOrCreate(
        ['code' => 'LOGIC'],
        ['name' => 'Logic', 'max_items' => 2, 'display_order' => 1, 'is_active' => true],
    );
    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'code' => 'KIT-ACCEPT-'.$suffix,
        'name' => 'Synthetic acceptance software fixture',
        'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);
    $instrument = new TestInstrument;
    $instrument->forceFill([
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'code' => 'TI-ACCEPT-'.$suffix,
    ])->save();
    $profile = TestInstrumentVersion::query()->create([
        'test_instrument_id' => $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'display_name' => 'New synthetic acceptance profile',
        'lock_version' => 1,
        'content_sha256' => null,
        'created_by_user_id' => $actor->id,
    ]);
    $subtestCode = 'COUNT-'.$suffix;
    $itemOneCode = 'COUNT-'.$suffix.'-V1';
    $itemTwoCode = 'COUNT-'.$suffix.'-V2';
    $nativeLabelByLocalNumber = ['1' => 'LOGIC_Q_1', '2' => 'LOGIC_Q_2'];
    $optionTokens = $opaqueSwapped ? ['token::cobalt', 'token::amber'] : ['A', 'B'];
    $profile = app(SaveTestInstrumentDraft::class)->handle(
        $actor,
        (int) $instrument->id,
        (int) $profile->id,
        (int) $profile->lock_version,
        'synthetic-acceptance-draft-'.$suffix,
        [
            'profile' => [
                'code' => $instrument->code,
                'display_name' => 'Synthetic two-item count profile',
                'publisher_name' => 'Synthetic software fixture; no publisher represented',
                'form_code' => 'SYN-COUNT-'.$suffix,
                'edition' => 'Synthetic fixture',
                'language' => 'Synthetic fixture',
                'source_reference' => $opaqueSwapped
                    ? 'repository-artifact:tests/Fixtures/TestKit/two-item-count-opaque-swapped-v1.json; AI-authored synthetic software-only procedure, not an institutional source'
                    : 'repository-artifact:tests/Fixtures/TestKit/two-item-count-native-v1.json; this is a synthetic software procedure, not an institutional source',
                'permitted_use_reference' => 'Synthetic software acceptance only; no institutional source or rights claim',
                'material_requirement' => $optionalAttachment
                    ? TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER
                    : TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
                'administration_procedure_reference' => $opaqueSwapped
                    ? 'Synthetic software acceptance procedure: physical slot KAPPA is LOGIC_Q_2→formal item 2/token::cobalt; slot OMEGA is LOGIC_Q_1→formal item 1/token::amber; one point per exact match; no institutional or publisher procedure is represented.'
                    : 'Synthetic software acceptance procedure: one page, fixed Set A, LOGIC_Q_1=A and LOGIC_Q_2=B, one point per exact match; no institutional or publisher procedure is represented.',
            ],
            'subtests' => [[
                'code' => $subtestCode,
                'name' => 'Synthetic two-item count',
                'display_order' => 1,
                'response_mode' => 'keyed_items',
                'item_order_policy' => $opaqueSwapped ? 'within_subtest_permutable' : 'fixed',
                'items' => [
                    ['item_code' => $itemOneCode, 'local_number' => '1', 'display_order' => 1, 'is_scored' => true, 'option_tokens' => $opaqueSwapped ? ['token::cobalt', 'token::amber'] : ['A', 'B']],
                    ['item_code' => $itemTwoCode, 'local_number' => '2', 'display_order' => 2, 'is_scored' => true, 'option_tokens' => $opaqueSwapped ? ['token::cobalt', 'token::amber'] : ['A', 'B']],
                ],
            ]],
            'metrics' => [[
                'code' => 'RAW_COUNT',
                'name' => 'Correct item count',
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                'subtest_code' => $subtestCode,
                'unit' => 'count',
                'min_raw_value' => '0',
                'max_raw_value' => '2',
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => [],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => (int) $area->id,
                'input_codes' => [],
                'input_contract_json' => [
                    'allowed_sources' => $optionalAttachment ? ['manual', 'import'] : ['import', 'manual', 'omr'],
                    'required_resources' => $optionalAttachment ? [] : ['answer_sheet', 'booklet_form', 'key'],
                    'source_requirements' => array_fill_keys($optionalAttachment ? ['manual', 'import'] : ['import', 'manual', 'omr'], [
                        'unit' => 'count',
                        'identity_requirements' => $optionalAttachment
                            ? ['administration', 'metric']
                            : ['administration', 'answer_sheet', 'booklet_form', 'key', 'metric'],
                    ]),
                ],
            ]],
        ],
    );
    $profile = app(ApproveTestInstrumentVersion::class)->approveDemoVersion(
        $actor,
        (int) $instrument->id,
        (int) $profile->id,
        (int) $profile->lock_version,
        'synthetic-acceptance-profile-'.$suffix,
    );
    $profile->load(['subtests.items', 'metrics']);
    $subtest = $profile->subtests->firstOrFail();
    $items = $subtest->items->sortBy('display_order')->values();
    $metric = $profile->metrics->firstOrFail();

    OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'code' => 'SHEET-ACCEPT-'.$suffix,
        'name' => 'Synthetic acceptance sheet',
    ]);
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'SHEET-ACCEPT-'.$suffix,
        'Synthetic two-item acceptance sheet',
        OmrTemplateAuthoringDraft::SOURCE_HTML,
        [
            'pages' => [['page_no' => 1, 'confirmed' => true]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'verification' => null,
        ],
        OmrRegistrationMode::DocumentBound,
        OmrQrMode::Disabled,
        1,
    );
    $draft->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED])->save();
    $draft = app(SaveOmrCanonicalMapping::class)->handle(
        $draft->fresh(),
        $actor,
        [
            'page_width' => 2480,
            'page_height' => 3508,
            'page_count' => 1,
            'blocks' => [[
                'name' => 'COUNT_ITEMS',
                'bubble_values' => $optionTokens,
                'labels' => $opaqueSwapped ? ['LOGIC_Q_2', 'LOGIC_Q_1'] : ['LOGIC_Q_1', 'LOGIC_Q_2'],
                'rows' => 2,
                'origin_x' => 295,
                'origin_y' => 826,
                'width' => 190,
                'height' => 160,
                'option_pitch_px' => 71,
                'row_pitch_px' => 80,
                'bubble_width_px' => 53,
            ]],
        ],
        (int) $draft->lock_version,
    );

    $sectionUid = (string) Str::uuid();
    $physicalInventory = [
        'revision' => 1,
        'sections' => [[
            'printed_section_uid' => $sectionUid,
            'name' => 'Synthetic logic items',
            'page_no' => 1,
            'numbering' => ['start' => 1, 'step' => 1],
            'expected_item_count' => 2,
            'items' => array_map(
                static fn (string $nativeLabel, int $index): array => [
                    'physical_item_uid' => (string) Str::uuid(),
                    'native_label' => $nativeLabel,
                    'page_no' => 1,
                    'printed_number' => $index + 1,
                    'option_tokens' => $optionTokens,
                    'guide_id' => 'COUNT_ITEMS_'.($index + 1),
                ],
                $opaqueSwapped ? ['LOGIC_Q_2', 'LOGIC_Q_1'] : ['LOGIC_Q_1', 'LOGIC_Q_2'],
                [0, 1],
            ),
        ]],
    ];
    $draft = app(SaveOmrCanonicalStudioState::class)->handle(
        $draft->fresh(),
        $actor,
        ['boxes' => []],
        null,
        ['physicalInventory' => $physicalInventory],
        (int) $draft->lock_version,
    );
    $disk = (string) config('omr.canonical.storage.disk', 'local');
    config(['omr.canonical.storage.disk' => 'sensitive']);
    try {
        $draft = app(MaterializeOmrCanonicalDraft::class)->handle(
            $draft->fresh(['markerProfile', 'template']),
            $actor,
            (int) $draft->lock_version,
        );
    } finally {
        config(['omr.canonical.storage.disk' => $disk]);
    }
    physicalChallengeSeedDocumentBoundDigitalPass($draft->fresh(['assets', 'template']), $actor);
    $compiled = app(CompileOmrCanonicalDraft::class)->handle($draft->fresh(['assets', 'template']), $actor);
    $sheet = $compiled->compiledVersion->fresh(['itemMappings', 'template']);

    $booklet = app(CreateBookletDraft::class)->handle(
        'Synthetic two-item acceptance booklet '.$suffix,
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $actor,
        isSynthetic: true,
    );
    $answerKeyByNativeLabel = $opaqueSwapped
        ? ['LOGIC_Q_1' => 'token::amber', 'LOGIC_Q_2' => 'token::cobalt']
        : ['LOGIC_Q_1' => 'A', 'LOGIC_Q_2' => 'B'];
    $nativeLabelsByAuthoredOrder = $opaqueSwapped
        ? ['LOGIC_Q_2', 'LOGIC_Q_1']
        : ['LOGIC_Q_1', 'LOGIC_Q_2'];
    $formalItemIdByNativeLabel = [
        'LOGIC_Q_1' => (int) $items->firstWhere('local_number', '1')->id,
        'LOGIC_Q_2' => (int) $items->firstWhere('local_number', '2')->id,
    ];
    $bookletSectionUid = (string) Str::uuid();
    $bookletItemUidByNativeLabel = array_combine(
        $nativeLabelsByAuthoredOrder,
        array_map(static fn (): string => (string) Str::uuid(), $nativeLabelsByAuthoredOrder),
    );
    $authoredItems = [];
    foreach ($nativeLabelsByAuthoredOrder as $position => $nativeLabel) {
        $authoredItems[] = [
            'booklet_item_uid' => $bookletItemUidByNativeLabel[$nativeLabel],
            'booklet_section_uid' => $bookletSectionUid,
            'section_name' => 'Synthetic logic items',
            'section_display_order' => 1,
            'section_expected_count' => 2,
            'option_tokens' => $optionTokens,
            'authored_position' => $position + 1,
            'key_required' => true,
        ];
    }
    $booklet = app(SaveAuthoredBookletVersion::class)->handle($booklet, $authoredItems, $actor);
    $keys = [];
    foreach ($nativeLabelsByAuthoredOrder as $position => $nativeLabel) {
        $keys[] = [
            'booklet_item_uid' => $bookletItemUidByNativeLabel[$nativeLabel],
            'test_instrument_item_id' => $formalItemIdByNativeLabel[$nativeLabel],
            'aptitude_area_id' => (int) $area->id,
            'area_position' => $position + 1,
            'global_position' => $position + 1,
            'correct_option_code' => $answerKeyByNativeLabel[$nativeLabel],
        ];
    }
    app(SaveBookletSetKeys::class)->handle($booklet, 'A', $keys, $actor);
    $booklet = app(FinalizeBookletVersion::class)->handle($booklet, $actor)->fresh(['sets']);
    $set = $booklet->sets->firstWhere('set_code', 'A');
    if (! $set instanceof OmrBookletSet) {
        throw new RuntimeException('Synthetic acceptance fixture did not finalize Set A.');
    }
    $set->load('items');
    $bookletItemIdByFormalId = [];
    foreach ($set->items as $setOccurrence) {
        $nativeLabel = array_search((string) $setOccurrence->booklet_item_uid, $bookletItemUidByNativeLabel, true);
        if (! is_string($nativeLabel)) {
            throw new RuntimeException('Synthetic acceptance fixture contains a Set occurrence without an explicit intrinsic UID pairing.');
        }
        $bookletItemIdByFormalId[$formalItemIdByNativeLabel[$nativeLabel]] = (int) $setOccurrence->booklet_item_id;
    }

    $preparation = app(StartTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $preparation = app(SaveTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
        'answer_sheet_draft_id' => null,
        'test_instrument_version_id' => (int) $profile->id,
        'norm_package_version_id' => null,
        'template_version_id' => (int) $sheet->id,
        'booklet_version_id' => (int) $booklet->id,
        'booklet_set_id' => (int) $set->id,
    ]);
    $resolved = app(KitCompositionContext::class)->resolvePreparation($preparation);
    $physicalByLabel = collect($resolved['physical_inventory']['items'])->keyBy('native_label');
    $compositionRows = [];
    foreach ($profile->subtests as $subtest) {
        foreach ($subtest->items as $item) {
            if (! $item->is_scored) {
                continue;
            }
            $nativeLabel = $nativeLabelByLocalNumber[(string) $item->local_number] ?? null;
            $physical = $nativeLabel === null ? null : $physicalByLabel->get($nativeLabel);
            $bookletItemId = $nativeLabel === null
                ? null
                : ($bookletItemIdByFormalId[(int) $item->id] ?? null);
            if ($physical === null || $bookletItemId === null || $bookletItemId < 1) {
                throw new RuntimeException('Synthetic acceptance fixture is missing an explicitly paired physical, intrinsic, and formal item identity.');
            }
            $compositionRows[] = [
                'disposition' => 'physical',
                'physical_item_uid' => (string) $physical['physical_item_uid'],
                'booklet_item_id' => $bookletItemId,
                'formal_subtest_id' => (int) $subtest->id,
                'formal_item_id' => (int) $item->id,
                'option_tokens' => array_values($physical['option_tokens']),
            ];
        }
    }
    $preparation = app(SaveTestKitComposition::class)->handle($actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'component_vector' => $resolved['component_vector'],
        'rows' => $compositionRows,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $resolved = app(KitCompositionContext::class)->resolvePreparation($preparation->fresh());
    $assembled = $assemble
        ? app(AssembleTestKitPreparation::class)->handle($actor, (int) $kit->id, [
            'preparation_id' => (int) $preparation->id,
            'expected_revision' => (int) $preparation->revision,
            'idempotency_key' => (string) Str::uuid(),
        ])
        : null;
    $literalPlan = json_decode(
        file_get_contents(base_path('tests/Fixtures/TestKit/'.($opaqueSwapped ? 'two-item-count-opaque-swapped-v1' : 'two-item-count-native-v1').'.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    return [
        'kit' => $kit,
        'version' => $assembled?->fresh(['templateVersion', 'bookletVersion', 'bookletSet', 'testInstrumentVersion']),
        'profile' => $profile,
        'metric' => $metric,
        'plan' => $literalPlan,
        'sheet' => $sheet,
        'booklet' => $booklet,
        'set' => $set,
        'preparation' => $preparation,
        'composition_rows' => $resolved['rows'],
        'component_vector' => $resolved['component_vector'],
        'acceptance_source_key' => $opaqueSwapped ? 'two-item-count-opaque-swapped-v1' : 'two-item-count-native-v1',
    ];
}
/**
 * Create and lock one exact Setup-only Test Kit without paper materials or acceptance evidence.
 *
 * @return array{kit:TestKit,preparation:TestKitPreparation,version:TestKitVersion,profile:TestInstrumentVersion}
 */
function testKitSetupOnlyFixture(User $actor, TestInstrumentVersion $profile): array
{
    $profile->loadMissing('instrument');
    if ($profile->status !== TestInstrumentVersion::STATUS_APPROVED
        || $profile->instrument === null
        || $profile->instrument->institution_id === null) {
        throw new RuntimeException('Setup-only fixture requires an existing institution-owned approved Setup.');
    }
    $suffix = Str::upper(Str::random(8));
    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => (int) $profile->instrument->institution_id,
        'code' => 'KIT-SETUP-ONLY-'.$suffix,
        'name' => 'Setup-only scoring fixture',
        'is_synthetic' => (bool) $profile->instrument->is_synthetic,
        'created_by_user_id' => $actor->id,
    ]);
    $preparation = app(StartTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $preparation = app(SaveTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
        'answer_sheet_draft_id' => null,
        'test_instrument_version_id' => (int) $profile->id,
        'norm_package_version_id' => null,
        'template_version_id' => null,
        'booklet_version_id' => null,
        'booklet_set_id' => null,
    ]);
    $version = app(AssembleTestKitPreparation::class)->handle($actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    return [
        'kit' => $kit,
        'preparation' => $preparation,
        'version' => $version->fresh(['testInstrumentVersion']),
        'profile' => $profile,
    ];
}
