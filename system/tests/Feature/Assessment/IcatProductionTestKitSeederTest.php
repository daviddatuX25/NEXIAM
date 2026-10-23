<?php

declare(strict_types=1);

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use Database\Seeders\IcatProductionTestKitSeeder;
use Database\Seeders\TestInstrumentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
function createIcatAnswerSheetFixture(TestInstrumentVersion $profile): void
{
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => Institution::current()->id,
        'code' => 'ICAT-ANSWERKEYS-TEST',
        'name' => 'ICAT answer-sheet test fixture',
        'is_synthetic' => true,
    ]);
    $templateVersion = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'test_instrument_version_id' => $profile->id,
        'registration_mode' => 'fiducial_anchored',
        'qr_mode' => 'qr_disabled',
        'page_count' => 2,
        'page_width' => 2480,
        'page_height' => 3508,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'icat-fixture-config'),
        'review_manifest_hash' => hash('sha256', 'icat-fixture-review'),
        'template_json_hash' => hash('sha256', 'icat-fixture-template'),
    ]);
    $subtests = $profile->subtests()->with('items')->orderBy('display_order')->get();
    $aptitudeAreaCodeBySubtest = [
        'VOCABULARY' => 'PROFILE_VERBAL',
        'COMPUTATION' => 'PROFILE_QUANT',
        'SPATIAL' => 'SPATIAL',
        'WORD_COMP' => 'LOGIC',
    ];
    $pageBySubtest = [
        'VOCABULARY' => 1,
        'COMPUTATION' => 2,
        'SPATIAL' => 2,
        'WORD_COMP' => 2,
    ];
    $displayOrderBySubtest = [
        'VOCABULARY' => 3,
        'COMPUTATION' => 1,
        'SPATIAL' => 2,
        'WORD_COMP' => 4,
    ];
    $areaBySubtest = [];
    foreach ($subtests as $subtest) {
        $aptitudeArea = AptitudeArea::query()
            ->where('code', $aptitudeAreaCodeBySubtest[$subtest->code])
            ->firstOrFail();
        $areaBySubtest[$subtest->id] = $templateVersion->areas()->create([
            'aptitude_area_id' => $aptitudeArea->id,
            'display_order' => $displayOrderBySubtest[$subtest->code],
            'max_items' => $subtest->items->where('is_scored', true)->count(),
            'region_x' => 0.1,
            'region_y' => 0.1,
            'region_w' => 0.8,
            'region_h' => 0.8,
        ]);
    }

    $fields = [];
    $globalPosition = 0;
    foreach ($subtests as $subtest) {
        $pageNo = $pageBySubtest[$subtest->code];
        $area = $areaBySubtest[$subtest->id];
        foreach ($subtest->items->where('is_scored', true)->sortBy('display_order') as $item) {
            $questionNo = (int) $item->local_number;
            $question = $area->questions()->create([
                'page_no' => $pageNo,
                'question_no' => $questionNo,
                'region_x' => 0.1,
                'region_y' => 0.1,
                'region_w' => 0.8,
                'region_h' => 0.02,
                'projection_manifest_hash' => hash('sha256', 'icat-fixture-projection'),
            ]);
            $nativeLabel = $subtest->code.'_Q_'.$questionNo;
            foreach ($item->option_tokens as $optionIndex => $token) {
                $question->options()->create([
                    'code' => $token,
                    'display_order' => $optionIndex + 1,
                    'region_x' => 0.1 + ($optionIndex * 0.03),
                    'region_y' => 0.1,
                    'region_w' => 0.02,
                    'region_h' => 0.02,
                    'projection_manifest_hash' => hash('sha256', 'icat-fixture-projection'),
                ]);
                $fields[] = [
                    'page' => $pageNo,
                    'label' => $nativeLabel,
                    'option' => $token,
                    'pixel' => ['x' => 1, 'y' => 1, 'w' => 2, 'h' => 2],
                ];
            }
            $templateVersion->itemMappings()->create([
                'test_instrument_version_id' => $profile->id,
                'test_instrument_subtest_id' => $subtest->id,
                'test_instrument_item_id' => $item->id,
                'native_label' => $nativeLabel,
                'page_no' => $pageNo,
                'global_position' => ++$globalPosition,
            ]);
        }
    }

    $pages = [
        ['page_no' => 1, 'page_dimensions' => ['width' => 2480, 'height' => 3508]],
        ['page_no' => 2, 'page_dimensions' => ['width' => 2480, 'height' => 3508]],
    ];
    $manifest = [
        'template_code' => (string) $template->code,
        'template_version' => 1,
        'fields' => $fields,
        'pages' => $pages,
    ];
    foreach ($pages as $page) {
        $templateVersion->pages()->create([
            'page_no' => $page['page_no'],
            'width' => 2480,
            'height' => 3508,
            'page_manifest_json' => $manifest,
            'page_manifest_hash' => hash('sha256', 'icat-fixture-projection'),
        ]);
    }
    $templateVersion->areas()->update(['projection_manifest_hash' => hash('sha256', 'icat-fixture-projection')]);
    $templateVersion->update([
        'review_manifest_json' => $manifest,
        'authoring_manifest' => ['source_kind' => 'system_designed'],
        'native_bundle_json' => ['fixture' => true],
        'native_bundle_hash' => hash('sha256', 'icat-fixture-native-bundle'),
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
    ]);
}

