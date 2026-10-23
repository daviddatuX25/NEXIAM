<?php

declare(strict_types=1);

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\AssembleTestKitVersion;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Assessment\TestKitVersionCompositionRow;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/NormConversionFixtures.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
});

/**
 * @param  array<string, mixed>  $fixture
 * @return array<string, mixed>
 */
function exactAssemblyRequest(array $fixture, string $key): array
{
    $preparation = $fixture['preparation'];
    $composition = app(KitCompositionContext::class)->resolvePreparation($preparation);

    return [
        'template_version_id' => (int) $preparation->template_version_id,
        'booklet_version_id' => (int) $preparation->booklet_version_id,
        'booklet_set_id' => (int) $preparation->booklet_set_id,
        'test_instrument_version_id' => (int) $preparation->test_instrument_version_id,
        'expected_revision' => 0,
        'idempotency_key' => $key,
        'source_preparation_id' => (int) $preparation->id,
        'source_preparation_revision' => (int) $preparation->revision,
        'composition_revision' => (int) $preparation->composition_revision,
        'composition_rows' => $composition['rows'],
        'component_vector' => $composition['component_vector'],
        'component_vector_sha256' => $composition['component_vector_sha256'],
        'composition_sha256' => $composition['composition_sha256'],
    ];
}

test('assembly seals the exact saved preparation map and replays idempotently', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution, assemble: false);
    $request = exactAssemblyRequest($fixture, (string) Str::uuid());
    $action = app(AssembleTestKitVersion::class);

    $first = $action->handle($this->actor, (int) $fixture['kit']->id, $request);
    $replay = $action->handle($this->actor, (int) $fixture['kit']->id, $request);

    $savedSheet = DB::table('omr_answer_sheet_template_versions')->where('id', $fixture['preparation']->template_version_id)->first();
    $savedBooklet = DB::table('omr_question_booklet_versions')->where('id', $fixture['preparation']->booklet_version_id)->first();

    expect($first->id)->toBe($replay->id)
        ->and($first->source_preparation_id)->toBe($fixture['preparation']->id)
        ->and($first->source_preparation_revision)->toBe($fixture['preparation']->revision)
        ->and($first->composition_revision)->toBe($fixture['preparation']->composition_revision)
        ->and($first->composition_sha256)->toBe($request['composition_sha256'])
        ->and($savedSheet->test_instrument_version_id)->toBeNull()
        ->and($savedBooklet->template_version_id)->toBeNull()
        ->and($savedBooklet->test_instrument_version_id)->toBeNull()
        ->and(TestKitVersionCompositionRow::query()->where('test_kit_version_id', $first->id)->count())->toBe(count($request['composition_rows']))
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count())->toBe(1);
});

test('assembly rejects empty, altered, and stale saved preparation evidence', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution, assemble: false);
    $action = app(AssembleTestKitVersion::class);
    $request = exactAssemblyRequest($fixture, (string) Str::uuid());

    expect(fn () => $action->handle($this->actor, (int) $fixture['kit']->id, array_replace($request, [
        'composition_rows' => [],
    ])))->toThrow(ValidationException::class);

    $alteredRows = $request['composition_rows'];
    $alteredRows[0]['test_instrument_item_id'] = (int) $alteredRows[0]['test_instrument_item_id'] + 1000;
    expect(fn () => $action->handle($this->actor, (int) $fixture['kit']->id, array_replace($request, [
        'composition_rows' => $alteredRows,
        'idempotency_key' => (string) Str::uuid(),
    ])))->toThrow(ValidationException::class);

    $alteredVector = $request['component_vector'];
    $alteredVector['test_instrument_version_id'] = (int) $alteredVector['test_instrument_version_id'] + 1000;
    expect(fn () => $action->handle($this->actor, (int) $fixture['kit']->id, array_replace($request, [
        'component_vector' => $alteredVector,
        'idempotency_key' => (string) Str::uuid(),
    ])))->toThrow(ValidationException::class);

    expect(fn () => $action->handle($this->actor, (int) $fixture['kit']->id, array_replace($request, [
        'source_preparation_revision' => $request['source_preparation_revision'] + 1,
        'idempotency_key' => (string) Str::uuid(),
    ])))->toThrow(ValidationException::class)
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count())->toBe(0);
});

test('assembly enforces fresh authorization and rolls back version, receipt, and audit atomically', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution, assemble: false);
    $request = exactAssemblyRequest($fixture, (string) Str::uuid());
    $unauthorizedActor = User::factory()->create();

    expect(fn () => app(AssembleTestKitVersion::class)->handle($unauthorizedActor, (int) $fixture['kit']->id, $request))
        ->toThrow(AuthorizationException::class)
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count())->toBe(0);

    $operationsBeforeFailure = DB::table('operations')->count();
    $auditsBeforeFailure = DB::table('audit_events')->count();
    $versionsBeforeFailure = TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count();
    $auditFailure = Mockery::mock(RecordAuditEvent::class);
    $auditFailure->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit unavailable'));
    $this->app->instance(RecordAuditEvent::class, $auditFailure);

    expect(fn () => app(AssembleTestKitVersion::class)->handle($this->actor, (int) $fixture['kit']->id, $request))
        ->toThrow(RuntimeException::class, 'audit unavailable')
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count())->toBe($versionsBeforeFailure)
        ->and(DB::table('operations')->count())->toBe($operationsBeforeFailure)
        ->and(DB::table('audit_events')->count())->toBe($auditsBeforeFailure);
});
test('synthetic Kit provenance cannot be reclassified before assembly', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution, assemble: false);

    expect(fn () => TestKit::query()->whereKey($fixture['kit']->id)->update(['is_synthetic' => false]))
        ->toThrow(QueryException::class)
        ->and($fixture['kit']->fresh()->is_synthetic)->toBeTrue()
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count())->toBe(0);
});

