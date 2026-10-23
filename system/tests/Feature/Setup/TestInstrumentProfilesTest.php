<?php

use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Seeders\TestInstrumentDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function testInstrumentProfilePayload(string $code = 'TEST-AUTHORING-PROFILE'): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'profile' => [
            'code' => $code,
            'display_name' => 'Test profile',
            'publisher_name' => null,
            'form_code' => null,
            'edition' => null,
            'language' => null,
            'source_reference' => null,
            'permitted_use_reference' => null,
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_UNRESOLVED,
            'administration_procedure_reference' => null,
        ],
        'subtests' => [[
            'code' => 'SECTION_A',
            'name' => 'Section A',
            'display_order' => 1,
            'response_mode' => 'keyed_items',
            'item_order_policy' => 'fixed',
            'items' => [[
                'item_code' => 'ITEM_A',
                'local_number' => '1',
                'display_order' => 1,
                'is_scored' => true,
                'option_tokens' => ['A', 'B'],
            ]],
        ]],
        'metrics' => [],
    ];
}

function testInstrumentApprovalPayload(array $payload): array
{
    $payload['profile']['publisher_name'] = 'Isolated software test fixture';
    $payload['profile']['form_code'] = 'TEST-FORM';
    $payload['profile']['edition'] = 'Test-only source fixture';
    $payload['profile']['language'] = 'Test-only';
    $payload['profile']['source_reference'] = 'test-fixture://approved-source-reference';
    $payload['profile']['permitted_use_reference'] = 'test-fixture://permitted-use-reference';
    $payload['metrics'] = [[
        'code' => 'RAW_A',
        'name' => 'Section A count',
        'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
        'subtest_code' => 'SECTION_A',
        'unit' => 'count',
        'min_raw_value' => null,
        'max_raw_value' => null,
        'precision_scale' => 0,
        'is_required' => true,
        'required_output_scales' => ['percentile_rank'],
        'procedure_reference' => null,
        'construct_aptitude_area_id' => null,
        'input_codes' => [],
        'input_contract_json' => [
            'allowed_sources' => ['import', 'manual'],
            'required_resources' => [],
            'source_requirements' => [
                'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
            ],
        ],
    ]];

    return $payload;
}

beforeEach(function (): void {
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
});

test('Guidance can create and inspect a draft without writing scores, keys, or norm rows', function (): void {
    $scoreCount = ScoreRecord::query()->count();
    $payload = testInstrumentProfilePayload();

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $instrument = TestInstrument::query()->where('code', $payload['profile']['code'])->firstOrFail();
    $draft = $instrument->draftVersion()->with('subtests.items')->firstOrFail();

    expect($instrument->is_synthetic)->toBeFalse()
        ->and($instrument->current_approved_version_id)->toBeNull()
        ->and($draft->status)->toBe(TestInstrumentVersion::STATUS_DRAFT)
        ->and($draft->subtests)->toHaveCount(1)
        ->and($draft->subtests->first()->items)->toHaveCount(1)
        ->and($draft->material_requirement)->toBe(TestInstrumentVersion::MATERIAL_REQUIREMENT_UNRESOLVED)
        ->and($draft->administration_procedure_reference)->toBeNull()
        ->and(ScoreRecord::query()->count())->toBe($scoreCount);

    $this->actingAs($this->guidance)
        ->get(route('setup.test-instruments.show', ['instrument' => $instrument->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/TestInstruments/Show')
            ->where('instrument.code', $payload['profile']['code'])
            ->where('instrument.draft_version.status', TestInstrumentVersion::STATUS_DRAFT)
            ->where('instrument.draft_version.profile.material_requirement', TestInstrumentVersion::MATERIAL_REQUIREMENT_UNRESOLVED)
            ->where('instrument.draft_version.profile.administration_procedure_reference', null)
            ->has('instrument.draft_version.subtests.0.items.0.option_tokens', 2)
            ->missing('instrument.draft_version.subtests.0.items.0.correct_option_code')
            ->missing('instrument.institution_id')
        );
});

test('metric declarations persist through approval and successor creation', function (): void {
    $payload = testInstrumentApprovalPayload(testInstrumentProfilePayload('DECLARED-NO-PAPER-PROFILE'));
    $payload['profile']['material_requirement'] = TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER;
    $payload['profile']['administration_procedure_reference'] = 'TEST-ONLY: no-paper procedure';

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $instrument = TestInstrument::query()->where('code', 'DECLARED-NO-PAPER-PROFILE')->firstOrFail();
    $draft = $instrument->draftVersion()->with('metrics')->firstOrFail();
    $contract = $draft->metrics->firstOrFail()->input_contract_json;

    $this->actingAs($this->guidance)
        ->get(route('setup.test-instruments.show', ['instrument' => $instrument->id]))
        ->assertOk();

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.approve', ['instrument' => $instrument->id, 'version' => $draft->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $draft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $approved = $draft->fresh();
    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.successor', ['instrument' => $instrument->id, 'version' => $approved->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $approved->lock_version,
        ])
        ->assertRedirect();

    $successor = $instrument->fresh()->draftVersion()->with('metrics')->firstOrFail();
    expect($contract['required_resources'])->toBe([])
        ->and($contract['allowed_sources'])->toBe(['import', 'manual'])
        ->and($approved->fresh()->metrics()->firstOrFail()->input_contract_json)->toBe($contract)
        ->and($successor->metrics->firstOrFail()->input_contract_json)->toBe($contract);
});

