<?php

use App\Ai\Context\PageCapabilityPackRegistry;
use App\Authorization\CapabilityCatalog;
use App\Models\Ai\KnowledgeIndexEntry;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Ai\XiamUsageReservation;
use App\Models\Foundation\Institution;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->withoutVite();
});

test('setup hub allows institutional admin with setup capabilities', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('capabilities.setup', true)
            ->where('capabilities.setup_institution', true)
            ->where('capabilities.setup_programs', true)
            ->where('user_desk', 'admin')
            ->where('capabilities.assessment', false)
            ->where('readiness.groups.0.key', 'exam_preparation')
            ->where('readiness.groups.1.key', 'institutional_setup')
            ->has('readiness.groups.1.categories', 11)
            ->where('readiness.groups.1.categories.0.key', 'cycles')
            ->where('readiness.groups.1.categories.0.capability', 'setup.cycles.manage')
            ->where('readiness.groups.1.categories.1.key', 'programs')
            ->where('readiness.groups.1.categories.1.capability', 'setup.programs.manage')
            ->where('readiness.groups.1.categories.2.key', 'privacy')
            ->where('readiness.groups.1.categories.2.label', 'Application Privacy Policy')
            ->where('readiness.groups.1.categories.2.capability', 'communications.policies.manage')
            ->where('readiness.groups.1.categories.3.key', 'requirements')
            ->where('readiness.groups.1.categories.3.label', 'Application Requirements')
            ->where('readiness.groups.1.categories.3.capability', 'registrar.requirements.manage')
            ->where('readiness.groups.1.categories.3.href', '/setup/requirements')
            ->where('readiness.groups.1.categories.4.key', 'intake_fields')
            ->where('readiness.groups.1.categories.4.label', 'Application Form Fields')
            ->where('readiness.groups.1.categories.4.capability', 'registrar.requirements.manage')
            ->where('readiness.groups.1.categories.5.key', 'aptitude')
            ->where('readiness.groups.1.categories.5.capability', 'assessment')
            ->where('readiness.groups.1.categories.6.key', 'quotas')
            ->where('readiness.groups.1.categories.7.key', 'xiam_knowledge_governance')
            ->where('readiness.groups.1.categories.7.label', 'Admissions Policies & AI Assistant')
            ->where('readiness.groups.1.categories.7.href', '/knowledge')
            ->where('readiness.groups.1.categories.7.capability', 'knowledge')
            ->where('readiness.groups.1.categories.7.checks.1.label', 'Approved policy/FAQ knowledge is active and indexed')
            ->where('readiness.groups.1.categories.7.checks.1.action_href', '/knowledge')
            ->where('readiness.groups.1.categories.8.key', 'public_communications')
            ->where('readiness.groups.1.categories.8.label', 'Public communications & standing policies')
            ->where('readiness.groups.1.categories.9.capability', 'notifications')
            ->where('readiness.groups.1.categories.8.href', '/communications')
        );
});

test('registrar cannot open aptitude configure (TA-only)', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('setup.aptitude'))
        ->assertForbidden();
});

test('unified public policy management replaces the legacy setup privacy editor', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)->get('/setup/privacy')->assertNotFound();
    $this->actingAs($registrar)->post('/setup/privacy', [
        'title' => 'Forbidden legacy privacy edit',
        'content' => 'No legacy writer remains.',
        'is_active' => true,
    ])->assertNotFound();
    $this->actingAs($registrar)->get(route('communications.policies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Communications/Policies/Index'));
});

test('setup hub allows guidance counselor for guidance-owned setup only', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('capabilities.setup', false)
            ->where('capabilities.setup_institution', false)
            ->where('capabilities.setup_programs', false)
            ->where('user_desk', 'guidance')
            ->where('capabilities.assessment', true)
            ->where('capabilities.scoring_omr', true)
            ->where('capabilities.booklet', true)
            ->where('capabilities.quota', true)
            ->where('capabilities.consultation_policy', true)
            ->where('capabilities.report_templates', true)
            ->where('capabilities.notifications', true)
            ->where('capabilities.ai_governance', true)
        );
});
test('answer-sheet and booklet library access stays independently capability-gated', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $guidance->syncRoles([]);
    $guidance->syncPermissions([CapabilityCatalog::GUIDANCE_SCORING_OMR]);

    $this->actingAs($guidance)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('capabilities.assessment', false)
            ->where('capabilities.scoring_omr', true)
            ->where('capabilities.booklet', false)
            ->where('user_desk', 'testing')
        );
    $this->actingAs($guidance)->get('/setup/exam-materials/answer-sheets')->assertOk();
    $this->actingAs($guidance)->get('/setup/exam-materials/booklets')->assertForbidden();

    $guidance->syncPermissions([CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE]);
    $this->actingAs($guidance)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('capabilities.scoring_omr', false)
            ->where('capabilities.booklet', true)
            ->where('user_desk', 'testing')
        );
    $this->actingAs($guidance)->get('/setup/exam-materials/answer-sheets')->assertForbidden();
    $this->actingAs($guidance)->get('/setup/exam-materials/booklets')->assertOk();
});

