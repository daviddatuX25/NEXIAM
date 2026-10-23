<?php

use App\Assessment\Actions\SaveTestInstrumentDraft;
use App\Assessment\Actions\SaveTestKitTestSetup;
use App\Assessment\Queries\TestKitTestSetupQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\TestInstrument;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @return array<string, mixed> */
function standaloneTestSetupPayload(string $suffix, int $instrumentId, int $versionId, int $revision): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'instrument' => [
            'id' => $instrumentId,
            'version_id' => $versionId,
            'expected_revision' => $revision,
            'profile' => [
                'code' => 'INSTRUMENT-'.$suffix,
                'display_name' => 'Profile '.$suffix,
                'publisher_name' => 'Profile publisher '.$suffix,
                'form_code' => 'FORM-'.$suffix,
                'edition' => 'Profile edition '.$suffix,
                'language' => 'English',
                'source_reference' => 'profile-source:'.$suffix,
                'permitted_use_reference' => 'profile-rights:'.$suffix,
                'material_requirement' => 'unresolved',
                'administration_procedure_reference' => null,
            ],
            'subtests' => [[
                'code' => 'SUBTEST-'.$suffix,
                'name' => 'Subtest '.$suffix,
                'display_order' => 1,
                'response_mode' => 'keyed_items',
                'item_order_policy' => 'fixed',
                'items' => [[
                    'item_code' => 'ITEM-'.$suffix,
                    'local_number' => '1',
                    'display_order' => 1,
                    'is_scored' => true,
                    'option_tokens' => ['A', 'B'],
                ]],
            ]],
            'metrics' => [[
                'code' => 'RAW-'.$suffix,
                'name' => 'Raw metric '.$suffix,
                'rule_kind' => 'correct_item_count',
                'subtest_code' => 'SUBTEST-'.$suffix,
                'unit' => 'count',
                'min_raw_value' => null,
                'max_raw_value' => null,
                'precision_scale' => 0,
                'is_required' => true,
                'required_output_scales' => ['percentile_rank'],
                'procedure_reference' => null,
                'construct_aptitude_area_id' => null,
                'input_codes' => [],
            ]],
        ],
        'norm' => [
            'package_id' => null,
            'version_id' => null,
            'expected_revision' => null,
            'package' => [
                'code' => 'NORMS-'.$suffix,
                'display_name' => 'Norms '.$suffix,
                'publisher_name' => 'Norm publisher '.$suffix,
                'form_code' => 'NORM-FORM-'.$suffix,
                'edition' => 'Norm edition '.$suffix,
                'language' => 'English',
                'applicable_population' => 'Population '.$suffix,
                'source_reference' => 'norm-source:'.$suffix,
                'permitted_use_reference' => 'norm-rights:'.$suffix,
            ],
            'tables' => [[
                'metric_code' => 'RAW-'.$suffix,
                'output_scale' => 'percentile_rank',
                'intervals' => [[
                    'min_score' => 0,
                    'max_score' => 1,
                    'output_value' => '41',
                ]],
            ]],
        ],
    ];
}

beforeEach(function (): void {
    seedE2Foundation();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
});

test('standalone Test Setup GET renders unified editor without requiring a test kit', function (): void {
    $suffix = Str::upper(Str::random(8));
    $draft = app(SaveTestInstrumentDraft::class)->handle(
        $this->guidance,
        null,
        null,
        null,
        (string) Str::uuid(),
        [
            'profile' => [
                'code' => 'INSTRUMENT-'.$suffix,
                'display_name' => 'Instrument '.$suffix,
                'material_requirement' => 'unresolved',
            ],
            'subtests' => [],
            'metrics' => [],
        ],
    );
    $instrument = TestInstrument::query()->findOrFail($draft->test_instrument_id);

    actingAsAdmissionSupportStaff($this, $this->guidance)
        ->get(route('setup.test-instruments.setup', ['instrument' => $instrument->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/TestKits/TestSetup')
            ->where('test_kit', null)
            ->where('preparation', null)
            ->where('actions.save', route('setup.test-instruments.setup.update', ['instrument' => $instrument->id], absolute: false))
        );
});

test('standalone Test Setup PUT saves instrument draft and norm tables without preparation_id', function (): void {
    $suffix = Str::upper(Str::random(8));
    $draft = app(SaveTestInstrumentDraft::class)->handle(
        $this->guidance,
        null,
        null,
        null,
        (string) Str::uuid(),
        [
            'profile' => [
                'code' => 'INSTRUMENT-'.$suffix,
                'display_name' => 'Instrument '.$suffix,
                'material_requirement' => 'unresolved',
            ],
            'subtests' => [],
            'metrics' => [],
        ],
    );
    $instrument = TestInstrument::query()->findOrFail($draft->test_instrument_id);
    $payload = standaloneTestSetupPayload($suffix, (int) $instrument->id, (int) $draft->id, (int) $draft->lock_version);

    $response = actingAsAdmissionSupportStaff($this, $this->guidance)->put(
        route('setup.test-instruments.setup.update', ['instrument' => $instrument->id]),
        $payload,
    );

    $response->assertRedirect(route('setup.test-instruments.setup', ['instrument' => $instrument->id]));
    $response->assertSessionHasNoErrors();

    $savedVersion = $instrument->draftVersion()->with(['subtests.items', 'metrics'])->firstOrFail();
    expect($savedVersion->subtests)->toHaveCount(1)
        ->and($savedVersion->subtests->first()->code)->toBe('SUBTEST-'.$suffix)
        ->and($savedVersion->subtests->first()->items)->toHaveCount(1)
        ->and($savedVersion->metrics)->toHaveCount(1)
        ->and($savedVersion->metrics->first()->code)->toBe('RAW-'.$suffix);

    $normTable = AssessmentNormTable::query()
        ->where('metric_code', 'RAW-'.$suffix)
        ->firstOrFail();
    expect($normTable->output_scale)->toBe('percentile_rank')
        ->and($normTable->intervals_json)->toHaveCount(1)
        ->and($normTable->intervals_json[0]['output_value'])->toBe('41');
});

test('standalone Test Setup saves and reopens a final-only definition without a norm package', function (): void {
    $suffix = Str::upper(Str::random(8));
    $definition = externalFinalProfilePayload(externalFinalConstructIds(), 'STANDALONE-FINAL-'.$suffix);
    $draft = app(SaveTestInstrumentDraft::class)->handle(
        $this->guidance,
        null,
        null,
        null,
        (string) Str::uuid(),
        $definition,
    );
    $instrument = TestInstrument::query()->findOrFail($draft->test_instrument_id);

    $saved = app(SaveTestKitTestSetup::class)->handleStandalone(
        $this->guidance,
        (int) $instrument->id,
        [
            'request_id' => (string) Str::uuid(),
            'instrument' => [
                'id' => (int) $instrument->id,
                'version_id' => (int) $draft->id,
                'expected_revision' => (int) $draft->lock_version,
                ...$definition,
            ],
            'norm' => ['package_id' => null, 'version_id' => null, 'expected_revision' => null, 'package' => [], 'tables' => []],
        ],
    );
    $reopened = app(TestKitTestSetupQuery::class)->queryForInstrument(
        $this->guidance,
        (int) $instrument->id,
    );

    expect($saved['norm_package_id'])->toBeNull()
        ->and($saved['norm_version_id'])->toBeNull()
        ->and($reopened['test_setup']['norm'])->toBeNull()
        ->and($reopened['test_setup']['instrument']['profile_metadata']['is_final_only'])->toBeTrue()
        ->and($reopened['test_setup']['instrument']['metrics'])->toHaveCount(6);
});
