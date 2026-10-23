<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => seedE2Foundation());

test('reconciles actor profile vs Institution::current() and rejects foreign institution', function (): void {
    $scope = app(CommunicationTemplateScope::class);
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $current = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $resolved = $scope->resolveInstitution($counselor);
    expect($resolved->id)->toBe($current->id);

    // Foreign institution user
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOR-INST-999',
        'name' => 'Foreign Institution',
    ]);

    $foreignUser = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $foreignUser->id,
        'institution_id' => $foreignInstitution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    // Foreign user has guidance capability on their own institution
    $perm = Permission::findOrCreate(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'web');
    $foreignUser->givePermissionTo($perm);

    // Should deny access because foreignUser profile institution does not match the active deployment institution
    expect($scope->canAccessMetadata($foreignUser, 'examination'))->toBeFalse()
        ->and($scope->canAccessFullSource($foreignUser, 'examination'))->toBeFalse();
});

test('enforces office capability checks across admissions, examination, and results', function (): void {
    $scope = app(CommunicationTemplateScope::class);

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    // Admissions: Registrar only
    expect($scope->canAccessMetadata($registrar, 'admissions'))->toBeTrue()
        ->and($scope->canAccessFullSource($registrar, 'admissions'))->toBeTrue()
        ->and($scope->canAccessMetadata($counselor, 'admissions'))->toBeFalse()
        ->and($scope->canAccessFullSource($counselor, 'admissions'))->toBeFalse();

    // Examination: Guidance only
    expect($scope->canAccessMetadata($counselor, 'examination'))->toBeTrue()
        ->and($scope->canAccessFullSource($counselor, 'examination'))->toBeTrue()
        ->and($scope->canAccessMetadata($registrar, 'examination'))->toBeFalse()
        ->and($scope->canAccessFullSource($registrar, 'examination'))->toBeFalse();
});

test('results metadata allows guidance.assessment.catalog OR guidance.results.release, but full-source requires release', function (): void {
    $scope = app(CommunicationTemplateScope::class);
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    // Create a staff user with ONLY guidance.assessment.catalog
    $catalogOnlyUser = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $catalogOnlyUser->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $catalogPerm = Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $catalogOnlyUser->givePermissionTo($catalogPerm);

    // Metadata should be allowed
    expect($scope->canAccessMetadata($catalogOnlyUser, 'results'))->toBeTrue();

    // Full source MUST be denied
    expect($scope->canAccessFullSource($catalogOnlyUser, 'results'))->toBeFalse();

    // Now grant guidance.results.release
    $releasePerm = Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, 'web');
    $catalogOnlyUser->givePermissionTo($releasePerm);

    // Now full source is allowed
    expect($scope->canAccessFullSource($catalogOnlyUser, 'results'))->toBeTrue();
});

test('projectMetadata strictly exposes allowed keys and omits copy, blueprints, and asset IDs', function (): void {
    $scope = app(CommunicationTemplateScope::class);
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $template = CommunicationTemplate::query()->create([
        'institution_id' => $institution->id,
        'domain' => 'results',
        'event_key' => 'result.released.online.v1',
        'slug' => 'released-online',
        'name' => 'Official Scorecard',
        'description' => 'Released online scorecard notice',
        'active_version' => 1,
        'family_revision' => 2,
    ]);

    CommunicationTemplateVersion::query()->create([
        'communication_template_id' => $template->id,
        'version' => 1,
        'email_subject' => 'SECRET EMAIL SUBJECT',
        'email_body' => 'SECRET EMAIL BODY CONTENT',
        'in_app_title' => 'SECRET IN APP TITLE',
        'in_app_body' => 'SECRET IN APP BODY',
        'document_mode' => 'docx',
    ]);

    CommunicationTemplateDraft::query()->create([
        'communication_template_id' => $template->id,
        'base_version' => 1,
        'email_subject' => 'DRAFT SUBJECT',
        'email_body' => 'DRAFT BODY',
        'in_app_title' => 'DRAFT TITLE',
        'in_app_body' => 'DRAFT BODY',
        'document_mode' => 'builder',
        'builder_json' => ['components' => []],
    ]);

    $projected = $scope->projectMetadata($template);

    // Expected exact keys
    $expectedKeys = [
        'id',
        'domain',
        'slug',
        'name',
        'description',
        'active_version',
        'family_revision',
        'has_draft',
        'updated_at',
        'channels',
        'supports_pdf',
        'pdf_name',
        'authoring_mode',
        'type',
        'purpose',
    ];

    expect(array_keys($projected))->toEqualCanonicalizing($expectedKeys)
        ->and($projected['id'])->toBe($template->id)
        ->and($projected['domain'])->toBe('results')
        ->and($projected['slug'])->toBe('released-online')
        ->and($projected['name'])->toBe('Standard Score Release Notice')
        ->and($projected['description'])->toBe('Sent to candidates when standardized entrance exam results are published online.')
        ->and($projected['active_version'])->toBe(1)
        ->and($projected['family_revision'])->toBe(2)
        ->and($projected['has_draft'])->toBeTrue()
        ->and($projected['supports_pdf'])->toBeTrue()
        ->and($projected['pdf_name'])->toBe('Official-Scorecard.pdf')
        ->and($projected['authoring_mode'])->toBe('email_pdf')
        ->and($projected['type'])->toBe('email_pdf')
        ->and($projected['purpose'])->toBe('Sent to candidates when standardized entrance exam results are published online.')
        ->and($projected)->not->toHaveKey('sample_body')
        ->and($projected)->not->toHaveKey('email_subject')
        ->and($projected)->not->toHaveKey('email_body')
        ->and($projected)->not->toHaveKey('in_app_title')
        ->and($projected)->not->toHaveKey('in_app_body')
        ->and($projected)->not->toHaveKey('blueprint')
        ->and($projected)->not->toHaveKey('builder_json')
        ->and($projected)->not->toHaveKey('document_asset_id');
});