test('approval rejects a draft with an unresolved metric declaration without approving it', function (): void {
    $payload = testInstrumentApprovalPayload(testInstrumentProfilePayload('MISSING-METRIC-DECLARATION'));
    unset($payload['metrics'][0]['input_contract_json']);
    $payload['profile']['material_requirement'] = TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER;
    $payload['profile']['administration_procedure_reference'] = 'TEST-ONLY: missing declaration procedure';

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $instrument = TestInstrument::query()->where('code', 'MISSING-METRIC-DECLARATION')->firstOrFail();
    $draft = $instrument->draftVersion()->firstOrFail();
    $scoresBefore = ScoreRecord::query()->count();

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.approve', ['instrument' => $instrument->id, 'version' => $draft->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $draft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertSessionHasErrors('metrics.0.input_contract_json');

    expect($instrument->fresh()->current_approved_version_id)->toBeNull()
        ->and($draft->fresh()->status)->toBe(TestInstrumentVersion::STATUS_DRAFT)
        ->and(ScoreRecord::query()->count())->toBe($scoresBefore);
});

test('draft ingress rejects unknown metric keys instead of silently dropping them', function (): void {
    $payload = testInstrumentApprovalPayload(testInstrumentProfilePayload('UNKNOWN-METRIC-KEY'));
    $payload['metrics'][0]['legacy_source_mode'] = 'manual';

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertSessionHasErrors('metrics.0');

    expect(TestInstrument::query()->where('code', 'UNKNOWN-METRIC-KEY')->exists())->toBeFalse();
    $payload = testInstrumentApprovalPayload(testInstrumentProfilePayload('UNKNOWN-CONTRACT-KEY'));
    $payload['metrics'][0]['input_contract_json']['unexpected'] = true;

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertSessionHasErrors('metrics.0.input_contract_json');

    expect(TestInstrument::query()->where('code', 'UNKNOWN-CONTRACT-KEY')->exists())->toBeFalse();
});

