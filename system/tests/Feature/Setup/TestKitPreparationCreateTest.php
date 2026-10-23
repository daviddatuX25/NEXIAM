<?php

use App\Assessment\Actions\CreateTestKitPreparation;
use App\Authorization\AuthorizationDecision;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
});

function createTestKitPreparationInput(array $overrides = []): array
{
    return array_merge([
        'code' => '  KIT-FIRST-'.Str::upper(Str::random(8)).'  ',
        'name' => '  Initial   preparation  ',
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

test('creating a Test Kit atomically opens an empty institutional preparation without a candidate', function (): void {
    $preparation = app(CreateTestKitPreparation::class)->handle($this->actor, createTestKitPreparationInput());
    $kit = TestKit::query()->findOrFail($preparation->test_kit_id);

    expect($preparation->state)->toBe(TestKitPreparation::STATE_PREPARING)
        ->and($preparation->revision)->toBe(1)
        ->and($preparation->test_instrument_version_id)->toBeNull()
        ->and($preparation->norm_package_version_id)->toBeNull()
        ->and($preparation->answer_sheet_draft_id)->toBeNull()
        ->and($preparation->template_version_id)->toBeNull()
        ->and($preparation->booklet_version_id)->toBeNull()
        ->and($preparation->booklet_set_id)->toBeNull()
        ->and($kit->institution_id)->toBe($this->institution->id)
        ->and($kit->created_by_user_id)->toBe($this->actor->id)
        ->and($kit->is_synthetic)->toBeFalse()
        ->and($preparation->created_by_user_id)->toBe($this->actor->id)
        ->and($preparation->updated_by_user_id)->toBe($this->actor->id)
        ->and(TestKitVersion::query()->where('test_kit_id', $kit->id)->exists())->toBeFalse()
        ->and($kit->name)->toBe('Initial preparation');
});

test('creation checks fresh assessment-catalog authority before returning an idempotent replay', function (): void {
    $familiesBefore = TestKit::query()->count();
    $preparationsBefore = TestKitPreparation::query()->count();
    $authorizer = Mockery::mock(CapabilityAuthorizer::class);
    $authorizer->shouldReceive('decideFresh')
        ->twice()
        ->withArgs(fn ($principal, string $capability, array $scope): bool => $principal->is($this->actor)
            && $capability === CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG
            && $scope === ['office' => 'guidance', 'institution_id' => $this->institution->id])
        ->andReturn(AuthorizationDecision::allow(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), AuthorizationDecision::deny('revoked'));
    $this->app->instance(CapabilityAuthorizer::class, $authorizer);
    $input = createTestKitPreparationInput();
    $action = app(CreateTestKitPreparation::class);
    $first = $action->handle($this->actor, $input);

    expect(fn () => $action->handle($this->actor, $input))->toThrow(AuthorizationException::class);
    expect(TestKitPreparation::query()->count())->toBe($preparationsBefore + 1)
        ->and(TestKit::query()->count())->toBe($familiesBefore + 1)
        ->and(TestKitPreparation::query()->findOrFail($first->id)->test_kit_id)->toBe($first->test_kit_id);
});

test('replaying the same create request returns the original preparation without duplicate families', function (): void {
    $familiesBefore = TestKit::query()->count();
    $preparationsBefore = TestKitPreparation::query()->count();
    $auditsBefore = DB::table('audit_events')->where('event_type', 'assessment.test_kit.preparation_created')->count();
    $action = app(CreateTestKitPreparation::class);
    $input = createTestKitPreparationInput();
    $first = $action->handle($this->actor, $input);
    $replayed = $action->handle($this->actor, $input);

    expect($replayed->is($first))->toBeTrue()
        ->and(TestKit::query()->count())->toBe($familiesBefore + 1)
        ->and(TestKitPreparation::query()->count())->toBe($preparationsBefore + 1)
        ->and(DB::table('audit_events')->where('event_type', 'assessment.test_kit.preparation_created')->count())->toBe($auditsBefore + 1);
});

test('reusing a create idempotency key with different content conflicts without another kit', function (): void {
    $familiesBefore = TestKit::query()->count();
    $preparationsBefore = TestKitPreparation::query()->count();
    $action = app(CreateTestKitPreparation::class);
    $input = createTestKitPreparationInput();
    $action->handle($this->actor, $input);

    expect(fn () => $action->handle($this->actor, [...$input, 'name' => 'Different preparation']))
        ->toThrow(ValidationException::class);
    expect(TestKit::query()->count())->toBe($familiesBefore + 1)
        ->and(TestKitPreparation::query()->count())->toBe($preparationsBefore + 1);
});

test('audit failure rolls back the family preparation and create receipt', function (): void {
    $familiesBefore = TestKit::query()->count();
    $preparationsBefore = TestKitPreparation::query()->count();
    $operationsBefore = DB::table('operations')->count();
    $auditsBefore = DB::table('audit_events')->count();
    $failedAudit = Mockery::mock(RecordAuditEvent::class);
    $failedAudit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    $this->app->instance(RecordAuditEvent::class, $failedAudit);

    expect(fn () => app(CreateTestKitPreparation::class)->handle($this->actor, createTestKitPreparationInput()))
        ->toThrow(RuntimeException::class, 'audit unavailable');

    expect(TestKit::query()->count())->toBe($familiesBefore)
        ->and(TestKitPreparation::query()->count())->toBe($preparationsBefore)
        ->and(DB::table('operations')->count())->toBe($operationsBefore)
        ->and(DB::table('audit_events')->count())->toBe($auditsBefore);
});

test('creation rejects caller-supplied foreign institution and synthetic provenance context', function (): void {
    $familiesBefore = TestKit::query()->count();
    $preparationsBefore = TestKitPreparation::query()->count();
    $input = createTestKitPreparationInput([
        'institution_id' => $this->institution->id + 1,
        'is_synthetic' => true,
    ]);

    expect(fn () => app(CreateTestKitPreparation::class)->handle($this->actor, $input))
        ->toThrow(ValidationException::class);
    expect(TestKit::query()->count())->toBe($familiesBefore)
        ->and(TestKitPreparation::query()->count())->toBe($preparationsBefore);
});
