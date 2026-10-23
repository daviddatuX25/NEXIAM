<?php

use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\CreateTestKitPreparation;
use App\Assessment\Actions\SaveTestInstrumentDraft;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\SaveTestKitTestSetup;
use App\Assessment\Queries\TestKitTestSetupQuery;
use App\Assessment\Support\TestKitNavigationContext;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function kitTestSetupCreatePreparation(User $actor, string $suffix): TestKitPreparation
{
    return app(CreateTestKitPreparation::class)->handle($actor, [
        'code' => 'TEST-SETUP-'.$suffix,
        'name' => 'Test Setup '.$suffix,
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
}

/** @return array<string, mixed> */
function kitTestSetupPayload(string $suffix, ?int $instrumentId = null, ?int $instrumentVersionId = null, ?int $instrumentRevision = null, ?int $normPackageId = null, ?int $normVersionId = null, ?int $normRevision = null, array $tables = []): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'preparation_id' => null,
        'expected_preparation_revision' => null,
        'instrument' => [
            'id' => $instrumentId,
            'version_id' => $instrumentVersionId,
            'expected_revision' => $instrumentRevision,
            'profile' => [
                'code' => 'PROFILE-'.$suffix,
                'display_name' => 'Profile '.$suffix,
                'publisher_name' => 'Profile publisher '.$suffix,
                'form_code' => 'FORM-'.$suffix,
                'edition' => 'Profile edition '.$suffix,
                'language' => 'English',
                'source_reference' => 'profile-source:'.$suffix,
                'permitted_use_reference' => 'profile-rights:'.$suffix,
                'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_UNRESOLVED,
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
            'package_id' => $normPackageId,
            'version_id' => $normVersionId,
            'expected_revision' => $normRevision,
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
            'tables' => $tables === [] ? [[
                'metric_code' => 'RAW-'.$suffix,
                'output_scale' => 'percentile_rank',
                'intervals' => [[
                    'min_score' => 0,
                    'max_score' => 1,
                    'output_value' => '41',
                ]],
            ]] : $tables,
        ],
    ];
}

function kitTestSetupSave(User $actor, TestKitPreparation $preparation, string $suffix, array $overrides = []): array
{
    $input = array_replace_recursive(kitTestSetupPayload($suffix), $overrides);
    $input['preparation_id'] = (int) $preparation->id;
    $input['expected_preparation_revision'] = (int) $preparation->revision;

    return app(SaveTestKitTestSetup::class)->handle($actor, (int) $preparation->test_kit_id, $input);
}

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
});

test('kit Test Setup saves and reopens a final-only definition without creating norm records', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $final = externalFinalProfilePayload(externalFinalConstructIds(), 'FINAL-'.$suffix);
    $payload = kitTestSetupPayload($suffix);
    $payload['preparation_id'] = (int) $preparation->id;
    $payload['expected_preparation_revision'] = (int) $preparation->revision;
    $payload['instrument']['profile'] = $final['profile'];
    $payload['instrument']['subtests'] = $final['subtests'];
    $payload['instrument']['metrics'] = $final['metrics'];
    $payload['norm'] = [
        'package_id' => null,
        'version_id' => null,
        'expected_revision' => null,
        'package' => [],
        'tables' => [],
    ];

    $saved = app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $payload);
    $reopened = app(TestKitTestSetupQuery::class)->query($this->guidance, (int) $preparation->test_kit_id);

    expect($saved['norm_package_id'])->toBeNull()
        ->and($saved['norm_version_id'])->toBeNull()
        ->and($reopened['test_setup']['norm'])->toBeNull()
        ->and($reopened['test_setup']['instrument']['profile_metadata']['is_final_only'])->toBeTrue()
        ->and($reopened['test_setup']['instrument']['metrics'])->toHaveCount(6)
        ->and(TestKitPreparation::query()->findOrFail($preparation->id)->norm_package_version_id)->toBeNull()
        ->and(AssessmentNormPackage::query()->where('code', 'NORMS-'.$suffix)->exists())->toBeFalse();
});

