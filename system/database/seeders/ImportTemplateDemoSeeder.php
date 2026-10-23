<?php

namespace Database\Seeders;

use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;

class ImportTemplateDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || config('database.default') !== 'sqlite'
            || ! str_starts_with((string) config('database.connections.sqlite.database'), '/tmp/nexiam-import-template')) {
            throw new RuntimeException('Template fixtures require an isolated /tmp/nexiam-import-template* SQLite database.');
        }
        (new DatabaseSeeder)->run([ReferenceCatalogSeeder::class, CapabilityCatalogSeeder::class, StaffIdentitySeeder::class, DepartmentSeeder::class]);
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        foreach ([CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, CapabilityCatalog::GUIDANCE_SCORING_IMPORT, CapabilityCatalog::GUIDANCE_SCORING_MANUAL] as $capability) {
            Permission::findOrCreate($capability, 'web');
            $actor->givePermissionTo($capability);
        }
        $this->createKit($actor, $institution, $cycle, 'SYN-HEADER-EIGHT', ['VERBAL', 'QUANT', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE']);
        $this->createKit($actor, $institution, $cycle, 'SYN-HEADER-TWO', ['WORD_COUNT', 'NUMBER_COUNT']);
        $this->command?->info('Synthetic headers ready; no applicants, assignments, scores or OMR evidence seeded.');
    }

    /** @param list<string> $codes */
    private function createKit(User $actor, Institution $institution, AcademicCycle $cycle, string $code, array $codes): void
    {
        if (TestKit::query()->where('institution_id', $institution->id)->where('code', $code)->exists()) {
            return;
        }
        $subtests = [];
        $metrics = [];
        foreach ($codes as $index => $metricCode) {
            $subtestCode = 'SUB_'.($index + 1);
            $subtests[] = [
                'code' => $subtestCode, 'name' => 'Synthetic '.$metricCode, 'display_order' => $index + 1,
                'response_mode' => 'keyed_items', 'item_order_policy' => 'fixed',
                'items' => [
                    ['item_code' => $subtestCode.'_1', 'local_number' => '1', 'display_order' => 1, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
                    ['item_code' => $subtestCode.'_2', 'local_number' => '2', 'display_order' => 2, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
                ],
            ];
            $metrics[] = [
                'code' => $metricCode, 'name' => 'Synthetic '.str_replace('_', ' ', $metricCode).' count',
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT, 'subtest_code' => $subtestCode,
                'unit' => 'count', 'min_raw_value' => null, 'max_raw_value' => null, 'precision_scale' => 0,
                'is_required' => $index === 0, 'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null, 'construct_aptitude_area_id' => null, 'input_codes' => [],
                'input_contract_json' => [
                    'allowed_sources' => ['import', 'manual'], 'required_resources' => [],
                    'source_requirements' => [
                        'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                        'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                    ],
                ],
            ];
        }
        $payload = [
            'profile' => [
                'code' => $code, 'display_name' => 'Synthetic header demo '.$code,
                'publisher_name' => 'Synthetic software fixture', 'form_code' => 'SYNTHETIC',
                'edition' => 'Synthetic header discovery only', 'language' => 'Synthetic',
                'source_reference' => 'test-fixture://synthetic-header-demo',
                'permitted_use_reference' => 'test-fixture://synthetic-header-discovery-only',
                'material_requirement' => 'no_paper',
                'administration_procedure_reference' => 'Synthetic header discovery only, not institutional scoring or OMR qualification.',
            ], 'subtests' => $subtests, 'metrics' => $metrics,
        ];
        $instrument = new TestInstrument(['institution_id' => $institution->id, 'code' => $code]);
        $instrument->forceFill(['is_synthetic' => true])->save();
        $draft = new TestInstrumentVersion([
            'test_instrument_id' => $instrument->id, 'version_number' => 1, 'status' => TestInstrumentVersion::STATUS_DRAFT,
            'lock_version' => 1, 'created_by_user_id' => $actor->id,
        ]);
        app(TestInstrumentDraftWriter::class)->save($draft, app(TestInstrumentProfileValidator::class)->normalize($payload));
        $profile = app(ApproveTestInstrumentVersion::class)->handle($actor, (int) $instrument->id, (int) $draft->id, (int) $draft->lock_version, (string) Str::uuid(), sourceReviewAttestation: true, isDemo: true);
        $kit = TestKit::query()->create([
            'public_id' => (string) Str::uuid(), 'institution_id' => $institution->id, 'code' => $code,
            'name' => 'Synthetic header demo · '.count($codes).' inputs', 'is_synthetic' => true, 'created_by_user_id' => $actor->id,
        ]);
        $preparation = app(StartTestKitPreparation::class)->handle($actor, (int) $kit->id, ['expected_family_version' => 0, 'idempotency_key' => (string) Str::uuid()]);
        $preparation = app(SaveTestKitPreparation::class)->handle($actor, (int) $kit->id, [
            'preparation_id' => (int) $preparation->id, 'expected_revision' => (int) $preparation->revision,
            'idempotency_key' => (string) Str::uuid(), 'answer_sheet_draft_id' => null, 'test_instrument_version_id' => (int) $profile->id,
            'norm_package_version_id' => null, 'template_version_id' => null, 'booklet_version_id' => null, 'booklet_set_id' => null,
        ]);
        $version = app(AssembleTestKitPreparation::class)->handle($actor, (int) $kit->id, [
            'preparation_id' => (int) $preparation->id, 'expected_revision' => (int) $preparation->revision, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $version->forceFill(['status' => TestKitVersion::STATUS_ACTIVE, 'activated_at' => now(), 'activated_by_user_id' => $actor->id, 'revision' => (int) $version->revision + 1])->save();
        ExamSession::query()->create([
            'academic_cycle_id' => $cycle->id, 'test_kit_version_id' => $version->id, 'test_instrument_version_id' => $profile->id,
            'code' => $code.'-SESSION', 'label' => 'Synthetic header context · empty roster',
            'status' => ExamSession::STATUS_COMPLETED, 'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
            'starts_at' => now()->subHours(2), 'effective_end_at' => now()->subHour(), 'closed_at' => now()->subHour(), 'version' => 1,
        ]);
    }
}
