<?php

declare(strict_types=1);

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\DesignateIntakePublicPolicy;
use App\Communications\Actions\PublishPublicPolicy;
use App\Communications\Actions\SavePublicPolicy;
use App\Communications\Actions\WithdrawPublicPolicy;
use App\Communications\PublicPolicySourceAuthorizer;
use App\Communications\Queries\AnnouncementQuery;
use App\Communications\Queries\PublicPolicyQuery;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Communications\PublicPolicy;
use App\Models\Communications\PublicPolicyCommandReceipt;
use App\Models\Communications\PublicPolicyRevision;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use App\Support\Navigation\StaffNavigationBuilder;
use Database\Seeders\PublicPoliciesDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    seedE2Foundation();
    $this->clerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
});

test('office clerk publishes a separate general policy without exposing its draft', function (): void {
    $this->actingAs($this->clerk)->post('/communications/policies', [
        'publisher_key' => 'office:guidance', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => 'Synthetic Guidance office policy',
        'content' => 'DEMO CONTENT — reviewed own-office public policy.',
    ])->assertRedirect('/communications/policies');
    $policy = PublicPolicy::query()->where('created_by', $this->clerk->id)->firstOrFail();
    expect($policy->kind)->toBe('policy');
    $this->get("/policies/{$policy->id}")->assertNotFound();
    $this->post("/communications/policies/{$policy->id}/publish", [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ])->assertRedirect('/communications/policies');
    $this->get("/policies/{$policy->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Policy')->where('policy.content', 'DEMO CONTENT — reviewed own-office public policy.')
        ->where('owningProgram', null));
});

test('published correction keeps the old public snapshot until explicit successor publication and retries once', function (): void {
    $save = app(SavePublicPolicy::class);
    $publish = app(PublishPublicPolicy::class);
    $initial = [
        'publisher_key' => 'office:guidance', 'program_id' => null, 'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic correction lifecycle', 'content' => 'Original public text.',
    ];
    $policy = $save->handle($this->clerk, $initial);
    $duplicate = $save->handle($this->clerk, $initial);
    expect((int) $duplicate->id)->toBe((int) $policy->id)
        ->and(PublicPolicyRevision::query()->where('public_policy_id', $policy->id)->count())->toBe(1);

    $policy = $publish->handle($this->clerk, $policy, ['request_id' => (string) Str::uuid(), 'expected_revision' => 1]);
    expect(fn () => $save->handle($this->clerk, [
        'policy_id' => (int) $policy->id, 'expected_revision' => 1, 'request_id' => (string) Str::uuid(),
        'title' => 'Stale synthetic correction', 'content' => 'Stale text.',
    ]))->toThrow(ValidationException::class);
    $releasedRevisionId = (int) $policy->published_revision_id;
    $correctionId = (string) Str::uuid();
    $correction = [
        'policy_id' => (int) $policy->id, 'expected_revision' => (int) $policy->revision,
        'request_id' => $correctionId, 'title' => 'Corrected synthetic lifecycle', 'content' => 'Corrected text stays private until release.',
    ];
    $saved = $save->handle($this->clerk, $correction);
    $replayed = $save->handle($this->clerk, $correction);
    expect((int) $saved->revision)->toBe((int) $replayed->revision)
        ->and((int) $saved->published_revision_id)->toBe($releasedRevisionId)
        ->and(app(PublicPolicyQuery::class)->eligiblePublicDetail((int) $policy->id)['policy']['content'])->toBe('Original public text.')
        ->and(PublicPolicyRevision::query()->where('public_policy_id', $policy->id)->count())->toBe(3)
        ->and(PublicPolicyRevision::query()->where('public_policy_id', $policy->id)->latest('revision')->value('state'))->toBe('draft');

    expect(fn () => $save->handle($this->clerk, [...$correction, 'content' => 'Altered request payload.']))
        ->toThrow(ValidationException::class);
    $released = $publish->handle($this->clerk, $saved, ['request_id' => (string) Str::uuid(), 'expected_revision' => (int) $saved->revision]);
    expect((int) $released->published_revision_id)->not->toBe($releasedRevisionId)
        ->and(app(PublicPolicyQuery::class)->eligiblePublicDetail((int) $policy->id)['policy']['content'])->toBe('Corrected text stays private until release.');
});

test('program-attached policy appears only in the exact active program context', function (): void {
    $program = Program::query()->where('code', 'BSN')->where('is_active', true)->firstOrFail();
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => (int) $program->id,
        'request_id' => (string) Str::uuid(), 'title' => 'Synthetic BSN policy', 'content' => 'Program-only synthetic policy content.',
    ]);
    app(PublishPublicPolicy::class)->handle($this->clerk, $policy, ['request_id' => (string) Str::uuid(), 'expected_revision' => 1]);

    $query = app(PublicPolicyQuery::class);
    expect(collect($query->publicPolicies())->pluck('id'))->not->toContain((int) $policy->id)
        ->and(collect($query->publicPolicies($program))->pluck('id'))->toContain((int) $policy->id)
        ->and($query->eligiblePublicDetail((int) $policy->id)['owningProgram']['code'])->toBe('BSN');
    $this->get('/programs/BSN/policies')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/ProgramPolicies')->where('program.code', 'BSN')->has('policies', 1));
});

test('withdrawal removes public eligibility but retains full immutable history', function (): void {
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => 'Synthetic withdrawn policy', 'content' => 'Full content remains in staff history.',
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, ['request_id' => (string) Str::uuid(), 'expected_revision' => 1]);
    $publishedPointer = (int) $policy->published_revision_id;
    $policy = app(WithdrawPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => (int) $policy->revision, 'reason' => 'This synthetic example is no longer current.',
    ]);

    expect($policy->status)->toBe('withdrawn')->and((int) $policy->published_revision_id)->toBe($publishedPointer)
        ->and(PublicPolicyRevision::query()->where('public_policy_id', $policy->id)->count())->toBe(3);
    $this->get("/policies/{$policy->id}")->assertNotFound();
    $this->actingAs($this->clerk)->get("/communications/policies/{$policy->id}/edit")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Communications/Policies/Edit')->where('policy.status', 'withdrawn')
        ->where('policy.content', 'Full content remains in staff history.')->has('revisions', 3));
});