test('kit Test Setup creates exact profile and norm drafts and stores no norm text in operation receipts', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));

    $result = kitTestSetupSave($this->guidance, $preparation, $suffix);

    $instrument = TestInstrument::query()->findOrFail($result['instrument_id']);
    $instrumentVersion = TestInstrumentVersion::query()->findOrFail($result['instrument_version_id']);
    $normPackage = AssessmentNormPackage::query()->findOrFail($result['norm_package_id']);
    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($result['norm_version_id']);
    $table = AssessmentNormTable::query()->where('norm_package_version_id', $normVersion->id)->firstOrFail();
    $updatedPreparation = TestKitPreparation::query()->findOrFail($preparation->id);

    expect($instrument->code)->toBe('PROFILE-'.$suffix)
        ->and($normPackage->code)->toBe('NORMS-'.$suffix)
        ->and($instrument->code)->not->toBe($normPackage->code)
        ->and($normVersion->source_reference)->toBe('norm-source:'.$suffix)
        ->and($normVersion->publisher_name)->toBe('Norm publisher '.$suffix)
        ->and($normVersion->norm_package_id)->toBe($normPackage->id)
        ->and($table->metric_code)->toBe('RAW-'.$suffix)
        ->and($table->output_scale)->toBe('percentile_rank')
        ->and($table->intervals_json[0]['output_value'])->toBe('41')
        ->and($updatedPreparation->test_instrument_version_id)->toBe($instrumentVersion->id)
        ->and($updatedPreparation->norm_package_version_id)->toBe($normVersion->id)
        ->and($updatedPreparation->revision)->toBe(2);

    $normReceipt = Operation::query()
        ->where('type', 'assessment.norm-package.save-draft')
        ->latest('created_at')
        ->firstOrFail();
    expect($normReceipt->payload)->toHaveKey('input_sha256')
        ->not->toHaveKey('input')
        ->not->toHaveKey('tables');
});

test('Test Setup preserves an incomplete decimal norm draft for later correction', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $payload = kitTestSetupPayload($suffix);
    $payload['instrument']['metrics'][0]['code'] = 'RAW';
    $payload['norm']['tables'] = [
        [
            'metric_code' => 'RAW',
            'output_scale' => 'percentile_rank',
            'intervals' => [['min_score' => '0', 'max_score' => '1', 'output_value' => '40']],
        ],
        [
            'metric_code' => 'FORMULA',
            'output_scale' => 'percentile_rank',
            'intervals' => [
                ['min_score' => '0', 'max_score' => '1', 'output_value' => '50'],
                ['min_score' => '2', 'max_score' => '3', 'output_value' => '60'],
            ],
        ],
    ];
    $payload['instrument']['metrics'][] = [
        'code' => 'FORMULA',
        'name' => 'Fractional formula metric',
        'rule_kind' => TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
        'subtest_code' => null,
        'unit' => 'count',
        'min_raw_value' => null,
        'max_raw_value' => null,
        'precision_scale' => 2,
        'is_required' => true,
        'required_output_scales' => ['percentile_rank'],
        'procedure_reference' => null,
        'construct_aptitude_area_id' => null,
        'input_codes' => ['RAW'],
        'formula_expression' => '0.50*RAW',
        'input_contract_json' => [
            'allowed_sources' => ['manual', 'import'],
            'required_resources' => [],
            'source_requirements' => [
                'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
            ],
        ],
    ];
    $payload['preparation_id'] = (int) $preparation->id;
    $payload['expected_preparation_revision'] = (int) $preparation->revision;

    $result = app(SaveTestKitTestSetup::class)->handle(
        $this->guidance,
        (int) $preparation->test_kit_id,
        $payload,
    );
    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($result['norm_version_id']);
    $formulaTable = AssessmentNormTable::query()
        ->where('norm_package_version_id', $normVersion->id)
        ->where('metric_code', 'FORMULA')
        ->firstOrFail();
    expect($normVersion->status)->toBe(AssessmentNormPackageVersion::STATUS_DRAFT)
        ->and(array_map(static fn (array $row): array => [
            (string) $row['min_score'], (string) $row['max_score'], (string) $row['output_value'],
        ], $formulaTable->intervals_json))->toBe([['0', '1', '50'], ['2', '3', '60']]);
});

