<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use App\Setup\Registry\SetupReadinessRegistry;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('setup readiness registry contains required domain definitions', function (): void {
    $domains = collect(app(SetupReadinessRegistry::class)->all())
        ->map(fn ($definition): string => $definition->key())
        ->all();

    expect($domains)->toContain(
        'cycles',
        'programs',
        'departments',
        'program_public_profiles',
        'notifications',
        'requirements',
        'intake_fields',
        'privacy',
        'rooms',
        'seating_arrangements',
        'exam_materials',
        'answer_sheets',
        'booklets',
        'omr_test',
        'test_instruments',
        'aptitude',
        'norm_packages',
        'result_templates',
        'admission_support',
        'quotas',
        'consultation_policy',
        'ai_center',
        'staff_accounts',
    );
});

test('setup readiness definitions map required domains to stages one through five', function (): void {
    $stagesByDomain = collect(app(SetupReadinessRegistry::class)->all())
        ->mapWithKeys(fn ($definition): array => [$definition->key() => $definition->stage()])
        ->all();

    expect($stagesByDomain)->toMatchArray([
        'cycles' => 1,
        'programs' => 1,
        'departments' => 1,
        'program_public_profiles' => 1,
        'notifications' => 1,
        'requirements' => 2,
        'intake_fields' => 2,
        'privacy' => 2,
        'rooms' => 3,
        'seating_arrangements' => 3,
        'exam_materials' => 3,
        'answer_sheets' => 3,
        'booklets' => 3,
        'omr_test' => 3,
        'test_instruments' => 4,
        'aptitude' => 4,
        'norm_packages' => 4,
        'result_templates' => 4,
        'admission_support' => 5,
        'quotas' => 5,
        'consultation_policy' => 5,
        'ai_center' => 5,
        'staff_accounts' => 1,
    ]);
    expect(array_keys(app(SetupReadinessRegistry::class)->byStage()))->toContain(1, 2, 3, 4, 5);
});

test('stage two uses applicant admission governance and application labels', function (): void {
    $registry = app(SetupReadinessRegistry::class);
    $requirementDefinition = $registry->get('requirements');
    $fieldsDefinition = $registry->get('intake_fields');

    expect($registry->byStage()[2]['title'])
        ->toBe('Applicant Admission & Governance')
        ->and($requirementDefinition?->label())
        ->toBe('Application Requirements')
        ->and($fieldsDefinition?->label())
        ->toBe('Application Form Fields');
});

test('setup readiness registry groups required domains by desk', function (): void {
    $registry = app(SetupReadinessRegistry::class);
    $keysByDesk = fn (string $desk): array => collect($registry->byDesk($desk))
        ->map(fn ($definition): string => $definition->key())
        ->all();

    expect($keysByDesk('admin'))->toContain(
        'cycles',
        'programs',
        'departments',
        'notifications',
        'privacy',
        'rooms',
        'staff_accounts',
    )
        ->and($keysByDesk('registrar'))->toContain('requirements', 'intake_fields')
        ->and($keysByDesk('testing'))->toContain(
            'seating_arrangements',
            'exam_materials',
            'answer_sheets',
            'booklets',
            'omr_test',
        )
        ->and($keysByDesk('guidance'))->toContain(
            'program_public_profiles',
            'test_instruments',
            'aptitude',
            'norm_packages',
            'result_templates',
            'admission_support',
            'quotas',
            'consultation_policy',
        )
        ->and($keysByDesk('system'))->toContain('ai_center');
});

test('setup readiness definitions map to owning capabilities and destinations', function (): void {
    $definitions = collect(app(SetupReadinessRegistry::class)->all())
        ->keyBy(fn ($definition): string => $definition->key());

    foreach (['cycles', 'rooms'] as $key) {
        expect($definitions[$key]->desk())->toBe('admin')
            ->and($definitions[$key]->capability())->toBe(match ($key) {
                'cycles' => CapabilityCatalog::SETUP_CYCLES_MANAGE,
                'rooms' => CapabilityCatalog::SETUP_ROOMS_MANAGE,
            });
    }

    foreach (['programs', 'departments'] as $key) {
        expect($definitions[$key]->desk())->toBe('admin')
            ->and($definitions[$key]->capability())->toBe(CapabilityCatalog::SETUP_PROGRAMS_MANAGE);
    }

    expect($definitions['privacy']->desk())->toBe('admin')
        ->and($definitions['privacy']->label())->toBe('Application Privacy Policy')
        ->and($definitions['privacy']->capability())->toBe(CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE)
        ->and($definitions['program_public_profiles']->desk())->toBe('guidance')
        ->and($definitions['program_public_profiles']->href())->toBe('/staff/programs')
        ->and($definitions['program_public_profiles']->capability())->toBe(CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE)
        ->and($definitions['seating_arrangements']->desk())->toBe('testing')
        ->and($definitions['seating_arrangements']->capability())->toBe(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);

    foreach (['requirements' => 'Application Requirements', 'intake_fields' => 'Application Form Fields'] as $key => $label) {
        expect($definitions[$key]->desk())->toBe('registrar')
            ->and($definitions[$key]->label())->toBe($label)
            ->and($definitions[$key]->capability())->toBe(CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE);
    }

    expect($definitions['notifications']->desk())->toBe('admin')
        ->and($definitions['notifications']->href())->toBe('/setup/notifications')
        ->and($definitions['notifications']->label())->toBe('Email & Notifications')
        ->and($definitions['notifications']->capability())->toBe('notifications')
        ->and($definitions['consultation_policy']->desk())->toBe('guidance')
        ->and($definitions['consultation_policy']->capability())->toBe(CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)
        ->and($definitions['ai_center']->desk())->toBe('system')
        ->and($definitions['ai_center']->capability())->toBe(CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE);
});