test('public policies remain readable without falling back to historical PrivacyPolicy rows', function (): void {
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => 'Synthetic standalone public policy', 'content' => 'Policy reading does not require privacy activation.',
    ]);
    app(PublishPublicPolicy::class)->handle($this->clerk, $policy, ['request_id' => (string) Str::uuid(), 'expected_revision' => 1]);
    PrivacyPolicy::query()->create([
        'title' => 'Historical synthetic privacy notice',
        'content' => 'Historical record only; it must not become the canonical public policy.',
        'is_active' => true,
    ]);
    expect(PrivacyPolicy::query()->where('is_active', true)->exists())->toBeTrue()
        ->and(app(AnnouncementQuery::class)->publicPrivacyPolicy()['policy'])->toBeNull()
        ->and(collect(app(PublicPolicyQuery::class)->publicPolicies())->pluck('id'))->toContain((int) $policy->id)
        ->and(app(PublicPolicyQuery::class)->eligiblePublicDetail((int) $policy->id)['policy']['content'])
        ->toBe('Policy reading does not require privacy activation.');
});

test('canonical privacy reader and bulletin retain the immutable published PublicPolicy snapshot', function (): void {
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => null, 'kind' => 'privacy',
        'request_id' => (string) Str::uuid(), 'title' => 'Synthetic designated privacy notice',
        'content' => 'Published privacy text remains authoritative while a draft is edited.',
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => (int) $policy->revision,
    ]);
    $policy = app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
        'expected_intake_policy_id' => null,
    ]);
    $published = $policy->publishedRevision()->firstOrFail();

    app(SavePublicPolicy::class)->handle($this->clerk, [
        'policy_id' => (int) $policy->getKey(),
        'expected_revision' => (int) $policy->revision,
        'request_id' => (string) Str::uuid(),
        'title' => 'Private successor draft',
        'content' => 'This text is not yet public or consentable.',
    ]);

    $this->get('/policies/privacy')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/PrivacyPolicy')
        ->where('policy.id', (int) $policy->getKey())
        ->missing('policy.revision_id')
        ->missing('policy.revision_number')
        ->missing('policy.digest'));
    $reader = app(AnnouncementQuery::class)->publicPrivacyPolicy()['policy'];
    $bulletinRows = collect(app(AnnouncementQuery::class)->publicPolicies())
        ->where('id', (int) $policy->getKey());

    expect($reader)->toMatchArray([
        'id' => (int) $policy->getKey(),
        'title' => $published->title,
        'content' => $published->content,
    ])->not->toHaveKeys(['revision_id', 'revision_number', 'digest'])
        ->and($bulletinRows)->toHaveCount(1)
        ->and($bulletinRows->first())->toMatchArray([
            'kind' => 'privacy',
            'title' => $published->title,
        ]);
});

test('announcement-only support authority does not authorize policy writes', function (): void {
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();
    $authorizer = app(PublicPolicySourceAuthorizer::class);
    expect(app(CapabilityAuthorizer::class)->decideFresh($support, CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE, [
        'institution_id' => $support->actorScope()->institutionId, 'office' => 'guidance',
    ])->allowed)->toBeTrue()
        ->and($authorizer->canManage($support, 'office:guidance'))->toBeFalse();

    expect(fn () => app(SavePublicPolicy::class)->handle($support, [
        'publisher_key' => 'office:guidance', 'program_id' => null, 'request_id' => (string) Str::uuid(),
        'title' => 'Denied synthetic support policy', 'content' => 'No write.',
    ]))->toThrow(AuthorizationException::class);
    expect(PublicPolicy::query()->where('title', 'Denied synthetic support policy')->exists())->toBeFalse();
});

test('academic policy source requires its current owned active target', function (): void {
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->where('is_active', true)->firstOrFail();
    $save = app(SavePublicPolicy::class);
    expect(fn () => $save->handle($head, [
        'publisher_key' => 'program:BSIT', 'request_id' => (string) Str::uuid(),
        'title' => 'Missing target academic policy', 'content' => 'Denied without the required target.',
    ]))->toThrow(AuthorizationException::class);

    $policy = $save->handle($head, [
        'publisher_key' => 'program:BSIT', 'program_id' => (int) $program->id,
        'request_id' => (string) Str::uuid(), 'title' => 'Owned academic policy', 'content' => 'Own-program synthetic policy.',
    ]);
    expect((int) $policy->program_id)->toBe((int) $program->id);
    $navigationItems = collect(app(StaffNavigationBuilder::class)->for($head))
        ->pluck('items')->flatten(1);
    expect($navigationItems->firstWhere('href', '/setup'))->not->toBeNull();
    $this->actingAs($head)->get(route('setup.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Setup/Index')
        ->where('capabilities.setup', false)

        ->where('capabilities.public_policies', true)
        ->where('capabilities.assessment', false));
    $this->get(route('communications.policies.create'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Communications/Policies/Create')
        ->where('publishers.0.key', 'program:BSIT'));
    $privacyCard = collect(app(ComputeSetupReadinessQuery::class)->handle($head)['stages'])
        ->pluck('cards')->flatten(1)->firstWhere('key', 'privacy');
    expect($privacyCard['capability'])->toBe(CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE);
    $program->forceFill(['is_active' => false])->save();
    expect(fn () => app(PublishPublicPolicy::class)->handle($head, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => (int) $policy->revision,
    ]))->toThrow(AuthorizationException::class);
    expect($policy->fresh()->status)->toBe('draft')->and($policy->fresh()->published_revision_id)->toBeNull();
});
test('program policies cannot be classified or designated as institution-wide privacy consent', function (): void {
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->where('is_active', true)->firstOrFail();
    $requestId = (string) Str::uuid();
    $httpRequestId = (string) Str::uuid();
    $savedAuditCount = AuditEvent::query()->where('event_type', 'communications.public_policy_saved')->count();

    expect(fn () => app(SavePublicPolicy::class)->handle($head, [
        'publisher_key' => 'program:BSIT',
        'program_id' => (int) $program->getKey(),
        'kind' => 'privacy',
        'request_id' => $requestId,
        'title' => 'Invalid program privacy policy',
        'content' => 'Program policies cannot become institution-wide consent.',
    ]))->toThrow(ValidationException::class);
    expect(PublicPolicy::query()->where('title', 'Invalid program privacy policy')->exists())->toBeFalse()
        ->and(PublicPolicyCommandReceipt::query()->where('actor_id', $head->getKey())->where('request_id', $requestId)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('event_type', 'communications.public_policy_saved')->count())->toBe($savedAuditCount);

    $this->actingAs($head)->post('/communications/policies', [
        'publisher_key' => 'program:BSIT',
        'program_id' => (int) $program->getKey(),
        'kind' => 'privacy',
        'request_id' => $httpRequestId,
        'title' => 'Invalid HTTP program privacy policy',
        'content' => 'This request must fail at the source boundary.',
    ])->assertSessionHasErrors('kind');
    expect(PublicPolicy::query()->where('title', 'Invalid HTTP program privacy policy')->exists())->toBeFalse()
        ->and(PublicPolicyCommandReceipt::query()->where('actor_id', $head->getKey())->where('request_id', $httpRequestId)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('event_type', 'communications.public_policy_saved')->count())->toBe($savedAuditCount);

    $programPolicy = app(SavePublicPolicy::class)->handle($head, [
        'publisher_key' => 'program:BSIT',
        'program_id' => (int) $program->getKey(),
        'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic own-program policy',
        'content' => 'This remains a program policy.',
    ]);
    $programPolicy = app(PublishPublicPolicy::class)->handle($head, $programPolicy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $programPolicy->revision,
    ]);
    $designationRequestId = (string) Str::uuid();
    expect(fn () => app(DesignateIntakePublicPolicy::class)->handle($head, $programPolicy, [
        'request_id' => $designationRequestId,
        'expected_revision' => (int) $programPolicy->revision,
        'expected_intake_policy_id' => null,
    ]))->toThrow(ValidationException::class);
    expect((bool) $programPolicy->fresh()->is_designated_intake)->toBeFalse()
        ->and(PublicPolicyCommandReceipt::query()->where('actor_id', $head->getKey())->where('request_id', $designationRequestId)->exists())->toBeFalse()
        ->and(PublicPolicy::currentIntakeSelection((int) $head->actorScope()->institutionId))->toBeNull();
});