test('input declarations cover paper manual, recognized, and mixed measurement contracts', function (): void {
    $validator = app(TestInstrumentProfileValidator::class);
    $paperManual = $validator->normalizeInputContract([
        'allowed_sources' => ['manual', 'import'],
        'required_resources' => ['booklet_form'],
        'source_requirements' => [
            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'booklet_form', 'metric']],
            'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'booklet_form', 'metric']],
        ],
    ], 'count', TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT);
    $recognized = $validator->normalizeInputContract([
        'allowed_sources' => ['omr'],
        'required_resources' => ['key', 'answer_sheet', 'booklet_form'],
        'source_requirements' => [
            'omr' => ['unit' => 'count', 'identity_requirements' => ['metric', 'key', 'administration', 'answer_sheet', 'booklet_form']],
        ],
    ], 'count', TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT);
    $mixedInput = testInstrumentProfilePayload('MIXED-DECLARATION-PROFILE');
    $mixedInput['profile']['material_requirement'] = TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED;
    $mixedInput['subtests'][] = [
        'code' => 'DURATION',
        'name' => 'Duration',
        'display_order' => 2,
        'response_mode' => 'recorded_measure',
        'item_order_policy' => 'fixed',
        'items' => [],
    ];
    $mixedInput['metrics'] = [
        [
            'code' => 'RECOGNIZED',
            'name' => 'Recognized count',
            'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => 'SECTION_A',
            'unit' => 'count',
            'is_required' => true,
            'required_output_scales' => [],
            'input_codes' => [],
            'input_contract_json' => $recognized,
        ],
        [
            'code' => 'MEASURED',
            'name' => 'Measured duration',
            'rule_kind' => TestInstrumentMetric::RULE_RECORDED_MEASURE,
            'subtest_code' => 'DURATION',
            'unit' => 'seconds',
            'is_required' => true,
            'required_output_scales' => [],
            'input_codes' => [],
            'input_contract_json' => [
                'allowed_sources' => ['manual'],
                'required_resources' => [],
                'source_requirements' => [
                    'manual' => ['unit' => 'seconds', 'identity_requirements' => ['administration', 'metric']],
                ],
            ],
        ],
    ];
    $mixed = $validator->normalize($mixedInput);

    expect($paperManual['required_resources'])->toBe(['booklet_form'])
        ->and($recognized['allowed_sources'])->toBe(['omr'])
        ->and($mixed['metrics'][0]['input_contract_json']['required_resources'])->toBe(['answer_sheet', 'booklet_form', 'key'])
        ->and($mixed['metrics'][1]['input_contract_json']['required_resources'])->toBe([]);
});
test('legacy NULL metric declarations keep the canonical hash projection unchanged', function (): void {
    $validator = app(TestInstrumentProfileValidator::class);
    $withoutDeclaration = testInstrumentApprovalPayload(testInstrumentProfilePayload('LEGACY-NULL-HASH'));
    unset($withoutDeclaration['metrics'][0]['input_contract_json']);
    $withNullDeclaration = $withoutDeclaration;
    $withNullDeclaration['metrics'][0]['input_contract_json'] = null;

    $legacyNormalized = $validator->normalize($withoutDeclaration);
    $nullNormalized = $validator->normalize($withNullDeclaration);

    expect(array_key_exists('input_contract_json', $nullNormalized['metrics'][0]))->toBeFalse()
        ->and($validator->digest($nullNormalized))->toBe($validator->digest($legacyNormalized));
});
test('material applicability is normalized and included in the profile content digest', function (): void {
    $validator = app(TestInstrumentProfileValidator::class);
    $paperBacked = testInstrumentProfilePayload('PAPER-APPLICABILITY-PROFILE');
    $paperBacked['profile']['material_requirement'] = ' PAPER_BACKED ';
    $paperBacked['profile']['administration_procedure_reference'] = '  TEST-ONLY: paper-backed procedure fixture  ';
    $normalizedPaperBacked = $validator->normalize($paperBacked);

    $noPaper = $paperBacked;
    $noPaper['profile']['material_requirement'] = ' no_paper ';
    $normalizedNoPaper = $validator->normalize($noPaper);

    $differentProcedure = $paperBacked;
    $differentProcedure['profile']['administration_procedure_reference'] = 'TEST-ONLY: alternate procedure fixture';
    $normalizedDifferentProcedure = $validator->normalize($differentProcedure);

    expect($normalizedPaperBacked['profile']['material_requirement'])->toBe(TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED)
        ->and($normalizedPaperBacked['profile']['administration_procedure_reference'])->toBe('TEST-ONLY: paper-backed procedure fixture')
        ->and($normalizedNoPaper['profile']['material_requirement'])->toBe(TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER)
        ->and($validator->digest($normalizedPaperBacked))->not->toBe($validator->digest($normalizedNoPaper))
        ->and($validator->digest($normalizedPaperBacked))->not->toBe($validator->digest($normalizedDifferentProcedure));

    $overlongProcedure = $paperBacked;
    $overlongProcedure['profile']['administration_procedure_reference'] = str_repeat('x', 501);

    expect(fn () => $validator->normalize($overlongProcedure))
        ->toThrow(ValidationException::class);
});