test('assembly does not accept a completed preparation as a fresh source', function (): void {
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $request = exactAssemblyRequest($fixture, (string) Str::uuid());
    $request['expected_revision'] = (int) $fixture['version']->version_number;

    expect(fn () => app(AssembleTestKitVersion::class)->handle($this->actor, (int) $fixture['kit']->id, $request))
        ->toThrow(ValidationException::class);
});

test('scoring-only preparation saves and seals without creating paper or OMR evidence', function (): void {
    $profile = createTestApprovedProfileVersion($this, $this->actor, 'SYN-NO-PAPER-'.Str::upper(Str::random(8)));
    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $this->actor->staffProfile->institution_id,
        'code' => 'SYN-KIT-'.Str::upper(Str::random(8)),
        'name' => 'Synthetic scoring-only Kit',
        'is_synthetic' => true,
        'created_by_user_id' => $this->actor->id,
    ]);
    $artifactTables = [
        'omr_answer_sheet_template_versions', 'omr_question_booklet_versions',
        'omr_booklet_sets', 'test_kit_version_composition_rows', 'test_kit_acceptance_receipts',
        'test_kit_preparation_composition_rows',
    ];
    $beforeArtifacts = [];
    foreach ($artifactTables as $table) {
        $beforeArtifacts[$table] = DB::table($table)->count();
    }
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $preparation = app(SaveTestKitPreparation::class)->handle($this->actor, $kit->id, [
        'preparation_id' => $preparation->id,
        'expected_revision' => $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
        'test_instrument_version_id' => $profile->id,
        'norm_package_version_id' => null,
        'answer_sheet_draft_id' => null,
        'template_version_id' => null,
        'booklet_version_id' => null,
        'booklet_set_id' => null,
    ]);
    expect(TestKitVersion::query()->where('test_kit_id', $kit->id)->exists())->toBeFalse();

    $version = app(AssembleTestKitPreparation::class)->handle($this->actor, $kit->id, [
        'preparation_id' => $preparation->id,
        'expected_revision' => $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    app(TestKitManifestBuilder::class)->verify($version);

    expect($version->manifest_schema_version)->toBe(3)
        ->and($version->status)->toBe(TestKitVersion::STATUS_CANDIDATE)
        ->and($version->test_instrument_version_id)->toBe($profile->id)
        ->and($version->template_version_id)->toBeNull()
        ->and($version->booklet_version_id)->toBeNull()
        ->and($version->booklet_set_id)->toBeNull()
        ->and($version->selected_acceptance_receipt_id)->toBeNull()
        ->and($version->manifest_json['requirements']['required_resources'])->toBe([])
        ->and($version->manifest_json['requirements']['omr_applicable'])->toBeFalse();
    foreach (['sheet', 'booklet', 'composition', 'item_map'] as $resource) {
        expect($version->manifest_json[$resource])->toBeNull();
    }
    $revisionOneManifest = $version->manifest_json;
    $revisionOneManifestHash = (string) $version->manifest_sha256;
    $successor = app(StartTestKitPreparation::class)->handle($this->actor, $kit->id, [
        'expected_family_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $secondProfile = createTestApprovedProfileVersion($this, $this->actor, 'SYN-NO-PAPER-SUCCESSOR-'.Str::upper(Str::random(8)));
    $successor = app(SaveTestKitPreparation::class)->handle($this->actor, $kit->id, [
        'preparation_id' => (int) $successor->id,
        'expected_revision' => (int) $successor->revision,
        'idempotency_key' => (string) Str::uuid(),
        'test_instrument_version_id' => (int) $secondProfile->id,
        'norm_package_version_id' => null,
        'answer_sheet_draft_id' => null,
        'template_version_id' => null,
        'booklet_version_id' => null,
        'booklet_set_id' => null,
    ]);
    $successorVersion = app(AssembleTestKitPreparation::class)->handle($this->actor, $kit->id, [
        'preparation_id' => (int) $successor->id,
        'expected_revision' => (int) $successor->revision,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    expect($successorVersion->test_instrument_version_id)->toBe($secondProfile->id);
    expect($successorVersion->version_number)->toBe(2)
        ->and($version->fresh()->manifest_json)->toBe($revisionOneManifest)
        ->and($version->fresh()->manifest_sha256)->toBe($revisionOneManifestHash)
        ->and($successorVersion->template_version_id)->toBeNull()
        ->and($successorVersion->booklet_version_id)->toBeNull()
        ->and($successorVersion->booklet_set_id)->toBeNull();
    foreach ($beforeArtifacts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
});