test('a saved command replay rechecks its recorded grant after revocation', function (): void {
    $payload = [
        'publisher_key' => 'office:guidance', 'program_id' => null, 'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic replay revocation', 'content' => 'Original request.',
    ];
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, $payload);
    $permission = Permission::findByName(CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE, 'web');
    Role::findByName('guidance_clerk', 'web')->revokePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => app(SavePublicPolicy::class)->handle($this->clerk, $payload))
        ->toThrow(AuthorizationException::class);
    expect(PublicPolicyRevision::query()->where('public_policy_id', $policy->id)->count())->toBe(1);
});

test('office policy targets reject inactive and foreign programs without mutation', function (): void {
    $institutionId = (int) $this->clerk->actorScope()->institutionId;
    $inactive = Program::query()->where('institution_id', $institutionId)->where('is_active', true)->firstOrFail();
    $inactive->forceFill(['is_active' => false])->save();
    $foreign = Institution::query()->create(['code' => 'SYN-FOREIGN-POLICY', 'name' => 'Synthetic foreign institution']);
    $foreignProgram = Program::query()->create([
        'institution_id' => $foreign->id, 'department_id' => null, 'code' => 'POLICY-FOREIGN',
        'name' => 'Foreign synthetic program', 'is_active' => true,
    ]);
    $save = app(SavePublicPolicy::class);
    foreach ([$inactive->id, $foreignProgram->id] as $programId) {
        expect(fn () => $save->handle($this->clerk, [
            'publisher_key' => 'office:guidance', 'program_id' => (int) $programId,
            'request_id' => (string) Str::uuid(), 'title' => 'Denied program policy', 'content' => 'Must not be written.',
        ]))->toThrow(AuthorizationException::class);
    }
    expect(PublicPolicy::query()->where('title', 'Denied program policy')->exists())->toBeFalse();
});

test('policy draft management does not release a correction when publish authority is revoked', function (): void {
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => 'Scoped draft correction',
        'content' => 'Previously published office policy.',
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ]);
    Role::findByName('guidance_clerk', 'web')
        ->revokePermissionTo(CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $saved = app(SavePublicPolicy::class)->handle($this->clerk, [
        'policy_id' => $policy->id, 'request_id' => (string) Str::uuid(),
        'expected_revision' => 2, 'title' => 'Private corrected draft', 'content' => 'Private new text.',
    ]);
    expect(app(PublicPolicyQuery::class)->eligiblePublicDetail($policy->id)['policy']['content'])
        ->toBe('Previously published office policy.');
    expect(fn () => app(PublishPublicPolicy::class)->handle($this->clerk, $saved, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 3,
    ]))->toThrow(AuthorizationException::class);
});

test('academic policy publication preserves the original issuing label after canonical rename', function (): void {
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $head->staffProfile->update(['program_codes' => ['BSN', 'BSIT']]);
    $policy = app(SavePublicPolicy::class)->handle($head, [
        'publisher_key' => 'program:BSN', 'program_id' => $program->id,
        'request_id' => (string) Str::uuid(), 'title' => 'Synthetic academic policy', 'content' => 'Original academic document.',
    ]);
    $program->update(['name' => 'Renamed canonical program']);
    $released = app(PublishPublicPolicy::class)->handle($head, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ]);
    expect($released->publisher_label)->toBe('Bachelor of Science in Nursing');
});

test('deactivating an office policy attachment leaves unrelated general policy workspace usable', function (): void {
    $save = app(SavePublicPolicy::class);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $save->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => $program->id,
        'request_id' => (string) Str::uuid(), 'title' => 'Inactive attachment', 'content' => 'Program scope history.',
    ]);
    $general = $save->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => 'Still manageable general policy', 'content' => 'General policy workspace.',
    ]);
    $program->update(['is_active' => false]);
    $this->actingAs($this->clerk)->get('/communications/policies')->assertOk()->assertInertia(fn ($page) => $page
        ->where('policies.data.0.id', $general->id)->has('policies.data', 1));
});

