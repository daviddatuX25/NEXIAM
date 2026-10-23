<?php

use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Support/ExternalFinalFixtures.php';

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
});

test('approves an explicit six-aptitude final definition with no raw or norm artifacts and no fictional publisher', function (): void {
    $norms = AssessmentNormPackageVersion::query()->count();
    $profile = createExternalFinalTestProfile($this->actor, externalFinalConstructIds(), 'SYN-FINAL-APPROVAL');

    expect($profile->status)->toBe(TestInstrumentVersion::STATUS_APPROVED)
        ->and($profile->publisher_name)->toBeNull()
        ->and($profile->subtests()->exists())->toBeFalse()
        ->and($profile->items()->exists())->toBeFalse()
        ->and($profile->metrics()->count())->toBe(6)
        ->and(AssessmentNormPackageVersion::query()->count())->toBe($norms);
    foreach ($profile->metrics as $metric) {
        expect($metric->test_instrument_subtest_id)->toBeNull()
            ->and($metric->min_raw_value)->toBeNull()
            ->and($metric->max_raw_value)->toBeNull()
            ->and($metric->unit)->toBeNull()
            ->and($metric->required_output_scales)->toBe(['percentile_rank']);
    }
});

test('rejects raw inputs, duplicate constructs, unsupported scales and OMR in an explicit final-only definition', function (): void {
    $validator = app(TestInstrumentProfileValidator::class);
    $valid = externalFinalProfilePayload(externalFinalConstructIds(), 'SYN-FINAL-INVALID');
    $invalid = [];
    $invalid['raw bound'] = $valid;
    $invalid['raw bound']['metrics'][0]['min_raw_value'] = 0;
    $invalid['duplicate construct'] = $valid;
    $invalid['duplicate construct']['metrics'][1]['construct_aptitude_area_id'] = $valid['metrics'][0]['construct_aptitude_area_id'];
    $invalid['scale'] = $valid;
    $invalid['scale']['metrics'][0]['required_output_scales'] = ['stanine'];
    $invalid['omr source'] = $valid;
    $invalid['omr source']['metrics'][0]['input_contract_json']['allowed_sources'][] = 'omr';
    $invalid['missing precision'] = $valid;
    $invalid['missing precision']['metrics'][0]['precision_scale'] = null;

    foreach ($invalid as $case => $payload) {
        try {
            $validator->normalize($payload);
            test()->fail("Accepted invalid final-only definition: {$case}");
        } catch (ValidationException $exception) {
            expect(array_keys($exception->errors()))->each->toMatch('/^metrics/');
        }
    }
});

test('incomplete final-only drafts and absent external authority cannot be approved', function (): void {
    $validator = app(TestInstrumentProfileValidator::class);
    $payload = externalFinalProfilePayload(externalFinalConstructIds(), 'SYN-FINAL-INCOMPLETE');
    array_pop($payload['metrics']);
    $normalized = $validator->normalize($payload);
    expect(fn () => $validator->assertMetricInputContractsApprovable($normalized, $normalized['profile']))
        ->toThrow(ValidationException::class);
    $payload = externalFinalProfilePayload(externalFinalConstructIds(), 'SYN-FINAL-NO-AUTHORITY');
    $payload['profile']['permitted_use_reference'] = null;
    $normalized = $validator->normalize($payload);
    expect(fn () => $validator->assertApprovable($normalized, $normalized['profile']))
        ->toThrow(ValidationException::class);
});

test('PostgreSQL enforces the closed rule set and unique external construct bindings', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-specific closed-rule CHECK; application boundaries run on both drivers.');
    }
    $profile = createExternalFinalTestProfile($this->actor, externalFinalConstructIds(), 'SYN-FINAL-SQL');
    $draft = TestInstrumentVersion::query()->create([
        'test_instrument_id' => $profile->test_instrument_id,
        'version_number' => 2,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'display_name' => 'Synthetic constraint boundary draft',
        'lock_version' => 1,
        'created_by_user_id' => $this->actor->id,
    ]);
    $entry = externalFinalProfilePayload(externalFinalConstructIds(), 'SYN-FINAL-SQL')['metrics'][0];
    unset($entry['subtest_code'], $entry['input_codes']);
    $entry['test_instrument_version_id'] = $draft->id;
    TestInstrumentMetric::query()->create($entry);
    expect(fn () => DB::transaction(fn () => TestInstrumentMetric::query()->create([
        ...$entry, 'code' => 'DUPLICATE_CONSTRUCT',
    ])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => TestInstrumentMetric::query()->create([
        ...$entry, 'code' => 'UNKNOWN_RULE', 'rule_kind' => 'unsupported_kind',
    ])))->toThrow(QueryException::class);
});