test('non-Guidance staff cannot open or create institution-scoped profiles', function (): void {
    $payload = testInstrumentProfilePayload('DENIED-PROFILE');

    $this->actingAs($this->registrar)
        ->get(route('setup.test-instruments.index'))
        ->assertForbidden();

    $this->actingAs($this->registrar)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertForbidden();

    expect(TestInstrument::query()->where('code', 'DENIED-PROFILE')->exists())->toBeFalse();
});

test('draft create is payload-idempotent and rejects same-key different content', function (): void {
    $payload = testInstrumentProfilePayload('IDEMPOTENT-PROFILE');

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect();
    $instrumentId = TestInstrument::query()->where('code', 'IDEMPOTENT-PROFILE')->value('id');

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect(route('setup.test-instruments.edit', ['instrument' => $instrumentId]));

    expect(TestInstrument::query()->where('code', 'IDEMPOTENT-PROFILE')->count())->toBe(1)
        ->and(TestInstrumentVersion::query()->where('test_instrument_id', $instrumentId)->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'assessment.test_instrument_draft_saved')
            ->where('payload->instrument_id', $instrumentId)->count())->toBe(1);

    $changedPayload = $payload;
    $changedPayload['profile']['display_name'] = 'Different content';
    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $changedPayload)
        ->assertSessionHasErrors('request_id');

    expect(TestInstrument::query()->where('code', 'IDEMPOTENT-PROFILE')->count())->toBe(1)
        ->and(TestInstrumentVersion::query()->where('test_instrument_id', $instrumentId)->count())->toBe(1);
});

test('draft revision conflicts reject stale writes and local item numbers may restart in another subtest', function (): void {
    $payload = testInstrumentProfilePayload('REVISION-PROFILE');
    $payload['subtests'][] = [
        'code' => 'SECTION_B',
        'name' => 'Section B',
        'display_order' => 2,
        'response_mode' => 'keyed_items',
        'item_order_policy' => 'fixed',
        'items' => [[
            'item_code' => 'ITEM_B',
            'local_number' => '1',
            'display_order' => 1,
            'is_scored' => true,
            'option_tokens' => ['A', 'B'],
        ]],
    ];

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect();

    $instrument = TestInstrument::query()->where('code', 'REVISION-PROFILE')->firstOrFail();
    $draft = $instrument->draftVersion()->firstOrFail();
    expect(TestInstrumentItem::query()
        ->where('test_instrument_version_id', $draft->id)
        ->where('local_number', '1')
        ->count())->toBe(2);

    $update = testInstrumentProfilePayload('REVISION-PROFILE');
    $update['request_id'] = (string) Str::uuid();
    $update['expected_revision'] = 1;
    $update['profile']['display_name'] = 'Updated draft';

    $this->actingAs($this->guidance)
        ->put(route('setup.test-instruments.versions.update', ['instrument' => $instrument->id, 'version' => $draft->id]), $update)
        ->assertRedirect();

    $stale = $update;
    $stale['request_id'] = (string) Str::uuid();
    $stale['profile']['display_name'] = 'Stale update';
    $this->actingAs($this->guidance)
        ->put(route('setup.test-instruments.versions.update', ['instrument' => $instrument->id, 'version' => $draft->id]), $stale)
        ->assertSessionHasErrors('expected_revision');

    expect($draft->fresh()->lock_version)->toBe(2)
        ->and($draft->fresh()->display_name)->toBe('Updated draft');
});