test('repeating policy demo preparation does not resurrect a renamed withdrawn office document', function (): void {
    $seeder = new PublicPoliciesDemoSeeder;
    $seeder->run();
    $policy = PublicPolicy::query()->where('publisher_key', 'office:guidance')->whereNull('program_id')
        ->where('title', PublicPoliciesDemoSeeder::TITLE_GUIDANCE_ETHICS)->firstOrFail();
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'policy_id' => $policy->id, 'request_id' => (string) Str::uuid(),
        'expected_revision' => $policy->revision, 'title' => 'Staff-renamed synthetic document',
        'content' => 'Staff changed this demo draft before retiring the document.',
    ]);
    $policy = app(WithdrawPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => $policy->revision,
        'reason' => 'Staff deliberately retired this synthetic example.',
    ]);
    $seeder->run();

    $publicIds = collect(app(PublicPolicyQuery::class)->publicPolicies())->pluck('id');
    expect($policy->fresh()->status)->toBe('withdrawn')
        ->and($publicIds)->not->toContain((int) $policy->id);
});

test('policy-only grants authorize publication without granting canonical privacy or announcement writes', function (): void {
    $actor = User::factory()->create();
    $scope = $this->clerk->actorScope();
    StaffProfile::query()->create([
        'user_id' => $actor->id, 'institution_id' => $scope->institutionId,
        'office' => 'guidance', 'program_codes' => [], 'active_cycle_code' => $scope->activeCycleCode,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $actor->givePermissionTo([
        CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE,
        CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH,
    ]);
    $navigationItems = collect(app(StaffNavigationBuilder::class)->for($actor))
        ->pluck('items')->flatten(1);
    expect($navigationItems->firstWhere('href', '/setup'))->not->toBeNull();
    $this->actingAs($actor)->get(route('setup.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Setup/Index')
        ->where('capabilities.setup', false)
        ->where('capabilities.public_policies', true)
        ->where('capabilities.announcements', false)
        ->where('readiness.groups.1.categories.8.checks.0.action_capability', 'announcements')
        ->where('readiness.groups.1.categories.8.checks.1.action_capability', 'public_policies'));
    $privacyCard = collect(app(ComputeSetupReadinessQuery::class)->handle($actor)['stages'])
        ->pluck('cards')->flatten(1)->firstWhere('key', 'privacy');
    expect($privacyCard['capability'])->toBe(CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE);
    $this->get(route('communications.policies.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Communications/Policies/Index'));
    $this->actingAs($actor)->post('/communications/policies', [
        'publisher_key' => 'office:guidance', 'program_id' => null, 'request_id' => (string) Str::uuid(),
        'title' => 'Policy-only granted actor', 'content' => 'Independently authorized office policy.',
    ])->assertRedirect('/communications/policies');
    $policy = PublicPolicy::query()->where('created_by', $actor->id)->firstOrFail();
    $this->post("/communications/policies/{$policy->id}/publish", [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ])->assertRedirect('/communications/policies');
    $this->get("/policies/{$policy->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->where('policy.content', 'Independently authorized office policy.'));
    $this->get('/setup/privacy')->assertNotFound();
    $this->post('/setup/privacy', [
        'title' => 'Forbidden canonical privacy edit', 'content' => 'No legacy writer.', 'is_active' => true,
    ])->assertNotFound();
    $this->post('/setup/privacy/1/activate')->assertNotFound();
    $this->post('/communications', [
        'publisher_key' => 'office:guidance', 'request_id' => (string) Str::uuid(),
        'title' => 'Forbidden announcement', 'summary' => 'No announcement grant.', 'body' => 'Not authorized.',
        'priority' => 'normal', 'is_featured' => false, 'starts_at' => null, 'ends_at' => null,
    ])->assertForbidden();
});
test('public policy list filters search within authorized issuer and placement scope', function (): void {
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    $searchTerm = 'filter-scope-'.Str::uuid();
    $searchQuery = strtoupper($searchTerm);
    $title = "Synthetic {$searchTerm} policy";

    app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => $title,
        'content' => 'General synthetic policy content.',
    ]);
    $programPolicy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => (int) $program->id,
        'request_id' => (string) Str::uuid(), 'title' => $title,
        'content' => 'Program synthetic policy content.',
    ]);
    app(SavePublicPolicy::class)->handle($registrar, [
        'publisher_key' => 'office:registrar', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => $title,
        'content' => 'Foreign-office synthetic policy content.',
    ]);

    $this->actingAs($this->clerk)->get('/communications/policies?search='.urlencode($searchQuery).'&status=draft&placement=program')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('policies.total', 1)
            ->where('policies.data.0.id', (int) $programPolicy->getKey())
            ->where('filters.search', $searchQuery)
            ->where('filters.status', 'draft')
            ->where('filters.placement', 'program'));
});
test('policy demo seeder preserves a saved successor behind the published pointer', function (): void {
    $seeder = new PublicPoliciesDemoSeeder;
    $seeder->run();

    $policy = PublicPolicy::query()
        ->where('publisher_key', 'office:guidance')
        ->whereNull('program_id')
        ->where('title', PublicPoliciesDemoSeeder::TITLE_GUIDANCE_EXAM)
        ->firstOrFail();
    $published = app(PublicPolicyQuery::class)->eligiblePublicDetail((int) $policy->getKey())['policy'];
    $staffPolicy = collect(app(PublicPolicyQuery::class)->staffIndex($this->clerk)['policies']['data'])
        ->firstWhere('id', (int) $policy->getKey());
    $revision = (int) $policy->revision;
    $publishedRevisionId = (int) $policy->published_revision_id;
    $receiptCount = PublicPolicyCommandReceipt::query()->count();

    expect($policy->status)->toBe('published')
        ->and($revision)->toBeGreaterThan((int) $published['revision'])
        ->and($published['title'])->toBe(PublicPoliciesDemoSeeder::TITLE_GUIDANCE_EXAM)
        ->and($published['content'])->toBe(PublicPoliciesDemoSeeder::CONTENT_GUIDANCE_EXAM)
        ->and($staffPolicy['revision'])->toBe($revision)
        ->and($staffPolicy['published_revision'])->toBe((int) $published['revision']);

    $seeder->run();

    expect((int) $policy->fresh()->revision)->toBe($revision)
        ->and((int) $policy->fresh()->published_revision_id)->toBe($publishedRevisionId)
        ->and($policy->fresh()->title)->toBe(PublicPoliciesDemoSeeder::TITLE_GUIDANCE_EXAM)
        ->and(PublicPolicyCommandReceipt::query()->count())->toBe($receiptCount);
});

