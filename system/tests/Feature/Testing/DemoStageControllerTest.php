<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest and ordinary reader receive the development meta guide with personas and safe boundaries', function (): void {
    seedE2Foundation();
    $this->get(route('demo.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Foundation/DemoGuide')->where('can_control', false)->where('preparation_url', null)
        ->has('workflows', 8)->where('workflows.0.key', 'application-review')->where('workflows.0.steps.0.url', '/apply')
        ->has('materials', 3)->missing('operator_stages')->missing('demo_sessions')->missing('warp_stages')->missing('roles')->missing('phases')
        ->has('persona_directory.staff')->has('persona_directory.applicants'));
    $this->actingAs(User::factory()->create())->get(route('demo.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_control', false)->where('preparation_url', null)->has('workflows', 8));
});

test('freshly revoked demo operator loses protected preparation entry with no stage mutation deck', function (): void {
    seedE2Foundation();
    $operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($operator)->get(route('demo.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('can_control', true)->where('preparation_url', '/demo/preparation')
        ->missing('operator_stages')->missing('demo_sessions')->missing('warp_stages'));
    $operator->roles()->where('name', 'guidance_counselor')->firstOrFail()->revokePermissionTo(CapabilityCatalog::DEMO_STAGE_CONTROL);
    $this->get(route('demo.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_control', false)->where('preparation_url', null));
    $this->getJson('/demo/preparation')->assertForbidden();
});

test('demo guide fixed assets and preparation are denied in production', function (): void {
    seedE2Foundation();
    $this->app->detectEnvironment(static fn (): string => 'production');
    $this->get(route('demo.index'))->assertForbidden();
    $this->get(route('capstone.demo-guide'))->assertForbidden();
    $this->get(route('demo.diagrams.show', ['diagram' => 'AD-IDN-01']))->assertForbidden();
    $this->get(route('demo.materials.show', ['material' => 'protocol']))->assertForbidden();
    $this->actingAs(User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail())->getJson('/demo/preparation')->assertForbidden();
    $this->postJson('/demo/preparation/preview', ['purpose' => 'demo', 'scenario' => 'application-review', 'action' => 'prepare'])->assertForbidden();
    $this->app->detectEnvironment(static fn (): string => 'testing');
});

test('public capstone guide reuses safe workflows without protected credentials or controls', function (): void {
    $this->get(route('capstone.demo-guide'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Foundation/DemoGuide')->where('can_control', false)->where('preparation_url', null)
        ->has('workflows', 8)->has('materials', 3)->missing('operator_stages')->missing('roles')->missing('phases'))
        ->assertDontSee('guidance.counselor@nexiam.test')->assertDontSee('REVW-DEM1');
});

test('fixed approved diagram and material assets retain safe consumer responses', function (): void {
    $this->get(route('demo.diagrams.show', ['diagram' => 'AD-IDN-01']))->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox")
        ->assertHeader('X-Frame-Options', 'DENY')->assertSee('<svg', false);
    $this->get(route('demo.materials.show', ['material' => 'protocol']))->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->assertHeader('Content-Disposition', 'attachment; filename="appendix-n-facilitator-protocol.md"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('demo.diagrams.show', ['diagram' => 'not-approved']))->assertNotFound();
    $this->get(route('demo.diagrams.show', ['diagram' => 'protocol']))->assertNotFound();
    $this->get(route('demo.materials.show', ['material' => 'AD-IDN-01']))->assertNotFound();
    $this->get('/demo/diagrams/%2e%2e%2Fsecret.svg')->assertNotFound();
});

test('retired prefix selected activation and timeline endpoints cannot mutate any existing cohort', function (): void {
    seedE2Foundation();
    $before = AdmissionAttempt::query()->count();
    $sessions = ExamSession::query()->count();
    $this->post('/demo/stages/1/activate')->assertNotFound();
    $this->post('/demo/sessions/EX-STAGE-RESV/warp', ['stage' => '1h-reminder'])->assertNotFound();
    $this->actingAs(User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail())->post('/demo/stages/1/activate')->assertNotFound();
    $this->post('/demo/sessions/EX-STAGE-RESV/warp', ['stage' => 'live'])->assertNotFound();
    expect(AdmissionAttempt::query()->count())->toBe($before)->and(ExamSession::query()->count())->toBe($sessions);
});

test('authenticated demo operator receives step personas and persona directory with copyable credentials', function (): void {
    seedE2Foundation();
    $operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($operator)->get(route('demo.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Foundation/DemoGuide')
        ->where('can_control', true)
        ->where('preparation_url', '/demo/preparation')
        ->has('workflows.0.cli_command')
        ->where('workflows.0.scenario_key', 'application-review')
        ->has('workflows.0.steps.0.actor_persona')
        ->has('persona_directory.staff')
        ->has('persona_directory.applicants')
    );
});