test('raw sum dependency cycles and incompatible units are rejected before a profile draft is written', function (): void {
    $payload = testInstrumentProfilePayload('CYCLIC-PROFILE');
    $payload['metrics'] = [
        [
            'code' => 'RAW_A', 'name' => 'Raw A', 'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
            'subtest_code' => null, 'unit' => 'count', 'min_raw_value' => null, 'max_raw_value' => null,
            'precision_scale' => 0, 'is_required' => true, 'required_output_scales' => [],
            'procedure_reference' => null, 'construct_aptitude_area_id' => null, 'input_codes' => ['RAW_B', 'RAW_C'],
        ],
        [
            'code' => 'RAW_B', 'name' => 'Raw B', 'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
            'subtest_code' => null, 'unit' => 'count', 'min_raw_value' => null, 'max_raw_value' => null,
            'precision_scale' => 0, 'is_required' => true, 'required_output_scales' => [],
            'procedure_reference' => null, 'construct_aptitude_area_id' => null, 'input_codes' => ['RAW_A', 'RAW_C'],
        ],
        [
            'code' => 'RAW_C', 'name' => 'Raw C', 'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => 'SECTION_A', 'unit' => 'count', 'min_raw_value' => null, 'max_raw_value' => null,
            'precision_scale' => 0, 'is_required' => true, 'required_output_scales' => [],
            'procedure_reference' => null, 'construct_aptitude_area_id' => null, 'input_codes' => [],
        ],
    ];

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertSessionHasErrors('metrics');
    expect(TestInstrument::query()->where('code', 'CYCLIC-PROFILE')->exists())->toBeFalse();

    $incompatible = testInstrumentProfilePayload('INCOMPATIBLE-UNITS');
    $incompatible['subtests'][] = [
        'code' => 'SECTION_MEASURE', 'name' => 'Recorded section', 'display_order' => 2,
        'response_mode' => 'recorded_measure', 'item_order_policy' => 'fixed', 'items' => [],
    ];
    $incompatible['metrics'] = [
        [
            'code' => 'RAW_COUNT', 'name' => 'Count', 'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
            'subtest_code' => 'SECTION_A', 'unit' => 'count', 'precision_scale' => 0,
            'is_required' => true, 'required_output_scales' => [], 'procedure_reference' => null,
            'construct_aptitude_area_id' => null, 'input_codes' => [],
        ],
        [
            'code' => 'RAW_TIME', 'name' => 'Time', 'rule_kind' => TestInstrumentMetric::RULE_RECORDED_MEASURE,
            'subtest_code' => 'SECTION_MEASURE', 'unit' => 'seconds', 'min_raw_value' => '0', 'max_raw_value' => '1.5',
            'precision_scale' => 2, 'is_required' => true, 'required_output_scales' => [],
            'procedure_reference' => 'test-only procedure', 'construct_aptitude_area_id' => null, 'input_codes' => [],
        ],
        [
            'code' => 'RAW_TOTAL', 'name' => 'Invalid mixed-unit sum', 'rule_kind' => TestInstrumentMetric::RULE_SUM_RAW_METRICS,
            'subtest_code' => null, 'unit' => 'count', 'precision_scale' => 0,
            'is_required' => true, 'required_output_scales' => [], 'procedure_reference' => null,
            'construct_aptitude_area_id' => null, 'input_codes' => ['RAW_COUNT', 'RAW_TIME'],
        ],
    ];

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $incompatible)
        ->assertSessionHasErrors('metrics.2.input_codes');
    expect(TestInstrument::query()->where('code', 'INCOMPATIBLE-UNITS')->exists())->toBeFalse();
});

