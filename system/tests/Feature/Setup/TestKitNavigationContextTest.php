<?php

use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Support\TestKitNavigationContext;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestKit;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
});

function navigationContextKit(
    User $actor,
    Institution $institution,
    string $code,
    bool $isSynthetic = true,
): TestKit {
    return TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => (int) $institution->id,
        'code' => $code,
        'name' => 'Synthetic navigation context fixture',
        'is_synthetic' => $isSynthetic,
        'created_by_user_id' => (int) $actor->id,
    ]);
}

test('resolves same-institution kit preparation and ignores arbitrary return URLs', function (): void {
    $kit = navigationContextKit($this->actor, $this->institution, 'SYN-NAV-PREP');
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $request = Request::create('/setup/exam-materials/booklets', 'GET', [
        'test_kit_id' => (int) $kit->id,
        'preparation_id' => (int) $preparation->id,
        'return_url' => 'https://attacker.invalid/redirect',
    ]);

    $context = app(TestKitNavigationContext::class)->resolve($this->actor, $request);

    expect($context)
        ->toMatchArray([
            'test_kit_id' => (int) $kit->id,
            'preparation_id' => (int) $preparation->id,
            'test_kit_version_id' => null,
            'booklet_version_id' => null,
            'read_only' => false,
            'back_url' => route('setup.exam-materials.test-kits.show', ['testKit' => $kit->id], absolute: false),
            'code' => 'SYN-NAV-PREP',
        ])
        ->and($context)->not->toContain('https://attacker.invalid/redirect');
});

test('answer sheet library receives verified kit return context and keeps exact selection identity', function (): void {
    $kit = navigationContextKit($this->actor, $this->institution, 'SYN-NAV-SHEET');
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);

    actingAsAdmissionSupportStaff($this, $this->actor)
        ->get(route('setup.exam-materials.answer-sheets.index', [
            'test_kit_id' => $kit->id,
            'preparation_id' => $preparation->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/OmrConfiguration/Canonical/Index')
            ->where('kit_context.test_kit_id', (int) $kit->id)
            ->where('kit_context.preparation_id', (int) $preparation->id)
            ->where('kit_context.test_kit_version_id', null)
            ->where('kit_context.back_url', route('setup.exam-materials.test-kits.show', [
                'testKit' => $kit->id,
            ], absolute: false))
            ->where('kit_context.read_only', false)
            ->where('selected_template_version_id', null));
});

test('creates an exact Booklet authoring workspace without attaching it to an open preparation', function (): void {
    $kit = navigationContextKit($this->actor, $this->institution, 'INST-NAV-BOOKLET', false);
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);

    $response = actingAsAdmissionSupportStaff($this, $this->actor)
        ->post(route('setup.exam-materials.booklets.store', [
            'test_kit_id' => $kit->id,
            'preparation_id' => $preparation->id,
        ]), [
            'name' => 'Contextual Booklet draft',
            'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
            'template_version_id' => null,
        ]);

    $version = OmrQuestionBookletVersion::query()
        ->whereHas('booklet', fn ($query) => $query->where('name', 'Contextual Booklet draft'))
        ->firstOrFail();
    $expectedUrl = route('setup.exam-materials.booklets.show', [
        'booklet' => $version->booklet_id,
        'test_kit_id' => $kit->id,
        'preparation_id' => $preparation->id,
        'authoring_booklet_version_id' => $version->id,
    ], absolute: false);

    $response->assertRedirect($expectedUrl);
    expect($preparation->fresh()->booklet_version_id)->toBeNull();

    actingAsAdmissionSupportStaff($this, $this->actor)
        ->get($expectedUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Booklets')
            ->where('kit_context.preparation_id', (int) $preparation->id)
            ->where('kit_context.booklet_version_id', null)
            ->where('kit_context.authoring_booklet_version_id', (int) $version->id)
            ->where('selected_booklet_id', (int) $version->booklet_id));
});

test('rejects a Booklet target with mismatched kit provenance', function (): void {
    $kit = navigationContextKit($this->actor, $this->institution, 'SYN-NAV-BOOKLET-PROVENANCE');
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    $institutionalBooklet = app(CreateBookletDraft::class)->handle(
        'Institutional Booklet target',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        isSynthetic: false,
    );
    $request = Request::create('/setup/exam-materials/booklets', 'GET', [
        'test_kit_id' => (int) $kit->id,
        'preparation_id' => (int) $preparation->id,
        'authoring_booklet_version_id' => (int) $institutionalBooklet->id,
    ]);

    expect(app(TestKitNavigationContext::class)->resolve($this->actor, $request))->toBeNull();
});

