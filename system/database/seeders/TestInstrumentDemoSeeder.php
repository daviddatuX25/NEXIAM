<?php

namespace Database\Seeders;

use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentMetricInput;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TestInstrumentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::current();

        DB::transaction(function () use ($institution): void {
            $instrument = TestInstrument::query()
                ->where('institution_id', $institution->id)
                ->where('code', 'SYNTHETIC-PROFILE-DEMO')
                ->lockForUpdate()
                ->first();

            $normPackage = AssessmentNormPackage::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => 'OASIS3-NORM-DEMO',
                ],
                [
                    'is_synthetic' => true,
                ],
            );

            if (! $normPackage->is_synthetic) {
                throw new RuntimeException('The demo norm package code is already owned by an institutional package.');
            }

            if ($normPackage->versions()->doesntExist()) {
                $normVersion = AssessmentNormPackageVersion::query()->create([
                    'norm_package_id' => $normPackage->id,
                    'version_number' => 1,
                    'status' => AssessmentNormPackageVersion::STATUS_APPROVED,
                    'display_name' => 'OASIS-3 Demo Norm Package · Synthetic Fixture',
                    'form_code' => 'OASIS3_DEMO',
                    'edition' => 'Synthetic',
                    'language' => 'English',
                    'source_reference' => 'demo://oasis-3-synthetic-norms',
                    'permitted_use_reference' => 'demo://rehearsal',
                    'source_reviewed_by_user_id' => 1,
                    'source_reviewed_at' => now(),
                    'approved_by_user_id' => 1,
                    'approved_at' => now(),
                    'content_sha256' => hash('sha256', 'OASIS3-NORM-DEMO-V1'),
                ]);

                $normIntervalsByMetric = [
                    'VOCABULARY_RAW' => [
                        ['min_score' => 0, 'max_score' => 10, 'percentile_rank' => 10],
                        ['min_score' => 11, 'max_score' => 20, 'percentile_rank' => 40],
                        ['min_score' => 21, 'max_score' => 30, 'percentile_rank' => 70],
                        ['min_score' => 31, 'max_score' => 40, 'percentile_rank' => 95],
                    ],
                    'COMPUTATION_RAW' => [
                        ['min_score' => 0, 'max_score' => 7, 'percentile_rank' => 10],
                        ['min_score' => 8, 'max_score' => 15, 'percentile_rank' => 40],
                        ['min_score' => 16, 'max_score' => 22, 'percentile_rank' => 70],
                        ['min_score' => 23, 'max_score' => 30, 'percentile_rank' => 95],
                    ],
                    'SPATIAL_RAW' => [
                        ['min_score' => 0, 'max_score' => 5, 'percentile_rank' => 10],
                        ['min_score' => 6, 'max_score' => 10, 'percentile_rank' => 40],
                        ['min_score' => 11, 'max_score' => 15, 'percentile_rank' => 70],
                        ['min_score' => 16, 'max_score' => 20, 'percentile_rank' => 95],
                    ],
                    'WORD_COMP_RAW' => [
                        ['min_score' => 0, 'max_score' => 25, 'percentile_rank' => 10],
                        ['min_score' => 26, 'max_score' => 50, 'percentile_rank' => 40],
                        ['min_score' => 51, 'max_score' => 75, 'percentile_rank' => 70],
                        ['min_score' => 76, 'max_score' => 100, 'percentile_rank' => 95],
                    ],
                    'GENERAL_ABILITY_RAW' => [
                        ['min_score' => 0, 'max_score' => 17, 'percentile_rank' => 10],
                        ['min_score' => 18, 'max_score' => 35, 'percentile_rank' => 40],
                        ['min_score' => 36, 'max_score' => 52, 'percentile_rank' => 70],
                        ['min_score' => 53, 'max_score' => 70, 'percentile_rank' => 95],
                    ],
                    'MANUAL_DEXTERITY_RAW' => [
                        ['min_score' => 0, 'max_score' => 30, 'percentile_rank' => 10],
                        ['min_score' => 31, 'max_score' => 60, 'percentile_rank' => 40],
                        ['min_score' => 61, 'max_score' => 90, 'percentile_rank' => 70],
                        ['min_score' => 91, 'max_score' => 120, 'percentile_rank' => 95],
                    ],
                ];

                foreach ($normIntervalsByMetric as $metricCode => $intervals) {
                    AssessmentNormTable::query()->create([
                        'norm_package_version_id' => $normVersion->id,
                        'metric_code' => $metricCode,
                        'output_scale' => AssessmentNormTable::SCALE_PERCENTILE_RANK,
                        'intervals_json' => array_map(
                            static fn (array $interval): array => [
                                'min_score' => $interval['min_score'],
                                'max_score' => $interval['max_score'],
                                'output_value' => (string) $interval['percentile_rank'],
                            ],
                            $intervals,
                        ),
                    ]);
                }

                $normPackage->update(['current_approved_version_id' => $normVersion->id]);
            }

            if ($instrument !== null) {
                if (! $instrument->is_synthetic) {
                    throw new RuntimeException('The demo profile code is already owned by an institutional instrument.');
                }
            } else {
                $instrument = new TestInstrument;
                $instrument->forceFill([
                    'institution_id' => $institution->id,
                    'code' => 'SYNTHETIC-PROFILE-DEMO',
                    'is_synthetic' => true,
                ])->save();
            }

            if (! $instrument->versions()->exists()) {
                $version = TestInstrumentVersion::query()->create([
                    'test_instrument_id' => $instrument->id,
                    'version_number' => 1,
                    'status' => TestInstrumentVersion::STATUS_DRAFT,
                    'display_name' => 'Synthetic sample profile · software only',
                    'form_code' => 'SYN_FORM',
                    'edition' => 'Synthetic sample',
                    'language' => 'Not applicable',
                    'lock_version' => 1,
                ]);

                $alphaSubtest = TestInstrumentSubtest::query()->create([
                    'test_instrument_version_id' => $version->id,
                    'code' => 'SECTION_ALPHA',
                    'name' => 'Sample section',
                    'display_order' => 1,
                    'response_mode' => 'keyed_items',
                    'item_order_policy' => 'fixed',
                ]);

                foreach ([1, 2, 3] as $position) {
                    TestInstrumentItem::query()->create([
                        'test_instrument_version_id' => $version->id,
                        'test_instrument_subtest_id' => $alphaSubtest->id,
                        'item_code' => 'ITEM_'.$position,
                        'local_number' => (string) $position,
                        'display_order' => $position,
                        'is_scored' => true,
                        'option_tokens' => ['A', 'B'],
                    ]);
                }

                TestInstrumentMetric::query()->create([
                    'test_instrument_version_id' => $version->id,
                    'code' => 'RAW_COUNT',
                    'name' => 'Sample raw count',
                    'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                    'test_instrument_subtest_id' => $alphaSubtest->id,
                    'unit' => 'count',
                    'min_raw_value' => '0.000000',
                    'max_raw_value' => '3.000000',
                    'precision_scale' => 0,
                    'is_required' => true,
                    'required_output_scales' => [],
                ]);
            }
            $oasisInstrument = TestInstrument::query()
                ->where('institution_id', $institution->id)
                ->where('code', 'OASIS-3-DEMO')
                ->lockForUpdate()
                ->first();

            if ($oasisInstrument === null) {
                $oasisInstrument = new TestInstrument;
                $oasisInstrument->forceFill([
                    'institution_id' => $institution->id,
                    'code' => 'OASIS-3-DEMO',
                    'is_synthetic' => true,
                ])->save();
            }
            $oasisVersion = $oasisInstrument->versions()->where('version_number', 1)->first();
            if ($oasisVersion === null) {
                $oasisVersion = TestInstrumentVersion::query()->create([
                    'test_instrument_id' => $oasisInstrument->id,
                    'version_number' => 1,
                    'status' => TestInstrumentVersion::STATUS_DRAFT,
                    'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
                    'administration_procedure_reference' => 'Synthetic demo OASIS-3 paper survey administration procedure',
                    'display_name' => 'OASIS-3 Demo Profile · Synthetic Software Fixture',
                    'form_code' => 'OASIS3_DEMO',
                    'edition' => 'Synthetic Demo Form',
                    'language' => 'English',
                    'source_reference' => 'demo://oasis-3-synthetic-profile',
                    'permitted_use_reference' => 'demo://software-rehearsal-only',
                    'lock_version' => 1,
                ]);

                $areas = AptitudeArea::query()
                    ->whereIn('code', ['VERBAL', 'QUANT', 'SPATIAL', 'MEMORY', 'LOGIC', 'LANGUAGE'])
                    ->get()
                    ->keyBy('code');
                $defaultContract = [
                    'allowed_sources' => ['import', 'manual'],
                    'required_resources' => [],
                    'source_requirements' => [
                        'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                        'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                    ],
                ];

                $subtestsData = [
                    ['code' => 'VOCABULARY', 'name' => 'Vocabulary', 'order' => 1, 'items' => 40, 'options' => ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'], 'mode' => 'keyed_items', 'construct' => 'VERBAL'],
                    ['code' => 'COMPUTATION', 'name' => 'Computation', 'order' => 2, 'items' => 30, 'options' => ['A', 'B', 'C', 'D', 'E'], 'mode' => 'keyed_items', 'construct' => 'QUANT'],
                    ['code' => 'SPATIAL', 'name' => 'Spatial Relations', 'order' => 3, 'items' => 20, 'options' => ['A', 'B', 'C', 'D'], 'mode' => 'keyed_items', 'construct' => 'SPATIAL'],
                    ['code' => 'WORD_COMP', 'name' => 'Word Comparison', 'order' => 4, 'items' => 100, 'options' => ['S', 'D'], 'mode' => 'keyed_items', 'construct' => 'MEMORY'],
                ];

                foreach ($subtestsData as $data) {
                    $subtest = TestInstrumentSubtest::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'code' => $data['code'],
                        'name' => $data['name'],
                        'display_order' => $data['order'],
                        'response_mode' => $data['mode'],
                        'item_order_policy' => 'fixed',
                    ]);

                    for ($position = 1; $position <= $data['items']; $position++) {
                        TestInstrumentItem::query()->create([
                            'test_instrument_version_id' => $oasisVersion->id,
                            'test_instrument_subtest_id' => $subtest->id,
                            'item_code' => $data['code'].'_ITEM_'.$position,
                            'local_number' => (string) $position,
                            'display_order' => $position,
                            'is_scored' => true,
                            'option_tokens' => $data['options'],
                        ]);
                    }

                    TestInstrumentMetric::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'code' => $data['code'].'_RAW',
                        'name' => $data['name'].' Raw Score',
                        'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                        'test_instrument_subtest_id' => $subtest->id,
                        'unit' => 'count',
                        'min_raw_value' => '0.000000',
                        'max_raw_value' => (string) $data['items'].'.000000',
                        'precision_scale' => 0,
                        'is_required' => true,
                        'required_output_scales' => ['percentile_rank'],
                        'construct_aptitude_area_id' => $areas->get($data['construct'])?->id,
                        'input_contract_json' => $defaultContract,
                    ]);
                }

                $vocabMetric = TestInstrumentMetric::query()->where('test_instrument_version_id', $oasisVersion->id)->where('code', 'VOCABULARY_RAW')->first();
                $compMetric = TestInstrumentMetric::query()->where('test_instrument_version_id', $oasisVersion->id)->where('code', 'COMPUTATION_RAW')->first();
                $spatialMetric = TestInstrumentMetric::query()->where('test_instrument_version_id', $oasisVersion->id)->where('code', 'SPATIAL_RAW')->first();
                $wordCompMetric = TestInstrumentMetric::query()->where('test_instrument_version_id', $oasisVersion->id)->where('code', 'WORD_COMP_RAW')->first();

                if ($vocabMetric !== null && $compMetric !== null) {
                    $gaMetric = TestInstrumentMetric::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'code' => 'GENERAL_ABILITY_RAW',
                        'name' => 'General Ability Composite Raw',
                        'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                        'test_instrument_subtest_id' => null,
                        'unit' => 'count',
                        'min_raw_value' => '0.000000',
                        'max_raw_value' => '70.000000',
                        'precision_scale' => 0,
                        'is_required' => true,
                        'required_output_scales' => ['percentile_rank'],
                        'construct_aptitude_area_id' => $areas->get('LOGIC')?->id,
                        'input_contract_json' => $defaultContract,
                    ]);

                    TestInstrumentMetricInput::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'metric_id' => $gaMetric->id,
                        'input_metric_id' => $vocabMetric->id,
                        'position' => 1,
                    ]);
                    TestInstrumentMetricInput::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'metric_id' => $gaMetric->id,
                        'input_metric_id' => $compMetric->id,
                        'position' => 2,
                    ]);
                }

                if ($spatialMetric !== null && $wordCompMetric !== null) {
                    $mdMetric = TestInstrumentMetric::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'code' => 'MANUAL_DEXTERITY_RAW',
                        'name' => 'Manual Dexterity Composite Raw',
                        'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                        'test_instrument_subtest_id' => null,
                        'unit' => 'count',
                        'min_raw_value' => '0.000000',
                        'max_raw_value' => '120.000000',
                        'precision_scale' => 0,
                        'is_required' => true,
                        'required_output_scales' => ['percentile_rank'],
                        'construct_aptitude_area_id' => $areas->get('LANGUAGE')?->id,
                        'input_contract_json' => $defaultContract,
                    ]);

                    TestInstrumentMetricInput::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'metric_id' => $mdMetric->id,
                        'input_metric_id' => $spatialMetric->id,
                        'position' => 1,
                    ]);
                    TestInstrumentMetricInput::query()->create([
                        'test_instrument_version_id' => $oasisVersion->id,
                        'metric_id' => $mdMetric->id,
                        'input_metric_id' => $wordCompMetric->id,
                        'position' => 2,
                    ]);
                }

                $oasisVersion->forceFill([
                    'status' => TestInstrumentVersion::STATUS_APPROVED,
                    'source_reviewed_by_user_id' => 1,
                    'source_reviewed_at' => now(),
                    'approved_by_user_id' => 1,
                    'approved_at' => now(),
                    'content_sha256' => hash('sha256', 'OASIS3-DEMO-VERSION-1'),
                ])->save();
                $oasisInstrument->update(['current_approved_version_id' => $oasisVersion->id]);
            }
            // Bind Draft #7 if present
            $draft7 = OmrTemplateAuthoringDraft::query()
                ->whereKey(7)
                ->where('institution_id', $institution->id)
                ->where('is_synthetic', true)
                ->where('stage', '!=', OmrTemplateAuthoringDraft::STAGE_COMPILED)
                ->first();
            if ($draft7 !== null && $oasisVersion !== null) {
                $draft7->update(['test_instrument_version_id' => $oasisVersion->id]);
            }
            $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
            (new FormulaScoringDemoSeeder)->seed($institution, $actor);
        });
    }
}