test('approval requires source evidence and an explicit Guidance review attestation', function (): void {
    $payload = testInstrumentApprovalPayload(testInstrumentProfilePayload('SOURCE-HOLD-PROFILE'));
    $payload['profile']['material_requirement'] = TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED;
    $payload['metrics'][0]['input_contract_json']['required_resources'] = ['booklet_form'];
    $payload['metrics'][0]['input_contract_json']['source_requirements']['import']['identity_requirements'] = ['administration', 'booklet_form', 'metric'];
    $payload['metrics'][0]['input_contract_json']['source_requirements']['manual']['identity_requirements'] = ['administration', 'booklet_form', 'metric'];
    $payload['profile']['administration_procedure_reference'] = 'TEST-ONLY: source validation fixture procedure';
    $payload['profile']['publisher_name'] = null;
    $payload['profile']['source_reference'] = null;
    $payload['profile']['permitted_use_reference'] = null;

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect();

    $instrument = TestInstrument::query()->where('code', 'SOURCE-HOLD-PROFILE')->firstOrFail();
    $draft = $instrument->draftVersion()->firstOrFail();

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.approve', ['instrument' => $instrument->id, 'version' => $draft->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $draft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertSessionHasErrors('profile.publisher_name');

    expect($instrument->fresh()->current_approved_version_id)->toBeNull()
        ->and($draft->fresh()->status)->toBe(TestInstrumentVersion::STATUS_DRAFT);
});

