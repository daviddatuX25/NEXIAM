<?php

use App\Assessment\Actions\CreateTestKitPreparation;
use App\Assessment\Queries\TestKitTestSetupQuery;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitPreparation;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/** @return array<string, mixed> */
function testKitTestSetupHttpPayload(string $suffix, int $preparationId, int $preparationRevision): array
{
    return [
        'preparation_id' => $preparationId,
        'expected_preparation_revision' => $preparationRevision,
        'idempotency_key' => (string) Str::uuid(),
        'instrument' => [
            'id' => null,
            'version_id' => null,
            'expected_revision' => null,
            'profile' => [
                'code' => 'PROFILE-'.$suffix,
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

test('Test Setup HTTP save accepts its nested norm package contract', function (): void {
    $preparation = app(CreateTestKitPreparation::class)->handle($this->guidance, [
        'code' => 'HTTP-SETUP-'.Str::upper(Str::random(8)),
        'name' => 'HTTP Test Setup',
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $payload = testKitTestSetupHttpPayload(
        Str::upper(Str::random(8)),
        (int) $preparation->id,
        (int) $preparation->revision,
    );

    $response = actingAsAdmissionSupportStaff($this, $this->guidance)->put(
        route('setup.exam-materials.test-kits.test-setup.update', [
            'testKit' => (int) $preparation->test_kit_id,
            'test_kit_id' => (int) $preparation->test_kit_id,
            'preparation_id' => (int) $preparation->id,
        ]),
        $payload,
    );

    $response->assertRedirect(route('setup.exam-materials.test-kits.test-setup', [
        'testKit' => (int) $preparation->test_kit_id,
        'test_kit_id' => (int) $preparation->test_kit_id,
        'preparation_id' => (int) $preparation->id,
    ]));
    $response->assertSessionHasNoErrors();
});
test('Test Setup HTTP save defaults omitted metric required status to true', function (): void {
    $preparation = app(CreateTestKitPreparation::class)->handle($this->guidance, [
        'code' => 'HTTP-SETUP-'.Str::upper(Str::random(8)),
        'name' => 'HTTP Test Setup',
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $payload = testKitTestSetupHttpPayload(
        Str::upper(Str::random(8)),
        (int) $preparation->id,
        (int) $preparation->revision,
    );
    unset($payload['instrument']['metrics'][0]['is_required']);

    $response = actingAsAdmissionSupportStaff($this, $this->guidance)->put(
        route('setup.exam-materials.test-kits.test-setup.update', [
            'testKit' => (int) $preparation->test_kit_id,
            'test_kit_id' => (int) $preparation->test_kit_id,
            'preparation_id' => (int) $preparation->id,
        ]),
        $payload,
    );

    $response->assertRedirect(route('setup.exam-materials.test-kits.test-setup', [
        'testKit' => (int) $preparation->test_kit_id,
        'test_kit_id' => (int) $preparation->test_kit_id,
        'preparation_id' => (int) $preparation->id,
    ]));
    $response->assertSessionHasNoErrors();
});

test('Test Setup HTTP rejects invalid percentile rank interval values', function (mixed $outputValue): void {
    $preparation = app(CreateTestKitPreparation::class)->handle($this->guidance, [
        'code' => 'HTTP-SETUP-'.Str::upper(Str::random(8)),
        'name' => 'HTTP Test Setup',
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $payload = testKitTestSetupHttpPayload(
        Str::upper(Str::random(8)),
        (int) $preparation->id,
        (int) $preparation->revision,
    );
    $payload['norm']['tables'][0]['intervals'][0]['output_value'] = $outputValue;

    actingAsAdmissionSupportStaff($this, $this->guidance)->put(
        route('setup.exam-materials.test-kits.test-setup.update', [
            'testKit' => (int) $preparation->test_kit_id,
            'test_kit_id' => (int) $preparation->test_kit_id,
            'preparation_id' => (int) $preparation->id,
        ]),
        $payload,
    )->assertSessionHasErrors('norm.tables.0.intervals.0.output_value');
})->with([
    'above the percentile maximum' => ['150'],
    'non-numeric text' => ['not-a-number'],
]);

test('Test Setup HTTP saves a final-only profile with no norm package and derives no-paper applicability', function (): void {
    $preparation = app(CreateTestKitPreparation::class)->handle($this->guidance, [
        'code' => 'HTTP-FINAL-'.Str::upper(Str::random(8)),
        'name' => 'HTTP External Final Test Setup',
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $definition = externalFinalProfilePayload(externalFinalConstructIds(), 'HTTP-FINAL-'.Str::upper(Str::random(8)));
    $payload = testKitTestSetupHttpPayload(
        Str::upper(Str::random(8)),
        (int) $preparation->id,
        (int) $preparation->revision,
    );
    $payload['instrument']['profile'] = $definition['profile'];
    $payload['instrument']['subtests'] = [];
    $payload['instrument']['metrics'] = $definition['metrics'];
    unset($payload['norm']);

    $response = actingAsAdmissionSupportStaff($this, $this->guidance)->put(
        route('setup.exam-materials.test-kits.test-setup.update', [
            'testKit' => (int) $preparation->test_kit_id,
            'test_kit_id' => (int) $preparation->test_kit_id,
            'preparation_id' => (int) $preparation->id,
        ]),
        $payload,
    );

    $response->assertRedirect(route('setup.exam-materials.test-kits.test-setup', [
        'testKit' => (int) $preparation->test_kit_id,
        'test_kit_id' => (int) $preparation->test_kit_id,
        'preparation_id' => (int) $preparation->id,
    ]));
    $response->assertSessionHasNoErrors();
    $savedPreparation = TestKitPreparation::query()->findOrFail($preparation->id);
    $version = TestInstrumentVersion::query()->findOrFail($savedPreparation->test_instrument_version_id);
    $projection = app(TestKitTestSetupQuery::class)->query(
        $this->guidance,
        (int) $preparation->test_kit_id,
    );

    expect($savedPreparation->norm_package_version_id)->toBeNull()
        ->and($version->material_requirement)->toBe('no_paper')
        ->and($version->metrics)->toHaveCount(6)
        ->and($projection['test_setup']['norm'])->toBeNull()
        ->and($projection['test_setup']['instrument']['profile_metadata']['is_final_only'])->toBeTrue();
});
