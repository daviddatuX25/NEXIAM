<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Assessment\Actions\SaveTestKitTestSetup;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class TestKitPreparationDemoSeeder extends Seeder
{
    public const EMPTY_KIT_CODE = 'ICAT-2026-EMPTY';

    public const DRAFT_KIT_CODE = 'ICAT-2026-PREP';

    private const PROFILE_CODE = 'OASIS-3-ICAT';

    private const NORM_CODE = 'OASIS3-NORMS-2026';

    private const SETUP_REQUEST_ID = 'a1b2c3d4-e5f6-47a8-b9c0-123456789abc';

    public function run(): void
    {
        $institution = Institution::current();
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        DB::transaction(function () use ($institution, $actor): void {
            $emptyKit = $this->syntheticKit($institution, $actor, self::EMPTY_KIT_CODE, 'ICAT-2026 Empty Preparation Kit');
            $this->openPreparation($emptyKit, $actor);

            $draftKit = $this->syntheticKit($institution, $actor, self::DRAFT_KIT_CODE, 'ICAT-2026 Examination Kit');
            $preparation = $this->openPreparation($draftKit, $actor);
            if ($preparation->test_instrument_version_id === null) {
                $profile = $this->syntheticProfile($institution, $actor);
                $preparation->forceFill([
                    'test_instrument_version_id' => (int) $profile->getKey(),
                    'composition_revision' => ((int) ($preparation->composition_revision ?? 1)) + 1,
                ])->save();
            } else {
                $profile = TestInstrumentVersion::query()->findOrFail($preparation->test_instrument_version_id);
            }

            $existingNorm = $preparation->normPackageVersion;

            app(SaveTestKitTestSetup::class)->handle($actor, (int) $draftKit->getKey(), [
                'preparation_id' => (int) $preparation->getKey(),
                'expected_preparation_revision' => 1,
                'request_id' => self::SETUP_REQUEST_ID,
                'instrument' => [
                    'id' => (int) $profile->test_instrument_id,
                    'version_id' => (int) $profile->getKey(),
                    'expected_revision' => 1,
                    'profile' => [
                        'code' => self::PROFILE_CODE,
                        'display_name' => 'OASIS-3 General Aptitude Battery (Form A)',
                        'publisher_name' => 'Synthetic software-only fixture',
                        'form_code' => 'A',
                        'edition' => 'OASIS-3 Form A',
                        'language' => 'English',
                        'source_reference' => 'software-only://kit-preparation-demo/profile',
                        'permitted_use_reference' => 'Synthetic software demonstration data only; not an official test form or licensed scoring key.',
                        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
                        'administration_procedure_reference' => 'Demo administration: administer the paper-backed form in the displayed subtest order.',
                    ],
                    'subtests' => [
                        $this->keyedSubtest('SAMPLE_SECTION', 'Sample Section', 1, 2),
                    ],
                    'metrics' => [
                        $this->rawCountMetric('SAMPLE_SECTION_RAW_A', 'Sample Section Raw A', 'SAMPLE_SECTION'),
                        $this->rawCountMetric('SAMPLE_SECTION_RAW_B', 'Sample Section Raw B', 'SAMPLE_SECTION'),
                        [
                            'code' => 'SAMPLE_COMPOSITE',
                            'name' => 'Sample Composite',
                            'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                            'subtest_code' => null,
                            'unit' => 'count',
                            'min_raw_value' => null,
                            'max_raw_value' => null,
                            'precision_scale' => 0,
                            'is_required' => true,
                            'required_output_scales' => ['percentile_rank'],
                            'procedure_reference' => null,
                            'construct_aptitude_area_id' => null,
                            'input_codes' => ['SAMPLE_SECTION_RAW_A', 'SAMPLE_SECTION_RAW_B'],
                        ],
                    ],
                ],
                'norm' => [
                    'package_id' => null,
                    'version_id' => null,
                    'expected_revision' => null,
                    'package' => [
                        'code' => self::NORM_CODE,
                        'display_name' => 'OASIS-3 National Norms (College Entrance Applicants)',
                        'publisher_name' => 'Synthetic software-only fixture',
                        'form_code' => 'A',
                        'edition' => '2026 demonstration reference',
                        'language' => 'English',
                        'applicable_population' => 'First-year college applicants',
                        'source_reference' => 'software-only://kit-preparation-demo/norm',
                        'permitted_use_reference' => 'Synthetic demonstration percentiles only; not validated national norms for operational decisions.',
                    ],
                    'tables' => [
                        [
                            'metric_code' => 'SAMPLE_COMPOSITE',
                            'output_scale' => 'percentile_rank',
                            'intervals' => [
                                ['min_score' => 0, 'max_score' => 2, 'output_value' => '25'],
                                ['min_score' => 3, 'max_score' => 4, 'output_value' => '75'],
                            ],
                        ],
                    ],
                ],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function keyedSubtest(string $code, string $name, int $displayOrder, int $itemCount): array
    {
        $items = [];

        for ($number = 1; $number <= $itemCount; $number++) {
            $itemNumber = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $items[] = [
                'item_code' => $code.'_'.$itemNumber,
                'local_number' => (string) $number,
                'display_order' => $number,
                'is_scored' => true,
                'option_tokens' => ['A', 'B', 'C', 'D'],
            ];
        }

        return [
            'code' => $code,
            'name' => $name,
            'display_order' => $displayOrder,
            'response_mode' => 'keyed_items',
            'item_order_policy' => 'fixed',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rawCountMetric(string $code, string $name, string $subtestCode): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => $subtestCode,
            'unit' => 'count',
            'min_raw_value' => null,
            'max_raw_value' => null,
            'precision_scale' => 0,
            'is_required' => true,
            'required_output_scales' => ['percentile_rank'],
            'procedure_reference' => null,
            'construct_aptitude_area_id' => null,
            'input_codes' => [],
        ];
    }

    private function syntheticProfile(Institution $institution, User $actor): TestInstrumentVersion
    {
        $instrument = TestInstrument::query()
            ->where('institution_id', $institution->getKey())
            ->where('code', self::PROFILE_CODE)
            ->lockForUpdate()
            ->first();

        if ($instrument === null) {
            $instrument = new TestInstrument;
            $instrument->forceFill([
                'institution_id' => (int) $institution->getKey(),
                'code' => self::PROFILE_CODE,
                'is_synthetic' => true,
            ])->save();
        } elseif (! $instrument->is_synthetic) {
            throw new RuntimeException('The synthetic demo profile code belongs to an institutional profile; it will not be relabeled.');
        }

        $version = TestInstrumentVersion::query()
            ->where('test_instrument_id', $instrument->getKey())
            ->where('version_number', 1)
            ->lockForUpdate()
            ->first();

        if ($version !== null) {
            if ($version->status !== TestInstrumentVersion::STATUS_DRAFT) {
                throw new RuntimeException('The synthetic demo profile version is not an editable draft.');
            }

            return $version;
        }

        return TestInstrumentVersion::query()->create([
            'test_instrument_id' => (int) $instrument->getKey(),
            'version_number' => 1,
            'status' => TestInstrumentVersion::STATUS_DRAFT,
            'display_name' => 'OASIS-3 General Aptitude Battery (Form A)',
            'lock_version' => 1,
            'created_by_user_id' => (int) $actor->getKey(),
        ]);
    }

    private function syntheticKit(Institution $institution, User $actor, string $code, string $name): TestKit
    {
        $kit = TestKit::query()
            ->where('institution_id', $institution->getKey())
            ->where('code', $code)
            ->lockForUpdate()
            ->first();

        if ($kit !== null) {
            if (! $kit->is_synthetic) {
                throw new RuntimeException("The {$code} code belongs to an institutional Test Kit; it will not be relabeled.");
            }

            return $kit;
        }

        return TestKit::query()->create([
            'public_id' => (string) Str::uuid(),
            'institution_id' => (int) $institution->getKey(),
            'code' => $code,
            'name' => $name,
            'is_synthetic' => true,
            'created_by_user_id' => (int) $actor->getKey(),
        ]);
    }

    private function openPreparation(TestKit $kit, User $actor): TestKitPreparation
    {
        return TestKitPreparation::query()->firstOrCreate(
            [
                'test_kit_id' => (int) $kit->getKey(),
                'state' => TestKitPreparation::STATE_PREPARING,
            ],
            [
                'revision' => 1,
                'created_by_user_id' => (int) $actor->getKey(),
                'updated_by_user_id' => (int) $actor->getKey(),
            ],
        );
    }
}
