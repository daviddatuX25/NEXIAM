<?php

use App\Authorization\CapabilityCatalog;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

const RESEARCH_ROLES = [
    'student' => ['scenario' => 'A', 'section' => 'C', 'prefix' => 'STU', 'count' => 6],
    'registrar' => ['scenario' => 'B', 'section' => 'A', 'prefix' => 'REG', 'count' => 6],
    'guidance' => ['scenario' => 'C', 'section' => 'B', 'prefix' => 'GUD', 'count' => 8],
    'academic-leader' => ['scenario' => 'D', 'section' => 'D', 'prefix' => 'LEAD', 'count' => 4],
];

test('student research reader supplies neutral assigned tasks without facilitator content', function (): void {
    $this->get('/demo/research?role=student')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Foundation/ResearchSessionGuide')
            ->where('selected_role', 'student')
            ->where('participant.scenario', 'A')
            ->where('participant.tasks.0.id', 'A1')
            ->where('participant.tasks.5.id', 'A6')
            ->where('questionnaire.url', '/demo/research/instruments/student')
            ->where('questionnaire.id', 'section-c')
            ->where('questionnaire.kind', 'material')
            ->missing('facilitator')
            ->missing('participant.tasks.0.item_ids')
            ->missing('participant.case_trace')
            ->missing('source_version')
        )
        ->assertDontSee('STU-PU-01');
});

test('all roles expose only their canonical task sequence and role questionnaire mapping', function (): void {
    foreach (RESEARCH_ROLES as $roleKey => $expected) {
        $response = $this->get('/demo/research?role='.$roleKey);
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Foundation/ResearchSessionGuide')
            ->where('selected_role', $roleKey)
            ->where('participant.scenario', $expected['scenario'])
            ->where('participant.selected_task', $expected['scenario'].'1')
            ->has('participant.tasks', $expected['count'])
            ->where('questionnaire.id', 'section-'.strtolower($expected['section']))
            ->where('questionnaire.url', '/demo/research/instruments/'.$roleKey)
            ->missing('facilitator')
            ->missing('participant.tasks.0.item_ids'));
    }
});

test('task selection is bounded to its selected scenario and unknown or traversal keys are denied', function (): void {
    foreach (RESEARCH_ROLES as $roleKey => $expected) {
        $first = $expected['scenario'].'1';
        $last = $expected['scenario'].$expected['count'];
        $this->get('/demo/research?role='.$roleKey.'&task='.$last)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('participant.selected_task', $last));

        $foreignRole = collect(RESEARCH_ROLES)->keys()->first(fn (string $candidate): bool => $candidate !== $roleKey);
        $foreignTask = RESEARCH_ROLES[$foreignRole]['scenario'].'1';
        $this->get('/demo/research?role='.$roleKey.'&task='.$foreignTask)->assertNotFound();
        $this->get('/demo/research?role='.$roleKey.'&task=../'.$first)->assertNotFound();
    }

    $this->get('/demo/research?role=unknown')->assertNotFound();
    $this->get('/demo/research?role=../../etc/passwd')->assertNotFound();
    $this->get('/demo/research?role=student&task[]=A1')->assertNotFound();
});

test('each public instrument contains common instructions and only its own matrix', function (): void {
    foreach (RESEARCH_ROLES as $roleKey => $expected) {
        $response = $this->get('/demo/research/instruments/'.$roleKey);
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="research-instrument-'.$roleKey.'.md"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertSee($expected['prefix'].'-');

        foreach (RESEARCH_ROLES as $otherRole => $other) {
            if ($otherRole !== $roleKey) {
                $response->assertDontSee($other['prefix'].'-PU-');
                $response->assertDontSee($other['prefix'].'-PEOU-');
            }
        }
    }

    $this->get('/demo/research/instruments/../../etc/passwd')->assertNotFound();
    $this->get('/demo/research/instruments/unknown')->assertNotFound();
});