test('exact Test Setup replay returns saved IDs before rejecting its stale preparation revision', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $input = kitTestSetupPayload($suffix);
    $input['preparation_id'] = (int) $preparation->id;
    $input['expected_preparation_revision'] = (int) $preparation->revision;

    $action = app(SaveTestKitTestSetup::class);
    $first = $action->handle($this->guidance, (int) $preparation->test_kit_id, $input);
    $replay = $action->handle($this->guidance, (int) $preparation->test_kit_id, $input);

    expect($replay)->toBe($first)
        ->and(TestKitPreparation::query()->findOrFail($preparation->id)->revision)->toBe(2)
        ->and(AuditEvent::query()->where('event_type', 'assessment.test_kit.test_setup_saved')->count())->toBe(1);
});

test('two preparations can share an exact profile while retaining different norm versions', function (): void {
    $actor = $this->guidance;
    $firstPreparation = kitTestSetupCreatePreparation($actor, Str::upper(Str::random(8)));
    $firstSuffix = Str::upper(Str::random(7));
    $first = kitTestSetupSave($actor, $firstPreparation, $firstSuffix);
    $secondPreparation = kitTestSetupCreatePreparation($actor, Str::upper(Str::random(8)));
    app(SaveTestKitPreparation::class)->handle($actor, (int) $secondPreparation->test_kit_id, [
        'preparation_id' => (int) $secondPreparation->id,
        'expected_revision' => (int) $secondPreparation->revision,
        'idempotency_key' => (string) Str::uuid(),
        'test_instrument_version_id' => $first['instrument_version_id'],
        'norm_package_version_id' => null,
        'answer_sheet_draft_id' => null,
        'template_version_id' => null,
        'booklet_version_id' => null,
        'booklet_set_id' => null,
    ]);
    $secondPreparation->refresh();
    $instrumentVersion = TestInstrumentVersion::query()->findOrFail($first['instrument_version_id']);
    $secondSuffix = Str::upper(Str::random(7));
    $secondPayload = kitTestSetupPayload(
        $firstSuffix,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
    );
    $secondPayload['norm']['package']['code'] = 'NORMS-'.$secondSuffix;
    $secondPayload['norm']['package']['display_name'] = 'Norms '.$secondSuffix;
    $secondPayload['preparation_id'] = (int) $secondPreparation->id;
    $secondPayload['expected_preparation_revision'] = (int) $secondPreparation->revision;
    $second = app(SaveTestKitTestSetup::class)->handle($actor, (int) $secondPreparation->test_kit_id, $secondPayload);

    expect($second['instrument_id'])->toBe($first['instrument_id'])
        ->and($second['instrument_version_id'])->toBe($first['instrument_version_id'])
        ->and($second['norm_package_id'])->not->toBe($first['norm_package_id'])
        ->and($second['norm_version_id'])->not->toBe($first['norm_version_id'])
        ->and(TestKitPreparation::query()->findOrFail($firstPreparation->id)->norm_package_version_id)->toBe($first['norm_version_id'])
        ->and(TestKitPreparation::query()->findOrFail($secondPreparation->id)->norm_package_version_id)->toBe($second['norm_version_id']);
});

test('stale preparation revisions reject a new Test Setup payload without component writes', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $first = kitTestSetupSave($this->guidance, $preparation, $suffix);
    $instrumentVersion = TestInstrumentVersion::query()->findOrFail($first['instrument_version_id']);
    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($first['norm_version_id']);
    $payload = kitTestSetupPayload(
        $suffix,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
        $first['norm_package_id'],
        $first['norm_version_id'],
        (int) $normVersion->lock_version,
    );
    $payload['preparation_id'] = (int) $preparation->id;
    $payload['expected_preparation_revision'] = 1;

    expect(fn () => app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $payload))
        ->toThrow(ValidationException::class);
    expect($preparation->fresh()->revision)->toBe(2)
        ->and($instrumentVersion->fresh()->lock_version)->toBe(1)
        ->and($normVersion->fresh()->lock_version)->toBe(1);
});