test('exam materials readiness resolves its standalone test kit destination for authorized setup users', function (): void {
    $definition = collect(app(SetupReadinessRegistry::class)->all())
        ->first(fn ($definition): bool => $definition->key() === 'exam_materials');

    expect($definition)->not->toBeNull()
        ->and($definition->href())->toBe(route('setup.exam-materials.test-kits.index', [], false))
        ->and($definition->capability())->toBe(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);

    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('user_desk', 'guidance')
            ->where('readiness.cards', fn (Collection $cards): bool => $cards->contains(
                fn (array $card): bool => $card['key'] === 'exam_materials'
                    && $card['href'] === $definition->href()
                    && $card['capability'] === CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG
            ))
        );

    $this->actingAs($guidance)
        ->get($definition->href())
        ->assertOk();

    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->get($definition->href())
        ->assertForbidden();
});
test('routine assessment navigation centers Test Kits and keeps only the two resource libraries secondary', function (): void {
    seedE2Foundation();

    $readiness = app(ComputeSetupReadinessQuery::class)->handle();
    $cards = collect($readiness['cards'])->keyBy('key');
    $navigationCards = $cards->filter(fn (array $card): bool => $card['in_navigation']);

    expect($navigationCards->keys())->toContain('exam_materials', 'answer_sheets', 'booklets')
        ->and($navigationCards->keys())->not->toContain('omr_test', 'test_instruments', 'aptitude', 'norm_packages')
        ->and($cards['exam_materials']['navigation_context'])->toBe('Test setup')
        ->and($cards['answer_sheets']['navigation_context'])->toBe('Resource Library')
        ->and($cards['booklets']['navigation_context'])->toBe('Resource Library')
        ->and($cards['answer_sheets']['capability'])->toBe(CapabilityCatalog::GUIDANCE_SCORING_OMR)
        ->and($cards['booklets']['capability'])->toBe(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    foreach (['exam_materials', 'answer_sheets', 'booklets'] as $key) {
        $this->actingAs($guidance)->get($cards[$key]['href'])->assertOk();
    }

    $aptitudeCheck = collect($readiness['groups'])->pluck('categories')->flatten(1)->firstWhere('key', 'aptitude');
    $kitCheck = collect($readiness['groups'])->pluck('categories')->flatten(1)->firstWhere('key', 'omr');
    expect($aptitudeCheck['label'])->toBe('Test setup')
        ->and($aptitudeCheck['href'])->toBe(route('setup.exam-materials.test-kits.index', [], false))
        ->and($aptitudeCheck['checks'])->toHaveCount(2)
        ->and($kitCheck['label'])->toBe('Test Kit readiness')
        ->and($kitCheck['href'])->toBe(route('setup.exam-materials.test-kits.index', [], false))
        ->and($kitCheck['checks'][0]['label'])->toBe('An active Test Kit bundle is available');
});

test('computed setup readiness omits disabled seating and restores its card when enabled', function (): void {
    seedE2Foundation();

    $institution = Institution::current();
    $institution->forceFill(['enable_seating_arrangements' => false])->save();
    $readiness = app(ComputeSetupReadinessQuery::class)->handle();

    expect(collect($readiness['cards'])->pluck('key'))->not->toContain('seating_arrangements');

    $institution->forceFill(['enable_seating_arrangements' => true])->save();
    $cycle = AcademicCycle::active();
    $room = Room::query()->where('institution_id', $institution->id)->first();
    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-SEAT-TEST',
        'status' => ExamSession::STATUS_DRAFT,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
    ]);

    $readiness = app(ComputeSetupReadinessQuery::class)->handle();

    expect(collect($readiness['cards'])->pluck('key'))->toContain('seating_arrangements');
});