test('public policy previews derive plain text and provenance from only the published revision', function (): void {
    $publishedTitle = 'Synthetic published policy with its complete descriptive title';
    $publishedText = 'Published guidance '.str_repeat('readable policy text ', 20);
    $publishedContent = "<p>Published \n\t<strong>guidance</strong> ".str_repeat('readable policy text ', 20).'</p>';
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => null,
        'request_id' => (string) Str::uuid(), 'title' => $publishedTitle, 'content' => $publishedContent,
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ]);
    $publishedRevision = (int) $policy->revision;
    app(SavePublicPolicy::class)->handle($this->clerk, [
        'policy_id' => (int) $policy->id, 'expected_revision' => $publishedRevision,
        'request_id' => (string) Str::uuid(),
        'title' => 'PRIVATE successor title', 'content' => 'PRIVATE successor content must not appear publicly.',
    ]);

    $query = app(PublicPolicyQuery::class);
    $preview = collect($query->publicPolicies())->firstWhere('id', (int) $policy->id);
    $detail = $query->eligiblePublicDetail((int) $policy->id)['policy'];
    expect($preview['title'])->toBe($publishedTitle)
        ->and($preview['excerpt'])->toBe(Str::limit(trim($publishedText), 200, '…'))
        ->and($preview['excerpt'])->not->toContain('<', '>', 'PRIVATE')
        ->and($preview['kind'])->toBe('policy')
        ->and($preview['reference'])->toBe('Version '.$publishedRevision)
        ->and($preview['revision'])->toBe($publishedRevision)
        ->and($preview['publisher_key'])->toBe('office:guidance')
        ->and($preview['publisher_label'])->toBe('Guidance Office')
        ->and($preview['placement'])->toBe('General')
        ->and($preview['program_code'])->toBeNull()
        ->and($preview['program_name'])->toBeNull()
        ->and($preview['href'])->toBe('/policies/'.$policy->id)
        ->and($preview['published_at'])->toBe($policy->published_at->toIso8601String())
        ->and(array_key_exists('content', $preview))->toBeFalse()
        ->and($detail['title'])->toBe($publishedTitle)
        ->and($detail['excerpt'])->toBe($preview['excerpt'])
        ->and($detail['content'])->toBe(trim($publishedContent))
        ->and(json_encode([$preview, $detail]))->not->toContain('PRIVATE');
});

test('general bulletin and exact program queries never mix policy placements', function (): void {
    $program = Program::query()->where('code', 'BSN')->where('is_active', true)->firstOrFail();
    $otherProgram = Program::query()->where('code', 'BSIT')->where('is_active', true)->firstOrFail();
    $ids = [];
    foreach ([null, $program->id, $otherProgram->id] as $programId) {
        $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
            'publisher_key' => 'office:guidance', 'program_id' => $programId,
            'request_id' => (string) Str::uuid(), 'title' => 'Synthetic placement policy '.$programId,
            'content' => 'Published placement guidance.',
        ]);
        $policy = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
            'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
        ]);
        $ids[] = (int) $policy->id;
    }

    $query = app(PublicPolicyQuery::class);
    expect(array_column($query->eligiblePublicBulletinPreviews(), 'id'))->toBe([$ids[0]])
        ->and(array_column($query->publicPolicies($program), 'id'))->toBe([$ids[1]])
        ->and(array_column($query->eligiblePublicBulletinPreviews($otherProgram), 'id'))->toBe([$ids[2]])
        ->and($query->publicPolicies($program)[0]['placement'])->toBe('BSN program')
        ->and($query->publicPolicies($program)[0]['program_name'])->toBe($program->name)
        ->and($query->eligiblePublicBulletinPreviews(limit: 0))->toBe([]);
    $this->get('/programs/BSN/policies')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/ProgramPolicies')->has('policies', 1)->where('policies.0.id', $ids[1]));
});

test('inactive program policies are unavailable to preview and full public readers', function (): void {
    $program = Program::query()->where('code', 'BSN')->where('is_active', true)->firstOrFail();
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance', 'program_id' => (int) $program->id,
        'request_id' => (string) Str::uuid(), 'title' => 'Synthetic formerly active program policy',
        'content' => 'This published attachment requires an active program.',
    ]);
    app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
    ]);
    $program->update(['is_active' => false]);

    $query = app(PublicPolicyQuery::class);
    expect($query->publicPolicies($program))->toBe([])
        ->and($query->eligiblePublicBulletinPreviews($program))->toBe([])
        ->and($query->eligiblePublicBulletinPreviews())->toBe([]);
    $this->get('/policies/'.$policy->id)->assertNotFound();
    $this->get('/programs/BSN/policies')->assertNotFound();
});

