<?php

use App\Assessment\Actions\Omr\Operational\CompareOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompleteOmrDiagnosticRun;
use App\Assessment\Actions\RecordTestKitAcceptanceReceipt;
use App\Assessment\Support\TestKitEligibility;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

beforeEach(function (): void {
    seedE2Foundation();
});

test('test kit index and create pages require the assessment catalog capability', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/TestKits/Index')
            ->has('test_kits')
            ->where('actions.create', route('setup.exam-materials.test-kits.create')));

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Setup/TestKits/Create'));

    actingAsAdmissionSupportStaff($this, $clerk)
        ->get(route('setup.exam-materials.test-kits.index'))
        ->assertForbidden();

    actingAsAdmissionSupportStaff($this, $clerk)
        ->get(route('setup.exam-materials.test-kits.create'))
        ->assertForbidden();
});

test('test kit store validates preparation identity fields', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->from(route('setup.exam-materials.test-kits.create'))
        ->post(route('setup.exam-materials.test-kits.store'), [])
        ->assertSessionHasErrors([
            'code',
            'name',
            'expected_revision',
            'idempotency_key',
        ]);
});

test('test kit store creates an empty preparation and redirects to its family detail', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $code = 'HTTP-KIT-'.Str::upper(Str::random(8));

    $response = actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.exam-materials.test-kits.store'), [
            'code' => $code,
            'name' => 'HTTP preparation test kit',
            'expected_revision' => 0,
            'idempotency_key' => (string) Str::uuid(),
        ]);
    $kit = TestKit::query()->where('code', $code)->firstOrFail();
    $response->assertRedirect(route('setup.exam-materials.test-kits.show', ['testKit' => $kit->id]));

    $preparation = TestKitPreparation::query()->where('test_kit_id', $kit->id)->firstOrFail();

    expect($kit->is_synthetic)->toBeFalse()
        ->and($preparation->state)->toBe(TestKitPreparation::STATE_PREPARING)
        ->and($preparation->revision)->toBe(1)
        ->and(TestKitVersion::query()->where('test_kit_id', $kit->id)->exists())->toBeFalse();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.show', ['testKit' => $kit->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/TestKits/Show')
            ->where('preparation.state', TestKitPreparation::STATE_PREPARING)
            ->where('test_kit_version', null)
            ->where('trial.trial_url', null)
            ->where('actions.answer_sheet', route('setup.exam-materials.answer-sheets.index', [
                'test_kit_id' => $kit->id,
                'preparation_id' => $preparation->id,
                'answer_sheet_draft_id' => null,
                'template_version_id' => null,
            ], absolute: false)));
});

test('test kit detail requires the assessment catalog capability', function (): void {
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $clerk)
        ->get(route('setup.exam-materials.test-kits.show', ['testKit' => 1]))
        ->assertForbidden();
});

test('test kit lifecycle routes enforce the assessment catalog capability', function (): void {
    $clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $routes = [
        'select-receipt' => ['acceptance_receipt_id' => 1, 'expected_revision' => 1],
        'activate' => ['expected_revision' => 1],
        'retire' => ['expected_revision' => 1],
        'revoke' => ['expected_revision' => 1, 'reason' => 'Test'],
    ];
    foreach ($routes as $name => $payload) {
        actingAsAdmissionSupportStaff($this, $clerk)
            ->post(route('setup.exam-materials.test-kits.'.$name, ['testKit' => 1, 'version' => 1]), $payload)
            ->assertForbidden();
    }
});

