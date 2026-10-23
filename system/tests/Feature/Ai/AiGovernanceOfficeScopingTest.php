<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Gateway\ToolPolicyGate;
use App\Ai\Policy\UsageBudgetService;
use App\Authorization\CapabilityAuthorizer;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Ai\XiamUsageReservation;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('AI governance center exposes umbrella and office-scoped data only to governors', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->actingAs($admin)->get(route('setup.ai-center'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Setup/AiCenter')
        ->where('is_umbrella_admin', true)
        ->has('switchboard', 53)
        ->where('switchboard', fn ($tools) => collect($tools)->pluck('id')->intersect([
            'registrar.enrollment.stamp_enrolling@1',
            'registrar.enrollment.stamp_enrolled@1',
            'registrar.enrollment.reverse_stamp@1',
        ])->isEmpty())
        ->has('policy.applicant_inquiry_enabled')
        ->has('policy.applicant_self_booking_enabled')
        ->has('provider_config')
        ->has('provider_config.active_driver')
    );

    $this->actingAs($guidance)->get(route('setup.ai-center'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Setup/AiCenter')
        ->where('is_umbrella_admin', false)
        ->has('switchboard', 22)
        ->where('switchboard', fn ($tools) => collect($tools)->every(fn ($tool) => $tool['governance_scope'] === 'guidance'))
        ->where('staff_quotas', fn ($staff) => collect($staff)->isNotEmpty() && collect($staff)->every(fn ($quota) => $quota['office'] === 'guidance'))
        ->where('provider_config', null)
        ->has('policy.applicant_inquiry_enabled')
    );

    $this->actingAs($registrar)->get(route('setup.ai-center'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Setup/AiCenter')
        ->where('is_umbrella_admin', false)
        ->has('switchboard', 3)
        ->where('switchboard', fn ($tools) => collect($tools)->every(fn ($tool) => $tool['governance_scope'] === 'registrar'))
        ->where('staff_quotas', fn ($staff) => collect($staff)->isNotEmpty() && collect($staff)->every(fn ($quota) => $quota['office'] === 'registrar'))
    );

    foreach (['registrar.clerk@nexiam.test', 'guidance.clerk@nexiam.test'] as $email) {
        $this->actingAs(User::query()->where('email', $email)->firstOrFail())
            ->get(route('setup.ai-center'))
            ->assertForbidden();
    }
});

test('office governors toggle only their own tools and cannot override an Admin Office lock', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $institutionId = (int) $guidance->actorScope()->institutionId;

    $this->actingAs($guidance)->post(route('setup.ai-center.tools.toggle'), [
        'tool_id' => 'exams.session.create@1', 'enabled' => false,
    ])->assertSessionHas('success');
    expect(XiamInstitutionPolicy::forInstitution($institutionId)->isToolEnabled('exams.session.create@1'))->toBeFalse();

    $this->post(route('setup.ai-center.tools.toggle'), [
        'tool_id' => 'exams.session.create@1', 'enabled' => true,
    ])->assertSessionHas('success');
    expect(XiamInstitutionPolicy::forInstitution($institutionId)->isToolEnabled('exams.session.create@1'))->toBeTrue();

    foreach (['admissions.attempt.accept@1', 'setup.cycles.activate@1'] as $toolId) {
        $this->post(route('setup.ai-center.tools.toggle'), ['tool_id' => $toolId, 'enabled' => false])->assertForbidden();
    }

    $this->actingAs($admin)->post(route('setup.ai-center.tools.toggle'), [
        'tool_id' => 'exams.session.create@1', 'enabled' => false, 'lock_for_office' => true,
    ])->assertSessionHas('success');
    expect(XiamInstitutionPolicy::forInstitution($institutionId)->fresh()->admin_locked_tools)
        ->toContain('exams.session.create@1');

    $this->actingAs($guidance)->post(route('setup.ai-center.tools.toggle'), [
        'tool_id' => 'exams.session.create@1', 'enabled' => true,
    ])->assertForbidden();

    $this->actingAs($admin)->post(route('setup.ai-center.tools.toggle'), [
        'tool_id' => 'exams.session.create@1', 'enabled' => true, 'lock_for_office' => true,
    ])->assertSessionHas('success');
    $guidanceContext = new InvocationContext(
        actorId: (string) $guidance->id,
        actorType: 'staff',
        institutionId: $institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'ai-lock-test',
        purpose: 'assistant_turn',
        correlationId: 'ai-lock-test',
    );
    expect(collect(app(AssistantToolCatalog::class)->forActor($guidance, $guidanceContext, app(CapabilityAuthorizer::class)))
        ->map(fn ($tool) => $tool->id())->all())->toContain('exams.session.create@1');
});

test('central applicant policy is admin-only and its switches narrow runtime applicant access', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institutionId = (int) $admin->actorScope()->institutionId;
    $applicant = submitSyntheticApplicant('ai-policy-applicant@example.test', 'PolicyApplicant');
    $applicantUser = app(AuthenticateApplicantWithAccessKey::class)
        ->handle($applicant['access_token'], '2001-01-01');
    $context = new InvocationContext(
        actorId: (string) $applicantUser->id,
        actorType: 'applicant',
        institutionId: $institutionId,
        surfaceProfile: 'applicant',
        mode: 'assistant',
        contextEpoch: 'ai-applicant-policy-test',
        purpose: 'assistant_turn',
        correlationId: 'ai-applicant-policy-test',
    );

    $payload = [
        'applicant_inquiry_enabled' => true,
        'applicant_self_booking_enabled' => false,
        'daily_applicant_pool_asks' => 100,
        'per_applicant_daily_ask_limit' => 15,
    ];
    foreach ([$guidance, User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail()] as $officeLead) {
        $this->actingAs($officeLead)->post(route('setup.ai-center.applicant-policy'), $payload)->assertForbidden();
    }
    $this->actingAs($admin)->post(route('setup.ai-center.applicant-policy'), $payload)->assertSessionHas('success');

    expect(app(ToolPolicyGate::class)->resolveEnvelope($context, $applicantUser))
        ->toBe(ToolPolicyGate::ENVELOPE_READ_ALLOWED);
    $catalog = app(AssistantToolCatalog::class);
    expect(collect($catalog->forActor($applicantUser, $context, app(CapabilityAuthorizer::class)))
        ->map(fn ($tool) => $tool->id())->all())->not->toContain('exams.slot.reserve@1');

    $payload['applicant_inquiry_enabled'] = false;
    $this->actingAs($admin)->post(route('setup.ai-center.applicant-policy'), $payload)->assertSessionHas('success');
    expect(app(ToolPolicyGate::class)->resolveEnvelope($context, $applicantUser))
        ->toBe('read_denied');
});