test('synthetic fixtures cannot be approved, while reviewed versions use immutable successors', function (): void {
    $this->seed(TestInstrumentDemoSeeder::class);
    $synthetic = TestInstrument::query()->where('code', 'SYNTHETIC-PROFILE-DEMO')->firstOrFail();
    $syntheticDraft = $synthetic->draftVersion()->firstOrFail();

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.approve', ['instrument' => $synthetic->id, 'version' => $syntheticDraft->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $syntheticDraft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertSessionHasErrors('version');
    $syntheticUnresolved = $syntheticDraft->fresh();
    $syntheticUnresolved->status = TestInstrumentVersion::STATUS_APPROVED;
    expect(fn () => $syntheticUnresolved->save())
        ->toThrow(ValidationException::class);

    $payload = testInstrumentApprovalPayload(testInstrumentProfilePayload('REVIEWED-TEST-PROFILE'));
    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.store'), $payload)
        ->assertRedirect();

    $instrument = TestInstrument::query()->where('code', 'REVIEWED-TEST-PROFILE')->firstOrFail();
    $draft = $instrument->draftVersion()->firstOrFail();
    $unresolvedDraft = $draft->fresh();
    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.approve', ['instrument' => $instrument->id, 'version' => $draft->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $unresolvedDraft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertSessionHasErrors('profile.material_requirement');
    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::transaction(fn () => DB::table('test_instrument_versions')
            ->where('id', $draft->id)
            ->update(['status' => TestInstrumentVersion::STATUS_APPROVED])))
            ->toThrow(QueryException::class, 'declared material applicability');
    }

    expect($unresolvedDraft->fresh()->status)->toBe(TestInstrumentVersion::STATUS_DRAFT);

    $payload['metrics'][0]['input_contract_json']['required_resources'] = ['booklet_form'];
    $payload['metrics'][0]['input_contract_json']['source_requirements']['import']['identity_requirements'] = ['administration', 'booklet_form', 'metric'];
    $payload['metrics'][0]['input_contract_json']['source_requirements']['manual']['identity_requirements'] = ['administration', 'booklet_form', 'metric'];
    $payload['profile']['material_requirement'] = TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED;
    $payload['request_id'] = (string) Str::uuid();
    $payload['expected_revision'] = $unresolvedDraft->lock_version;
    $this->actingAs($this->guidance)
        ->put(route('setup.test-instruments.versions.update', ['instrument' => $instrument->id, 'version' => $draft->id]), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $declaredDraft = $draft->fresh();
    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.approve', ['instrument' => $instrument->id, 'version' => $draft->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $declaredDraft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertSessionHasErrors('profile.administration_procedure_reference');

    $payload['profile']['administration_procedure_reference'] = 'TEST-ONLY: applicability approval fixture';
    $payload['request_id'] = (string) Str::uuid();
    $payload['expected_revision'] = $declaredDraft->lock_version;
    $this->actingAs($this->guidance)
        ->put(route('setup.test-instruments.versions.update', ['instrument' => $instrument->id, 'version' => $draft->id]), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $draft->refresh();
    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.approve', ['instrument' => $instrument->id, 'version' => $draft->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $draft->lock_version,
            'source_review_attestation' => true,
        ])
        ->assertRedirect();

    $approved = $draft->fresh();
    $originalDigest = $approved->content_sha256;
    expect($approved->status)->toBe(TestInstrumentVersion::STATUS_APPROVED)
        ->and($instrument->fresh()->current_approved_version_id)->toBe($approved->id)
        ->and($approved->source_reviewed_by_user_id)->toBe($this->guidance->id)
        ->and($approved->source_reviewed_at)->not->toBeNull()
        ->and($approved->material_requirement)->toBe(TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED)
        ->and($approved->administration_procedure_reference)->toBe('TEST-ONLY: applicability approval fixture')
        ->and($originalDigest)->toMatch('/^[a-f0-9]{64}$/');

    $changedDeclaration = $approved->fresh();
    $changedDeclaration->material_requirement = TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER;
    expect(fn () => $changedDeclaration->save())
        ->toThrow(RuntimeException::class, 'Approved test instrument material applicability is immutable.');

    $changedProcedureReference = $approved->fresh();
    $changedProcedureReference->administration_procedure_reference = 'TEST-ONLY: changed procedure reference';
    expect(fn () => $changedProcedureReference->save())
        ->toThrow(RuntimeException::class, 'Approved test instrument material applicability is immutable.');

    if (DB::connection()->getDriverName() === 'pgsql') {
        expect(fn () => DB::transaction(fn () => DB::table('test_instrument_versions')
            ->where('id', $approved->id)
            ->update([
                'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
                'administration_procedure_reference' => 'TEST-ONLY: changed procedure reference',
            ])))
            ->toThrow(QueryException::class);
    }

    $this->actingAs($this->guidance)
        ->put(route('setup.test-instruments.versions.update', ['instrument' => $instrument->id, 'version' => $approved->id]), [
            ...$payload,
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $approved->lock_version,
        ])
        ->assertSessionHasErrors('version');

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.successor', ['instrument' => $instrument->id, 'version' => $approved->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $approved->lock_version,
        ])
        ->assertRedirect(route('setup.test-instruments.edit', ['instrument' => $instrument->id]));

    $successor = $instrument->fresh()->draftVersion()->with('subtests.items')->firstOrFail();
    expect($successor->version_number)->toBe($approved->version_number + 1)
        ->and($successor->predecessor_version_id)->toBe($approved->id)
        ->and($successor->subtests->first()->items)->toHaveCount(1)
        ->and($instrument->fresh()->current_approved_version_id)->toBe($approved->id)
        ->and($approved->fresh()->content_sha256)->toBe($originalDigest);

    $this->actingAs($this->guidance)
        ->post(route('setup.test-instruments.versions.retire', ['instrument' => $instrument->id, 'version' => $approved->id]), [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => $approved->lock_version,
            'reason' => 'Test-only retirement flow',
        ])
        ->assertRedirect();

    expect($instrument->fresh()->current_approved_version_id)->toBeNull()
        ->and($approved->fresh()->status)->toBe(TestInstrumentVersion::STATUS_RETIRED)
        ->and($approved->fresh()->content_sha256)->toBe($originalDigest)
        ->and($approved->fresh()->display_name)->toBe($payload['profile']['display_name']);
});

test('profile queries stay within the Guidance actor institution', function (): void {
    $payload = testInstrumentProfilePayload('SCOPED-PROFILE');
    $this->actingAs($this->guidance)->post(route('setup.test-instruments.store'), $payload)->assertRedirect();
    $instrument = TestInstrument::query()->where('code', 'SCOPED-PROFILE')->firstOrFail();

    $otherInstitution = Institution::query()->create([
        'code' => 'TEST-INST-002',
        'name' => 'Test institution two',
    ]);
    $this->guidance->staffProfile()->update(['institution_id' => $otherInstitution->id]);

    $this->actingAs($this->guidance->fresh())
        ->get(route('setup.test-instruments.show', ['instrument' => $instrument->id]))
        ->assertNotFound();
});