test('an explicit empty table list clears the selected draft and stale norm revisions conflict', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $first = kitTestSetupSave($this->guidance, $preparation, $suffix);
    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($first['norm_version_id']);
    $instrumentVersion = TestInstrumentVersion::query()->findOrFail($first['instrument_version_id']);
    $preparation->refresh();

    $clearInput = kitTestSetupPayload(
        $suffix,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
        $first['norm_package_id'],
        $first['norm_version_id'],
        (int) $normVersion->lock_version,
        [],
    );
    $clearInput['request_id'] = (string) Str::uuid();
    $clearInput['preparation_id'] = (int) $preparation->id;
    $clearInput['expected_preparation_revision'] = (int) $preparation->revision;
    $clearInput['norm']['tables'] = [];
    app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $clearInput);

    expect(AssessmentNormTable::query()->where('norm_package_version_id', $normVersion->id)->exists())->toBeFalse();

    $updatedPreparation = $preparation->fresh();
    $staleInput = $clearInput;
    $staleInput['request_id'] = (string) Str::uuid();
    $staleInput['expected_preparation_revision'] = (int) $updatedPreparation->revision;
    $staleInput['instrument']['expected_revision'] = (int) $instrumentVersion->fresh()->lock_version;
    $staleInput['norm']['expected_revision'] = 1;
    $normVersion->newQuery()->whereKey($normVersion->id)->update(['lock_version' => 2]);

    expect(fn () => app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $staleInput))
        ->toThrow(ValidationException::class);
    expect($updatedPreparation->fresh()->revision)->toBe(3);
});

test('omitting norm tables preserves the selected draft tables', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $first = kitTestSetupSave($this->guidance, $preparation, $suffix);
    $preparation->refresh();
    $instrumentVersion = TestInstrumentVersion::query()->findOrFail($first['instrument_version_id']);
    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($first['norm_version_id']);
    $payload = kitTestSetupPayload(
        $suffix,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
        $first['norm_package_id'],
        $first['norm_version_id'],
        (int) $normVersion->lock_version,
    );
    unset($payload['norm']['tables']);
    $payload['request_id'] = (string) Str::uuid();
    $payload['preparation_id'] = (int) $preparation->id;
    $payload['expected_preparation_revision'] = (int) $preparation->revision;

    app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $payload);

    expect(AssessmentNormTable::query()->where('norm_package_version_id', $normVersion->id)->count())->toBe(1)
        ->and(AssessmentNormPackageVersion::query()->findOrFail($normVersion->id)->content_sha256)->not->toBeNull();
});

test('a failing outer audit rolls back profile, norm, selection and receipts', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $input = kitTestSetupPayload($suffix);
    $input['preparation_id'] = (int) $preparation->id;
    $input['expected_preparation_revision'] = (int) $preparation->revision;

    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')
        ->once()
        ->with('assessment.test_kit.test_setup_saved', Mockery::type('array'), (string) $this->guidance->id)
        ->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(RecordAuditEvent::class, $audit);

    expect(fn () => app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $input))
        ->toThrow(RuntimeException::class, 'audit unavailable');

    expect(TestInstrument::query()->where('code', 'PROFILE-'.$suffix)->exists())->toBeFalse()
        ->and(AssessmentNormPackage::query()->where('code', 'NORMS-'.$suffix)->exists())->toBeFalse()
        ->and(TestKitPreparation::query()->findOrFail($preparation->id)->revision)->toBe(1)
        ->and(Operation::query()->where('type', 'test_kit.save_test_setup')->exists())->toBeFalse()
        ->and(Operation::query()->where('type', 'assessment.norm-package.save-draft')->exists())->toBeFalse();
});

