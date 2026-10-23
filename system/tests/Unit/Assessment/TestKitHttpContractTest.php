<?php

declare(strict_types=1);

use App\Assessment\Support\KitCompositionContext;
use App\Authorization\AuthorizationDecision;
use App\Authorization\CapabilityAuthorizer;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Support/TestKitHelpers.php';

beforeEach(function (): void {
    $this->testKitPostgresTransactionStarted = false;

    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Test-kit HTTP contracts require the isolated PostgreSQL test-kit configuration.');
    }

    expect((string) DB::scalar('SELECT current_database()'))
        ->toMatch('/^nexiam_test_kit_acceptance_[a-z0-9_]+$/');

    DB::beginTransaction();
    $this->testKitPostgresTransactionStarted = true;
    $this->institution = Institution::query()->create([
        'code' => 'SYN-INST-'.Str::upper(Str::random(6)),
        'name' => 'Synthetic PostgreSQL preparation contract fixture',
    ]);
    $this->actor = User::factory()->create();

    $authorizer = Mockery::mock(CapabilityAuthorizer::class);
    $authorizer->shouldReceive('decideFresh')->andReturnUsing(
        fn ($principal, $capability, $scope): AuthorizationDecision => $principal instanceof User && $principal->is($this->actor)
            ? AuthorizationDecision::allow($capability)
            : AuthorizationDecision::deny('unauthorized', $capability),
    );
    app()->instance(CapabilityAuthorizer::class, $authorizer);
});

afterEach(function (): void {
    if (! $this->testKitPostgresTransactionStarted) {
        return;
    }

    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});
test('PostgreSQL preparation contract assembles the exact saved composition', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $preparation = $fixture['preparation']->fresh();
    $version = $fixture['version']->fresh();
    $resolved = app(KitCompositionContext::class)->resolvePreparation($preparation);

    expect($preparation->state)->toBe(TestKitPreparation::STATE_ASSEMBLED)
        ->and((int) $preparation->test_instrument_version_id)->toBe((int) $fixture['profile']->id)
        ->and((int) $preparation->template_version_id)->toBe((int) $fixture['sheet']->id)
        ->and((int) $preparation->booklet_version_id)->toBe((int) $fixture['booklet']->id)
        ->and((int) $preparation->booklet_set_id)->toBe((int) $fixture['set']->id)
        ->and($resolved['rows'])->toBe($fixture['composition_rows'])
        ->and($resolved['component_vector'])->toBe($fixture['component_vector'])
        ->and((int) $version->source_preparation_id)->toBe((int) $preparation->id)
        ->and((int) $version->source_preparation_revision)->toBe((int) $preparation->revision)
        ->and((int) $version->composition_revision)->toBe((int) $preparation->composition_revision)
        ->and((int) $version->test_kit_id)->toBe((int) $fixture['kit']->id)
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count())->toBe(1);
});