test('guidance counselor retains direct access to the aptitude authoring route', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.aptitude'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Setup/Aptitude'));
});

test('setup hub denies users without setup assessment or quota capability', function (): void {
    seedE2Foundation();
    $user = User::factory()->create(['email' => 'nosetup@nexiam.test']);

    $this->actingAs($user)
        ->get(route('setup.index'))
        ->assertForbidden();
});

test('setup readiness exposes primary and secondary navigation destinations with least-privilege gates', function (): void {
    seedE2Foundation();
    $stages = app(ComputeSetupReadinessQuery::class)->handle()['stages'];
    $destinations = collect($stages)->pluck('cards')->flatten(1);
    $hrefs = $destinations->pluck('href');
    $cards = $destinations->keyBy('key');

    expect($hrefs->duplicates())->toBeEmpty()
        ->and($hrefs)->toContain('/setup/exam-materials/test-kits', '/setup/exam-materials/answer-sheets', '/setup/exam-materials/booklets')
        ->and($cards['exam_materials']['navigation_context'])->toBe('Test setup')
        ->and($cards['exam_materials']['capability'])->toBe(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
        ->and($cards['answer_sheets']['navigation_context'])->toBe('Resource Library')
        ->and($cards['answer_sheets']['capability'])->toBe(CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->and($cards['booklets']['navigation_context'])->toBe('Resource Library')
        ->and($cards['booklets']['capability'])->toBe(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
});

test('Xiam governance readiness fails closed on missing coverage and unresolved states', function (): void {
    seedE2Foundation();

    $readiness = static fn (): array => app(ComputeSetupReadinessQuery::class)->handle();
    $check = static function (array $readiness, string $label): array {
        $category = collect($readiness['groups'])
            ->pluck('categories')
            ->flatten(1)
            ->firstWhere('key', 'xiam_knowledge_governance');

        return collect($category['checks'])->firstWhere('label', $label);
    };

    $institutionId = Institution::current()->id;
    PrivacyPolicy::query()->create([
        'title' => 'Historical synthetic active privacy policy',
        'content' => 'Historical provenance does not satisfy intake readiness.',
        'is_active' => true,
    ]);
    expect(PrivacyPolicy::query()->where('is_active', true)->exists())->toBeTrue();

    $initialReadiness = $readiness();
    expect($initialReadiness['overall_percent'])->toBeLessThan(100)
        ->and($check($initialReadiness, 'A designated published privacy policy is available')['passed'])->toBeFalse()
        ->and(collect($initialReadiness['cards'])->firstWhere('key', 'privacy')['status'])->toBe('critical')
        ->and($check($initialReadiness, 'Approved policy/FAQ knowledge is active and indexed')['passed'])->toBeFalse()
        ->and($check($initialReadiness, 'Ask-count metering is configured')['passed'])->toBeTrue()
        ->and($check($initialReadiness, 'Emergency stop circuit breaker is disarmed')['passed'])->toBeTrue();

    (new PublicIntakeConsentDemoSeeder)->run();
    $ready = $readiness();
    expect($check($ready, 'A designated published privacy policy is available')['passed'])->toBeTrue()
        ->and(collect($ready['cards'])->firstWhere('key', 'privacy')['status'])->toBe('ready');

    $creator = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $approver = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $source = KnowledgeSource::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'setup-readiness-'.Str::uuid(),
        'title' => 'Readiness policy fixture',
        'source_class' => 'governed_document',
        'audience' => 'staff_and_applicant_inquiry',
        'purpose' => 'policy_inquiry',
        'classification' => 'public_institutional',
        'institution_id' => $institutionId,
        'owner_user_id' => (string) $creator->id,
    ]);
    $version = KnowledgeSourceVersion::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_id' => $source->id,
        'version' => 1,
        'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
        'title' => 'Readiness policy fixture',
        'body_text' => 'Approved policy content for readiness coverage.',
        'content_checksum' => hash('sha256', 'Approved policy content for readiness coverage.'),
        'created_by' => (string) $creator->id,
        'approved_by' => (string) $approver->id,
        'approved_at' => now(),
        'published_at' => now(),
    ]);
    $indexEntry = KnowledgeIndexEntry::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_version_id' => $version->id,
        'knowledge_source_id' => $source->id,
        'status' => 'active',
        'search_text' => 'Approved policy content for readiness coverage.',
        'meta' => [],
    ]);

    expect($check($readiness(), 'Approved policy/FAQ knowledge is active and indexed')['passed'])->toBeFalse();

    $source->update(['source_class' => 'institution_policy']);
    expect($check($readiness(), 'Approved policy/FAQ knowledge is active and indexed')['passed'])->toBeTrue();

    $indexEntry->update(['status' => 'rebuilding']);
    expect($check($readiness(), 'Approved policy/FAQ knowledge is active and indexed')['passed'])->toBeFalse();
    $indexEntry->update(['status' => 'active']);

    XiamUsageReservation::query()
        ->where('state', XiamUsageReservation::STATE_UNKNOWN)
        ->where(fn ($query) => $query->where('institution_id', $institutionId)->orWhereNull('institution_id'))
        ->delete();
    expect($check($readiness(), 'Unknown usage reservation holds are reconciled')['passed'])->toBeTrue();

    $unknownHold = XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'staff',
        'actor_id' => (string) $creator->id,
        'institution_id' => $institutionId,
        'run_id' => null,
        'unit' => 'asks',
        'amount' => 1,
        'state' => XiamUsageReservation::STATE_UNKNOWN,
    ]);
    expect($check($readiness(), 'Unknown usage reservation holds are reconciled')['passed'])->toBeFalse();

    $unknownHold->update(['state' => XiamUsageReservation::STATE_SETTLED]);
    expect($check($readiness(), 'Unknown usage reservation holds are reconciled')['passed'])->toBeTrue();

    config(['xiam.emergency_stop' => true]);
    expect($check($readiness(), 'Emergency stop circuit breaker is disarmed')['passed'])->toBeFalse();
    config(['xiam.emergency_stop' => false]);
    expect($check($readiness(), 'Emergency stop circuit breaker is disarmed')['passed'])->toBeTrue();
});

