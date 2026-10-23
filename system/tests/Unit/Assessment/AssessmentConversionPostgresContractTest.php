<?php

declare(strict_types=1);

use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\ConvertAssessmentResults;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\AssessmentConversionRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

beforeEach(function (): void {
    $this->conversionPostgresTransactionStarted = false;
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Assessment conversion lineage contracts require PostgreSQL.');
    }
    $database = (string) DB::scalar('SELECT current_database()');
    if (! str_starts_with($database, 'nexiam_test_kit_acceptance_')) {
        throw new RuntimeException('Assessment conversion contract writes require the isolated acceptance database.');
    }

    DB::beginTransaction();
    $this->conversionPostgresTransactionStarted = true;
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    foreach ([CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, CapabilityCatalog::GUIDANCE_SCORING_MANUAL] as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $this->guidance->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ]);
});

afterEach(function (): void {
    if (! $this->conversionPostgresTransactionStarted) {
        return;
    }
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function expectAssessmentConversionPgConstraint(Closure $write): void
{
    static $counter = 0;
    $savepoint = 'assessment_conversion_pg_'.(++$counter);
    DB::statement('SAVEPOINT '.$savepoint);
    try {
        $write();
    } catch (QueryException) {
        DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
        DB::statement('RELEASE SAVEPOINT '.$savepoint);

        return;
    }
    DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
    DB::statement('RELEASE SAVEPOINT '.$savepoint);
    throw new RuntimeException('Expected PostgreSQL assessment-conversion constraint to reject the write.');
}

test('PostgreSQL conversion lineage enforces ownership, pending seal prerequisites, immutability, retirement, and legacy manual records', function (): void {
    $profile = createTestApprovedProfileVersion($this, $this->guidance, 'PG-CONV-'.Str::upper(Str::random(8)));
    $first = createTestDirectAdministration($this, $this->guidance, $profile, 'pg-conv-one-'.Str::uuid().'@example.test');
    $second = createTestDirectAdministration($this, $this->guidance, $profile, 'pg-conv-two-'.Str::uuid().'@example.test');
    $actor = $this->guidance;
    $rawRevision = static function (array $fixture) use ($actor): object {
        app(AcceptAssessmentRawResults::class)->handle(
            administration: $fixture['administration'], metricValues: ['RAW_A' => 2],
            source: AssessmentScoringRun::SOURCE_MANUAL, actor: $actor,
            idempotencyKey: (string) Str::uuid(),
        );

        return $fixture['administration']->rawRevisions()->where('is_current', true)->firstOrFail();
    };
    $ownedRaw = $rawRevision($first);
    $foreignRaw = $rawRevision($second);
    $norm = createTestApprovedNormVersion($this->guidance, 'PG-NORM-'.Str::upper(Str::random(8)));
    $revision = app(ConvertAssessmentResults::class)->handle($first['administration'], $norm, $this->guidance);

    expectAssessmentConversionPgConstraint(fn () => DB::table('assessment_conversion_raw_dependencies')->insert([
        'conversion_revision_id' => $revision->id,
        'administration_id' => $first['administration']->id,
        'metric_id' => $foreignRaw->metric_id,
        'raw_result_revision_id' => $foreignRaw->id,
    ]));
    expectAssessmentConversionPgConstraint(fn () => DB::table('assessment_conversion_raw_dependencies')
        ->where('conversion_revision_id', $revision->id)
        ->where('raw_result_revision_id', $ownedRaw->id)
        ->update(['raw_result_revision_id' => $foreignRaw->id]));

    $pending = (new AssessmentConversionRevision)->forceFill([
        'administration_id' => $first['administration']->id, 'norm_package_version_id' => $norm->id,
        'source' => AssessmentConversionRevision::SOURCE_NORM_CONVERTED, 'revision_no' => 2,
        'typed_outputs_json' => [], 'raw_dependency_sha256' => str_repeat('b', 64),
        'status' => AssessmentConversionRevision::STATUS_PENDING, 'converted_by_user_id' => $this->guidance->id,
        'converted_at' => now(), 'is_current' => false,
    ]);
    $pending->save();
    expectAssessmentConversionPgConstraint(function () use ($pending): void {
        $pending->status = AssessmentConversionRevision::STATUS_VALID;
        $pending->is_current = true;
        $pending->save();
    });
    $pending->delete();

    $dependency = DB::table('assessment_conversion_raw_dependencies')->where('conversion_revision_id', $revision->id)->firstOrFail();
    expectAssessmentConversionPgConstraint(fn () => DB::table('assessment_conversion_raw_dependencies')
        ->where('id', $dependency->id)->delete());
    $successor = createTestApprovedNormVersion($this->guidance, 'PG-NORM-'.Str::upper(Str::random(8)), $norm, alternateNorm: true);
    $next = app(ConvertAssessmentResults::class)->handle($first['administration'], $successor, $this->guidance);
    expect($revision->fresh()->status)->toBe(AssessmentConversionRevision::STATUS_SUPERSEDED)
        ->and($next->fresh()->status)->toBe(AssessmentConversionRevision::STATUS_VALID);
    $revision->refresh();
    expectAssessmentConversionPgConstraint(function () use ($revision): void {
        $revision->status = AssessmentConversionRevision::STATUS_VALID;
        $revision->is_current = false;
        $revision->save();
    });

    $manual = (new AssessmentConversionRevision)->forceFill([
        'administration_id' => $second['administration']->id,
        'norm_package_version_id' => null,
        'source' => AssessmentConversionRevision::SOURCE_MANUAL_CONVERTED,
        'revision_no' => 1,
        'typed_outputs_json' => ['RAW_A' => ['percentile_rank' => '75', 'stanine' => '6']],
        'provenance_json' => ['source' => 'manual_converted', 'synthetic_fixture' => true],
        'status' => AssessmentConversionRevision::STATUS_VALID,
        'converted_by_user_id' => $this->guidance->id, 'converted_at' => now(), 'is_current' => true,
    ]);
    $manual->save();
    expect($manual->fresh()->norm_package_version_id)->toBeNull()
        ->and($manual->fresh()->raw_dependency_sha256)->toBeNull()
        ->and(DB::table('assessment_conversion_raw_dependencies')->where('conversion_revision_id', $manual->id)->count())->toBe(0);
})->group('postgres-assessment-conversion');