test('operator participant projection remains identical to the guest projection without facilitator mapping', function (): void {
    seedE2Foundation();
    $operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $url = '/demo/research?role=guidance&task=C5';

    $guestResponse = $this->get($url)->assertOk();
    $operatorResponse = $this->actingAs($operator)->get($url)->assertOk();

    expect($operatorResponse->viewData('page')['props']['participant'])->toEqual($guestResponse->viewData('page')['props']['participant'])
        ->and($operatorResponse->viewData('page')['props']['groups'])->toEqual($guestResponse->viewData('page')['props']['groups'])
        ->and($operatorResponse->viewData('page')['props']['questionnaire'])->toEqual($guestResponse->viewData('page')['props']['questionnaire'])
        ->and($operatorResponse->viewData('page')['props']['xiam'])->toBeNull()
        ->and($guestResponse->viewData('page')['props']['xiam'])->toBeNull();
});

test('research references neither surface nor mark operational notifications as seen', function (string $path): void {
    seedE2Foundation();
    Queue::fake();
    $operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $privateBody = 'Unrelated case '.fake()->uuid();
    $delivery = app(NotificationIntentDispatcher::class)->dispatch(
        NotificationIntent::v1('test.research-boundary.v1', 'test.research-boundary', [
            'subject' => 'Private operational notice',
            'body' => $privateBody,
        ]),
        'research-boundary',
        [$operator],
        [NotificationDelivery::CHANNEL_IN_APP],
    )->firstOrFail();

    $this->actingAs($operator)->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('notifications', null))
        ->assertDontSee($privateBody);
    expect($delivery->refresh()->seen_at)->toBeNull();
})->with(['/demo/research', '/demo/research/facilitator']);

test('facilitator reference requires an authenticated fresh demo operator decision', function (): void {
    $this->get('/demo/research/facilitator')->assertRedirect(route('login'));

    $ordinary = User::factory()->create();
    $this->actingAs($ordinary)->get('/demo/research/facilitator')->assertForbidden();

    seedE2Foundation();
    $operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($operator)->get('/demo/research/facilitator?role=guidance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Foundation/ResearchFacilitatorGuide')
            ->where('selected_role', 'guidance')
            ->has('facilitator.preparation_markdown')
            ->has('facilitator.standard_briefing')
            ->has('facilitator.observation_markdown')
            ->has('facilitator.rules_markdown')
            ->has('facilitator.roles', 4)
            ->where('facilitator.roles.2.tasks.4.id', 'C5')
            ->where('facilitator.roles.2.tasks.4.item_ids.0', 'GUD-PU-04')
            ->where('facilitator.paired_exchange.tasks.0', 'C5')
            ->where('facilitator.paired_exchange.tasks.1', 'D4')
            ->has('facilitator.case_trace', 24)
            ->where('source_version.appendix_n', fn (?string $hash): bool => is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash) === 1)
            ->where('source_version.appendix_j', fn (?string $hash): bool => is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash) === 1)
            ->missing('participant'))
        ->assertDontSee('Password: password');

    $operator->roles()->where('name', 'guidance_counselor')->firstOrFail()
        ->revokePermissionTo(CapabilityCatalog::DEMO_STAGE_CONTROL);
    $this->get('/demo/research/facilitator')->assertForbidden();
});

test('research routes deny production even when their route definitions remain loaded', function (): void {
    seedE2Foundation();
    $operator = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($operator);
    $this->app->detectEnvironment(static fn (): string => 'production');

    $this->get('/demo/research')->assertForbidden();
    $this->get('/demo/research/instruments/student')->assertForbidden();
    $this->get('/demo/research/facilitator')->assertForbidden();

    $this->app->detectEnvironment(static fn (): string => 'testing');
});

test('research guide is read-only and ignores unrecognized operational request scope', function (): void {
    $response = $this->get('/demo/research?role=student&task=A1&applicant=42&session=secret&ready=1');
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('participant.selected_task', 'A1')
        ->missing('applicant')
        ->missing('session')
        ->missing('ready')
        ->missing('facilitator'));

    $this->post('/demo/research')->assertStatus(405);
    $this->put('/demo/research')->assertStatus(405);
    $this->delete('/demo/research')->assertStatus(405);
    $this->post('/demo/research/instruments/student')->assertStatus(405);
});

test('missing compiled research publication fails visibly without fallback content', function (): void {
    $originalBasePath = $this->app->basePath();
    $this->app->setBasePath(sys_get_temp_dir().'/research-guide-missing-'.uniqid('', true));

    try {
        $this->get('/demo/research')->assertStatus(503)->assertDontSee('Foundation/ResearchSessionGuide');
    } finally {
        $this->app->setBasePath($originalBasePath);
    }
});
