<?php

namespace Database\Seeders;

use App\Assessment\Actions\SaveAssessmentNormDraft;
use App\Assessment\Actions\SaveTestInstrumentDraft;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class FormulaScoringDemoSeeder
{
    private const INSTRUMENT_CODE = 'FORMULA-SCORING-DEMO';

    public function seed(Institution $institution, User $actor): void
    {
        DB::transaction(function () use ($institution, $actor): void {
            $instrument = TestInstrument::query()
                ->where('institution_id', $institution->id)
                ->where('code', self::INSTRUMENT_CODE)
                ->lockForUpdate()
                ->first();

            if ($instrument !== null) {
                if (! $instrument->is_synthetic) {
                    throw new RuntimeException('FORMULA-SCORING-DEMO is already owned by an institutional instrument.');
                }

                // The owned demo is a user-inspectable draft; never rewrite a saved draft or any successor.
                return;
            }

            $instrument = new TestInstrument;
            $instrument->forceFill([
                'institution_id' => $institution->id,
                'code' => self::INSTRUMENT_CODE,
                'is_synthetic' => true,
            ])->save();

            $draft = TestInstrumentVersion::query()->create([
                'test_instrument_id' => $instrument->id,
                'version_number' => 1,
                'status' => TestInstrumentVersion::STATUS_DRAFT,
                'display_name' => 'Formula scoring examples · synthetic draft only',
                'lock_version' => 1,
                'created_by_user_id' => $actor->id,
            ]);

            $requestId = (string) Str::uuid();
            app(SaveAssessmentNormDraft::class)->handle(
                actor: $actor,
                package: null,
                version: null,
                expectedRevision: null,
                requestId: (string) Str::uuid(),
                input: [
                    'code' => 'INSTRUMENT_'.$instrument->id,
                    'display_name' => 'Formula scoring synthetic norm examples · draft only',
                    'publisher_name' => 'Synthetic fixture; no norm publisher represented',
                    'form_code' => 'SYN-FORMULA-SCORING',
                    'edition' => 'Synthetic software fixture',
                    'language' => 'Synthetic fixture',
                    'applicable_population' => 'Synthetic software examples only',
                    'source_reference' => 'demo://formula-scoring/synthetic-draft-norm-table',
                    'permitted_use_reference' => 'Synthetic software demonstration only; not official norms or percentile authority.',
                    'is_synthetic' => true,
                    'tables' => [],
                ],
            );

            app(SaveTestInstrumentDraft::class)->handle(
                $actor,
                (int) $instrument->id,
                (int) $draft->id,
                (int) $draft->lock_version,
                $requestId,
                $this->payload(),
            );
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $subtests = [];
        foreach (['A', 'B'] as $position => $section) {
            $subtests[] = [
                'code' => 'SECTION_'.$section,
                'name' => 'Synthetic Section '.$section,
                'display_order' => $position + 1,
                'response_mode' => 'keyed_items',
                'item_order_policy' => 'fixed',
                'items' => array_map(static fn (int $item): array => [
                    'item_code' => $section.'_ITEM_'.$item,
                    'local_number' => (string) $item,
                    'display_order' => $item,
                    'is_scored' => true,
                    'option_tokens' => ['A', 'B'],
                ], range(1, 10)),
            ];
        }

        $metrics = [
            $this->leafMetric('A_RAW', 'Section A correct-item count', 'SECTION_A', 10),
            $this->leafMetric('B_RAW', 'Section B correct-item count', 'SECTION_B', 10),
            $this->leafMetric('A_CORE_RAW', 'Section A selected-item subset count', 'SECTION_A', 5, [
                'A_ITEM_1', 'A_ITEM_2', 'A_ITEM_3', 'A_ITEM_4', 'A_ITEM_5',
            ]),
            $this->formulaMetric(
                'WEIGHTED_RAW',
                'Weighted raw score · synthetic example',
                'ROUND(0.20*A_RAW+0.80*B_RAW,0)',
                ['A_RAW', 'B_RAW'],
                0,
            ),
            $this->formulaMetric(
                'SCALED_DECIMAL_RAW',
                'Scaled decimal raw score · synthetic example',
                '0.20*A_CORE_RAW',
                ['A_CORE_RAW'],
                2,
            ),
        ];

        return [
            'profile' => [
                'code' => self::INSTRUMENT_CODE,
                'display_name' => 'Formula scoring examples · synthetic draft only',
                'publisher_name' => 'Synthetic fixture; no publisher represented',
                'form_code' => 'SYN-FORMULA-SCORING',
                'edition' => 'Synthetic software fixture',
                'language' => 'Synthetic fixture',
                'source_reference' => 'demo://formula-scoring/synthetic-source-not-an-institutional-instrument',
                'permitted_use_reference' => 'Synthetic software demonstration only; no official source, rights, norms, or scoring authority is claimed.',
                'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
                'administration_procedure_reference' => 'Synthetic software-only examples for manual/import scoring; no paper form, answer key, or institutional administration procedure is represented.',
            ],
            'subtests' => $subtests,
            'metrics' => $metrics,
            'tables' => $this->syntheticNormTables(),
        ];
    }

    /** @param list<string>|null $itemCodes @return array<string, mixed> */
    private function leafMetric(string $code, string $name, string $subtestCode, int $maximum, ?array $itemCodes = null): array
    {
        $metric = [
            'code' => $code,
            'name' => $name,
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => $subtestCode,
            'unit' => 'count',
            'min_raw_value' => '0',
            'max_raw_value' => (string) $maximum,
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => [],
            'input_contract_json' => $this->manualImportContract('count'),
        ];
        if ($itemCodes !== null) {
            $metric['item_codes'] = $itemCodes;
        }

        return $metric;
    }

    /** @param list<string> $inputCodes @return array<string, mixed> */
    private function formulaMetric(string $code, string $name, string $expression, array $inputCodes, int $scale): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'rule_kind' => TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            'subtest_code' => null,
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => $scale,
            'is_required' => true,
            'required_output_scales' => $code === 'WEIGHTED_RAW' ? ['percentile_rank'] : [],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => $inputCodes,
            'formula_expression' => $expression,
            'item_codes' => null,
            'input_contract_json' => $this->manualImportContract('count'),
        ];
    }

    /** @return list<array{metric_code: string, output_scale: string, intervals: list<array{min_score: int, max_score: int, output_value: string}>}> */
    private function syntheticNormTables(): array
    {
        $ranges = [
            'A_RAW' => [10, 4],
            'B_RAW' => [10, 4],
            'A_CORE_RAW' => [5, 2],
            'WEIGHTED_RAW' => [10, 4],
        ];

        return array_map(
            static fn (string $metricCode, array $range): array => [
                'metric_code' => $metricCode,
                'output_scale' => 'percentile_rank',
                'intervals' => [
                    ['min_score' => 0, 'max_score' => $range[1], 'output_value' => '20'],
                    ['min_score' => $range[1] + 1, 'max_score' => $range[0], 'output_value' => '80'],
                ],
            ],
            array_keys($ranges),
            array_values($ranges),
        );
    }

    /** @return array{allowed_sources: list<string>, required_resources: list<string>, source_requirements: array<string, array{unit: string, identity_requirements: list<string>}>} */
    private function manualImportContract(string $unit): array
    {
        return [
            'allowed_sources' => ['import', 'manual'],
            'required_resources' => [],
            'source_requirements' => [
                'import' => ['unit' => $unit, 'identity_requirements' => ['administration', 'metric']],
                'manual' => ['unit' => $unit, 'identity_requirements' => ['administration', 'metric']],
            ],
        ];
    }
}
