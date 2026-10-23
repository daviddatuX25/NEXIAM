<?php

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\CreateTestKitPreparation;
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

function testKitPreparationCreateInput(array $overrides = []): array
{
    return array_merge([
        'code' => 'PREP-'.Str::upper(Str::random(8)),
        'name' => 'HTTP preparation flow',
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

test('preparation creation enforces authorization and replays exact requests without duplicating kit scope', function (): void {
    $action = app(CreateTestKitPreparation::class);
    $input = testKitPreparationCreateInput();
    $first = $action->handle($this->actor, $input);
    $replayed = $action->handle($this->actor, $input);
    $unauthorized = User::factory()->create();
    $families = TestKit::query()->count();

    expect($replayed->is($first))->toBeTrue()
        ->and((int) $first->testKit->institution_id)->toBe((int) $this->institution->id)
        ->and((bool) $first->testKit->is_synthetic)->toBeFalse()
        ->and(TestKit::query()->count())->toBe($families)
        ->and(fn () => $action->handle($unauthorized, testKitPreparationCreateInput()))
        ->toThrow(AuthorizationException::class);
});

test('changed create payload conflicts and audit failure rolls back preparation, kit and receipt', function (): void {
    $action = app(CreateTestKitPreparation::class);
    $input = testKitPreparationCreateInput();
    $action->handle($this->actor, $input);
    expect(fn () => $action->handle($this->actor, [...$input, 'name' => 'Conflicting name']))
        ->toThrow(ValidationException::class);

    $families = TestKit::query()->count();
    $preparations = TestKitPreparation::query()->count();
    $operations = DB::table('operations')->count();
    $audits = DB::table('audit_events')->count();
    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    $this->app->instance(RecordAuditEvent::class, $audit);

    expect(fn () => app(CreateTestKitPreparation::class)->handle($this->actor, testKitPreparationCreateInput()))
        ->toThrow(RuntimeException::class, 'audit unavailable');
    expect(TestKit::query()->count())->toBe($families)
        ->and(TestKitPreparation::query()->count())->toBe($preparations)
        ->and(DB::table('operations')->count())->toBe($operations)
        ->and(DB::table('audit_events')->count())->toBe($audits);
});

test('assembly rejects stale preparation revision without creating a version', function (): void {
    $preparation = app(CreateTestKitPreparation::class)->handle($this->actor, testKitPreparationCreateInput());
    $kit = TestKit::query()->findOrFail($preparation->test_kit_id);
    $versions = TestKitVersion::query()->where('test_kit_id', $kit->id)->count();

    expect(fn () => app(AssembleTestKitPreparation::class)->handle($this->actor, (int) $kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]))->toThrow(ValidationException::class);

    expect(TestKitVersion::query()->where('test_kit_id', $kit->id)->count())->toBe($versions);
});