test('approved selected norm versions are immutable through kit Test Setup', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $first = kitTestSetupSave($this->guidance, $preparation, $suffix);
    $preparation->refresh();
    $instrumentVersion = TestInstrumentVersion::query()->findOrFail($first['instrument_version_id']);
    $normVersion = AssessmentNormPackageVersion::query()->findOrFail($first['norm_version_id']);
    AssessmentNormPackageVersion::query()->whereKey($normVersion->id)->update(['status' => AssessmentNormPackageVersion::STATUS_APPROVED]);
    $payload = kitTestSetupPayload(
        $suffix,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
        $first['norm_package_id'],
        $first['norm_version_id'],
        (int) $normVersion->lock_version,
    );
    $payload['preparation_id'] = (int) $preparation->id;
    $payload['expected_preparation_revision'] = (int) $preparation->revision;

    expect(fn () => app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $payload))
        ->toThrow(ValidationException::class);
    expect($preparation->fresh()->revision)->toBe(2)
        ->and(TestInstrumentVersion::query()->findOrFail($first['instrument_version_id'])->lock_version)->toBe(1)
        ->and(AssessmentNormTable::query()->where('norm_package_version_id', $normVersion->id)->count())->toBe(1);
});

test('approved selected profiles cannot be edited through kit Test Setup', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $first = kitTestSetupSave($this->guidance, $preparation, $suffix);
    $preparation->refresh();
    $instrumentVersion = TestInstrumentVersion::query()->findOrFail($first['instrument_version_id']);
    $approvalPayload = kitTestSetupPayload($suffix)['instrument'];
    $approvalPayload['profile']['material_requirement'] = TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER;
    $approvalPayload['profile']['administration_procedure_reference'] = 'TEST-FIXTURE: declared no-paper approval procedure';
    $approvalPayload['metrics'][0]['input_contract_json'] = [
        'allowed_sources' => ['manual', 'import'],
        'required_resources' => [],
        'source_requirements' => [
            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
            'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
        ],
    ];
    $instrumentVersion = app(SaveTestInstrumentDraft::class)->handle(
        $this->guidance,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
        (string) Str::uuid(),
        $approvalPayload,
    );
    $instrumentVersion = app(ApproveTestInstrumentVersion::class)->handle(
        $this->guidance,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
        (string) Str::uuid(),
        true,
    );

    $payload = kitTestSetupPayload(
        $suffix,
        $first['instrument_id'],
        $first['instrument_version_id'],
        (int) $instrumentVersion->lock_version,
        $first['norm_package_id'],
        $first['norm_version_id'],
        1,
    );
    $payload['preparation_id'] = (int) $preparation->id;
    $payload['expected_preparation_revision'] = (int) $preparation->revision;

    expect(fn () => app(SaveTestKitTestSetup::class)->handle($this->guidance, (int) $preparation->test_kit_id, $payload))
        ->toThrow(ValidationException::class);
    expect($preparation->fresh()->revision)->toBe(2)
        ->and(AssessmentNormTable::query()->where('norm_package_version_id', $first['norm_version_id'])->count())->toBe(1);
});

