<?php

use App\Admission\Support\QuotaReservation;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use App\Staff\Queries\CoursePlacementAggregateQuery;
use App\Testing\PracticeWorkspace;

test('unauthenticated users cannot view staff dashboard', function (): void {
    $response = $this->get('/staff');

    $response->assertRedirect('/login');
});

test('registrar administrator receives admissions metrics on dashboard', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $dismissed = AdmissionAttempt::query()
        ->forWorkspace()
        ->where('academic_cycle_id', AcademicCycle::active()->id)
        ->where('status', 'dismissed')
        ->count();
    $response = $this->actingAs($registrar)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $registrar->authenticationState->session_version,
    ])->get('/staff');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Home')
            ->has('dashboard.as_of')
            ->has('dashboard.registrar.kpis.total')
            ->where('dashboard.registrar.kpis.dismissed', $dismissed)
            ->has('dashboard.registrar.kpis.yoy_delta')
            ->has('dashboard.registrar.kpis.yoy_comparison')
            ->has('dashboard.registrar.growth_trend')
            ->has('dashboard.registrar.pipeline_distribution')
            ->where('dashboard.guidance', null)
        );
});

test('guidance counselor receives a cycle-scoped placement aggregate with campus program quota scope', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidance->givePermissionTo(CapabilityCatalog::GUIDANCE_REPORTS_VIEW);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $institutionId = (int) $guidance->actorScope()->institutionId;
    $cycle = AcademicCycle::query()
        ->where('institution_id', $institutionId)
        ->where('is_active', true)
        ->firstOrFail();
    $attempt = submitSyntheticApplicant('dashboard-course-placement@example.test', 'DashboardPlacement')['attempt'];
    $program = Program::query()->where('institution_id', $institutionId)->where('code', 'BSIT')->firstOrFail();
    $attempt->forceFill(['status' => 'accepted'])->save();
    AdmissionClearanceReceipt::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'receipt_number' => 'CLR-DASH-'.$attempt->id,
        'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
        'evidence_vector' => ['ready' => true],
        'confirmed_by_user_id' => $registrar->id,
        'confirmed_at' => now(),
        'idempotency_key' => 'dashboard-course-placement-'.$attempt->id,
    ]);

    $programIds = Program::query()
        ->active()
        ->where('institution_id', $institutionId)
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();
    $programQuotas = ProgramQuota::query()
        ->where('academic_cycle_id', $cycle->id)
        ->whereIn('program_id', $programIds)
        ->get(['program_id', 'slots'])
        ->keyBy('program_id');
    $expectedQuota = 0;
    foreach ($programIds as $programId) {
        $expectedQuota += (int) ($programQuotas->get($programId)?->slots ?? 90);
    }
    $expectedPlaced = array_sum(QuotaReservation::batchCommittedForCycle(
        (int) $cycle->id,
        $programIds,
        practiceSetId: app(PracticeWorkspace::class)->currentId(),
    ));
    $expectedPlacement = [
        'placed' => $expectedPlaced,
        'quota' => $expectedQuota,
        'percent' => $expectedQuota > 0 ? round(($expectedPlaced / $expectedQuota) * 100, 1) : 0.0,
    ];

    expect((int) $attempt->academic_cycle_id)->toBe((int) $cycle->id)
        ->and($expectedPlacement['placed'])->toBeGreaterThan(0)
        ->and($expectedPlacement['quota'])->toBeGreaterThan(0)
        ->and(app(CoursePlacementAggregateQuery::class)->forCycle(null, $institutionId))->toBeNull()
        ->and(app(CoursePlacementAggregateQuery::class)->forCycle((int) $cycle->id, $institutionId + 1))->toBeNull();

    $response = $this->actingAs($guidance)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $guidance->authenticationState->session_version,
    ])->get('/staff');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Home')
            ->has('dashboard.as_of')
            ->has('dashboard.guidance.kpis.scheduled_sessions')
            ->has('dashboard.guidance.session_trends')
            ->where('dashboard.course_placement.placed', $expectedPlacement['placed'])
            ->where('dashboard.course_placement.quota', $expectedPlacement['quota'])
            ->where('dashboard.course_placement.percent', fn ($percent): bool => (float) $percent === $expectedPlacement['percent'])
            ->has('dashboard.academic_scope.programs')
            ->where('dashboard.academic_scope.scope_mode', 'institution')
            ->has('dashboard.guidance.kpis.today_release_appointments')
            ->has('dashboard.guidance.kpis.pending_referrals')
            ->has('dashboard.guidance.kpis.open_inquiries')
            ->has('dashboard.guidance.kpis.pending_advising_qualifiers')
            ->where('dashboard.registrar', null)
        );
});