test('computed setup readiness includes stages and cards alongside groups', function (): void {
    seedE2Foundation();
    Institution::current()->forceFill(['enable_seating_arrangements' => false])->save();

    $readiness = app(ComputeSetupReadinessQuery::class)->handle();

    expect($readiness)->toHaveKeys(['groups', 'stages', 'cards'])
        ->and($readiness['groups'])->not->toBeEmpty()
        ->and($readiness['stages'])->toHaveCount(5)
        ->and(array_column($readiness['stages'], 'stage'))->toBe([1, 2, 3, 4, 5])
        ->and(collect($readiness['stages'])->flatMap(fn (array $stage): array => $stage['cards'])->pluck('key')->all())
        ->toBe(collect($readiness['cards'])->pluck('key')->all());
});

test('setup dashboard exposes the desk and staged readiness card props', function (): void {
    seedE2Foundation();
    Institution::current()->forceFill(['enable_seating_arrangements' => false])->save();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('user_desk', 'admin')
            ->has('readiness.stages', 5)
            ->has('readiness.cards')
        );
});
test('guidance counselor remains on the guidance desk', function (): void {
    seedE2Foundation();
    Institution::current()->forceFill(['enable_seating_arrangements' => false])->save();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Index')
            ->where('user_desk', 'guidance')
            ->has('readiness.stages', 5)
            ->has('readiness.cards')
            ->has('readiness.groups')
        );
});

test('notification readiness accepts current defaults and valid custom policies but rejects unavailable or invalid current configuration', function (): void {
    seedE2Foundation();

    $institution = Institution::current();
    $cycle = AcademicCycle::active();
    $definition = app(SetupReadinessRegistry::class)->get('notifications');
    $readiness = fn (): array => app(ComputeSetupReadinessQuery::class)->handle();
    $diagnostic = fn (): array => collect($readiness()['groups'])
        ->pluck('categories')
        ->flatten(1)
        ->firstWhere('key', 'notifications');
    $card = fn (): array => collect($readiness()['cards'])->firstWhere('key', 'notifications');

    expect($cycle->institution_id)->toBe($institution->id)
        ->and($cycle->admission_notification_policy)->toBeNull()
        ->and($cycle->exam_notification_policy)->toBeNull()
        ->and($definition->status($institution))->toBe('ready')
        ->and($card()['status'])->toBe('ready')
        ->and(collect($diagnostic()['checks'])->every(fn (array $check): bool => $check['passed']))->toBeTrue();

    $cycle->forceFill([
        'admission_notification_policy' => [
            'schema_version' => 1,
            'version' => 1,
            'applicant_status_email_enabled' => false,
            'registrar_new_submission_alert_enabled' => false,
        ],
        'exam_notification_policy' => [
            'schema_version' => 1,
            'version' => 1,
            'publication_email_enabled' => false,
            'reminder_offsets_minutes' => [],
            'reminder_email_enabled' => false,
        ],
    ])->save();

    expect($definition->status($institution))->toBe('ready')
        ->and($card()['status'])->toBe('ready')
        ->and(collect($diagnostic()['checks'])->every(fn (array $check): bool => $check['passed']))->toBeTrue();

    $cycle->forceFill(['exam_notification_policy' => ['schema_version' => 999]])->save();

    expect($definition->status($institution))->toBe('critical')
        ->and($card()['status'])->toBe('critical')
        ->and(collect($diagnostic()['checks'])->contains(fn (array $check): bool => ! $check['passed']
            && $check['severity'] === 'critical'))->toBeTrue();

    $foreignInstitution = Institution::query()->create([
        'code' => 'SETUP-NOTIFICATIONS-FOREIGN',
        'name' => 'Foreign setup institution',
    ]);
    $cycle->forceFill([
        'admission_notification_policy' => null,
        'exam_notification_policy' => null,
        'is_active' => false,
    ])->save();
    $foreignCycle = $cycle->replicate();
    $foreignCycle->forceFill([
        'institution_id' => $foreignInstitution->id,
        'code' => 'NOTIFICATIONS-FOREIGN-CYCLE',
        'is_active' => true,
    ])->save();

    expect($definition->status($institution))->toBe('critical')
        ->and($card()['status'])->toBe('critical')
        ->and(collect($diagnostic()['checks'])->contains(fn (array $check): bool => ! $check['passed']
            && $check['severity'] === 'critical'))->toBeTrue();

    $foreignCycle->forceFill(['is_active' => false])->save();

    expect($definition->status($institution))->toBe('critical')
        ->and($card()['status'])->toBe('critical')
        ->and(collect($diagnostic()['checks'])->contains(fn (array $check): bool => ! $check['passed']
            && $check['severity'] === 'critical'))->toBeTrue();
});