test('homepage bounds the general bulletin and includes the designated privacy revision once', function (bool $hasPrivacy): void {
    if ($hasPrivacy) {
        PrivacyPolicy::query()->create([
            'title' => 'Synthetic legacy privacy policy',
            'content' => 'Legacy source remains historical and is not a public fallback.',
            'is_active' => true,
        ]);
    }

    $canonicalPrivacy = null;
    if ($hasPrivacy) {
        $canonicalPrivacy = app(SavePublicPolicy::class)->handle($this->clerk, [
            'publisher_key' => 'office:guidance', 'program_id' => null, 'kind' => 'privacy',
            'request_id' => (string) Str::uuid(), 'title' => 'Synthetic canonical privacy notice',
            'content' => "Canonical \nprivacy notice.",
        ]);
        $canonicalPrivacy = app(PublishPublicPolicy::class)->handle($this->clerk, $canonicalPrivacy, [
            'request_id' => (string) Str::uuid(), 'expected_revision' => (int) $canonicalPrivacy->revision,
        ]);
        $canonicalPrivacy = app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $canonicalPrivacy, [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => (int) $canonicalPrivacy->revision,
            'expected_intake_policy_id' => null,
        ]);
        $canonicalPrivacy->forceFill(['published_at' => now()->addDay()])->save();
        $canonicalPrivacy->load('publishedRevision');
    }

    foreach (range(1, 7) as $number) {
        $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
            'publisher_key' => 'office:guidance', 'program_id' => null,
            'request_id' => (string) Str::uuid(), 'title' => 'Synthetic bulletin policy '.$number,
            'content' => 'Published bulletin content '.$number,
        ]);
        $policy = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
            'request_id' => (string) Str::uuid(), 'expected_revision' => 1,
        ]);
        $policy->update(['published_at' => now()->subDays(8 - $number)]);
    }
    $query = app(PublicPolicyQuery::class);
    $announcements = app(AnnouncementQuery::class);
    $directory = $announcements->publicPolicies();
    $home = $announcements->publicHome()['publicPolicies'];

    expect($directory)->toHaveCount($hasPrivacy ? 8 : 7)
        ->and($home)->toHaveCount(6)
        ->and($home)->toBe(array_slice($directory, 0, 6))
        ->and(array_column($query->eligiblePublicBulletinPreviews(limit: 2), 'id'))
        ->toBe(array_slice(array_column($directory, 'id'), 0, 2))
        ->and(collect($directory)->where('kind', 'privacy')->count())->toBe($hasPrivacy ? 1 : 0);

    if ($canonicalPrivacy !== null) {
        $revision = $canonicalPrivacy->publishedRevision;
        expect($directory[0])->toMatchArray([
            'id' => (int) $canonicalPrivacy->getKey(),
            'kind' => 'privacy',
            'title' => $revision->title,
            'revision' => (int) $revision->revision,
            'revision_id' => (int) $revision->getKey(),
            'revision_digest' => $revision->contentDigest(),
            'reference' => 'Version '.$revision->revision,
            'href' => '/policies/'.$canonicalPrivacy->getKey(),
        ])->and(array_key_exists('content', $directory[0]))->toBeFalse();
    }
})->with([true, false]);
test('policy kind is captured in immutable revisions and cannot change after publication', function (): void {
    $this->actingAs($this->clerk)->post('/communications/policies', [
        'publisher_key' => 'office:guidance',
        'program_id' => null,
        'kind' => 'privacy',
        'is_designated_intake' => true,
        'request_id' => (string) Str::uuid(),
        'title' => 'Forged HTTP designation',
        'content' => 'Ordinary Save must not designate intake.',
    ])->assertSessionHasErrors('is_designated_intake');
    $save = app(SavePublicPolicy::class);
    $publish = app(PublishPublicPolicy::class);
    $policy = $save->handle($this->clerk, [
        'publisher_key' => 'office:guidance',
        'program_id' => null,
        'kind' => 'policy',
        'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic kind classification',
        'content' => 'Original content.',
    ]);
    $policy = $save->handle($this->clerk, [
        'policy_id' => (int) $policy->getKey(),
        'expected_revision' => (int) $policy->revision,
        'kind' => 'privacy',
        'request_id' => (string) Str::uuid(),
        'title' => $policy->title,
        'content' => $policy->content,
    ]);
    expect($policy->kind)->toBe('privacy')
        ->and($policy->revisions()->reorder('revision', 'desc')->value('kind'))->toBe('privacy');

    $policy = $publish->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
    ]);
    $publishedRevisionId = (int) $policy->published_revision_id;
    expect($policy->publishedRevision->kind)->toBe('privacy');

    $saved = $save->handle($this->clerk, [
        'policy_id' => (int) $policy->getKey(),
        'expected_revision' => (int) $policy->revision,
        'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic kind classification',
        'content' => 'Edited without sending kind; the locked classification must remain privacy.',
    ]);
    expect($saved->kind)->toBe('privacy')
        ->and($saved->revisions()->reorder('revision', 'desc')->value('kind'))->toBe('privacy')
        ->and((int) $saved->published_revision_id)->toBe($publishedRevisionId);

    expect(fn () => $save->handle($this->clerk, [
        'policy_id' => (int) $saved->getKey(),
        'expected_revision' => (int) $saved->revision,
        'kind' => 'policy',
        'request_id' => (string) Str::uuid(),
        'title' => $saved->title,
        'content' => $saved->content,
    ]))->toThrow(ValidationException::class);
    expect(fn () => $save->handle($this->clerk, [
        'publisher_key' => 'office:guidance',
        'program_id' => null,
        'kind' => 'privacy',
        'is_designated_intake' => true,
        'request_id' => (string) Str::uuid(),
        'title' => 'Forged designation field',
        'content' => 'The save operation cannot select intake.',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $publish->handle($this->clerk, $saved, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $saved->revision,
        'is_designated_intake' => true,
    ]))->toThrow(ValidationException::class);
    expect(PublicPolicy::currentIntakeSelection((int) $saved->institution_id))->toBeNull();
    $ordinaryPolicy = $save->handle($this->clerk, [
        'publisher_key' => 'office:guidance',
        'program_id' => null,
        'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic immutable ordinary policy',
        'content' => 'A published ordinary policy cannot be repurposed as privacy.',
    ]);
    $ordinaryPolicy = $publish->handle($this->clerk, $ordinaryPolicy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $ordinaryPolicy->revision,
    ]);
    expect($ordinaryPolicy->kind)->toBe('policy')
        ->and(fn () => $save->handle($this->clerk, [
            'policy_id' => (int) $ordinaryPolicy->getKey(),
            'expected_revision' => (int) $ordinaryPolicy->revision,
            'kind' => 'privacy',
            'request_id' => (string) Str::uuid(),
            'title' => $ordinaryPolicy->title,
            'content' => $ordinaryPolicy->content,
        ]))->toThrow(ValidationException::class);
});