test('PDF eligibility is keyed strictly to accepted, session-published, and released-online', function (): void {
    $scope = app(CommunicationTemplateScope::class);

    $accepted = $scope->projectMetadata(['domain' => 'admissions', 'slug' => 'accepted']);
    expect($accepted['supports_pdf'])->toBeTrue()
        ->and($accepted['pdf_name'])->toBe('Official-Offer-Letter.pdf');

    $sessionPublished = $scope->projectMetadata(['domain' => 'examination', 'slug' => 'session-published']);
    expect($sessionPublished['supports_pdf'])->toBeTrue()
        ->and($sessionPublished['pdf_name'])->toBe('Examination-Permit.pdf');

    $releasedOnline = $scope->projectMetadata(['domain' => 'results', 'slug' => 'released-online']);
    expect($releasedOnline['supports_pdf'])->toBeTrue()
        ->and($releasedOnline['pdf_name'])->toBe('Official-Scorecard.pdf');

    $reminder = $scope->projectMetadata(['domain' => 'examination', 'slug' => 'reminder']);
    expect($reminder['supports_pdf'])->toBeFalse()
        ->and($reminder['pdf_name'])->toBeNull();

    $f2f = $scope->projectMetadata(['domain' => 'results', 'slug' => 'released-f2f']);
    expect($f2f['supports_pdf'])->toBeFalse()
        ->and($f2f['pdf_name'])->toBeNull();
});

test('Results catalog and family permit metadata readers but deny all protected authoring operations', function (): void {
    $institution = Institution::current();
    app(ProvisionCanonicalCommunicationTemplates::class)->provision($institution, null, 'results');
    $reader = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $reader->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $reader->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web'));
    $family = '/setup/notifications/results/templates/released-online';
    $before = [
        CommunicationTemplateDraft::query()->count(),
        CommunicationTemplateVersion::query()->count(),
    ];

    $this->actingAs($reader)->get(route('setup.notifications.results'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications/Results')
            ->has('notices', 6)
            ->where('capabilities.can_manage', false)
            ->missing('notices.0.sample_subject')
            ->missing('notices.0.sample_body')
            ->missing('notices.0.tokens')
            ->missing('notices.0.editor_url')
            ->missing('notices.0.endpoints'));

    $this->get($family)->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications/Templates/Studio')
            ->missing('draft.state')
            ->missing('sampleContext')
            ->missing('placeholderGroups')
            ->missing('family.email_blueprint')
            ->missing('family.subject'));

    $builtinFamily = '/setup/notifications/results/templates/consultation-reserved';
    $this->get($builtinFamily)->assertOk()
        ->assertInertia(fn ($page) => $page->where('family.authoring_mode', 'builtin')
            ->where('family.type', 'builtin')->where('family.purpose', 'Consultation booking confirmation')
            ->missing('builtin_message')->missing('endpoints')->missing('versions.0.inspect_url'));
    $this->get($builtinFamily.'?version=1')->assertForbidden();
    $this->get($builtinFamily.'?inspect=draft')->assertForbidden();

    foreach (['/edit', '/editor-state'] as $endpoint) {
        $this->get($family.$endpoint)->assertForbidden();
    }
    $this->get($family.'/edit?version=1')->assertForbidden();
    $this->getJson($family.'/editor-state?version=1')->assertForbidden();
    foreach (['/drafts', '/preview', '/publish', '/test-mail'] as $endpoint) {
        $this->postJson($family.$endpoint, [])->assertForbidden();
    }
    foreach (['/import', '/import-review'] as $endpoint) {
        $this->postJson($family.$endpoint, [])->assertNotFound();
    }
    $this->put(route('setup.notifications.results.update'), [])->assertForbidden();
    expect([
        CommunicationTemplateDraft::query()->count(),
        CommunicationTemplateVersion::query()->count(),
    ])->toBe($before);
});

