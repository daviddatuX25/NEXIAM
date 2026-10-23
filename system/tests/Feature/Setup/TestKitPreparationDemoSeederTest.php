<?php

declare(strict_types=1);

use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentMetricInput;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use Database\Seeders\TestKitPreparationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('kit preparation demo seeder replays stable synthetic empty and authoring fixtures', function (): void {
    seedE2Foundation();

    $seeder = new TestKitPreparationDemoSeeder;
    $seeder->run();

    $emptyKit = TestKit::query()->where('code', TestKitPreparationDemoSeeder::EMPTY_KIT_CODE)->firstOrFail();
    $draftKit = TestKit::query()->where('code', TestKitPreparationDemoSeeder::DRAFT_KIT_CODE)->firstOrFail();
    $emptyPreparation = TestKitPreparation::query()->where('test_kit_id', $emptyKit->id)->where('state', 'preparing')->firstOrFail();
    $draftPreparation = TestKitPreparation::query()->where('test_kit_id', $draftKit->id)->where('state', 'preparing')->firstOrFail();
    $profileVersion = TestInstrumentVersion::query()->findOrFail($draftPreparation->test_instrument_version_id);
    $instrument = TestInstrument::query()->findOrFail($profileVersion->test_instrument_id);
    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($draftPreparation->norm_package_version_id);
    $normPackage = AssessmentNormPackage::query()->findOrFail($normVersion->norm_package_id);

    $profileCounts = [
        'kits' => TestKit::query()->whereIn('code', [TestKitPreparationDemoSeeder::EMPTY_KIT_CODE, TestKitPreparationDemoSeeder::DRAFT_KIT_CODE])->count(),
        'preparations' => TestKitPreparation::query()->whereIn('test_kit_id', [$emptyKit->id, $draftKit->id])->count(),
        'profile_versions' => TestInstrumentVersion::query()->where('test_instrument_id', $instrument->id)->count(),
        'norm_versions' => AssessmentNormPackageVersion::query()->where('norm_package_id', $normPackage->id)->count(),
    ];
    $ids = [$emptyKit->id, $emptyPreparation->id, $draftKit->id, $draftPreparation->id, $profileVersion->id, $normVersion->id];

    $this->assertDatabaseHas('test_kits', [
        'id' => $emptyKit->id,
        'is_synthetic' => true,
    ]);
    $section = TestInstrumentSubtest::query()->where('test_instrument_version_id', $profileVersion->id)->where('code', 'SAMPLE_SECTION')->firstOrFail();
    $items = TestInstrumentItem::query()->where('test_instrument_subtest_id', $section->id)->orderBy('display_order')->get();
    $composite = TestInstrumentMetric::query()->where('test_instrument_version_id', $profileVersion->id)->where('code', 'SAMPLE_COMPOSITE')->firstOrFail();
    $inputs = TestInstrumentMetricInput::query()->where('metric_id', $composite->id)->with('inputMetric')->orderBy('position')->get()
        ->map(static fn (TestInstrumentMetricInput $input): string => (string) $input->inputMetric->code)
        ->all();
    $normTable = AssessmentNormTable::query()->where('norm_package_version_id', $normVersion->id)->where('metric_code', 'SAMPLE_COMPOSITE')->firstOrFail();

    expect($emptyPreparation->revision)->toBe(1)
        ->and($emptyPreparation->test_instrument_version_id)->toBeNull()
        ->and($emptyPreparation->norm_package_version_id)->toBeNull()
        ->and($emptyKit->is_synthetic)->toBeTrue()
        ->and($draftKit->is_synthetic)->toBeTrue()
        ->and($profileVersion->status)->toBe(TestInstrumentVersion::STATUS_DRAFT)
        ->and($profileVersion->source_reference)->toBe('software-only://kit-preparation-demo/profile')
        ->and($profileVersion->publisher_name)->toBe('Synthetic software-only fixture')
        ->and($normVersion->status)->toBe(AssessmentNormPackageVersion::STATUS_DRAFT)
        ->and($normVersion->source_reference)->toBe('software-only://kit-preparation-demo/norm')
        ->and($normVersion->publisher_name)->toBe('Synthetic software-only fixture')
        ->and($instrument->is_synthetic)->toBeTrue()
        ->and($normPackage->is_synthetic)->toBeTrue()
        ->and($section->response_mode)->toBe('keyed_items')
        ->and($items)->toHaveCount(2)
        ->and($composite->rule_kind)->toBe(TestInstrumentMetric::RULE_SUM_RAW_METRICS)
        ->and($inputs)->toBe(['SAMPLE_SECTION_RAW_A', 'SAMPLE_SECTION_RAW_B'])
        ->and($normTable->intervals_json)->toHaveCount(2)
        ->and(TestKitVersion::query()->whereIn('test_kit_id', [$emptyKit->id, $draftKit->id])->count())->toBe(0);

    $seeder->run();

    $emptyKit->refresh();
    $draftKit->refresh();
    $emptyPreparation->refresh();
    $draftPreparation->refresh();
    $profileVersion->refresh();
    $normVersion->refresh();

    expect([
        $emptyKit->id,
        $emptyPreparation->id,
        $draftKit->id,
        $draftPreparation->id,
        $profileVersion->id,
        $normVersion->id,
    ])->toBe($ids)
        ->and([
            'kits' => TestKit::query()->whereIn('code', [TestKitPreparationDemoSeeder::EMPTY_KIT_CODE, TestKitPreparationDemoSeeder::DRAFT_KIT_CODE])->count(),
            'preparations' => TestKitPreparation::query()->whereIn('test_kit_id', [$emptyKit->id, $draftKit->id])->count(),
            'profile_versions' => TestInstrumentVersion::query()->where('test_instrument_id', $instrument->id)->count(),
            'norm_versions' => AssessmentNormPackageVersion::query()->where('norm_package_id', $normPackage->id)->count(),
        ])->toBe($profileCounts)
        ->and($draftPreparation->revision)->toBe(2)
        ->and(TestKitVersion::query()->whereIn('test_kit_id', [$emptyKit->id, $draftKit->id])->count())->toBe(0);
});