test('ICAT production seeder creates a finalized booklet, assembled kit version, and default exam session', function (): void {
    seedE2Foundation();
    (new TestInstrumentDemoSeeder)->run();
    $profile = TestInstrumentVersion::query()->where('form_code', 'OASIS3_DEMO')->firstOrFail();
    createIcatAnswerSheetFixture($profile);
    (new IcatProductionTestKitSeeder)->run();

    $booklet = OmrQuestionBooklet::query()->where('code', 'ICAT-ANSWERKEYS-2026')->firstOrFail();
    $bookletVersion = OmrQuestionBookletVersion::query()
        ->where('booklet_id', $booklet->id)
        ->where('version', 1)
        ->firstOrFail();
    $setA = OmrBookletSet::query()
        ->where('booklet_version_id', $bookletVersion->id)
        ->where('set_code', 'A')
        ->firstOrFail();
    $kit = TestKit::query()->where('code', 'KIT-ICAT-2026')->firstOrFail();
    $kitVersion = TestKitVersion::query()->where('test_kit_id', $kit->id)->firstOrFail();
    $session = ExamSession::query()
        ->where('academic_cycle_id', AcademicCycle::active()->id)
        ->where('code', 'EX-ICAT-2026')
        ->firstOrFail();

    $profileItemIds = $profile->subtests()
        ->with('items')
        ->get()
        ->flatMap(fn ($subtest) => $subtest->items->where('is_scored', true))
        ->pluck('id')
        ->sort()
        ->values()
        ->all();
    $setItemIds = $setA->items()
        ->pluck('test_instrument_item_id')
        ->sort()
        ->values()
        ->all();

    expect($bookletVersion->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($bookletVersion->finalized_at)->not->toBeNull()
        ->and($setA->status)->toBe(OmrBookletSet::STATUS_FINALIZED)
        ->and($setA->items()->count())->toBe(190)
        ->and($setItemIds)->toBe($profileItemIds)
        ->and($kitVersion->status)->toBe(TestKitVersion::STATUS_CANDIDATE)
        ->and($kitVersion->version_number)->toBe(1)
        ->and($kitVersion->booklet_version_id)->toBe($bookletVersion->id)
        ->and($kitVersion->booklet_set_id)->toBe($setA->id)
        ->and($session->test_kit_version_id)->toBe($kitVersion->id)
        ->and($session->test_instrument_version_id)->toBe($kitVersion->test_instrument_version_id)
        ->and($session->status)->toBe(ExamSession::STATUS_DRAFT);
});