test('all notice GETs leave unconfigured families and drafts unprovisioned', function (): void {
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    CommunicationTemplate::query()->where('institution_id', Institution::current()->id)->delete();

    foreach ([
        [$registrar, 'admissions', 'accepted'],
        [$counselor, 'examination', 'reminder'],
        [$counselor, 'results', 'released-online'],
    ] as [$actor, $domain, $slug]) {
        $this->actingAs($actor)->get('/setup/notifications/'.$domain)->assertOk()
            ->assertInertia(fn ($page) => $page->has('notices', 0));
        $family = '/setup/notifications/'.$domain.'/templates/'.$slug;
        $this->get($family)->assertNotFound();
        $this->get($family.'/edit')->assertNotFound();
        $this->getJson($family.'/editor-state')->assertNotFound();
    }
    expect(CommunicationTemplate::query()->count())->toBe(0)
        ->and(CommunicationTemplateDraft::query()->count())->toBe(0);
});

test('metadata projection discards nested caller content and uses canonical registry values', function (): void {
    $metadata = app(CommunicationTemplateScope::class)->projectMetadata([
        'domain' => 'results',
        'slug' => 'released-online',
        'name' => 'SECRET SUBJECT',
        'description' => 'SECRET BODY',
        'tokens' => [['token' => 'secret', 'sample' => 'PRIVATE CONTEXT', 'asset_id' => 'private-id']],
        'channels' => [['blueprint' => ['secret']]],
        'email_blueprint' => ['secret'],
        'import_provenance' => ['secret'],
        'endpoints' => ['source' => '/private/source'],
    ]);

    expect($metadata['name'])->toBe('Standard Score Release Notice')
        ->and($metadata['channels'])->toBe(['in_app', 'mail'])
        ->and($metadata)->not->toHaveKeys(['tokens', 'email_blueprint', 'import_provenance', 'endpoints'])
        ->and(json_encode($metadata, JSON_THROW_ON_ERROR))->not->toContain('SECRET', 'PRIVATE', 'private-id');
});

test('scope rejects revoked loaded grants and changed or inactive offices freshly', function (): void {
    $scope = app(CommunicationTemplateScope::class);
    $actor = User::factory()->create();
    $profile = StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => Institution::current()->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $permission = Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, 'web');
    $actor->givePermissionTo($permission);
    $actor->load('permissions', 'roles');
    expect($scope->canAccessFullSource($actor, 'results'))->toBeTrue();

    $otherInstance = User::query()->findOrFail($actor->id);
    $otherInstance->revokePermissionTo($permission);
    expect($scope->canAccessMetadata($actor, 'results'))->toBeFalse()
        ->and($scope->canAccessFullSource($actor, 'results'))->toBeFalse();

    $otherInstance->givePermissionTo($permission);
    $profile->update(['office' => 'registrar']);
    expect($scope->canAccessFullSource($actor, 'results'))->toBeFalse();
    $profile->update(['office' => 'guidance', 'status' => StaffProfile::STATUS_INACTIVE]);
    expect($scope->canAccessMetadata($actor, 'results'))->toBeFalse();
});

test('explicit foreign institution cannot bypass deployment scope even with matching foreign staff grants', function (): void {
    $scope = app(CommunicationTemplateScope::class);
    $foreignInstitution = Institution::query()->create(['code' => 'FOREIGN-NOTICE', 'name' => 'Foreign']);
    $actor = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $foreignInstitution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $actor->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, 'web'));
    expect($scope->canAccessMetadata($actor, 'results', $foreignInstitution))->toBeFalse()
        ->and($scope->canAccessFullSource($actor, 'results', $foreignInstitution))->toBeFalse();
    $this->actingAs($actor)->get('/setup/notifications/results')->assertForbidden();
    $this->get('/setup/notifications/results/templates/released-online')->assertForbidden();
});

test('Results release-only staff can inspect the protected editor without an assessment grant', function (): void {
    $institution = Institution::current();
    app(ProvisionCanonicalCommunicationTemplates::class)->provision($institution, null, 'results');
    $actor = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $actor->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, 'web'));
    $family = '/setup/notifications/results/templates/released-online';

    $this->actingAs($actor)->get($family)->assertOk();
    $this->get($family.'/edit')->assertOk();
    $this->getJson($family.'/editor-state')->assertOk()
        ->assertJsonPath('family.domain', 'results')
        ->assertJsonPath('family.slug', 'released-online');
});

test('metadata PDF eligibility rejects mismatched event keys and cross-domain borrowed slugs', function (): void {
    $scope = app(CommunicationTemplateScope::class);
    foreach ([
        ['domain' => 'admissions', 'slug' => 'accepted', 'event_key' => 'admission.dismissed.v1'],
        ['domain' => 'examination', 'slug' => 'session-published', 'event_key' => 'exam.reminder.v1'],
        ['domain' => 'results', 'slug' => 'released-online', 'event_key' => 'result.released.f2f.v1'],
        ['domain' => 'results', 'slug' => 'accepted', 'event_key' => 'admission.accepted.v1'],
    ] as $family) {
        $metadata = $scope->projectMetadata($family);
        expect($metadata['supports_pdf'])->toBeFalse()
            ->and($metadata['pdf_name'])->toBeNull();
    }
});