test('Test Setup query exposes exact selected source and normalized scoring tables', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $result = kitTestSetupSave($this->guidance, $preparation, $suffix);

    $projection = app(TestKitTestSetupQuery::class)->query($this->guidance, (int) $preparation->test_kit_id);

    expect($projection['read_only'])->toBeFalse()
        ->and($projection['preparation']['selections']['test_instrument_version_id'])->toBe($result['instrument_version_id'])
        ->and($projection['preparation']['selections']['norm_package_version_id'])->toBe($result['norm_version_id'])
        ->and($projection['test_setup']['instrument']['profile']['code'])->toBe('PROFILE-'.$suffix)
        ->and($projection['test_setup']['instrument']['metrics'][0]['code'])->toBe('RAW-'.$suffix)
        ->and($projection['test_setup']['norm']['package']['code'])->toBe('NORMS-'.$suffix)
        ->and($projection['test_setup']['norm']['tables'][0]['metric_code'])->toBe('RAW-'.$suffix)
        ->and($projection['test_setup']['norm']['tables'][0]['intervals'][0]['output_value'])->toBe('41')
        ->and($projection['actions']['save_preparation'])->not->toBeNull();
});
test('standalone profile bridge keeps empty table submissions explicit and clears its norm draft', function (): void {
    $suffix = Str::upper(Str::random(8));
    $profilePayload = kitTestSetupPayload($suffix)['instrument'];
    $input = [
        'profile' => $profilePayload['profile'],
        'subtests' => $profilePayload['subtests'],
        'metrics' => $profilePayload['metrics'],
        'tables' => [[
            'metric_code' => 'RAW-'.$suffix,
            'output_scale' => 'percentile_rank',
            'intervals' => [[
                'min_score' => 0,
                'max_score' => 1,
                'output_value' => '40',
            ]],
        ]],
    ];
    $first = app(SaveTestInstrumentDraft::class)->handle(
        $this->guidance,
        null,
        null,
        null,
        (string) Str::uuid(),
        $input,
    );
    $package = AssessmentNormPackage::query()->where('code', 'INSTRUMENT_'.$first->test_instrument_id)->firstOrFail();
    $normVersion = $package->draftVersion()->firstOrFail();
    expect(AssessmentNormTable::query()->where('norm_package_version_id', $normVersion->id)->count())->toBe(1);

    $input['tables'] = [];
    app(SaveTestInstrumentDraft::class)->handle(
        $this->guidance,
        (int) $first->test_instrument_id,
        (int) $first->id,
        (int) $first->lock_version,
        (string) Str::uuid(),
        $input,
    );

    expect(AssessmentNormTable::query()->where('norm_package_version_id', $normVersion->id)->exists())->toBeFalse();
});

test('sealed profile-only Kit resolves Test Setup navigation without fabricating a Booklet identity', function (): void {
    $preparation = kitTestSetupCreatePreparation($this->guidance, Str::upper(Str::random(8)));
    $suffix = Str::upper(Str::random(8));
    $profileOnlyMetricContract = [
        'allowed_sources' => ['import', 'manual'],
        'required_resources' => [],
        'source_requirements' => [
            'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
        ],
    ];
    $setup = kitTestSetupSave($this->guidance, $preparation, $suffix, [
        'instrument' => [
            'profile' => [
                'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
                'administration_procedure_reference' => 'TEST-FIXTURE: profile-only procedure',
            ],
            'metrics' => [
                0 => ['input_contract_json' => $profileOnlyMetricContract],
            ],
        ],
    ]);

    $profile = TestInstrumentVersion::query()->findOrFail($setup['instrument_version_id']);
    app(ApproveTestInstrumentVersion::class)->handle(
        $this->guidance,
        (int) $setup['instrument_id'],
        (int) $profile->id,
        (int) $profile->lock_version,
        (string) Str::uuid(),
        true,
    );
    $preparation->refresh();
    $preparation = app(SaveTestKitPreparation::class)->handle(
        $this->guidance,
        (int) $preparation->test_kit_id,
        [
            'preparation_id' => (int) $preparation->id,
            'expected_revision' => (int) $preparation->revision,
            'idempotency_key' => (string) Str::uuid(),
            'test_instrument_version_id' => (int) $profile->id,
            'norm_package_version_id' => null,
            'answer_sheet_draft_id' => null,
            'template_version_id' => null,
            'booklet_version_id' => null,
            'booklet_set_id' => null,
        ],
    );
    $version = app(AssembleTestKitPreparation::class)->handle(
        $this->guidance,
        (int) $preparation->test_kit_id,
        [
            'preparation_id' => (int) $preparation->id,
            'expected_revision' => (int) $preparation->revision,
            'idempotency_key' => (string) Str::uuid(),
        ],
    );
    $request = Request::create('/setup/exam-materials/test-kits/test-setup', 'GET', [
        'test_kit_id' => (int) $preparation->test_kit_id,
        'test_kit_version_id' => (int) $version->id,
    ]);

    $context = app(TestKitNavigationContext::class)->resolve($this->guidance, $request);

    expect($version->booklet_version_id)->toBeNull()
        ->and($version->template_version_id)->toBeNull()
        ->and($context)->toMatchArray([
            'test_kit_id' => (int) $preparation->test_kit_id,
            'test_kit_version_id' => (int) $version->id,
            'booklet_version_id' => null,
            'read_only' => true,
        ]);
});