test('office leads grant ask extensions only to same-office staff and the credit offsets net usage', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidanceClerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $registrarClerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $institutionId = (int) $guidance->actorScope()->institutionId;

    XiamUsageReservation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_type' => 'staff',
        'actor_id' => (string) $guidanceClerk->id,
        'institution_id' => $institutionId,
        'office' => 'guidance',
        'run_id' => null,
        'unit' => 'asks',
        'amount' => 40,
        'state' => XiamUsageReservation::STATE_SETTLED,
    ]);

    $this->actingAs($guidance)->post(route('setup.ai-center.extensions.grant'), [
        'target_user_id' => (string) $guidanceClerk->id,
        'amount' => 25,
        'reason' => 'Temporary examination workload increase',
    ])->assertSessionHas('success');

    $extension = XiamUsageReservation::query()->where('actor_id', (string) $guidanceClerk->id)
        ->where('unit', 'asks_extension')->firstOrFail();
    expect($extension->office)->toBe('guidance')
        ->and((int) $extension->granted_by_user_id)->toBe((int) $guidance->id)
        ->and($extension->extension_reason)->toBe('Temporary examination workload increase')
        ->and(app(UsageBudgetService::class)->usedAsks('staff', (string) $guidanceClerk->id))->toBe(15)
        ->and(XiamUsageReservation::query()->where('actor_id', (string) $guidanceClerk->id)->where('unit', 'asks')->sum('amount'))->toBe(40);

    $this->post(route('setup.ai-center.extensions.grant'), [
        'target_user_id' => (string) $registrarClerk->id,
        'amount' => 25,
        'reason' => 'Wrong office must be denied',
    ])->assertForbidden();
});