test('rejects an authoring override on an exact sealed Test Kit version', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $request = Request::create('/setup/exam-materials/booklets', 'GET', [
        'test_kit_id' => (int) $fixture['kit']->id,
        'test_kit_version_id' => (int) $fixture['version']->id,
        'authoring_booklet_version_id' => (int) $fixture['booklet']->id,
    ]);

    expect(app(TestKitNavigationContext::class)->resolve($this->actor, $request))->toBeNull();
});

test('creates a separate authoring target while preserving an existing preparation Booklet selection', function (): void {
    $kit = navigationContextKit($this->actor, $this->institution, 'INST-NAV-BOOKLET-SUCCESSOR', false);
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    $selectedVersion = app(CreateBookletDraft::class)->handle(
        'Already selected Booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        isSynthetic: false,
    );
    $preparation->forceFill([
        'booklet_version_id' => (int) $selectedVersion->id,
    ])->save();

    $response = actingAsAdmissionSupportStaff($this, $this->actor)
        ->post(route('setup.exam-materials.booklets.store', [
            'test_kit_id' => $kit->id,
            'preparation_id' => $preparation->id,
        ]), [
            'name' => 'Separate authoring Booklet',
            'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
            'template_version_id' => null,
        ]);

    $authoringVersion = OmrQuestionBookletVersion::query()
        ->whereHas('booklet', fn ($query) => $query->where('name', 'Separate authoring Booklet'))
        ->firstOrFail();
    $expectedUrl = route('setup.exam-materials.booklets.show', [
        'booklet' => $authoringVersion->booklet_id,
        'test_kit_id' => $kit->id,
        'preparation_id' => $preparation->id,
        'authoring_booklet_version_id' => $authoringVersion->id,
    ], absolute: false);

    $response->assertRedirect($expectedUrl);
    expect($preparation->fresh()->booklet_version_id)->toBe((int) $selectedVersion->id);

    actingAsAdmissionSupportStaff($this, $this->actor)
        ->get($expectedUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kit_context.booklet_version_id', (int) $selectedVersion->id)
            ->where('kit_context.authoring_booklet_version_id', (int) $authoringVersion->id)
            ->where('selected_booklet_id', (int) $authoringVersion->booklet_id));
});

test('does not infer a kit from an arbitrary return URL or fall back to a family', function (): void {
    $kit = navigationContextKit($this->actor, $this->institution, 'SYN-NAV-EMPTY');
    $request = Request::create('/setup/exam-materials/booklets', 'GET', [
        'test_kit_id' => (int) $kit->id,
        'return_url' => '/setup/exam-materials/test-kits/1/versions/1',
    ]);

    expect(app(TestKitNavigationContext::class)->resolve($this->actor, $request))->toBeNull();
});

test('rejects foreign-institution kit and mismatched explicit kit/preparation context', function (): void {
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOREIGN-'.Str::upper(Str::random(6)),
        'name' => 'Foreign synthetic institution',
    ]);
    $foreignKit = navigationContextKit($this->actor, $foreignInstitution, 'SYN-NAV-FOREIGN');
    $localPreparationKit = navigationContextKit($this->actor, $this->institution, 'SYN-NAV-PREP-LOCAL');
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $localPreparationKit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $otherLocalKit = navigationContextKit($this->actor, $this->institution, 'SYN-NAV-OTHER-LOCAL');

    $foreignRequest = Request::create('/setup/exam-materials/booklets', 'GET', [
        'test_kit_id' => (int) $foreignKit->id,
    ]);
    $mismatchedRequest = Request::create('/setup/exam-materials/booklets', 'GET', [
        'test_kit_id' => (int) $otherLocalKit->id,
        'preparation_id' => (int) $preparation->id,
    ]);

    expect(app(TestKitNavigationContext::class)->resolve($this->actor, $foreignRequest))->toBeNull()
        ->and(app(TestKitNavigationContext::class)->resolve($this->actor, $mismatchedRequest))->toBeNull();
});

test('rejects malformed and unrelated query context rather than trusting component or booklet parameters', function (): void {
    $kit = navigationContextKit($this->actor, $this->institution, 'SYN-NAV-STRICT');
    $request = Request::create('/setup/exam-materials/booklets', 'GET', [
        'test_kit_id' => (int) $kit->id,
        'preparation_id' => 'not-an-id',
        'booklet_version_id' => 9001,
        'answer_sheet_draft_id' => 9002,
        'template_version_id' => 9003,
        'authoring_booklet_version_id' => 'not-an-id',
    ]);

    expect(app(TestKitNavigationContext::class)->resolve($this->actor, $request))->toBeNull();
});