test('guidance exam scheduling alone does not authorize placement summary', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidance->syncRoles([]);
    $guidance->syncPermissions([CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE]);

    $response = $this->actingAs($guidance)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $guidance->authenticationState->session_version,
    ])->get('/staff');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Home')
            ->where('dashboard.course_placement', null)
            ->where('dashboard.academic_scope', null)
            ->where('dashboard.registrar', null)
        );
});

test('program head receives assigned-program quota metrics on dashboard', function (): void {
    seedE2Foundation();
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $institutionId = (int) $programHead->actorScope()->institutionId;
    $cycle = AcademicCycle::query()
        ->where('institution_id', $institutionId)
        ->where('is_active', true)
        ->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    foreach (['BSIT', 'BSN'] as $programCode) {
        $program = Program::query()->where('institution_id', $institutionId)->where('code', $programCode)->firstOrFail();
        $attempt = submitSyntheticApplicant('dashboard-scope-'.$programCode.'@example.test', 'DashboardScope'.$programCode)['attempt'];
        $attempt->forceFill(['status' => 'accepted'])->save();
        AdmissionClearanceReceipt::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_id' => $program->id,
            'receipt_number' => 'CLR-SCOPE-'.$attempt->id,
            'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
            'evidence_vector' => ['ready' => true],
            'confirmed_by_user_id' => $registrar->id,
            'confirmed_at' => now(),
            'idempotency_key' => 'dashboard-scope-'.$attempt->id,
        ]);
    }
    $programIds = Program::query()
        ->active()
        ->where('institution_id', $institutionId)
        ->whereIn('code', $programHead->actorScope()->programCodes)
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();
    $quotas = ProgramQuota::query()
        ->where('academic_cycle_id', $cycle->id)
        ->whereIn('program_id', $programIds)
        ->get(['program_id', 'slots'])
        ->keyBy('program_id');
    $quota = 0;
    foreach ($programIds as $programId) {
        $quota += (int) ($quotas->get($programId)?->slots ?? 90);
    }
    $placed = 1;
    $expectedPlacement = [
        'placed' => $placed,
        'quota' => $quota,
        'percent' => $quota > 0 ? round(($placed / $quota) * 100, 1) : 0.0,
    ];

    $response = $this->actingAs($programHead)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $programHead->authenticationState->session_version,
    ])->get('/staff');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Home')
            ->has('dashboard.as_of')
            ->where('dashboard.program_head.is_dean', false)
            ->where('dashboard.program_head.program_code', 'BSIT')
            ->where('dashboard.program_head.programs_count', 1)
            ->has('dashboard.program_head.quota')
            ->has('dashboard.program_head.allocated')
            ->where('dashboard.course_placement.placed', $expectedPlacement['placed'])
            ->where('dashboard.course_placement.quota', $expectedPlacement['quota'])
            ->where('dashboard.course_placement.percent', fn ($percent): bool => (float) $percent === $expectedPlacement['percent'])
            ->where('dashboard.registrar', null)
            ->where('dashboard.guidance', null)
        );
});

test('dean receives department aggregated quota and program metrics on dashboard', function (): void {
    seedE2Foundation();
    $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();

    $response = $this->actingAs($dean)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $dean->authenticationState->session_version,
    ])->get('/staff');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Home')
            ->has('dashboard.as_of')
            ->where('dashboard.program_head.is_dean', true)
            ->where('dashboard.program_head.department_code', 'CAS')
            ->where('dashboard.program_head.programs_count', 5)
            ->where('dashboard.academic_scope.scope_mode', 'department')
            ->where('dashboard.academic_scope.can_filter_department', false)
            ->where('dashboard.registrar', null)
            ->where('dashboard.guidance', null)
            ->where('dashboard.setup_readiness', null)
        );
});