test('test kit detail projects authentic current software verification independently from operational holds', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    require_once __DIR__.'/../../Support/TestKitSoftwareAcceptanceFixtures.php';
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $fixture = testKitAcceptanceFixture($guidance, $institution);
    $accepted = testKitAcceptedSoftwareFixture($fixture, $guidance, $institution);

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.show', [
            'testKit' => $accepted['fixture']['kit']->id,
            'version' => $accepted['fixture']['version']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('trial.current_verification', true)
            ->where('trial.historical_result', 'passed')
            ->where('readiness.is_ready_for_activation', false)
            ->has('readiness.operational_holds'));
});
test('test kit detail keeps an authentic stale pass historical rather than current proof', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    require_once __DIR__.'/../../Support/TestKitSoftwareAcceptanceFixtures.php';
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $fixture = testKitAcceptanceFixture($guidance, $institution);
    $accepted = testKitAcceptedSoftwareFixture($fixture, $guidance, $institution);
    $accepted['run']->forceFill(['revision' => $accepted['run']->revision + 1])->save();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.show', [
            'testKit' => $accepted['fixture']['kit']->id,
            'version' => $accepted['fixture']['version']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('trial.current_verification', false)
            ->where('trial.historical_result', 'passed')
            ->where('readiness.is_ready_for_activation', false));
});
test('test kit detail reports an authentic failed receipt without treating it as verified', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    require_once __DIR__.'/../../Support/TestKitSoftwareAcceptanceFixtures.php';
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $fixture = testKitAcceptanceFixture($guidance, $institution);
    $prepared = testKitDiagnosticEvidenceFixture($fixture, $guidance, $institution);
    $run = $prepared['run'];
    app(CompareOmrDiagnosticRun::class)->handle(
        run: $run, actor: $guidance, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid(),
    );
    $run = $run->fresh();
    app(CompleteOmrDiagnosticRun::class)->handle(
        run: $run, actor: $guidance, expectedRevision: (int) $run->revision, idempotencyKey: (string) Str::uuid(),
    );
    $run = $run->fresh();
    $receiptResult = app(RecordTestKitAcceptanceReceipt::class)->handle(
        actor: $guidance, version: $fixture['version'], run: $run, expectedRunRevision: (int) $run->revision,
        protocolSchemaVersion: 'unsupported-test-protocol',
    );

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.show', [
            'testKit' => $fixture['kit']->id,
            'version' => $fixture['version']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('trial.current_verification', false)
            ->where('trial.historical_result', 'failed')
            ->where('trial.receipt_id', (int) $receiptResult['id'])
            ->where('readiness.is_ready_for_activation', false));
});
test('test kit detail provides rich panel view-models to authorized staff', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $fixture = testKitAcceptanceFixture($guidance, $institution);

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.show', [
            'testKit' => $fixture['kit']->id,
            'version' => $fixture['version']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('test_profile')
            ->has('answer_sheet')
            ->has('booklet')
            ->has('norms')
            ->has('trial')
            ->has('recent_receipts')
            ->missing('profile')
            ->missing('norm_package')
            ->missing('acceptance')
            ->where('test_profile.version_id', $fixture['profile']->id)
            ->where('test_profile.version_number', $fixture['profile']->version_number)
            ->where('test_profile.url', route('setup.test-instruments.show', ['instrument' => $fixture['profile']->test_instrument_id], absolute: false))
            ->where('answer_sheet.version_id', $fixture['sheet']->id)
            ->where('answer_sheet.preview_url', route('setup.omr.templates.preview', [
                'version' => $fixture['sheet']->id,
                'test_kit_id' => $fixture['kit']->id,
                'test_kit_version_id' => $fixture['version']->id,
            ], absolute: false))
            ->missing('answer_sheet.studio_url')
            ->where('booklet.version_id', $fixture['booklet']->id)
            ->where('booklet.workspace_url', route('setup.exam-materials.booklets.show', [
                'booklet' => $fixture['booklet']->booklet_id,
                'booklet_version_id' => $fixture['booklet']->id,
                'test_kit_id' => $fixture['kit']->id,
                'test_kit_version_id' => $fixture['version']->id,
            ], absolute: false))
            ->where('norms.status', 'unselected')
            ->where('trial.trial_url', route('setup.exam-materials.omr-test', [
                'test_kit_version_id' => $fixture['version']->id,
            ], absolute: false))
            ->where('readiness.is_ready_for_activation', false)
            ->where('readiness.blockers', fn (Collection $blockers): bool => $blockers->contains('passing_receipt_required')
                && $blockers->contains('institutional_provenance_required')
                && $blockers->contains('physical_qualification_required')
                && $blockers->contains('source_evidence_unavailable'))
            ->has('actions')
            ->where('actions.start_preparation', route('setup.exam-materials.test-kits.preparation.start', [
                'testKit' => $fixture['kit']->id,
            ])));
});

test('test kit detail activation projection uses canonical eligibility blockers', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $fixture = testKitAcceptanceFixture($guidance, $institution);

    $this->mock(TestKitEligibility::class, function (MockInterface $mock) use ($fixture): void {
        $mock->shouldReceive('evaluate')
            ->once()
            ->with(
                Mockery::on(fn (TestKitVersion $version): bool => $version->is($fixture['version'])),
                null,
                TestKitEligibility::PURPOSE_ACTIVATION,
                null,
            )
            ->andReturn([
                'eligible' => false,
                'blockers' => [['code' => 'canonical_hold', 'message' => 'Canonical eligibility hold.']],
                'pins' => [],
            ]);
    });

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.exam-materials.test-kits.show', [
            'testKit' => $fixture['kit']->id,
            'version' => $fixture['version']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('readiness.is_ready_for_activation', false)
            ->where('readiness.blockers', ['canonical_hold'])
            ->where('readiness.blocker_details.0.message', 'Canonical eligibility hold.'));
});