test('intake designation is explicit, conflict-safe, replayable without reapplying and retained on withdrawal', function (): void {
    $createPublishedPrivacy = function (string $title): PublicPolicy {
        $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
            'publisher_key' => 'office:guidance',
            'program_id' => null,
            'kind' => 'privacy',
            'request_id' => (string) Str::uuid(),
            'title' => $title,
            'content' => 'Synthetic published privacy content for selection tests.',
        ]);

        return app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => (int) $policy->revision,
        ]);
    };
    $first = $createPublishedPrivacy('Synthetic first privacy policy');
    $first = app(SavePublicPolicy::class)->handle($this->clerk, [
        'policy_id' => (int) $first->getKey(),
        'expected_revision' => (int) $first->revision,
        'request_id' => (string) Str::uuid(),
        'title' => 'Private unpublished draft title',
        'content' => 'Private unpublished draft text.',
    ]);
    $second = $createPublishedPrivacy('Synthetic successor privacy policy');
    $firstPointer = (int) $first->published_revision_id;
    $secondPointer = (int) $second->published_revision_id;
    $firstRevisionCount = $first->revisions()->count();
    $secondRevisionCount = $second->revisions()->count();
    $firstSelection = [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $first->revision,
        'expected_intake_policy_id' => null,
    ];
    $support = User::query()->where('email', 'guidance.support@nexiam.test')->firstOrFail();
    expect(fn () => app(DesignateIntakePublicPolicy::class)->handle($support, $first, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $first->revision,
        'expected_intake_policy_id' => null,
    ]))->toThrow(AuthorizationException::class)
        ->and(PublicPolicy::currentIntakeSelection((int) $first->institution_id))->toBeNull();

    $this->actingAs($this->clerk)->post(route('communications.policies.designate-intake', $first), $firstSelection)
        ->assertRedirect(route('communications.policies.index'));
    $selectedFirst = PublicPolicy::currentIntakeSelection((int) $first->institution_id);
    expect((int) $selectedFirst?->getKey())->toBe((int) $first->getKey())
        ->and(PublicPolicy::designatedIntake((int) $first->institution_id)?->publishedRevision?->getKey())->toBe($firstPointer);
    $index = app(PublicPolicyQuery::class)->staffIndex($this->clerk);
    $indexRows = collect($index['policies']['data']);
    expect($index['current_intake_selection']['title'])->toBe('Synthetic first privacy policy')
        ->and($index['expected_intake_policy_id'])->toBe((int) $first->getKey())
        ->and($indexRows->firstWhere('id', (int) $first->getKey())['title'])->toBe('Private unpublished draft title')
        ->and($indexRows->firstWhere('id', (int) $first->getKey())['published_title'])->toBe('Synthetic first privacy policy')
        ->and($indexRows->firstWhere('id', (int) $first->getKey())['can_designate_intake'])->toBeFalse()
        ->and($indexRows->firstWhere('id', (int) $second->getKey())['can_designate_intake'])->toBeTrue();

    $staleRequest = [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $second->revision,
        'expected_intake_policy_id' => null,
    ];
    expect(fn () => app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $second, $staleRequest))
        ->toThrow(ValidationException::class);
    expect((int) PublicPolicy::currentIntakeSelection((int) $first->institution_id)?->getKey())->toBe((int) $first->getKey());

    $secondSelection = [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $second->revision,
        'expected_intake_policy_id' => (int) $first->getKey(),
    ];
    $this->actingAs($this->clerk)->post(route('communications.policies.designate-intake', $second), $secondSelection)
        ->assertRedirect(route('communications.policies.index'));
    expect((int) PublicPolicy::currentIntakeSelection((int) $first->institution_id)?->getKey())->toBe((int) $second->getKey())
        ->and((bool) $first->fresh()->is_designated_intake)->toBeFalse()
        ->and((bool) $second->fresh()->is_designated_intake)->toBeTrue()
        ->and((int) $first->fresh()->published_revision_id)->toBe($firstPointer)
        ->and((int) $second->fresh()->published_revision_id)->toBe($secondPointer)
        ->and($first->revisions()->count())->toBe($firstRevisionCount)
        ->and($second->revisions()->count())->toBe($secondRevisionCount);
    $designationAudits = AuditEvent::query()->where('event_type', 'communications.public_policy_intake_designated')->get();
    expect($designationAudits)->toHaveCount(2);
    $replacementAudit = $designationAudits->firstWhere('payload.selected_intake_policy_id', (int) $second->getKey());
    expect($replacementAudit->payload['previous_intake_policy_id'])->toBe((int) $first->getKey())
        ->and($replacementAudit->payload['selected_published_revision_id'])->toBe($secondPointer);

    app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $first, $firstSelection);
    expect((int) PublicPolicy::currentIntakeSelection((int) $first->institution_id)?->getKey())->toBe((int) $second->getKey())
        ->and(PublicPolicyCommandReceipt::query()->where('operation', 'designate_intake')->count())->toBe(2);

    $withdrawn = app(WithdrawPublicPolicy::class)->handle($this->clerk, $second->fresh(), [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $second->revision,
        'reason' => 'Synthetic test withdrawal keeps selected identity but closes intake.',
    ]);
    expect((bool) $withdrawn->is_designated_intake)->toBeTrue()
        ->and((int) PublicPolicy::currentIntakeSelection((int) $first->institution_id)?->getKey())->toBe((int) $second->getKey())
        ->and(PublicPolicy::designatedIntake((int) $first->institution_id))->toBeNull();
    $withdrawnSelection = app(PublicPolicyQuery::class)->staffIndex($this->clerk)['current_intake_selection'];
    expect($withdrawnSelection)->toMatchArray([
        'id' => (int) $second->getKey(),
        'status' => 'withdrawn',
        'root_kind' => null,
        'title' => null,
        'revision' => null,
        'snapshot_available' => false,
    ]);
    app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $second, $secondSelection);
    expect((int) PublicPolicy::currentIntakeSelection((int) $first->institution_id)?->getKey())->toBe((int) $second->getKey());
});