test('legacy OMR aliases retain independent libraries without an outside-Kit pair route', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get('/setup/omr')
        ->assertRedirect('/setup/exam-materials/answer-sheets');

    expect(route('setup.exam-materials.answer-sheets.index'))
        ->toContain('/setup/exam-materials/answer-sheets')
        ->and(route('setup.omr.booklets'))
        ->toContain('/setup/omr/booklets');

    expect(Route::has('setup.omr.pairs'))->toBeFalse();
});

test('knowledge routes are canonical with actionable check links and no duplicate authoring routes', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get('/knowledge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Knowledge/Index'));

    $this->actingAs($registrar)
        ->get('/knowledge/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Knowledge/Create'));

    $this->actingAs($registrar)
        ->get('/knowledge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Knowledge/Index'));

    $registry = app(PageCapabilityPackRegistry::class);
    foreach (['knowledge.index', 'knowledge.create', 'knowledge.show'] as $routeName) {
        expect($registry->forRoute($routeName)?->pageIdentity)->toBe('knowledge.index');
    }

    $readiness = app(ComputeSetupReadinessQuery::class)->handle();
    $category = collect($readiness['groups'])
        ->pluck('categories')
        ->flatten(1)
        ->firstWhere('key', 'xiam_knowledge_governance');

    expect($category['href'])->toBe('/knowledge')
        ->and($category['label'])->toBe('Admissions Policies & AI Assistant');
    $checks = collect($category['checks']);
    $privacyCheck = $checks->firstWhere('label', 'A designated published privacy policy is available');
    expect($privacyCheck['action_href'])->toBe('/communications/policies')
        ->and($privacyCheck['action_label'])->toBe('Open public policies');

    $knowledgeCheck = $checks->firstWhere('label', 'Approved policy/FAQ knowledge is active and indexed');
    expect($knowledgeCheck['action_href'])->toBe('/knowledge')
        ->and($knowledgeCheck['action_label'])->toBe('Manage Policies & FAQs');
});