test('designation, selector replacement and receipt roll back when its audit fails', function (): void {
    $createPublishedPrivacy = function (string $title): PublicPolicy {
        $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
            'publisher_key' => 'office:guidance',
            'program_id' => null,
            'kind' => 'privacy',
            'request_id' => (string) Str::uuid(),
            'title' => $title,
            'content' => 'Synthetic audit rollback privacy text.',
        ]);

        return app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
            'request_id' => (string) Str::uuid(),
            'expected_revision' => (int) $policy->revision,
        ]);
    };
    $first = $createPublishedPrivacy('Synthetic audit rollback current policy');
    $second = $createPublishedPrivacy('Synthetic audit rollback candidate');
    app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $first, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $first->revision,
        'expected_intake_policy_id' => null,
    ]);
    $receiptCount = PublicPolicyCommandReceipt::query()->where('operation', 'designate_intake')->count();

    $this->mock(RecordAuditEvent::class, fn ($mock) => $mock
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Audit unavailable')));

    expect(fn () => app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $second, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $second->revision,
        'expected_intake_policy_id' => (int) $first->getKey(),
    ]))->toThrow(RuntimeException::class, 'Audit unavailable');

    expect((int) PublicPolicy::currentIntakeSelection((int) $first->institution_id)?->getKey())->toBe((int) $first->getKey())
        ->and((bool) $first->fresh()->is_designated_intake)->toBeTrue()
        ->and((bool) $second->fresh()->is_designated_intake)->toBeFalse()
        ->and(PublicPolicyCommandReceipt::query()->where('operation', 'designate_intake')->count())->toBe($receiptCount);
});

test('unknown privacy snapshot can be recovered by explicit unchanged-text publication without losing selection', function (): void {
    $policy = app(SavePublicPolicy::class)->handle($this->clerk, [
        'publisher_key' => 'office:guidance',
        'program_id' => null,
        'kind' => 'privacy',
        'request_id' => (string) Str::uuid(),
        'title' => 'Synthetic legacy privacy revision',
        'content' => 'Existing text requires an explicit privacy-kind snapshot.',
    ]);
    $policy = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
    ]);
    $policy = app(DesignateIntakePublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
        'expected_intake_policy_id' => null,
    ]);
    $oldRevisionId = (int) $policy->published_revision_id;
    DB::table('public_policy_revisions')->where('id', $oldRevisionId)->update(['kind' => null]);
    expect(PublicPolicy::currentIntakeSelection((int) $policy->institution_id)?->getKey())->toBe($policy->getKey())
        ->and(PublicPolicy::designatedIntake((int) $policy->institution_id))->toBeNull();

    $this->actingAs($this->clerk)->get("/communications/policies/{$policy->getKey()}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Communications/Policies/Edit')
            ->where('policy.kind', 'privacy')
            ->where('policy.published_kind', null)
            ->where('policy.kind_locked', true));

    $recovered = app(PublishPublicPolicy::class)->handle($this->clerk, $policy, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => (int) $policy->revision,
    ]);
    expect((int) $recovered->published_revision_id)->not->toBe($oldRevisionId)
        ->and($recovered->publishedRevision->kind)->toBe('privacy')
        ->and($recovered->publishedRevision->title)->toBe('Synthetic legacy privacy revision')
        ->and($recovered->publishedRevision->content)->toBe('Existing text requires an explicit privacy-kind snapshot.')
        ->and($recovered->is_designated_intake)->toBeTrue()
        ->and(PublicPolicy::designatedIntake((int) $policy->institution_id)?->getKey())->toBe($policy->getKey())
        ->and(PublicPolicyRevision::query()->whereKey($oldRevisionId)->value('kind'))->toBeNull();
});

test('foreign public policy identifiers never choose another institution lock or mutate its document', function (): void {
    $foreign = Institution::query()->create([
        'code' => 'SYN-FOREIGN-PUBLIC-POLICY',
        'name' => 'Foreign synthetic public policy institution',
    ]);
    $policy = PublicPolicy::query()->create([
        'institution_id' => $foreign->getKey(),
        'publisher_key' => 'office:guidance',
        'publisher_label' => 'Guidance',
        'program_id' => null,
        'kind' => 'privacy',
        'title' => 'Foreign private draft',
        'content' => 'A foreign institution document.',
        'revision' => 1,
        'status' => 'draft',
        'created_by' => $this->clerk->getKey(),
    ]);
    $requestId = (string) Str::uuid();

    expect(fn () => app(SavePublicPolicy::class)->handle($this->clerk, [
        'policy_id' => (int) $policy->getKey(),
        'expected_revision' => 1,
        'request_id' => $requestId,
        'title' => 'Forged foreign policy edit',
        'content' => 'Must remain unchanged.',
    ]))->toThrow(ModelNotFoundException::class);

    expect($policy->fresh()->title)->toBe('Foreign private draft')
        ->and($policy->fresh()->content)->toBe('A foreign institution document.')
        ->and(PublicPolicyCommandReceipt::query()->where('actor_id', $this->clerk->getKey())->where('request_id', $requestId)->exists())->toBeFalse();
});
test('both institutional admin and registrar can edit and publish the designated application privacy policy singleton', function (): void {
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $policy = PublicPolicy::query()->create([
        'institution_id' => $admin->actorScope()->institutionId,
        'publisher_key' => 'office:registrar',
        'publisher_label' => 'Registrar Office',
        'program_id' => null,
        'kind' => 'privacy',
        'title' => 'Original Registrar Privacy Notice',
        'content' => 'Initial privacy content.',
        'revision' => 1,
        'status' => 'draft',
        'is_designated_intake' => true,
        'created_by' => $registrar->getKey(),
    ]);

    $saved = app(SavePublicPolicy::class)->handle($admin, [
        'policy_id' => (int) $policy->getKey(),
        'expected_revision' => 1,
        'request_id' => (string) Str::uuid(),
        'title' => 'Admin Updated Privacy Notice',
        'content' => 'Admin updated privacy legal text.',
    ]);

    expect($saved->title)->toBe('Admin Updated Privacy Notice')
        ->and($saved->content)->toBe('Admin updated privacy legal text.')
        ->and($saved->revision)->toBe(2);

    $published = app(PublishPublicPolicy::class)->handle($registrar, $saved, [
        'request_id' => (string) Str::uuid(),
        'expected_revision' => 2,
    ]);

    expect($published->status)->toBe('published')
        ->and($published->publishedRevision?->title)->toBe('Admin Updated Privacy Notice')
        ->and($published->revision)->toBe(3);
});
